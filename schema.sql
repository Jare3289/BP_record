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

  `weight`      DECIMAL(5,2) NULL COMMENT 'น้ำหนัก (กก.)',
  `height`      DECIMAL(5,2) NULL COMMENT 'ส่วนสูง (ซม.)',

  `note`        VARCHAR(255) NULL COMMENT 'หมายเหตุ',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_date` (`record_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ช่วงการรักษา (เช่น ก่อนกินยา / กินยาช่วงที่ 1)
CREATE TABLE IF NOT EXISTS `phases` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `color`      VARCHAR(20) NOT NULL DEFAULT '#57b894',
  `note`       VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_start` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การวัดรายครั้ง (บันทึกทีละครั้ง: ช่วง + ครั้งที่)
CREATE TABLE IF NOT EXISTS `readings` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date` DATE NOT NULL,
  `period`      VARCHAR(20) NOT NULL DEFAULT 'morning' COMMENT 'morning|noon|evening|bedtime',
  `sys`         SMALLINT UNSIGNED NULL,
  `dia`         SMALLINT UNSIGNED NULL,
  `hr`          SMALLINT UNSIGNED NULL,
  `weight`      DECIMAL(5,2) NULL,
  `height`      DECIMAL(5,2) NULL,
  `note`        VARCHAR(255) NULL,
  `measured_at` DATETIME NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`record_date`),
  KEY `idx_date_period` (`record_date`, `period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- การตั้งค่า (เช่น เป้าหมายความดัน)
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(50) NOT NULL,
  `v` VARCHAR(255) NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `settings` (`k`,`v`) VALUES ('target_sys','135'),('target_dia','85')
  ON DUPLICATE KEY UPDATE `k`=`k`;
