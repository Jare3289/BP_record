-- ผลตรวจสุขภาพ นายนัชนันท์ นครคง (CNT-AA-12) · วันที่ตรวจ 25 มีนาคม 2568 (2025-03-25)
-- นำเข้า: mysql -u USER -p bp_record < seed_checkup.sql  (หรือ phpMyAdmin -> SQL)
-- ปลอดภัย: ลบผลตรวจวันเดียวกันก่อน (กันซ้ำ) แล้วค่อยใส่ใหม่ — รันซ้ำได้ไม่มีวันซ้ำ

-- 1) ลบผลตรวจเดิมของวันนี้ (ถ้ามี) พร้อมค่าตรวจย่อย
DELETE cv FROM `checkup_values` cv
  JOIN `checkups` c ON c.`id` = cv.`checkup_id`
  WHERE c.`checkup_date` = '2025-03-25';
DELETE FROM `checkups` WHERE `checkup_date` = '2025-03-25';

-- 2) เพิ่มผลตรวจหลัก (พื้นฐาน + ร่างกาย + ความดัน + สรุปแพทย์)
INSERT INTO `checkups`
  (`checkup_date`,`hospital`,`doctor`,`weight`,`height`,`sbp`,`dbp`,`pulse`,`xray`,`ekg`,`hbtyping`,`summary`,`note`)
VALUES
  ('2025-03-25', 'ตรวจสุขภาพประจำปี', NULL, 124, 171, 150, 98, 98, NULL, NULL, NULL,
   'ความดันโลหิตสูง ควรวัดความดันบ่อย ๆ และออกกำลังกายอย่างสม่ำเสมอ / ค่าดัชนีมวลกายเกินเกณฑ์ระดับ 3 ควรควบคุมอาหารอย่างจริงจัง และออกกำลังกาย 6 วันต่อสัปดาห์ให้ร่างกายใช้พลังงานมากขึ้น และควรปรึกษาผู้เชี่ยวชาญในการลดและควบคุมน้ำหนัก',
   'โรงเรียนชัยนาทพิทยาคม · ครู · อายุ 29 ปี');

SET @cid = LAST_INSERT_ID();

-- 3) ค่าตรวจย่อย (key-value)
INSERT INTO `checkup_values` (`checkup_id`,`code`,`val`) VALUES
  -- ความสมบูรณ์ของเม็ดเลือด (CBC)
  (@cid, 'hb', '14.9'),
  (@cid, 'hct', '45'),
  (@cid, 'wbc', '6580'),
  (@cid, 'neu', '64'),
  (@cid, 'lym', '31'),
  (@cid, 'mono', '2'),
  (@cid, 'eos', '3'),
  (@cid, 'baso', '0'),
  (@cid, 'plt', '216000'),
  (@cid, 'morph', 'Normal'),
  -- เคมีในเลือด (Biochemistry)
  (@cid, 'sugar', '87'),
  (@cid, 'bun', '12'),
  (@cid, 'creatinine', '0.9'),
  (@cid, 'uric', '8.5'),
  (@cid, 'sgot', '20'),
  (@cid, 'sgpt', '34'),
  (@cid, 'alp', '69'),
  (@cid, 'chol', '194'),
  (@cid, 'tg', '57'),
  (@cid, 'hdl', '56'),
  (@cid, 'ldl', '127'),
  -- ปัสสาวะ (Urine)
  (@cid, 'u_color', 'Yellow'),
  (@cid, 'u_appear', 'Clear'),
  (@cid, 'u_ph', '5'),
  (@cid, 'u_spgr', '1.030'),
  (@cid, 'u_protein', 'Negative'),
  (@cid, 'u_sugar', 'Negative'),
  (@cid, 'u_wbc', '0-1'),
  (@cid, 'u_rbc', '0-1'),
  (@cid, 'u_epi', '0-1'),
  (@cid, 'u_other', 'Ketone: Negative · Blood: Negative · Bacteria: Not found');
