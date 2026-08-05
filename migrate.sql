-- ==========================================================
-- migrate.sql — อัปเดตฐานข้อมูลเดิมให้รองรับฟีเจอร์ใหม่
--   • เพิ่มคอลัมน์ น้ำหนัก (weight) และ ส่วนสูง (height)
--   • เพิ่มตาราง phases (ช่วงการรักษา เช่น ก่อนกินยา / กินยาช่วงที่ 1)
--
-- นำเข้าครั้งเดียว (สำหรับฐานข้อมูลที่มีข้อมูลอยู่แล้ว):
--   mysql -u USER -p bp_record < migrate.sql
-- หรือวางใน phpMyAdmin → แท็บ SQL
-- (คำสั่งใช้ IF NOT EXISTS — รันซ้ำได้ปลอดภัยบน MariaDB/InfinityFree)
-- ==========================================================

ALTER TABLE `bp_readings`
  ADD COLUMN IF NOT EXISTS `weight` DECIMAL(5,2) NULL COMMENT 'น้ำหนัก (กก.)' AFTER `n2_hr`,
  ADD COLUMN IF NOT EXISTS `height` DECIMAL(5,2) NULL COMMENT 'ส่วนสูง (ซม.)' AFTER `weight`;

CREATE TABLE IF NOT EXISTS `phases` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL COMMENT 'ชื่อช่วง เช่น ก่อนกินยา',
  `start_date` DATE NOT NULL COMMENT 'วันเริ่มช่วง (ช่วงนี้ใช้จนถึงวันเริ่มของช่วงถัดไป)',
  `color`      VARCHAR(20) NOT NULL DEFAULT '#57b894',
  `note`       VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_start` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
