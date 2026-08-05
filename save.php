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
