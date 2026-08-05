-- โครงสร้างฐานข้อมูลสำหรับบันทึกความดันโลหิต
-- นำเข้าด้วย: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS `bp_record`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bp_record`;

CREATE TABLE IF NOT EXISTS `bp_readings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date`   DATE NOT NULL COMMENT 'วันที่วัด',

  -- ตอนเช้า (เข้า) ครั้งที่ 1
  `m1_sys`  SMALLINT UNSIGNED NULL COMMENT 'เช้า ครั้งที่1 - บน (systolic)',
  `m1_dia`  SMALLINT UNSIGNED NULL COMMENT 'เช้า ครั้งที่1 - ล่าง (diastolic)',
  `m1_hr`   SMALLINT UNSIGNED NULL COMMENT 'เช้า ครั้งที่1 - หัวใจ (pulse)',
  -- ตอนเช้า (เข้า) ครั้งที่ 2
  `m2_sys`  SMALLINT UNSIGNED NULL,
  `m2_dia`  SMALLINT UNSIGNED NULL,
  `m2_hr`   SMALLINT UNSIGNED NULL,

  -- ก่อนนอน ครั้งที่ 1
  `n1_sys`  SMALLINT UNSIGNED NULL,
  `n1_dia`  SMALLINT UNSIGNED NULL,
  `n1_hr`   SMALLINT UNSIGNED NULL,
  -- ก่อนนอน ครั้งที่ 2
  `n2_sys`  SMALLINT UNSIGNED NULL,
  `n2_dia`  SMALLINT UNSIGNED NULL,
  `n2_hr`   SMALLINT UNSIGNED NULL,

  `note`        VARCHAR(255) NULL COMMENT 'หมายเหตุ',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_date` (`record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
