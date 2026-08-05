<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$rows = db()->query('SELECT * FROM bp_readings ORDER BY record_date ASC, id ASC')->fetchAll();

$morning = []; $night = [];
foreach ($rows as $r) {
    $ms = avg_of([$r['m1_sys'], $r['m2_sys']]);
    $md = avg_of([$r['m1_dia'], $r['m2_dia']]);
    if ($ms !== null && $md !== null) $morning[] = ['sys' => $ms, 'dia' => $md, 'date' => $r['record_date']];
    $ns = avg_of([$r['n1_sys'], $r['n2_sys']]);
    $nd = avg_of([$r['n1_dia'], $r['n2_dia']]);
    if ($ns !== null && $nd !== null) $night[] = ['sys' => $ns, 'dia' => $nd, 'date' => $r['record_date']];
}

$DIA_MIN = 40; $DIA_MAX = 100; $SYS_MIN = 70; $SYS_MAX = 170;
$W = 640; $H = 500; $L = 52; $R = 20; $T = 24; $B = 46;
$plotW = $W - $L - $R; $plotH = $H - $T - $B;
$px = function ($dia) use ($L, $plotW, $DIA_MIN, $DIA_MAX) {
    $dia = max($DIA_MIN, min($DIA_MAX, $dia));
    return $L + ($dia - $DIA_MIN) / ($DIA_MAX - $DIA_MIN) * $plotW;
};
$py = function ($sys) use ($T, $plotH, $SYS_MIN, $SYS_MAX) {
    $sys = max($SYS_MIN, min($SYS_MAX, $sys));
    return $T + ($SYS_MAX - $sys) / ($SYS_MAX - $SYS_MIN) * $plotH;
};
$zone = function ($d0, $d1, $s0, $s1, $fill, $extra = '') use ($px, $py) {
    return sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="%s" %s/>',
        $px($d0), $py($s1), $px($d1) - $px($d0), $py($s0) - $py($s1), $fill, $extra);
};

// สถิติแนวโน้ม 7 วันล่าสุด (สำหรับกราฟเส้น)
$recent = array_slice($rows, -14);

$PAGE = 'กราฟแนวโน้ม'; $ACTIVE = 'chart';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
  <h1 class="page-title mb-1"><i class="bi bi-graph-up-arrow"></i> กราฟแนวโน้มความดันโลหิต</h1>
  <p class="text-secondary mb-0">ความดันบน (แกนตั้ง) เทียบความดันล่าง (แกนนอน) · เกณฑ์วัดที่บ้าน 135/85</p>
</div>

<div class="row g-4">
  <div class="col-12 col-xl-8">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-bullseye"></i> แผนภาพกระจายตามโซนระดับความดัน</div>
      <div class="card-body">
        <div class="chart-legend mb-3">
          <span class="lg"><span class="dot dot-morning"></span> ค่าเฉลี่ยตอนเช้า</span>
          <span class="lg"><span class="dot dot-night"></span> ค่าเฉลี่ยก่อนนอน</span>
          <span class="vr-sep"></span>
          <span class="lg"><span class="sw" style="background:#5b3a9e"></span> ต่ำ</span>
          <span class="lg"><span class="sw" style="background:#1aa89a"></span> ปกติ</span>
          <span class="lg"><span class="sw" style="background:#f2a71b"></span> ค่อนข้างสูง</span>
          <span class="lg"><span class="sw" style="background:#d9534f"></span> สูง</span>
        </div>
        <?php if (!$morning && !$night): ?>
          <p class="text-center text-secondary py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>
            ยังไม่มีข้อมูล — <a href="index.php">เพิ่มบันทึกก่อน</a></p>
        <?php else: ?>
        <div class="chart-box">
        <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="bp-chart" role="img" aria-label="กราฟกระจายความดันโลหิต">
          <defs>
            <pattern id="hatch" width="8" height="8" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
              <rect width="8" height="8" fill="#d9534f"/>
              <line x1="0" y1="0" x2="0" y2="8" stroke="#c64541" stroke-width="4"/>
            </pattern>
          </defs>
          <?= $zone($DIA_MIN, $DIA_MAX, $SYS_MIN, $SYS_MAX, 'url(#hatch)') ?>
          <?= $zone(40, 80, 70, 120, '#1aa89a') ?>
          <?= $zone(40, 85, 120, 135, '#f2a71b') ?>
          <?= $zone(80, 85, 70, 120, '#f2a71b') ?>
          <?= $zone(40, 60, 70, 90, '#5b3a9e') ?>
          <?php foreach ([70, 90, 120, 135, 170] as $s): $y = $py($s); ?>
            <line x1="<?= $L ?>" y1="<?= $y ?>" x2="<?= $L + $plotW ?>" y2="<?= $y ?>" stroke="#fff" stroke-width="1" opacity="0.5"/>
            <text x="<?= $L - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end" class="axl"><?= $s ?></text>
          <?php endforeach; ?>
          <?php foreach ([40, 60, 80, 85, 100] as $d): $x = $px($d); ?>
            <line x1="<?= $x ?>" y1="<?= $T ?>" x2="<?= $x ?>" y2="<?= $T + $plotH ?>" stroke="#fff" stroke-width="1" opacity="0.35"/>
            <text x="<?= $x ?>" y="<?= $T + $plotH + 18 ?>" text-anchor="middle" class="axl"><?= $d ?></text>
          <?php endforeach; ?>
          <rect x="<?= $L ?>" y="<?= $T ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" fill="none" stroke="#cbd5e1" stroke-width="1"/>
          <text x="<?= $L ?>" y="16" class="axttl">Systolic (ความดันบน)</text>
          <text x="<?= $L + $plotW ?>" y="<?= $H - 8 ?>" text-anchor="end" class="axttl">Diastolic (ความดันล่าง)</text>
          <?php foreach ($night as $p): ?>
            <circle cx="<?= sprintf('%.1f', $px($p['dia'])) ?>" cy="<?= sprintf('%.1f', $py($p['sys'])) ?>" r="4"
                    fill="#1f2937" stroke="#fff" stroke-width="1">
              <title>ก่อนนอน <?= fmt_date($p['date']) ?> — <?= $p['sys'] ?>/<?= $p['dia'] ?></title></circle>
          <?php endforeach; ?>
          <?php foreach ($morning as $p): ?>
            <circle cx="<?= sprintf('%.1f', $px($p['dia'])) ?>" cy="<?= sprintf('%.1f', $py($p['sys'])) ?>" r="4"
                    fill="#fff" stroke="#334155" stroke-width="1.2">
              <title>เช้า <?= fmt_date($p['date']) ?> — <?= $p['sys'] ?>/<?= $p['dia'] ?></title></circle>
          <?php endforeach; ?>
        </svg>
        </div>
        <p class="text-center text-secondary small mt-2 mb-0">
          <i class="bi bi-info-circle"></i> แต่ละจุดคือค่าเฉลี่ยการวัด 2 ครั้งในช่วงนั้น · ชี้ที่จุดเพื่อดูรายละเอียด</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- กราฟเส้น 14 วันล่าสุด -->
  <div class="col-12 col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-activity"></i> แนวโน้มค่าเฉลี่ยรายวัน (14 วันล่าสุด)</div>
      <div class="card-body">
        <?php
        $pts = [];
        foreach ($recent as $r) {
            $av = daily_average($r);
            if ($av['sys'] !== null) $pts[] = ['date' => $r['record_date'], 'sys' => $av['sys'], 'dia' => $av['dia']];
        }
        if (!$pts): ?>
          <p class="text-center text-secondary py-5">ยังไม่มีข้อมูล</p>
        <?php else:
          $LW = 360; $LH = 320; $lL = 34; $lR = 12; $lT = 14; $lB = 46;
          $lpw = $LW - $lL - $lR; $lph = $LH - $lT - $lB;
          $ymin = 60; $ymax = 180;
          $lx = fn($i) => $lL + (count($pts) <= 1 ? $lpw / 2 : $i / (count($pts) - 1) * $lpw);
          $ly = fn($v) => $lT + ($ymax - max($ymin, min($ymax, $v))) / ($ymax - $ymin) * $lph;
          $line = function ($key, $color) use ($pts, $lx, $ly) {
              $d = '';
              foreach ($pts as $i => $p) $d .= ($i ? 'L' : 'M') . sprintf('%.1f %.1f ', $lx($i), $ly($p[$key]));
              return '<path d="' . trim($d) . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linejoin="round"/>';
          };
        ?>
        <div class="chart-legend mb-2 justify-content-center">
          <span class="lg"><span class="sw" style="background:#dc3545"></span> บน</span>
          <span class="lg"><span class="sw" style="background:#0d6efd"></span> ล่าง</span>
        </div>
        <div class="chart-box">
        <svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" class="bp-chart" role="img" aria-label="กราฟเส้นแนวโน้ม">
          <?php foreach ([80, 120, 135, 160] as $g): $y = $ly($g); ?>
            <line x1="<?= $lL ?>" y1="<?= $y ?>" x2="<?= $lL + $lpw ?>" y2="<?= $y ?>" stroke="#e5e7eb" stroke-width="1"/>
            <text x="<?= $lL - 6 ?>" y="<?= $y + 4 ?>" text-anchor="end" class="axl"><?= $g ?></text>
          <?php endforeach; ?>
          <?= $line('dia', '#0d6efd') ?>
          <?= $line('sys', '#dc3545') ?>
          <?php foreach ($pts as $i => $p): ?>
            <circle cx="<?= sprintf('%.1f', $lx($i)) ?>" cy="<?= sprintf('%.1f', $ly($p['sys'])) ?>" r="3" fill="#dc3545"><title><?= fmt_date($p['date']) ?> บน <?= $p['sys'] ?></title></circle>
            <circle cx="<?= sprintf('%.1f', $lx($i)) ?>" cy="<?= sprintf('%.1f', $ly($p['dia'])) ?>" r="3" fill="#0d6efd"><title><?= fmt_date($p['date']) ?> ล่าง <?= $p['dia'] ?></title></circle>
          <?php endforeach; ?>
          <?php $n = count($pts); foreach ($pts as $i => $p): if ($n > 1 && $i % max(1, intval($n / 5)) !== 0 && $i !== $n - 1) continue; ?>
            <text x="<?= sprintf('%.1f', $lx($i)) ?>" y="<?= $lT + $lph + 16 ?>" text-anchor="middle" class="axl" transform="rotate(35 <?= sprintf('%.1f', $lx($i)) ?> <?= $lT + $lph + 16 ?>)"><?= date('j/n', strtotime($p['date'])) ?></text>
          <?php endforeach; ?>
        </svg>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
