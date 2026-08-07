<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$phases = load_phases();
$chartStd = bp_standard();
$days   = group_days(all_readings());   // เก่า→ใหม่

// เตรียมข้อมูลรายวัน
$daily = [];
foreach ($days as $d) {
    $daily[] = [
        'date'   => $d['date'],
        'sys'    => $d['avg']['sys'],
        'dia'    => $d['avg']['dia'],
        'hr'     => $d['avg']['hr'],
        'weight' => $d['weight'],
        'height' => $d['height'],
        'phase'  => phase_for_date($phases, $d['date']),
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
    if ($d['hr']  !== null) $byPhase[$key]['hr'][]  = $d['hr'];
}
$phaseStats = [];
foreach ($byPhase as $k => $v) {
    $ns = $v['sys'] ?? []; $nd = $v['dia'] ?? []; $nh = $v['hr'] ?? [];
    if (!$ns) continue;
    $bpc = classify_bp((int) round(array_sum($ns) / count($ns)), $nd ? (int) round(array_sum($nd) / count($nd)) : null);
    $phaseStats[] = [
        'name'  => $v['name'], 'color' => $v['color'],
        'sys'   => (int) round(array_sum($ns) / count($ns)),
        'dia'   => $nd ? (int) round(array_sum($nd) / count($nd)) : 0,
        'hr'    => $nh ? (int) round(array_sum($nh) / count($nh)) : 0,
        'days'  => count($ns), 'bp' => $bpc,
    ];
}

// รวมจอมอนิเตอร์: ภาพรวมทั้งหมด + แต่ละช่วง
$oS = $oD = $oH = [];
foreach ($daily as $d) {
    if ($d['sys'] !== null) $oS[] = $d['sys'];
    if ($d['dia'] !== null) $oD[] = $d['dia'];
    if ($d['hr']  !== null) $oH[] = $d['hr'];
}
$monitors = [];
if ($oS) {
    $osys = (int) round(array_sum($oS) / count($oS));
    $odia = $oD ? (int) round(array_sum($oD) / count($oD)) : 0;
    $monitors[] = ['name' => 'ภาพรวมทั้งหมด', 'color' => '#2c5c7a', 'sys' => $osys, 'dia' => $odia,
        'hr' => $oH ? (int) round(array_sum($oH) / count($oH)) : 0, 'days' => count($oS),
        'bp' => classify_bp($osys, $odia), 'overall' => true];
}
foreach ($phaseStats as $ps) { $ps['overall'] = false; $monitors[] = $ps; }

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

    $out = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="line-svg" style="aspect-ratio:' . $W . '/' . $H . '">';
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
            $out .= sprintf('<circle class="tip-pt" cx="%.1f" cy="%.1f" r="3.5" fill="#fff" stroke="%s" stroke-width="2" data-tip="%s"/>',
                $x($i), $y($p[$key]), $color, htmlspecialchars(fmt_date($p['date']) . ' · ' . $p[$key], ENT_QUOTES));
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
$W = 700; $H = 560; $L = 52; $R = 20; $T = 24; $B = 46;
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
  <p class="text-secondary mb-0">แผนภาพกระจาย · เปรียบเทียบตามช่วง · แนวโน้มความดันและชีพจร (เกณฑ์<?= e(bp_standard_name($chartStd)) ?>)</p>
</div>

<div class="row g-4">
  <!-- Scatter -->
  <div class="col-12 col-xl-7">
    <div class="card app-card h-100 d-flex flex-column">
      <div class="card-header"><i class="bi bi-bullseye"></i> แผนภาพกระจายตามโซนระดับความดัน</div>
      <div class="card-body d-flex flex-column">
        <?php if ($phases): ?>
        <div class="phase-chips mb-3">
          <button class="phase-chip active" id="chip-all" onclick="clearPhases()"><i class="bi bi-grid-3x3"></i> ทั้งหมด</button>
          <?php foreach ($phases as $p): ?>
            <button class="phase-chip" data-phase="<?= (int)$p['id'] ?>" style="--pc:<?= e($p['color']) ?>" onclick="togglePhase('<?= (int)$p['id'] ?>', this)">
              <i class="bi bi-circle-fill"></i> <?= e($p['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <p class="text-secondary x-sm mb-3"><i class="bi bi-hand-index"></i> เลือกได้หลายช่วงพร้อมกัน · ชี้/แตะที่จุดเพื่อดูค่า</p>
        <?php else: ?>
        <div class="alert alert-light border small mb-3"><i class="bi bi-lightbulb"></i>
          เพิ่ม <a href="phases.php">ช่วงการรักษา</a> เพื่อไฮไลต์จุดตามช่วง (เช่น ก่อนกินยา / กินยาช่วงที่ 1)</div>
        <?php endif; ?>

        <?php if (!$scatter): ?>
          <p class="text-center text-secondary py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล</p>
        <?php else: ?>
        <div class="chart-box scatter-fit">
        <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="bp-chart" role="img" preserveAspectRatio="xMidYMid meet">
          <!-- โซนตามเกณฑ์ที่เลือก (สากล/ไทย): วาดจากรุนแรงสุด (พื้นหลัง) ไปหาปกติ (บนสุด)
               classify แบบ "บนและ/หรือล่าง" = สี่เหลี่ยมซ้อนจากมุมล่างซ้าย -->
          <?php
            // [dia_hi, sys_hi, สี] เรียงจากระดับสูงสุด→ปกติ
            $zoneDefs = $chartStd === 'th'
              ? [[110,180,'#f2a07a'],[100,160,'#f4d06a'],[90,140,'#b6d97f'],[80,130,'#6fbf8f']]
              : [[120,180,'#f2a07a'],[90,140,'#f4d06a'],[80,130,'#b6d97f'],[80,120,'#6fbf8f']];
            $sysTicks = $chartStd === 'th' ? [70,100,130,140,160,170] : [70,120,130,140,170];
          ?>
          <?= $zone($DIA_MIN, $DIA_MAX, $SYS_MIN, $SYS_MAX, '#e0699a') ?><!-- ระดับสูงสุด (พื้นหลัง) -->
          <?php foreach ($zoneDefs as $z): ?><?= $zone(40, $z[0], 70, $z[1], $z[2]) ?><?php endforeach; ?>
          <?php foreach ($sysTicks as $s): $y=$py($s); ?>
            <line x1="<?= $L ?>" y1="<?= $y ?>" x2="<?= $L+$plotW ?>" y2="<?= $y ?>" stroke="#fff" stroke-width="1" opacity="0.5"/>
            <text x="<?= $L-8 ?>" y="<?= $y+4 ?>" text-anchor="end" class="axl"><?= $s ?></text>
          <?php endforeach; ?>
          <?php foreach ([40,70,80,90,100] as $d): $x=$px($d); ?>
            <line x1="<?= $x ?>" y1="<?= $T ?>" x2="<?= $x ?>" y2="<?= $T+$plotH ?>" stroke="#fff" stroke-width="1" opacity="0.35"/>
            <text x="<?= $x ?>" y="<?= $T+$plotH+18 ?>" text-anchor="middle" class="axl"><?= $d ?></text>
          <?php endforeach; ?>
          <rect x="<?= $L ?>" y="<?= $T ?>" width="<?= $plotW ?>" height="<?= $plotH ?>" fill="none" stroke="#cbd5e1"/>
          <text x="<?= $L ?>" y="16" class="axttl">Systolic (ความดันบน)</text>
          <text x="<?= $L+$plotW ?>" y="<?= $H-8 ?>" text-anchor="end" class="axttl">Diastolic (ความดันล่าง)</text>
          <?php foreach ($scatter as $d): $ph=$d['phase']; ?>
            <circle class="scatter-pt" cx="<?= sprintf('%.1f',$px($d['dia'])) ?>" cy="<?= sprintf('%.1f',$py($d['sys'])) ?>" r="4.5"
              fill="#2c5c7a" stroke="#fff" stroke-width="1.2"
              data-base="#2c5c7a" data-phase="<?= $ph ? (int)$ph['id'] : '' ?>" data-color="<?= $ph ? e($ph['color']) : '#57b894' ?>"
              data-date="<?= fmt_date($d['date']) ?>" data-sys="<?= $d['sys'] ?>" data-dia="<?= $d['dia'] ?>"
              data-hr="<?= $d['hr'] !== null ? $d['hr'] : '' ?>" data-phasename="<?= $ph ? e($ph['name']) : '' ?>"></circle>
          <?php endforeach; ?>
        </svg>
        <div id="scatterTip" class="scatter-tip"></div>
        </div>
        <p class="text-center text-secondary small mt-2 mb-0"><i class="bi bi-info-circle"></i>
          จุดสีเดียวคือภาพรวม · เลือกชิปช่วง (หลายอันได้) เพื่อไฮไลต์</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- เปรียบเทียบรายช่วง = จอเครื่องวัดความดัน -->
  <div class="col-12 col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-activity"></i> ค่าเฉลี่ยตามช่วง (จอเครื่องวัด)</div>
      <div class="card-body">
        <?php if (!$monitors): ?>
          <p class="text-center text-secondary py-5"><i class="bi bi-activity fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล</p>
        <?php else: ?>
        <?php if (count($monitors) > 1): ?>
        <div class="phase-chips mb-3" id="monChips">
          <?php foreach ($monitors as $i => $m): ?>
            <button class="phase-chip <?= $i < 4 ? 'active' : '' ?>" style="--pc:<?= e($m['color']) ?>" data-mon-toggle="<?= $i ?>" onclick="toggleMon('<?= $i ?>', this)">
              <i class="bi <?= !empty($m['overall']) ? 'bi-grid-3x3-gap-fill' : 'bi-circle-fill' ?>"></i> <?= e($m['name']) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="monitor-grid" data-max="4">
          <?php foreach ($monitors as $i => $m): ?>
          <div class="bp-monitor <?= !empty($m['overall']) ? 'mon-overall' : '' ?> <?= $i >= 4 ? 'd-none' : '' ?>" data-mon="<?= $i ?>" style="--pc:<?= e($m['color']) ?>">
            <div class="mon-head"><span class="mon-dot"></span> <?= e($m['name']) ?> <span class="mon-days"><?= $m['days'] ?> วัน</span></div>
            <div class="mon-screen">
              <div class="mon-row"><span class="mon-lbl">SYS</span><span class="mon-val"><?= $m['sys'] ?></span><span class="mon-unit">mmHg</span></div>
              <div class="mon-row"><span class="mon-lbl">DIA</span><span class="mon-val"><?= $m['dia'] ?></span><span class="mon-unit">mmHg</span></div>
              <div class="mon-row pulse"><span class="mon-lbl"><i class="bi bi-heart-fill"></i></span><span class="mon-val sm"><?= $m['hr'] ?></span><span class="mon-unit">/min</span></div>
              <div class="mon-result"><?= e($m['bp']['label']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-center text-secondary small mt-3 mb-0"><i class="bi bi-info-circle"></i> เลือกแสดงได้สูงสุด 4 จอ · กดชิปเพื่อเลือกว่าจะแสดงจอไหน · เพิ่มช่วงได้ที่ <a href="phases.php">ช่วงการรักษา</a></p>
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
            60, 175, ($chartStd==='th'?[80,130,140,160]:[80,120,130,140]), 960, 240) . '</div>';
        else: echo '<p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p>'; endif; ?>
        <div class="d-flex gap-3 mt-1 x-sm text-secondary">
          <span><span class="ll-dot" style="background:#57b894"></span> ความดันบน</span>
          <span><span class="ll-dot" style="background:#2c5c7a"></span> ความดันล่าง</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ชีพจร -->
  <div class="col-12">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-heart-pulse"></i> แนวโน้มชีพจร (bpm)</div>
      <div class="card-body">
        <?php
        $hrPts = array_values(array_filter($daily, fn($d) => $d['hr'] !== null));
        if ($hrPts):
          echo '<div class="chart-box">' . svg_line($hrPts,
            [['key'=>'hr','color'=>'#e0699a','width'=>2.5,'area'=>true]], 45, 130, [60,80,100,120], 960, 230) . '</div>';
        else: echo '<p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p>'; endif; ?>
        <p class="text-center text-secondary small mt-2 mb-0"><i class="bi bi-info-circle"></i> น้ำหนัก · ส่วนสูง · BMI ดูได้ที่หน้า <a href="health.php">สุขภาพ</a></p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
