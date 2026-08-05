<?php
/**
 * บันทึก (เพิ่ม/แก้ไข) และลบข้อมูล
 */
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? 'save';

/** ---------- ลบข้อมูล ---------- */
if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = db()->prepare('DELETE FROM bp_readings WHERE id = ?');
        $stmt->execute([$id]);
    }
    header('Location: index.php?msg=deleted');
    exit;
}

/** ---------- เพิ่ม / แก้ไข ---------- */
$id   = (int) ($_POST['id'] ?? 0);
$date = trim($_POST['record_date'] ?? '');

// ตรวจสอบวันที่
if ($date === '' || !strtotime($date)) {
    header('Location: index.php?msg=error_date');
    exit;
}

// แปลงช่องตัวเลขให้เป็น int หรือ null
$fields = [
    'm1_sys', 'm1_dia', 'm1_hr', 'm2_sys', 'm2_dia', 'm2_hr',
    'n1_sys', 'n1_dia', 'n1_hr', 'n2_sys', 'n2_dia', 'n2_hr',
];

$data = ['record_date' => date('Y-m-d', strtotime($date))];
foreach ($fields as $f) {
    $v = $_POST[$f] ?? '';
    $data[$f] = ($v === '' || !is_numeric($v)) ? null : (int) $v;
}
$data['note'] = trim($_POST['note'] ?? '') ?: null;

try {
    if ($id > 0) {
        // UPDATE
        $set = 'record_date = :record_date, '
             . implode(', ', array_map(fn($f) => "$f = :$f", $fields))
             . ', note = :note';
        $sql = "UPDATE bp_readings SET $set WHERE id = :id";
        $data['id'] = $id;
        db()->prepare($sql)->execute($data);
    } else {
        // INSERT (ถ้าวันที่ซ้ำจะอัปเดตทับ)
        $cols = array_merge(['record_date'], $fields, ['note']);
        $placeholders = implode(', ', array_map(fn($c) => ":$c", $cols));
        $updates = implode(', ', array_map(fn($c) => "$c = VALUES($c)", array_merge($fields, ['note'])));
        $sql = 'INSERT INTO bp_readings (' . implode(', ', $cols) . ") VALUES ($placeholders) "
             . "ON DUPLICATE KEY UPDATE $updates";
        db()->prepare($sql)->execute($data);
    }
} catch (PDOException $e) {
    header('Location: index.php?msg=error');
    exit;
}

header('Location: index.php?msg=saved');
exit;
