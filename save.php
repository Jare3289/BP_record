<?php
/**
 * บันทึก (เพิ่ม/แก้ไข/ลบ) การวัดรายครั้ง · ช่วงการรักษา · การตั้งค่า
 */
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? 'save';

/** ---------- ลบการวัดรายครั้ง ---------- */
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) db()->prepare('DELETE FROM readings WHERE id = ?')->execute([$id]);
    header('Location: index.php?msg=deleted');
    exit;
}

/** ---------- จัดการช่วง (phases) ---------- */
if ($action === 'save_phase') {
    $pid   = (int) ($_POST['phase_id'] ?? 0);
    $name  = trim($_POST['name'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $color = trim($_POST['color'] ?? '#57b894');
    $pnote = trim($_POST['note'] ?? '') ?: null;
    if ($name === '' || !strtotime($start)) {
        header('Location: phases.php?msg=error');
        exit;
    }
    $start = date('Y-m-d', strtotime($start));
    if ($pid > 0) {
        db()->prepare('UPDATE phases SET name=?, start_date=?, color=?, note=? WHERE id=?')
            ->execute([$name, $start, $color, $pnote, $pid]);
    } else {
        db()->prepare('INSERT INTO phases (name, start_date, color, note) VALUES (?,?,?,?)')
            ->execute([$name, $start, $color, $pnote]);
    }
    header('Location: phases.php?msg=saved');
    exit;
}
if ($action === 'delete_phase') {
    $pid = (int) ($_POST['phase_id'] ?? 0);
    if ($pid > 0) db()->prepare('DELETE FROM phases WHERE id=?')->execute([$pid]);
    header('Location: phases.php?msg=deleted');
    exit;
}

/** ---------- บันทึกค่าสุขภาพรายครั้ง ---------- */
if ($action === 'save_health') {
    $hid    = (int) ($_POST['id'] ?? 0);
    $metric = $_POST['metric'] ?? '';
    $date   = trim($_POST['log_date'] ?? '');
    $val    = $_POST['val'] ?? '';
    if (!array_key_exists($metric, health_metrics()) || $date === '' || !strtotime($date) || $val === '' || !is_numeric($val)) {
        header('Location: health.php?msg=error'); exit;
    }
    $date = date('Y-m-d', strtotime($date));
    $val  = (float) $val;
    $note = trim($_POST['note'] ?? '') ?: null;
    try {
        if ($hid > 0) {
            db()->prepare('UPDATE health_logs SET log_date=?, metric=?, val=?, note=? WHERE id=?')->execute([$date, $metric, $val, $note, $hid]);
        } else {
            db()->prepare('INSERT INTO health_logs (log_date, metric, val, note) VALUES (?,?,?,?)')->execute([$date, $metric, $val, $note]);
        }
    } catch (Throwable $e) { header('Location: health.php?msg=error'); exit; }
    header('Location: health.php?msg=saved'); exit;
}
if ($action === 'delete_health') {
    $hid = (int) ($_POST['id'] ?? 0);
    if ($hid > 0) db()->prepare('DELETE FROM health_logs WHERE id=?')->execute([$hid]);
    header('Location: health.php?msg=deleted'); exit;
}

/** ---------- ประวัติการตรวจสุขภาพ ---------- */
if ($action === 'save_checkup') {
    $cid  = (int) ($_POST['id'] ?? 0);
    $date = trim($_POST['checkup_date'] ?? '');
    if ($date === '' || !strtotime($date)) { header('Location: health.php?msg=error'); exit; }
    $date = date('Y-m-d', strtotime($date));

    $numN = fn($k) => (($v = $_POST[$k] ?? '') === '' || !is_numeric($v)) ? null : $v;
    $fields = [
        'checkup_date' => $date,
        'hospital' => trim($_POST['hospital'] ?? '') ?: null,
        'doctor'   => trim($_POST['doctor'] ?? '') ?: null,
        'weight'   => $numN('weight'), 'height' => $numN('height'),
        'sbp'      => $numN('sbp'), 'dbp' => $numN('dbp'), 'pulse' => $numN('pulse'),
        'xray'     => trim($_POST['xray'] ?? '') ?: null,
        'ekg'      => trim($_POST['ekg'] ?? '') ?: null,
        'hbtyping' => trim($_POST['hbtyping'] ?? '') ?: null,
        'summary'  => trim($_POST['summary'] ?? '') ?: null,
        'note'     => trim($_POST['note'] ?? '') ?: null,
    ];
    try {
        if ($cid > 0) {
            $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($fields)));
            $fields['id'] = $cid;
            db()->prepare("UPDATE checkups SET $set WHERE id = :id")->execute($fields);
            unset($fields['id']);
        } else {
            $cols = array_keys($fields);
            $ph = implode(', ', array_map(fn($c) => ":$c", $cols));
            db()->prepare('INSERT INTO checkups (`' . implode('`,`', $cols) . "`) VALUES ($ph)")->execute($fields);
            $cid = (int) db()->lastInsertId();
        }
        // ผลตรวจ (key-value)
        db()->prepare('DELETE FROM checkup_values WHERE checkup_id = ?')->execute([$cid]);
        $vals = $_POST['v'] ?? [];
        if (is_array($vals)) {
            $ins = db()->prepare('INSERT INTO checkup_values (checkup_id, code, val) VALUES (?,?,?)');
            $valid = checkup_tests_map();
            foreach ($vals as $code => $v) {
                $v = trim((string) $v);
                if ($v === '' || !isset($valid[$code])) continue;
                $ins->execute([$cid, $code, mb_substr($v, 0, 100)]);
            }
        }
    } catch (Throwable $e) {
        header('Location: health.php?msg=error'); exit;
    }
    header('Location: health.php?msg=saved&open=' . $cid);
    exit;
}
if ($action === 'delete_checkup') {
    $cid = (int) ($_POST['id'] ?? 0);
    if ($cid > 0) {
        try { db()->prepare('DELETE FROM checkup_values WHERE checkup_id = ?')->execute([$cid]); } catch (Throwable $e) {}
        db()->prepare('DELETE FROM checkups WHERE id = ?')->execute([$cid]);
    }
    header('Location: health.php?msg=deleted'); exit;
}

/** ---------- สลับมาตรฐานการแปลผลความดัน (สากล/ไทย) ---------- */
if ($action === 'set_standard') {
    $std = ($_POST['bp_standard'] ?? 'intl') === 'th' ? 'th' : 'intl';
    try {
        db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')
            ->execute(['bp_standard', $std]);
    } catch (Throwable $e) {}
    $back = $_POST['back'] ?? 'dashboard.php';
    header('Location: ' . $back . '?msg=saved');
    exit;
}

/** ---------- การตั้งค่า (เป้าหมายความดัน) ---------- */
if ($action === 'save_settings') {
    $ts = (int) ($_POST['target_sys'] ?? 135);
    $td = (int) ($_POST['target_dia'] ?? 85);
    $ts = max(90, min(200, $ts));
    $td = max(50, min(130, $td));
    try {
        $stmt = db()->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
        $stmt->execute(['target_sys', (string)$ts]);
        $stmt->execute(['target_dia', (string)$td]);
    } catch (Throwable $e) {}
    $back = $_POST['back'] ?? 'dashboard.php';
    header('Location: ' . $back . '?msg=saved');
    exit;
}

/** ---------- เพิ่ม / แก้ไข การวัดรายครั้ง ---------- */
$id     = (int) ($_POST['id'] ?? 0);
$date   = trim($_POST['record_date'] ?? '');
$period = $_POST['period'] ?? 'morning';
if (!array_key_exists($period, periods())) $period = 'morning';

if ($date === '' || !strtotime($date)) {
    header('Location: index.php?msg=error_date');
    exit;
}
$date = date('Y-m-d', strtotime($date));

$numOrNull = fn($k) => (($v = $_POST[$k] ?? '') === '' || !is_numeric($v)) ? null : $v;
$sys = $numOrNull('sys'); $dia = $numOrNull('dia'); $hr = $numOrNull('hr');
$weight = $numOrNull('weight'); $height = $numOrNull('height');
$note = trim($_POST['note'] ?? '') ?: null;

$sys = $sys === null ? null : (int)$sys;
$dia = $dia === null ? null : (int)$dia;
$hr  = $hr  === null ? null : (int)$hr;
$weight = $weight === null ? null : (float)$weight;
$height = $height === null ? null : (float)$height;

try {
    if ($id > 0) {
        db()->prepare('UPDATE readings SET record_date=?, period=?, sys=?, dia=?, hr=?, weight=?, height=?, note=? WHERE id=?')
            ->execute([$date, $period, $sys, $dia, $hr, $weight, $height, $note, $id]);
    } else {
        db()->prepare('INSERT INTO readings (record_date, period, sys, dia, hr, weight, height, note, measured_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
            ->execute([$date, $period, $sys, $dia, $hr, $weight, $height, $note]);
    }
} catch (PDOException $e) {
    header('Location: index.php?msg=error');
    exit;
}

header('Location: index.php?msg=saved');
exit;
