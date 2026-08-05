<?php
/**
 * ตัวอย่างไฟล์ตั้งค่าเชื่อมต่อฐานข้อมูล (ค่าลับ)
 *
 * วิธีใช้: คัดลอกไฟล์นี้เป็น config.local.php แล้วใส่ค่าจริง
 *   cp config.local.example.php config.local.php
 *
 * ⚠️ ห้าม commit ไฟล์ config.local.php เข้า git (มีอยู่ใน .gitignore แล้ว)
 *
 * หมายเหตุ: ถ้า deploy ผ่าน GitHub Actions ไฟล์นี้จะถูกสร้างอัตโนมัติ
 * จากค่าใน GitHub Secrets — ไม่ต้องสร้างเองบนเซิร์ฟเวอร์
 */

define('DB_HOST', 'sql105.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_XXXXXXXX_bp');   // ใส่ชื่อฐานข้อมูลจริงจากหน้า InfinityFree
define('DB_USER', 'if0_XXXXXXXX');
define('DB_PASS', 'your-mysql-password');
define('DB_CHARSET', 'utf8mb4');
