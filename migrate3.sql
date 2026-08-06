-- ==========================================================
-- migrate3.sql — เพิ่มระบบประวัติการตรวจสุขภาพ (Health Checkup)
--   • checkups        : การตรวจแต่ละครั้ง (วันที่ + ข้อมูลพื้นฐาน + สรุป)
--   • checkup_values  : ผลตรวจแต่ละรายการ (แบบ key-value ยืดหยุ่น)
--
-- นำเข้า: mysql -u USER -p bp_record < migrate3.sql  (หรือ phpMyAdmin → SQL)
-- ==========================================================

CREATE TABLE IF NOT EXISTS `checkups` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `checkup_date` DATE NOT NULL,
  `hospital`     VARCHAR(150) NULL,
  `doctor`       VARCHAR(150) NULL,
  `weight`       DECIMAL(5,2) NULL,
  `height`       DECIMAL(5,2) NULL,
  `sbp`          SMALLINT UNSIGNED NULL,
  `dbp`          SMALLINT UNSIGNED NULL,
  `pulse`        SMALLINT UNSIGNED NULL,
  `xray`         TEXT NULL,
  `ekg`          TEXT NULL,
  `hbtyping`     TEXT NULL,
  `summary`      TEXT NULL,
  `note`         VARCHAR(255) NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cdate` (`checkup_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `checkup_values` (
  `checkup_id` INT UNSIGNED NOT NULL,
  `code`       VARCHAR(30) NOT NULL,
  `val`        VARCHAR(100) NULL,
  PRIMARY KEY (`checkup_id`, `code`),
  CONSTRAINT `fk_cv_checkup` FOREIGN KEY (`checkup_id`) REFERENCES `checkups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
