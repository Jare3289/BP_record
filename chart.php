<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$rows = db()->query('SELECT * FROM bp_readings ORDER BY record_date ASC, id ASC')->fetchAll();

// สร้างจุดข้อมูล: ค่าเฉลี่ยตอนเช้า และ ค่าเฉลี่ยก่อนนอน ของแต่ละวัน
$morning = []; // จุดขาว
$night   = []; // จุดเข้ม
foreach ($rows as $r) {
    $ms = avg_of([$r['m1_sys'], $r['m2_sys']]);
    $md = avg_of([$r['m1_dia'], $r['m2_dia']]);
    if ($ms !== null && $md !== null) {
        $morning[] = ['sys' => $ms, 'dia' => $md, 'date' => $r['record_date']];
    }
    $ns = avg_of([$r['n1_sys'], $r['n2_sys']]);
    $nd = avg_of([$r['n1_dia'], $r['n2_dia']]);
    if ($ns !== null && $nd !== null) {
        $night[] = ['sys' => $ns, 'dia' => $nd, 'date' => $r['record_date']];
    }
}

// ---- ระบบพิกัด SVG ----
$DIA_MIN = 40;  $DIA_MAX = 100;
$SYS_MIN = 70;  $SYS_MAX = 170;

$W = 640; $H = 500;
$L = 52; $R = 20; $T = 24; $B = 46;
$plotW = $W - $L - $R;
$plotH = $H - $T - $B;

$px = function ($dia) use ($L, $plotW, $DIA_MIN, $DIA_MAX) {
    $dia = max($DIA_MIN, min($DIA_MAX, $dia));
    return $L + ($dia - $DIA_MIN) / ($DIA_MAX - $DIA_MIN) * $plotW;
};
$py = function ($sys) use ($T, $plotH, $SYS_MIN, $SYS_MAX) {
    $sys = max($SYS_MIN, min($SYS_MAX, $sys));
    return $T + ($SYS_MAX - $sys) / ($SYS_MAX - $SYS_MIN) * $plotH;
};

// helper วาดสี่เหลี่ยมโซนจากพิกัดข้อมูล
$zone = function ($d0, $d1, $s0, $s1, $fill, $extra = '') use ($px, $py) {
    $x = $px($d0); $w = $px($d1) - $px($d0);
    $y = $py($s1); $h = $py($s0) - $py($s1);
    return sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="%s" %s/>', $x, $y, $w, $h, $fill, $extra);
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>กราฟแนวโน้มความดันโลหิต</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
  <header class="page-head">
    <h1>📈 กราฟแนวโน้มความดันโลหิต</h1>
    <p class="sub">ความดันบน (แกนตั้ง) เทียบกับ ความดันล่าง (แกนนอน) · เกณฑ์วัดที่บ้าน 135/85</p>
  </header>

  <nav class="tabs">
    <a href="index.php" class="tab">📋 บันทึก/ตาราง</a>
    <a href="chart.php" class="tab active">📈 กราฟแนวโน้ม</a>
  </nav>

  <section class="panel">
    <div class="chart-legend">
      <span class="lg"><span class="dot dot-morning"></span> ค่าเฉลี่ยตอนเช้า</span>
      <span class="lg"><span class="dot dot-night"></span> ค่าเฉลี่ยก่อนนอน</span>
      <span class="lg sep"></span>
      <span class="lg"><span class="sw" style="background:#5b3a9e"></span> ต่ำ</span>
      <span class="lg"><span class="sw" style="background:#1aa89a"></span> ปกติ</span>
      <span class="lg"><span class="sw" style="background:#f2a71b"></span> ค่อนข้างสูง</span>
      <span class="lg"><span class="sw" style="background:#d9534f"></span> สูง</span>
    </div>

    <div class="chart-box">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="bp-chart" role="img" aria-label="กราฟกระจายความดันโลหิต">
      <defs>
        <pattern id="hatch" width="8" height="8" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
          <rect width="8" height="8" fill="#d9534f"/>
          <line x1="0" y1="0" x2="0" y2="8" stroke="#c64541" stroke-width="4"/>
        </pattern>
      </defs>

      <!-- โซนสี -->
      <!-- พื้นหลังทั้งหมด = สูง (แดง) -->
      <?= $zone($DIA_MIN, $DIA_MAX, $SYS_MIN, $SYS_MAX, 'url(#hatch)') ?>
      <!-- ปกติ (เขียว): dia 40-80, sys 70-120 -->
      <?= $zone(40, 80, 70, 120, '#1aa89a') ?>
      <!-- ค่อนข้างสูง (ส้ม) แนวนอน: dia 40-85, sys 120-135 -->
      <?= $zone(40, 85, 120, 135, '#f2a71b') ?>
      <!-- ค่อนข้างสูง (ส้ม) แนวตั้ง: dia 80-85, sys 70-120 -->
      <?= $zone(80, 85, 70, 120, '#f2a71b') ?>
      <!-- ต่ำ (ม่วง): dia 40-60, sys 70-90 -->
      <?= $zone(40, 60, 70, 90, '#5b3a9e') ?>

      <!-- เส้นตาราง + ป้ายแกน Y (systolic) -->
      <?php foreach ([70, 90, 120, 135, 170] as $s): $y = $py($s); ?>
        <line x1="<?= $L ?>" y1="<?= $y ?>" x2="<?= $L + $plotW ?>" y2="<?= $y ?>" stroke="#ffffff" stroke-width="1" opacity="0.5"/>
        <text x="<?= $L - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" class="axl"><?= $s ?></text>
      <?php endforeach; ?>

      <!-- ป้ายแกน X (diastolic) -->
      <?php foreach ([40, 60, 80, 85, 100] as $d): $x = $px($d); ?>
        <line x1="<?= $x ?>" y1="<?= $T ?>" x2="<?= $x ?>" y2="<?= $T + $plotH ?>" stroke="#ffffff" stroke-width="1" opacity="0.35"/>
        <text x="<?= $x ?>" y="<?= $T + $plotH + 18 ?>" text-anchor="middle" class="axl"><?= $d ?></text>
      <?php endforeach; ?>

      <!-- กรอบ -->
      <rect x="<?= $L ?>" y="<?= $T ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" fill="none" stroke="#cbd5e1" stroke-width="1"/>

      <!-- ชื่อแกน -->
      <text x="<?= $L ?>" y="16" class="axttl">Systolic (ความดันบน)</text>
      <text x="<?= $L + $plotW ?>" y="<?= $H - 8 ?>" text-anchor="end" class="axttl">Diastolic (ความดันล่าง)</text>

      <!-- จุดข้อมูล: ก่อนนอน (เข้ม) วาดก่อน -->
      <?php foreach ($night as $p): ?>
        <circle cx="<?= sprintf('%.1f', $px($p['dia'])) ?>" cy="<?= sprintf('%.1f', $py($p['sys'])) ?>" r="4"
                fill="#1f2937" stroke="#ffffff" stroke-width="1">
          <title>ก่อนนอน <?= fmt_date($p['date']) ?> — <?= $p['sys'] ?>/<?= $p['dia'] ?></title>
        </circle>
      <?php endforeach; ?>
      <!-- จุดข้อมูล: เช้า (ขาว) -->
      <?php foreach ($morning as $p): ?>
        <circle cx="<?= sprintf('%.1f', $px($p['dia'])) ?>" cy="<?= sprintf('%.1f', $py($p['sys'])) ?>" r="4"
                fill="#ffffff" stroke="#334155" stroke-width="1.2">
          <title>เช้า <?= fmt_date($p['date']) ?> — <?= $p['sys'] ?>/<?= $p['dia'] ?></title>
        </circle>
      <?php endforeach; ?>
    </svg>
    </div>

    <?php if (!$morning && !$night): ?>
      <p class="empty" style="text-align:center">ยังไม่มีข้อมูลสำหรับวาดกราฟ — <a href="index.php">เพิ่มบันทึกก่อน</a></p>
    <?php else: ?>
      <p class="chart-note">แต่ละจุดคือค่าเฉลี่ยของการวัด 2 ครั้งในช่วงนั้น · ชี้ที่จุดเพื่อดูวันที่และค่า</p>
    <?php endif; ?>
  </section>

  <footer class="page-foot">
    เกณฑ์วัดที่บ้าน: สูง ≥ 135/85 · ข้อมูลนี้ใช้เพื่อการติดตามเบื้องต้น ไม่ทดแทนคำวินิจฉัยของแพทย์
  </footer>
</div>
</body>
</html>
