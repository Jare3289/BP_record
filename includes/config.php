<?php
/**
 * การตั้งค่าการเชื่อมต่อฐานข้อมูล
 * แก้ไขค่าด้านล่างให้ตรงกับเซิร์ฟเวอร์ MySQL ของคุณ
 */

// อ่านค่าจาก environment variable ได้ (เผื่อใช้บน hosting) หรือใช้ค่า default
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'bp_record');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

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
