<?php
/**
 * ฟังก์ชันช่วยคำนวณค่าเฉลี่ยและแปลผลความดันโลหิต
 */

/**
 * คำนวณค่าเฉลี่ยของค่าที่ไม่ว่างในอาเรย์ (ปัดเป็นจำนวนเต็ม)
 * คืน null ถ้าไม่มีค่าเลย
 */
function avg_of(array $values): ?int
{
    $nums = array_filter($values, fn($v) => $v !== null && $v !== '');
    if (count($nums) === 0) {
        return null;
    }
    return (int) round(array_sum($nums) / count($nums));
}

/**
 * คำนวณค่าเฉลี่ยรายวันของ 1 แถวข้อมูล
 * รวมทุกครั้งที่วัด (เช้า1, เช้า2, ก่อนนอน1, ก่อนนอน2)
 * คืน ['sys' => .., 'dia' => .., 'hr' => ..]
 */
function daily_average(array $row): array
{
    return [
        'sys' => avg_of([$row['m1_sys'], $row['m2_sys'], $row['n1_sys'], $row['n2_sys']]),
        'dia' => avg_of([$row['m1_dia'], $row['m2_dia'], $row['n1_dia'], $row['n2_dia']]),
        'hr'  => avg_of([$row['m1_hr'],  $row['m2_hr'],  $row['n1_hr'],  $row['n2_hr']]),
    ];
}

/**
 * มาตรฐานการแปลผลที่เลือกใช้: 'intl' = ACC/AHA (สากล, ค่าเริ่มต้น) | 'th' = สมาคมความดันฯ ไทย
 */
function bp_standard(): string
{
    return get_setting('bp_standard', 'intl') === 'th' ? 'th' : 'intl';
}

/** ป้ายชื่อมาตรฐานสำหรับแสดงผล */
function bp_standard_name(?string $std = null): string
{
    $std = $std ?? bp_standard();
    return $std === 'th' ? 'สมาคมความดันฯ ไทย' : 'สากล (ACC/AHA)';
}

/**
 * แปลผลความดันโลหิต — เลือกได้ 2 มาตรฐาน (สลับได้ผ่านการตั้งค่า bp_standard)
 *   intl = ACC/AHA 2017 (ค่าเริ่มต้น) · th = สมาคมความดันโลหิตสูงแห่งประเทศไทย
 * คืน ['label', 'level'(0-4), 'class'] — level/สี ใช้ชุดเดียวกันทั้งสองมาตรฐาน
 */
function classify_bp(?int $sys, ?int $dia): array
{
    if ($sys === null || $dia === null) {
        return ['label' => '-', 'level' => -1, 'class' => 'bp-none'];
    }

    if (bp_standard() === 'th') {
        // ---- สมาคมความดันโลหิตสูงแห่งประเทศไทย ----
        if ($sys >= 180 || $dia >= 110) return ['label' => 'ความดันสูงระดับ 3', 'level' => 4, 'class' => 'bp-crisis'];
        if ($sys >= 160 || $dia >= 100) return ['label' => 'ความดันสูงระดับ 2', 'level' => 3, 'class' => 'bp-stage2'];
        if ($sys >= 140 || $dia >= 90)  return ['label' => 'ความดันสูงระดับ 1', 'level' => 2, 'class' => 'bp-stage1'];
        if ($sys >= 130 || $dia >= 80)  return ['label' => 'เริ่มเสี่ยง', 'level' => 1, 'class' => 'bp-elevated'];
        return ['label' => 'ปกติ', 'level' => 0, 'class' => 'bp-normal'];
    }

    // ---- ACC/AHA 2017 (สากล — ค่าเริ่มต้น) ----
    if ($sys >= 180 || $dia >= 120) return ['label' => 'ภาวะวิกฤต ควรพบแพทย์', 'level' => 4, 'class' => 'bp-crisis'];
    if ($sys >= 140 || $dia >= 90)  return ['label' => 'ความดันสูง ระยะที่ 2', 'level' => 3, 'class' => 'bp-stage2'];
    if ($sys >= 130 || $dia >= 80)  return ['label' => 'ความดันสูง ระยะที่ 1', 'level' => 2, 'class' => 'bp-stage1'];
    if ($sys >= 120)                return ['label' => 'สูงเล็กน้อย', 'level' => 1, 'class' => 'bp-elevated'];
    return ['label' => 'ปกติ', 'level' => 0, 'class' => 'bp-normal'];
}

/**
 * ตารางเกณฑ์ของมาตรฐานหนึ่ง ๆ (สำหรับแสดงการ์ดอ้างอิง)
 * คืน [[ชื่อระดับ, สี, ข้อความบน, ข้อความล่าง], ...]
 */
function bp_criteria_table(string $std): array
{
    if ($std === 'th') {
        return [
            ['ปกติ', '#16a34a', '<130', '<80'],
            ['เริ่มเสี่ยง', '#65a30d', '130–139', '80–89'],
            ['สูงระดับ 1', '#d99a1a', '140–159', '90–99'],
            ['สูงระดับ 2', '#d1603a', '160–179', '100–109'],
            ['สูงระดับ 3', '#b91c1c', '≥180', '≥110'],
        ];
    }
    return [
        ['ปกติ', '#16a34a', '<120', '<80'],
        ['สูงเล็กน้อย', '#65a30d', '120–129', '<80'],
        ['ระยะที่ 1', '#d99a1a', '130–139', '80–89'],
        ['ระยะที่ 2', '#d1603a', '≥140', '≥90'],
        ['วิกฤต', '#b91c1c', '≥180', '≥120'],
    ];
}

/** helper escape */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** จัดรูปแบบวันที่เป็น d/m/Y (ค.ศ.) */
function fmt_date(?string $d): string
{
    if (!$d) return '-';
    $ts = strtotime($d);
    return $ts ? date('j/n/Y', $ts) : e($d);
}

/** คืนค่าตัวเลขหรือ '-' ถ้า null */
function num(?int $n): string
{
    return $n === null ? '-' : (string) $n;
}

/** จัดรูปแบบเลขทศนิยม ตัดศูนย์ท้ายที่ไม่จำเป็น (เช่น 170.00 -> 170, 70.50 -> 70.5) */
function fmt_num($v): string
{
    if ($v === null || $v === '') return '-';
    return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
}

/** ช่วงเวลาในหนึ่งวัน => [ป้าย, ลำดับ, ไอคอน] */
function periods(): array
{
    return [
        'morning' => ['ช่วงเช้า', 1, 'bi-sunrise'],
        'noon'    => ['ช่วงกลางวัน', 2, 'bi-sun'],
        'evening' => ['ช่วงเย็น', 3, 'bi-sunset'],
        'bedtime' => ['ก่อนนอน', 4, 'bi-moon-stars'],
    ];
}
function period_label(string $code): string { return periods()[$code][0] ?? $code; }
function period_order(string $code): int { return periods()[$code][1] ?? 9; }
function period_icon(string $code): string { return periods()[$code][2] ?? 'bi-clock'; }

/**
 * โหลดการวัดทั้งหมด (รายครั้ง) — ปลอดภัยแม้ตาราง readings ยังไม่ถูกสร้าง
 */
function all_readings(): array
{
    try {
        return db()->query('SELECT * FROM readings')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * รวมการวัดเป็นรายวัน (คำนวณค่าเฉลี่ย + แปลผล + ครั้งที่ในแต่ละช่วง)
 * คืน list เรียงตามวันที่ (เก่า→ใหม่) แต่ละรายการ:
 *   ['date','readings'(เรียงตามช่วง/ครั้ง),'avg'=>[sys,dia,hr],'bp','weight','height']
 */
function group_days(array $readings): array
{
    $byDate = [];
    foreach ($readings as $r) {
        $byDate[$r['record_date']][] = $r;
    }
    ksort($byDate);
    $out = [];
    foreach ($byDate as $date => $list) {
        // จัดลำดับ: ตามช่วง แล้วตาม id (=ครั้งที่)
        usort($list, function ($a, $b) {
            $o = period_order($a['period']) <=> period_order($b['period']);
            return $o !== 0 ? $o : ((int)$a['id'] <=> (int)$b['id']);
        });
        // กำหนด "ครั้งที่" ในแต่ละช่วง
        $seq = [];
        foreach ($list as &$r) {
            $seq[$r['period']] = ($seq[$r['period']] ?? 0) + 1;
            $r['seq'] = $seq[$r['period']];
        }
        unset($r);
        $avg = [
            'sys' => avg_of(array_column($list, 'sys')),
            'dia' => avg_of(array_column($list, 'dia')),
            'hr'  => avg_of(array_column($list, 'hr')),
        ];
        // น้ำหนัก/ส่วนสูงล่าสุดของวัน (ค่าที่ไม่ว่างตัวท้าย)
        $w = $h = null;
        foreach ($list as $r) {
            if (isset($r['weight']) && $r['weight'] !== null && $r['weight'] !== '') $w = (float)$r['weight'];
            if (isset($r['height']) && $r['height'] !== null && $r['height'] !== '') $h = (float)$r['height'];
        }
        $out[] = [
            'date' => $date, 'readings' => $list, 'avg' => $avg,
            'bp' => classify_bp($avg['sys'], $avg['dia']),
            'weight' => $w, 'height' => $h,
        ];
    }
    return $out;
}

/** อ่านค่าตั้งค่า (ปลอดภัยแม้ตาราง settings ยังไม่มี) */
function get_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = [];
            foreach (db()->query('SELECT k, v FROM settings')->fetchAll() as $row) $cache[$row['k']] = $row['v'];
        } catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}
function target_sys(): int { return (int) get_setting('target_sys', 135); }
function target_dia(): int { return (int) get_setting('target_dia', 85); }

/** อยู่ในเป้าหมายหรือไม่ (คุมได้) */
function in_target(?int $sys, ?int $dia): bool
{
    return $sys !== null && $dia !== null && $sys < target_sys() && $dia < target_dia();
}

/**
 * สร้าง HTML ปฏิทินสุขภาพ (Pixel) แนวนอน — วันที่ 1-31 = คอลัมน์, เดือน = แถว
 * $dateLevel: ['Y-m-d' => level], $years: [ปี...], $curYear: ปีที่แสดงเริ่มต้น
 */
function pixel_calendar_html(array $dateLevel, array $years, string $curYear): string
{
    $monthsTH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $lvlClass = [0=>'px-normal',1=>'px-elevated',2=>'px-stage1',3=>'px-stage2',4=>'px-crisis'];
    $lvlName  = array_map(fn($r) => $r[0], bp_criteria_table(bp_standard()));
    $h = '';
    foreach ($years as $y) {
        $h .= '<div class="pixel-wrap ' . ((string)$y === (string)$curYear ? '' : 'd-none') . '" data-year="' . $y . '"><table class="pixel-grid"><thead><tr><th></th>';
        for ($day = 1; $day <= 31; $day++) $h .= '<th class="px-dnum">' . $day . '</th>';
        $h .= '</tr></thead><tbody>';
        for ($mo = 1; $mo <= 12; $mo++) {
            $h .= '<tr><td class="px-month">' . $monthsTH[$mo - 1] . '</td>';
            for ($day = 1; $day <= 31; $day++) {
                if (!checkdate($mo, $day, (int)$y)) { $h .= '<td class="px-void"></td>'; continue; }
                $ds = sprintf('%04d-%02d-%02d', $y, $mo, $day);
                $lv = $dateLevel[$ds] ?? null;
                $cls = $lv === null ? 'px-empty' : ($lvlClass[$lv] ?? 'px-empty');
                $tip = date('j/n/Y', strtotime($ds)) . ' · ' . ($lv === null ? 'ไม่มีข้อมูล' : ($lvlName[$lv] ?? ''));
                $h .= '<td class="px-cell ' . $cls . '" data-tip="' . htmlspecialchars($tip, ENT_QUOTES) . '"></td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
    }
    $h .= '<div class="pixel-legend">'
        . '<span><span class="px-cell px-normal"></span> ' . htmlspecialchars($lvlName[0]) . '</span>'
        . '<span><span class="px-cell px-elevated"></span> ' . htmlspecialchars($lvlName[1]) . '</span>'
        . '<span><span class="px-cell px-stage1"></span> ' . htmlspecialchars($lvlName[2]) . '</span>'
        . '<span><span class="px-cell px-stage2"></span> ' . htmlspecialchars($lvlName[3]) . '</span>'
        . '<span><span class="px-cell px-crisis"></span> ' . htmlspecialchars($lvlName[4]) . '</span>'
        . '<span><span class="px-cell px-empty"></span> ไม่มีข้อมูล</span></div>';
    return $h;
}

/** เตรียมข้อมูลสำหรับ pixel: [dateLevel, years, curYear] จาก group_days */
function pixel_data(array $days): array
{
    $dateLevel = []; $years = [];
    foreach ($days as $d) {
        $dateLevel[$d['date']] = $d['bp']['level'];
        $years[substr($d['date'], 0, 4)] = true;
    }
    $years = array_keys($years); rsort($years);
    return [$dateLevel, $years, $years[0] ?? date('Y')];
}

/**
 * แคตตาล็อกรายการตรวจสุขภาพ แบ่งเป็นกลุ่ม
 * แต่ละรายการ: [code, ชื่อ(ไทย(อังกฤษ)), หน่วย, ค่าอ้างอิง(ข้อความ), low, high, type('num'|'text'), options(array|null)]
 * ถ้ามี options => แสดงเป็น dropdown (select) ในฟอร์มบันทึก
 */
function checkup_catalog(): array
{
    // ตัวเลือกมาตรฐานสำหรับค่าที่ไม่ใช่ตัวเลข
    $negPos  = ['Negative', 'Positive'];
    $urScale = ['Negative', 'Trace', '1+', '2+', '3+'];
    return [
        'เคมีในเลือด (Blood Chemistry)' => [
            ['sugar', 'น้ำตาลในเลือด (Glucose)', 'mg/dl', '70–99', 70, 99],
            ['hba1c', 'น้ำตาลสะสม (HbA1c)', '%', '<5.7', null, 5.7],
            ['bun', 'การทำงานของไต (BUN)', 'mg/dl', '6–20', 6, 20],
            ['creatinine', 'ครีเอตินิน (Creatinine)', 'mg/dl', '0.67–1.17', 0.67, 1.17],
            ['egfr', 'อัตราการกรองของไต (eGFR)', 'ml/min', '≥90', 90, null],
            ['uric', 'กรดยูริก (Uric Acid)', 'mg/dl', '3.4–7.0', 3.4, 7.0],
            ['chol', 'โคเลสเตอรอล (Cholesterol)', 'mg/dl', '0–199', 0, 199],
            ['tg', 'ไตรกลีเซอไรด์ (Triglyceride)', 'mg/dl', '0–150', 0, 150],
            ['hdl', 'ไขมันดี (HDL)', 'mg/dl', '≥40', 40, null],
            ['ldl', 'ไขมันไม่ดี (LDL)', 'mg/dl', '0–129', 0, 129],
            ['sgot', 'เอนไซม์ตับ (AST/SGOT)', 'U/L', '0–50', 0, 50],
            ['sgpt', 'เอนไซม์ตับ (ALT/SGPT)', 'U/L', '0–50', 0, 50],
            ['alp', 'เอนไซม์ตับ (ALP)', 'U/L', '40–129', 40, 129],
            ['ggt', 'เอนไซม์ตับ (GGT)', 'U/L', '10–71', 10, 71],
            ['vitd', 'วิตามินดี (Vitamin D)', 'ng/mL', '≥30', 30, null],
        ],
        'แร่ธาตุและเกลือแร่ (Electrolytes & Minerals)' => [
            ['na', 'โซเดียม (Sodium/Na)', 'mmol/L', '135–145', 135, 145],
            ['k', 'โพแทสเซียม (Potassium/K)', 'mmol/L', '3.5–5.1', 3.5, 5.1],
            ['cl', 'คลอไรด์ (Chloride/Cl)', 'mmol/L', '98–107', 98, 107],
            ['co2', 'ไบคาร์บอเนต (Bicarbonate/CO₂)', 'mmol/L', '22–29', 22, 29],
            ['ca', 'แคลเซียม (Calcium/Ca)', 'mg/dl', '8.6–10.2', 8.6, 10.2],
            ['mg', 'แมกนีเซียม (Magnesium/Mg)', 'mg/dl', '1.7–2.4', 1.7, 2.4],
            ['phos', 'ฟอสฟอรัส (Phosphorus/P)', 'mg/dl', '2.5–4.5', 2.5, 4.5],
            ['fe', 'ธาตุเหล็ก (Iron/Fe)', 'µg/dl', '60–170', 60, 170],
        ],
        'มะเร็ง / ไทรอยด์ / ไวรัส' => [
            ['afp', 'มะเร็งตับ (AFP)', 'ng/ml', '0.0–7.0', 0, 7],
            ['cea', 'มะเร็งลำไส้ (CEA)', 'ng/ml', '<3.8', null, 3.8],
            ['psa', 'มะเร็งต่อมลูกหมาก (PSA)', 'ng/ml', '0–4', 0, 4],
            ['ca125', 'มะเร็งรังไข่ (CA125)', 'U/ml', '<35', null, 35],
            ['ca153', 'มะเร็งเต้านม (CA15-3)', 'U/ml', '0–25', 0, 25],
            ['ca199', 'มะเร็งทางเดินอาหาร (CA19-9)', 'U/ml', '0–39', 0, 39],
            ['tsh', 'ไทรอยด์ (TSH)', 'uIU/ml', '0.27–4.20', 0.27, 4.20],
            ['ft4', 'ไทรอยด์ (FT4)', 'ng/dl', '0.93–1.70', 0.93, 1.70],
            ['ft3', 'ไทรอยด์ (FT3)', 'pg/ml', '2.0–4.4', 2.0, 4.4],
            ['esr', 'การอักเสบ (ESR)', 'mm/hr', '0–9', 0, 9],
            ['crp', 'การอักเสบ (CRP)', 'mg/L', '<5.0', null, 5.0],
            ['antihcv', 'ไวรัสตับอักเสบซี (Anti-HCV)', '', 'Negative', null, null, 'text', $negPos],
            ['hbsag', 'ไวรัสตับอักเสบบี (HBsAg)', '', 'Negative', null, null, 'text', $negPos],
            ['antihbs', 'ภูมิตับอักเสบบี (Anti-HBs)', '', 'Negative', null, null, 'text', $negPos],
        ],
        'ความสมบูรณ์ของเม็ดเลือด (CBC)' => [
            ['rbc', 'เม็ดเลือดแดง (RBC)', 'mil/cu.mm', '4.5–6.0', 4.5, 6.0],
            ['hb', 'ฮีโมโกลบิน (Hb)', 'g/dl', '13.0–18.0', 13, 18],
            ['hct', 'ฮีมาโตคริต (Hct)', '%', '40–54', 40, 54],
            ['mcv', 'ขนาดเม็ดเลือดแดง (MCV)', 'fL', '80–99', 80, 99],
            ['wbc', 'เม็ดเลือดขาว (WBC)', 'cells/cu.mm', '4000–10000', 4000, 10000],
            ['neu', 'นิวโทรฟิล (Neutrophil)', '%', '40–74', 40, 74],
            ['lym', 'ลิมโฟไซต์ (Lymphocyte)', '%', '19–48', 19, 48],
            ['mono', 'โมโนไซต์ (Monocyte)', '%', '2–10', 2, 10],
            ['eos', 'อีโอซิโนฟิล (Eosinophil)', '%', '0–7', 0, 7],
            ['baso', 'เบโซฟิล (Basophil)', '%', '0–2', 0, 2],
            ['plt', 'เกล็ดเลือด (Platelet)', 'Cells/cu.mm', '140000–450000', 140000, 450000],
            ['morph', 'รูปร่างเม็ดเลือดแดง (RBC Morphology)', '', 'Normal', null, null, 'text', ['Normal', 'Abnormal']],
        ],
        'ปัสสาวะ (Urine)' => [
            ['u_color', 'สี (Color)', '', 'Yellow', null, null, 'text', ['Yellow', 'Pale Yellow', 'Dark Yellow', 'Amber', 'Colorless', 'Red']],
            ['u_appear', 'ลักษณะ (Appearance)', '', 'Clear', null, null, 'text', ['Clear', 'Slightly Cloudy', 'Cloudy', 'Turbid']],
            ['u_spgr', 'ความถ่วงจำเพาะ (Sp.gr)', '', '1.003–1.030', null, null, 'text'],
            ['u_ph', 'ความเป็นกรด-ด่าง (pH)', '', '5.0–8.0', null, null, 'text'],
            ['u_protein', 'โปรตีน (Protein)', '', 'Negative', null, null, 'text', $urScale],
            ['u_sugar', 'น้ำตาล (Sugar)', '', 'Negative', null, null, 'text', $urScale],
            ['u_rbc', 'เม็ดเลือดแดง (RBC)', '/HPF', '0–2', null, null, 'text'],
            ['u_wbc', 'เม็ดเลือดขาว (WBC)', '/HPF', '0–5', null, null, 'text'],
            ['u_epi', 'เซลล์เยื่อบุผิว (Epithelial)', '/HPF', '0–10', null, null, 'text'],
            ['u_other', 'อื่น ๆ (Other)', '', '', null, null, 'text'],
        ],
        'อุจจาระ (Stool)' => [
            ['s_color', 'สี (Color)', '', '', null, null, 'text', ['Brown', 'Yellow', 'Green', 'Black', 'Red']],
            ['s_appear', 'ลักษณะ (Appearance)', '', '', null, null, 'text', ['Formed', 'Soft', 'Loose', 'Watery', 'Mucous']],
            ['s_wbc', 'เม็ดเลือดขาว (WBC/HPF)', '', '', null, null, 'text'],
            ['s_rbc', 'เม็ดเลือดแดง (RBC/HPF)', '', '', null, null, 'text'],
            ['s_para', 'พยาธิและไข่ (Parasites & Ova)', '', '', null, null, 'text', ['Not found', 'Found']],
            ['s_occult', 'เลือดแฝง (Occult Blood)', '', '', null, null, 'text', $negPos],
        ],
    ];
}

/** แผนที่ code => รายการ (สำหรับค้นเร็ว) */
function checkup_tests_map(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    foreach (checkup_catalog() as $tests) foreach ($tests as $t) $m[$t[0]] = $t;
    return $m;
}

/** ประเมินค่า: '' (ปกติ/ข้อความ), 'low', 'high' */
function checkup_flag(array $test, $val): string
{
    $type = $test[6] ?? 'num';
    if ($type === 'text' || $val === null || $val === '' || !is_numeric($val)) return '';
    $v = (float) $val; $low = $test[4]; $high = $test[5];
    if ($low !== null && $v < $low) return 'low';
    if ($high !== null && $v > $high) return 'high';
    return 'ok';
}

/**
 * แคตตาล็อกค่าสุขภาพที่บันทึกได้รายครั้ง
 * code => [ชื่อ, หน่วย, ไอคอน, สี, target_low, target_high, step]
 */
function health_metrics(): array
{
    return [
        'weight' => ['น้ำหนัก', 'กก.', 'bi-speedometer2', '#0d9488', null, null, '0.1'],
        'height' => ['ส่วนสูง', 'ซม.', 'bi-rulers', '#2c5c7a', null, null, '0.5'],
        'waist'  => ['รอบเอว', 'ซม.', 'bi-person-arms-up', '#65a30d', null, 90, '0.5'],
    ];
}

/** โหลดบันทึกค่าสุขภาพทั้งหมด (ปลอดภัยถ้าตารางยังไม่มี) */
function load_health_logs(?string $metric = null, int $limit = 0): array
{
    try {
        if ($metric) {
            $sql = 'SELECT * FROM health_logs WHERE metric = ? ORDER BY log_date ASC, id ASC';
            $stmt = db()->prepare($sql); $stmt->execute([$metric]);
            $rows = $stmt->fetchAll();
        } else {
            $rows = db()->query('SELECT * FROM health_logs ORDER BY log_date DESC, id DESC')->fetchAll();
        }
        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    } catch (Throwable $e) { return []; }
}

/** ค่าล่าสุดของแต่ละ metric => [metric => ['val','date']] */
function health_latest(): array
{
    $out = [];
    try {
        foreach (db()->query('SELECT metric, val, log_date FROM health_logs ORDER BY log_date ASC, id ASC') as $r) {
            $out[$r['metric']] = ['val' => $r['val'], 'date' => $r['log_date']];
        }
    } catch (Throwable $e) {}
    return $out;
}

/** ประเมินค่า metric เทียบ target => '', 'low', 'high', 'ok' */
function metric_flag(array $meta, $val): string
{
    if ($val === null || $val === '' || !is_numeric($val)) return '';
    $v = (float) $val; $low = $meta[4]; $high = $meta[5];
    if ($low !== null && $v < $low) return 'low';
    if ($high !== null && $v > $high) return 'high';
    return 'ok';
}

/** กราฟเส้นจิ๋ว (sparkline) จากชุดตัวเลข */
function sparkline_svg(array $values, string $color = '#57b894', int $w = 120, int $h = 34): string
{
    $values = array_values(array_filter($values, fn($v) => is_numeric($v)));
    $n = count($values);
    if ($n === 0) return '';
    if ($n === 1) $values = [$values[0], $values[0]];
    $n = count($values);
    $min = min($values); $max = max($values); $range = ($max - $min) ?: 1;
    $pad = 3;
    $x = fn($i) => $pad + $i / ($n - 1) * ($w - 2 * $pad);
    $y = fn($v) => $h - $pad - ($v - $min) / $range * ($h - 2 * $pad);
    $d = ''; foreach ($values as $i => $v) $d .= ($i ? 'L' : 'M') . sprintf('%.1f %.1f ', $x($i), $y($v));
    $area = 'M ' . sprintf('%.1f %.1f', $x(0), $y($values[0]));
    foreach ($values as $i => $v) $area .= ' L ' . sprintf('%.1f %.1f', $x($i), $y($v));
    $area .= sprintf(' L %.1f %.1f L %.1f %.1f Z', $x($n - 1), $h - $pad, $x(0), $h - $pad);
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">'
        . '<path d="' . $area . '" fill="' . $color . '" opacity="0.14"/>'
        . '<path d="' . trim($d) . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'
        . '<circle cx="' . sprintf('%.1f', $x($n - 1)) . '" cy="' . sprintf('%.1f', $y($values[$n - 1])) . '" r="2.5" fill="' . $color . '"/></svg>';
}

/** แหล่งอ้างอิงค่าปกติ (สำหรับแสดงในหน้าตรวจสุขภาพ) */
function reference_sources(): array
{
    return [
        ['ความดันโลหิต', 'ค่าเริ่มต้น ACC/AHA 2017 (สากล) · สลับเป็นเกณฑ์สมาคมความดันโลหิตสูงแห่งประเทศไทยได้ที่แดชบอร์ด · วัดที่บ้านสูงเมื่อ ≥135/85'],
        ['ดัชนีมวลกาย (BMI)', 'เกณฑ์เอเชีย-แปซิฟิก (WHO Asia-Pacific 2004)'],
        ['รอบเอว', 'ชาย < 90 ซม. · หญิง < 80 ซม. (IDF / กรมอนามัย)'],
        ['ระดับไขมันในเลือด', 'NCEP ATP III / แนวทางราชวิทยาลัยอายุรแพทย์ฯ'],
        ['น้ำตาล / HbA1c', 'สมาคมโรคเบาหวานแห่งประเทศไทย (ADA/สมาคมฯ)'],
        ['ค่าห้องปฏิบัติการ (Lab)', 'ช่วงอ้างอิงตามใบรายงานผลของห้องปฏิบัติการโรงพยาบาล'],
    ];
}

/** โหลดการตรวจสุขภาพทั้งหมด (ปลอดภัยถ้าตารางยังไม่มี) */
function load_checkups(): array
{
    try {
        return db()->query('SELECT * FROM checkups ORDER BY checkup_date DESC, id DESC')->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** โหลดผลตรวจของการตรวจหนึ่งครั้ง => [code => val] */
function load_checkup_values(int $id): array
{
    try {
        $stmt = db()->prepare('SELECT code, val FROM checkup_values WHERE checkup_id = ?');
        $stmt->execute([$id]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) $out[$r['code']] = $r['val'];
        return $out;
    } catch (Throwable $e) { return []; }
}

/** หาไฟล์รูปโปรไฟล์ที่มีอยู่จริง (รองรับ .png .jpg .jpeg .webp) */
function resolve_profile_photo(array $profile, string $baseDir): ?string
{
    $configured = $profile['photo'] ?? '';
    if ($configured && is_file($baseDir . '/' . $configured)) return $configured;
    foreach (['assets/profile.png', 'assets/profile.jpg', 'assets/profile.jpeg', 'assets/profile.webp'] as $c) {
        if (is_file($baseDir . '/' . $c)) return $c;
    }
    return null;
}

/**
 * โหลดรายการช่วง (phases) เรียงตามวันเริ่ม
 * ปลอดภัยแม้ตาราง phases ยังไม่ถูกสร้าง (คืน [] )
 */
function load_phases(): array
{
    try {
        return db()->query('SELECT * FROM phases ORDER BY start_date ASC, id ASC')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * หาช่วงที่ครอบคลุมวันที่ที่กำหนด
 * (ช่วงล่าสุดที่ start_date <= วันที่นั้น)
 */
function phase_for_date(array $phases, ?string $date): ?array
{
    if (!$date) return null;
    $found = null;
    foreach ($phases as $p) {
        if ($p['start_date'] <= $date) $found = $p;
        else break;
    }
    return $found;
}

/** คำนวณ BMI จากน้ำหนัก(กก.) และส่วนสูง(ซม.) */
function calc_bmi($w, $h): ?float
{
    $w = (float) $w; $h = (float) $h;
    if ($w <= 0 || $h <= 0) return null;
    return round($w / (($h / 100) ** 2), 1);
}

/** แปลผล BMI (เกณฑ์เอเชีย) => [label, class] */
function bmi_category(?float $bmi): array
{
    if ($bmi === null) return ['-', 'bp-none'];
    if ($bmi < 18.5) return ['น้ำหนักน้อย', 'bp-elevated'];
    if ($bmi < 23)   return ['ปกติ', 'bp-normal'];
    if ($bmi < 25)   return ['ท้วม', 'bp-stage1'];
    if ($bmi < 30)   return ['อ้วน', 'bp-stage2'];
    return ['อ้วนมาก', 'bp-crisis'];
}
