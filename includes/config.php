<?php
/**
 * การตั้งค่าการเชื่อมต่อฐานข้อมูล
 * แก้ไขค่าด้านล่างให้ตรงกับเซิร์ฟเวอร์ MySQL ของคุณ
 */

// โหลดค่าเชื่อมต่อจริงจากไฟล์ที่ไม่อยู่ใน git (สร้างโดย workflow หรือสร้างเองบนเซิร์ฟเวอร์)
// ดูตัวอย่างที่ includes/config.local.example.php
$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) {
    require $__local;
}

// ค่า default (ใช้เมื่อไม่มี config.local.php และไม่มี environment variable)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'bp_record');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// เขตเวลา
date_default_timezone_set('Asia/Bangkok');

/**
 * คืนค่า PDO connection (singleton)
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . htmlspecialchars($e->getMessage()));
    }

    return $pdo;
}
