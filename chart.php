<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$rows   = db()->query('SELECT * FROM bp_readings ORDER BY record_date ASC, id ASC')->fetchAll();
$phases = load_phases();

// เตรียมข้อมูลรายวัน (เรียงเก่า→ใหม่)
$daily = [];
foreach ($rows as $r) {
    $a  = daily_average($r);
    $ph = phase_for_date($phases, $r['record_date']);
    $daily[] = [
        'date'   => $r['record_date'],
        'sys'    => $a['sys'],
        'dia'    => $a['dia'],
        'hr'     => $a['hr'],
        'weight' => isset($r['weight']) && $r['weight'] !== null ? (float)$r['weight'] : null,
        'height' => isset($r['height']) && $r['height'] !== null ? (float)$r['height'] : null,
        'phase'  => $ph,
    ];
}

// จุด scatter (มีทั้ง sys/dia)
$scatter = array_values(array_filter($daily, fn($d) => $d['sys'] !== null && $d['dia'] !== null));

// เปรียบเทียบค่าเฉลี่ยรายช่วง
$byPhase = [];
foreach ($daily as $d) {
    $key = $d['phase'] ? $d['phase']['id'] : 0;
    $byPhase[$key]['name']  = $d['phase'] ? $d['phase']['name'] : 'ไม่ระบุช่วง';
    $byPhase[$key]['color'] = $d['phase'] ? $d['phase']['color'] : '#94a3b8';
    if ($d['sys'] !== null) $byPhase[$key]['sys'][] = $d['sys'];
    if ($d['dia'] !== null) $byPhase[$key]['dia'][] = $d['dia'];
}
$phaseStats = [];
foreach ($byPhase as $k => $v) {
    $ns = $v['sys'] ?? []; $nd = $v['dia'] ?? [];
    if (!$ns) continue;
    $phaseStats[] = [
        'name'  => $v['name'], 'color' => $v['color'],
        'sys'   => (int) round(array_sum($ns) / count($ns)),
        'dia'   => $nd ? (int) round(array_sum($nd) / count($nd)) : 0,
        'days'  => count($ns),
    ];
}

$weightPts = array_values(array_filter($daily, fn($d) => $d['weight'] !== null));
$latestWH  = null;
for ($i = count($daily) - 1; $i >= 0; $i--) {
    if ($daily[$i]['weight'] !== null && $daily[$i]['height'] !== null) { $latestWH = $daily[$i]; break; }
}
$bmi  = $latestWH ? calc_bmi($latestWH['weight'], $latestWH['height']) : null;
$bmiC = bmi_category($bmi);

/** ---- ตัวช่วยวาดกราฟเส้น (รองรับหลายเส้น + ค่าว่าง) ---- */
function svg_line(array $pts, array $series, float $ymin, float $ymax, array $grid, int $W = 900, int $H = 240, int $every = 0): string
{
    $L = 40; $R = 14; $T = 16; $B = 32;
    $pw = $W - $L - $R; $ph = $H - $T - $B; $n = count($pts);
    if ($n === 0) return '';
    $x = fn($i) => $L + ($n <= 1 ? $pw / 2 : $i / ($n - 1) * $pw);
    $y = fn($v) => $T + ($ymax - max($ymin, min($ymax, $v))) / ($ymax - $ymin) * $ph;
    $every = $every ?: max(1, (int) ceil($n / 8));

    $out = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="line-svg" preserveAspectRatio="none">';
    foreach ($grid as $g) {
        $yy = $y($g);
        $out .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="var(--bs-border-color)" stroke-width="1" stroke-dasharray="3 4"/>', $L, $yy, $L + $pw, $yy);
        $out .= sprintf('<text x="%d" y="%.1f" text-anchor="end" class="axl">%s</text>', $L - 6, $yy + 4, $g);
    }
    foreach ($series as $s) {
        $key = $s['key']; $color = $s['color']; $w = $s['width'] ?? 2.5;
        // เส้น (เว้นช่องเมื่อค่าว่าง)
        $d = ''; $pen = false;
        foreach ($pts as $i => $p) {
            if (($p[$key] ?? null) === null) { $pen = false; continue; }
            $d .= ($pen ? 'L' : 'M') . sprintf('%.1f %.1f ', $x($i), $y($p[$key]));
            $pen = true;
        }
        // พื้นที่ไล่สี (เฉพาะเส้นที่ไม่มีค่าว่าง)
        if (!empty($s['area'])) {
            $hasNull = false;
            foreach ($pts as $p) if (($p[$key] ?? null) === null) { $hasNull = true; break; }
            if (!$hasNull && $n > 0) {
                $ad = 'M ' . sprintf('%.1f %.1f', $x(0), $y($pts[0][$key]));
                foreach ($pts as $i => $p) $ad .= ' L ' . sprintf('%.1f %.1f', $x($i), $y($p[$key]));
                $ad .= sprintf(' L %.1f %.1f L %.1f %.1f Z', $x($n - 1), $T + $ph, $x(0), $T + $ph);
                $out .= sprintf('<path d="%s" fill="%s" opacity="0.14"/>', $ad, $color);
            }
        }
        $out .= sprintf('<path d="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linejoin="round"/>', trim($d), $color, $w);
        // จุด marker
        foreach ($pts as $i => $p) {
            if (($p[$key] ?? null) === null) continue;
            $out .= sprintf('<circle cx="%.1f" cy="%.1f" r="3" fill="#fff" stroke="%s" stroke-width="2"><title>%s · %s</title></circle>',
                $x($i), $y($p[$key]), $color, fmt_date($p['date']), $p[$key]);
        }
    }
    foreach ($pts as $i => $p) {
        if ($i % $every !== 0 && $i !== $n - 1) continue;
        $out .= sprintf('<text x="%.1f" y="%d" text-anchor="middle" class="axl">%s</text>', $x($i), $T + $ph + 20, date('j/n', strtotime($p['date'])));
    }
    return $out . '</svg>';
}

// พิกัด scatter
$DIA_MIN = 40; $DIA_MAX = 100; $SYS_MIN = 70; $SYS_MAX = 170;
$W = 640; $H = 500; $L = 52; $R = 20; $T = 24; $B = 46;
$plotW = $W - $L - $R; $plotH = $H - $T - $B;
$px = fn($dia) => $L + (max($DIA_MIN, min($DIA_MAX, $dia)) - $DIA_MIN) / ($DIA_MAX - $DIA_MIN) * $plotW;
$py = fn($sys) => $T + ($SYS_MAX - max($SYS_MIN, min($SYS_MAX, $sys))) / ($SYS_MAX - $SYS_MIN) * $plotH;
$zone = fn($d0, $d1, $s0, $s1, $fill, $ex = '') =>
    sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="%s" %s/>', $px($d0), $py($s1), $px($d1) - $px($d0), $py($s0) - $py($s1), $fill, $ex);

$PAGE = 'กราฟแนวโน้ม'; $ACTIVE = 'chart';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
  <h1 class="page-title mb-1"><i class="bi bi-graph-up-arrow"></i> กราฟแนวโน้มความดันโลหิต</h1>
  <p class="text-secondary mb-0">แผนภาพกระจาย · เปรียบเทียบตามช่วง · แนวโน้มความดัน ชีพจร และน้ำหนัก</p>
</div>

<div class="row g-4">
  <!-- Scatter -->
  <div class="col-12 col-xl-7">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-bullseye"></i> แผนภาพกระจายตามโซนระดับความดัน</div>
      <div class="card-body">
        <?php if ($phases): ?>
        <div class="phase-chips mb-3">
          <button class="phase-chip active" onclick="highlightPhase('all', this)"><i class="bi bi-grid-3x3"></i> ทั้งหมด</button>
          <?php foreach ($phases as $p): ?>
            <button class="phase-chip" style="--pc:<?= e($p['color']) ?>" onclick="highlightPhase('<?= (int)$p['id'] ?>', this)">
              <i class="bi bi-circle-fill"></i> <?= e($p['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-light border small mb-3"><i class="bi bi-lightbulb"></i>
          เพิ่ม <a href="phases.php">ช่วงการรักษา</a> เพื่อไฮไลต์จุดตามช่วง (เช่น ก่อนกินยา / กินยาช่วงที่ 1)</div>
        <?php endif; ?>

        <?php if (!$scatter): ?>
          <p class="text-center text-secondary py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล</p>
        <?php else: ?>
        <div class="chart-box">
        <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="bp-chart" role="img">
          <defs><pattern id="hatch" width="8" height="8" patternTransform="rotate(45)" patternUnits="userSpaceOnUse">
            <rect width="8" height="8" fill="#d9534f"/><line x1="0" y1="0" x2="0" y2="8" stroke="#c64541" stroke-width="4"/></pattern></defs>
          <?= $zone($DIA_MIN, $DIA_MAX, $SYS_MIN, $SYS_MAX, 'url(#hatch)') ?>
          <?= $zone(40, 80, 70, 120, '#1aa89a') ?>
          <?= $zone(40, 85, 120, 135, '#f2a71b') ?>
          <?= $zone(80, 85, 70, 120, '#f2a71b') ?>
          <?= $zone(40, 60, 70, 90, '#5b3a9e') ?>
          <?php foreach ([70,90,120,135,170] as $s): $y=$py($s); ?>
            <line x1="<?= $L ?>" y1="<?= $y ?>" x2="<?= $L+$plotW ?>" y2="<?= $y ?>" stroke="#fff" stroke-width="1" opacity="0.5"/>
            <text x="<?= $L-8 ?>" y="<?= $y+4 ?>" text-anchor="end" class="axl"><?= $s ?></text>
          <?php endforeach; ?>
          <?php foreach ([40,60,80,85,100] as $d): $x=$px($d); ?>
            <line x1="<?= $x ?>" y1="<?= $T ?>" x2="<?= $x ?>" y2="<?= $T+$plotH ?>" stroke="#fff" stroke-width="1" opacity="0.35"/>
            <text x="<?= $x ?>" y="<?= $T+$plotH+18 ?>" text-anchor="middle" class="axl"><?= $d ?></text>
          <?php endforeach; ?>
          <rect x="<?= $L ?>" y="<?= $T ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" fill="none" stroke="#cbd5e1"/>
          <text x="<?= $L ?>" y="16" class="axttl">Systolic (ความดันบน)</text>
          <text x="<?= $L+$plotW ?>" y="<?= $H-8 ?>" text-anchor="end" class="axttl">Diastolic (ความดันล่าง)</text>
          <?php foreach ($scatter as $d): $ph=$d['phase']; ?>
            <circle class="scatter-pt" cx="<?= sprintf('%.1f',$px($d['dia'])) ?>" cy="<?= sprintf('%.1f',$py($d['sys'])) ?>" r="4.5"
              fill="#2c5c7a" stroke="#fff" stroke-width="1.2"
              data-base="#2c5c7a" data-phase="<?= $ph ? (int)$ph['id'] : '' ?>" data-color="<?= $ph ? e($ph['color']) : '#57b894' ?>">
              <title><?= fmt_date($d['date']) ?> — <?= $d['sys'] ?>/<?= $d['dia'] ?><?= $ph ? ' · '.e($ph['name']) : '' ?></title>
            </circle>
          <?php endforeach; ?>
        </svg>
        </div>
        <p class="text-center text-secondary small mt-2 mb-0"><i class="bi bi-info-circle"></i>
          จุดสีเดียวคือภาพรวม · กดชิปช่วงด้านบนเพื่อไฮไลต์เฉพาะช่วงนั้น</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- เปรียบเทียบรายช่วง -->
  <div class="col-12 col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-bar-chart-line"></i> เปรียบเทียบค่าเฉลี่ยตามช่วง</div>
      <div class="card-body">
        <?php if (!$phaseStats || count($phaseStats) < 1): ?>
          <p class="text-center text-secondary py-5"><i class="bi bi-signpost fs-3 d-block mb-2"></i>
            ยังไม่มีช่วง — <a href="phases.php">เพิ่มช่วงการรักษา</a><br><span class="small">เพื่อเปรียบเทียบค่าก่อน/หลังกินยา</span></p>
        <?php else:
          $maxv = 180; $gh = 240; $baseY = 200; $scaleY = fn($v) => $baseY - ($v / $maxv) * ($baseY - 20);
          $groupN = count($phaseStats); $gw = 100 / $groupN;
        ?>
        <div class="chart-box">
        <svg viewBox="0 0 <?= max(360, $groupN*130) ?> 250" class="bar-cmp" preserveAspectRatio="xMidYMid meet">
          <?php $CW = max(360, $groupN*130); $pad = 30; $areaW = $CW - $pad*2; $slot = $areaW / $groupN;
            foreach ([80,120,135,160] as $g): $gy=$scaleY($g); ?>
            <line x1="<?= $pad ?>" y1="<?= $gy ?>" x2="<?= $CW-$pad ?>" y2="<?= $gy ?>" stroke="var(--bs-border-color)" stroke-dasharray="3 4"/>
            <text x="<?= $pad-4 ?>" y="<?= $gy+4 ?>" text-anchor="end" class="axl"><?= $g ?></text>
          <?php endforeach; ?>
          <?php foreach ($phaseStats as $i => $ps):
            $cx = $pad + $slot*$i + $slot/2; $bw = 22;
            $sysY = $scaleY($ps['sys']); $diaY = $scaleY($ps['dia']); ?>
            <rect x="<?= $cx-$bw-3 ?>" y="<?= $sysY ?>" width="<?= $bw ?>" height="<?= $baseY-$sysY ?>" rx="5" fill="<?= e($ps['color']) ?>"><title><?= e($ps['name']) ?> บนเฉลี่ย <?= $ps['sys'] ?></title></rect>
            <rect x="<?= $cx+3 ?>" y="<?= $diaY ?>" width="<?= $bw ?>" height="<?= $baseY-$diaY ?>" rx="5" fill="<?= e($ps['color']) ?>" opacity="0.45"><title><?= e($ps['name']) ?> ล่างเฉลี่ย <?= $ps['dia'] ?></title></rect>
            <text x="<?= $cx-$bw/2-3 ?>" y="<?= $sysY-5 ?>" text-anchor="middle" class="bar-val"><?= $ps['sys'] ?></text>
            <text x="<?= $cx+$bw/2+3 ?>" y="<?= $diaY-5 ?>" text-anchor="middle" class="bar-val dim"><?= $ps['dia'] ?></text>
            <text x="<?= $cx ?>" y="222" text-anchor="middle" class="bar-lbl"><?= e(mb_strimwidth($ps['name'],0,16,'…','UTF-8')) ?></text>
            <text x="<?= $cx ?>" y="236" text-anchor="middle" class="axl"><?= $ps['days'] ?> วัน</text>
          <?php endforeach; ?>
        </svg>
        </div>
        <div class="d-flex gap-3 mt-2 x-sm text-secondary justify-content-center">
          <span><span class="ll-dot" style="background:#2c5c7a"></span> บน (เข้ม)</span>
          <span><span class="ll-dot" style="background:#2c5c7a;opacity:.45"></span> ล่าง (จาง)</span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- แนวโน้มความดัน -->
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header"><i class="bi bi-activity"></i> แนวโน้มความดันเฉลี่ยรายวัน</div>
      <div class="card-body">
        <?php
        $bpPts = array_values(array_filter($daily, fn($d) => $d['sys'] !== null));
        if ($bpPts):
          echo '<div class="chart-box">' . svg_line($bpPts,
            [['key'=>'sys','color'=>'#57b894','width'=>3,'area'=>true],
             ['key'=>'dia','color'=>'#2c5c7a','width'=>2.5]],
            60, 175, [80,120,135,160], 960, 240) . '</div>';
        else: echo '<p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p>'; endif; ?>
        <div class="d-flex gap-3 mt-1 x-sm text-secondary">
          <span><span class="ll-dot" style="background:#57b894"></span> ความดันบน</span>
          <span><span class="ll-dot" style="background:#2c5c7a"></span> ความดันล่าง</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ชีพจร -->
  <div class="col-12 col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-heart-pulse"></i> แนวโน้มชีพจร (bpm)</div>
      <div class="card-body">
        <?php
        $hrPts = array_values(array_filter($daily, fn($d) => $d['hr'] !== null));
        if ($hrPts):
          echo '<div class="chart-box">' . svg_line($hrPts,
            [['key'=>'hr','color'=>'#e0699a','width'=>2.5,'area'=>true]], 45, 130, [60,80,100,120], 560, 230) . '</div>';
        else: echo '<p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p>'; endif; ?>
      </div>
    </div>
  </div>

  <!-- น้ำหนัก + BMI -->
  <div class="col-12 col-lg-6">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-speedometer2"></i> น้ำหนัก และ BMI</div>
      <div class="card-body">
        <?php if ($bmi !== null): ?>
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="bmi-badge"><span class="bmi-n"><?= e($bmi) ?></span><span class="bmi-u">BMI</span></div>
          <div>
            <span class="badge-result <?= e($bmiC[1]) ?>"><?= e($bmiC[0]) ?></span>
            <div class="text-secondary small mt-1">น้ำหนักล่าสุด <?= e(fmt_num($latestWH['weight'])) ?> กก. · สูง <?= e(fmt_num($latestWH['height'])) ?> ซม.</div>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($weightPts): ?>
          <?php
            $ws = array_map(fn($d)=>$d['weight'], $weightPts);
            $wmin = floor(min($ws) - 2); $wmax = ceil(max($ws) + 2);
            $grid = [];
            for ($g = $wmin; $g <= $wmax; $g += max(1, round(($wmax-$wmin)/4))) $grid[] = (int)$g;
            echo '<div class="chart-box">' . svg_line($weightPts,
              [['key'=>'weight','color'=>'#0d9488','width'=>2.5,'area'=>true]], $wmin, $wmax, $grid, 560, 200) . '</div>';
          ?>
        <?php else: ?>
          <p class="text-center text-secondary py-4"><i class="bi bi-clipboard-plus fs-4 d-block mb-2"></i>
            ยังไม่มีข้อมูลน้ำหนัก<br><span class="small">เพิ่มน้ำหนัก/ส่วนสูงได้ในหน้า <a href="index.php">ตารางบันทึก</a></span></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
