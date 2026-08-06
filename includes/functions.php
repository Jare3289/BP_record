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
 * แปลผลความดันโลหิตตามเกณฑ์ ACC/AHA
 * คืน ['label' => ข้อความ, 'level' => 0-4, 'class' => css class]
 *
 * level: 0 = ปกติ, 1 = สูงเล็กน้อย, 2 = ระยะที่1, 3 = ระยะที่2, 4 = วิกฤต
 */
function classify_bp(?int $sys, ?int $dia): array
{
    if ($sys === null || $dia === null) {
        return ['label' => '-', 'level' => -1, 'class' => 'bp-none'];
    }

    // ภาวะวิกฤต (Hypertensive crisis)
    if ($sys >= 180 || $dia >= 120) {
        return ['label' => 'ภาวะวิกฤต ควรพบแพทย์', 'level' => 4, 'class' => 'bp-crisis'];
    }
    // ระยะที่ 2
    if ($sys >= 140 || $dia >= 90) {
        return ['label' => 'ความดันโลหิตสูง ระยะที่ 2', 'level' => 3, 'class' => 'bp-stage2'];
    }
    // ระยะที่ 1
    if ($sys >= 130 || $dia >= 80) {
        return ['label' => 'ความดันโลหิตสูง ระยะที่ 1', 'level' => 2, 'class' => 'bp-stage1'];
    }
    // สูงเล็กน้อย (Elevated)
    if ($sys >= 120) {
        return ['label' => 'ความดันโลหิตสูงเล็กน้อย', 'level' => 1, 'class' => 'bp-elevated'];
    }
    // ปกติ
    return ['label' => 'ปกติ', 'level' => 0, 'class' => 'bp-normal'];
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
                $h .= '<td class="px-cell ' . $cls . '" title="' . date('j/n/Y', strtotime($ds)) . '"></td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
    }
    $h .= '<div class="pixel-legend">'
        . '<span><span class="px-cell px-normal"></span> ปกติ</span>'
        . '<span><span class="px-cell px-elevated"></span> สูงเล็กน้อย</span>'
        . '<span><span class="px-cell px-stage1"></span> ระยะที่ 1</span>'
        . '<span><span class="px-cell px-stage2"></span> ระยะที่ 2</span>'
        . '<span><span class="px-cell px-crisis"></span> วิกฤต</span>'
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
