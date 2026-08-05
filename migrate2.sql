-- ==========================================================
-- migrate2.sql — เปลี่ยนเป็นระบบ "บันทึกทีละครั้ง" + การตั้งค่า
--   • ตาราง readings : เก็บการวัดเป็นรายครั้ง (ช่วง + ครั้งที่)
--   • ตาราง settings : ค่าปรับได้ เช่น เป้าหมายความดัน
--   • ย้ายข้อมูลเดิมจาก bp_readings เข้าตาราง readings (รันครั้งเดียว)
--
-- นำเข้า: mysql -u USER -p bp_record < migrate2.sql  (หรือ phpMyAdmin → SQL)
-- ปลอดภัยถ้ารันซ้ำ (ย้ายข้อมูลเฉพาะตอน readings ยังว่าง)
-- ==========================================================

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

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(50) NOT NULL,
  `v` VARCHAR(255) NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`k`,`v`) VALUES ('target_sys','135'), ('target_dia','85')
  ON DUPLICATE KEY UPDATE `k`=`k`;

-- ย้ายข้อมูลเดิม (ทำเป็นคำสั่งเดียว, ทำงานเฉพาะตอน readings ยังว่าง)
-- ลำดับบล็อก: เช้า#1, เช้า#2, ก่อนนอน#1, ก่อนนอน#2 (ครั้งที่ คำนวณจากลำดับ id)
INSERT INTO `readings` (record_date, period, sys, dia, hr, weight, height, note)
SELECT record_date, period, sys, dia, hr, weight, height, note FROM (
      SELECT record_date, 'morning' AS period, m1_sys AS sys, m1_dia AS dia, m1_hr AS hr, weight, height, note
        FROM bp_readings WHERE m1_sys IS NOT NULL
  UNION ALL
      SELECT record_date, 'morning', m2_sys, m2_dia, m2_hr, NULL, NULL, NULL
        FROM bp_readings WHERE m2_sys IS NOT NULL
  UNION ALL
      SELECT record_date, 'bedtime', n1_sys, n1_dia, n1_hr, NULL, NULL, NULL
        FROM bp_readings WHERE n1_sys IS NOT NULL
  UNION ALL
      SELECT record_date, 'bedtime', n2_sys, n2_dia, n2_hr, NULL, NULL, NULL
        FROM bp_readings WHERE n2_sys IS NOT NULL
) AS src
WHERE (SELECT COUNT(*) FROM readings) = 0;
