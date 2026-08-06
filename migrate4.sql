-- ==========================================================
-- migrate4.sql — เพิ่มการบันทึกค่าสุขภาพรายครั้ง (Health Logs)
--   บันทึกค่าอื่น ๆ ในชีวิต เช่น น้ำตาล, การนอน, ก้าวเดิน, น้ำดื่ม ฯลฯ
--
-- นำเข้า: mysql -u USER -p bp_record < migrate4.sql  (หรือ phpMyAdmin → SQL)
-- ==========================================================

CREATE TABLE IF NOT EXISTS `health_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_date`   DATE NOT NULL,
  `metric`     VARCHAR(30) NOT NULL,
  `val`        DECIMAL(10,2) NULL,
  `note`       VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metric_date` (`metric`, `log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
