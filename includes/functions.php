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
