<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$readings = all_readings();
$days   = group_days($readings);   // เก่า→ใหม่
$phases = load_phases();
$tSys = target_sys(); $tDia = target_dia();

$sumS = $sumD = $sumH = 0; $nS = $nD = $nH = 0;
$levelCount = [0=>0,1=>0,2=>0,3=>0,4=>0];
$inRange = 0; $dateLevel = [];
foreach ($days as $d) {
    $a = $d['avg']; $bp = $d['bp'];
    if ($a['sys'] !== null) { $sumS += $a['sys']; $nS++; }
    if ($a['dia'] !== null) { $sumD += $a['dia']; $nD++; }
    if ($a['hr']  !== null) { $sumH += $a['hr'];  $nH++; }
    if ($bp['level'] >= 0) $levelCount[$bp['level']]++;
    if (in_target($a['sys'], $a['dia'])) $inRange++;
    $dateLevel[$d['date']] = $bp['level'];
}
$total = count($days);
$avgS = $nS ? round($sumS/$nS) : null;
$avgD = $nD ? round($sumD/$nD) : null;
$avgH = $nH ? round($sumH/$nH) : null;
$overall = classify_bp($avgS, $avgD);
$daysDesc = array_reverse($days);
$latest = $daysDesc[0] ?? null;
$pctRange = $total ? round($inRange/$total*100) : 0;
$pctHigh  = 100 - $pctRange;
$currentPhase = $latest ? phase_for_date($phases, $latest['date']) : null;

$monthsTH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// ค่าเฉลี่ยความดันบนรายเดือน (6 เดือนล่าสุด)
$byMonth = [];
foreach ($days as $d) if ($d['avg']['sys'] !== null) $byMonth[substr($d['date'],0,7)][] = $d['avg']['sys'];
ksort($byMonth);
$mk = array_slice(array_keys($byMonth), -6);
$monthPts = [];
foreach ($mk as $ym) { $arr = $byMonth[$ym]; $monthPts[] = ['lbl' => $monthsTH[(int)substr($ym,5,2)-1], 'sys' => (int)round(array_sum($arr)/count($arr))]; }
$mvals = array_map(fn($p) => $p['sys'], $monthPts);
$heroTrend = count($mvals) >= 2 ? $mvals[count($mvals)-1] - $mvals[count($mvals)-2] : 0;

// การวัดตามช่วง
$periodCount = ['morning'=>0,'noon'=>0,'evening'=>0,'bedtime'=>0];
foreach ($readings as $r) if (isset($periodCount[$r['period']])) $periodCount[$r['period']]++;

// การวัดล่าสุด (รายครั้ง)
$recent = [];
foreach ($daysDesc as $d) {
    foreach (array_reverse($d['readings']) as $r) {
        $recent[] = ['date' => $d['date'], 'r' => $r, 'bp' => classify_bp($r['sys'] !== null ? (int)$r['sys'] : null, $r['dia'] !== null ? (int)$r['dia'] : null)];
    }
    if (count($recent) >= 12) break;
}
$recent = array_slice($recent, 0, 12);

// ปฏิทินเดือนล่าสุด
$calDate = $latest ? $latest['date'] : date('Y-m-d');
$calY = (int)substr($calDate,0,4); $calM = (int)substr($calDate,5,2);
$firstDow = (int)date('w', mktime(0,0,0,$calM,1,$calY));
$daysInMonth = (int)date('t', mktime(0,0,0,$calM,1,$calY));
$latestDay = $latest ? (int)substr($latest['date'],8,2) : 0;

$greet = (int)date('H') < 12 ? 'สวัสดีตอนเช้า' : ((int)date('H') < 18 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนค่ำ');
$msg = $_GET['msg'] ?? '';
$flash = $msg === 'saved' ? 'บันทึกการตั้งค่าแล้ว' : null;
$lvlMeta = [0=>['ปกติ','#16a34a'],1=>['สูงเล็กน้อย','#65a30d'],2=>['ระยะที่ 1','#d99a1a'],3=>['ระยะที่ 2','#d1603a'],4=>['วิกฤต','#b91c1c']];

$PAGE = 'แดชบอร์ด'; $ACTIVE = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="dash">
  <!-- หัวเรื่อง -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="text-secondary small"><i class="bi bi-house-door"></i> แดชบอร์ด / สุขภาพความดัน</div>
      <h1 class="page-title mb-0"><?= $greet ?>, <?= e($profile['name']) ?> 👋</h1>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($flash): ?><span class="badge text-bg-success"><i class="bi bi-check-circle"></i> <?= e($flash) ?></span><?php endif; ?>
      <button class="pill-btn" data-bs-toggle="offcanvas" data-bs-target="#settingsPanel"><i class="bi bi-sliders"></i> เป้าหมาย</button>
      <a href="index.php" class="pill-btn pill-primary"><i class="bi bi-plus-lg"></i> เพิ่มบันทึก</a>
    </div>
  </div>

  <div class="row g-3">
    <!-- HERO ค่าเฉลี่ยความดัน -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="bento-hero h-100">
        <div class="bh-icon"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="bh-bignum"><?= e($avgS) ?><span>/</span><?= e($avgD) ?></div>
        <?php if ($heroTrend !== 0): ?>
          <span class="bh-trend <?= $heroTrend < 0 ? 'good' : 'bad' ?>"><i class="bi bi-arrow-<?= $heroTrend < 0 ? 'down' : 'up' ?>"></i> <?= abs($heroTrend) ?> vs เดือนก่อน</span>
        <?php endif; ?>
        <div class="bh-label">ค่าเฉลี่ยความดัน (mmHg)</div>
        <div class="bh-foot"><span class="badge-result <?= e($overall['class']) ?>"><?= e($overall['label']) ?></span></div>
      </div>
    </div>

    <!-- สถิติย่อ -->
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-tile h-100">
        <div class="st-ic ok"><i class="bi bi-emoji-smile"></i></div>
        <div><div class="st-lbl">วันที่คุมได้</div><div class="st-num"><?= $inRange ?></div><div class="st-sub"><?= $pctRange ?>% ของทั้งหมด</div></div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-tile h-100">
        <div class="st-ic warn"><i class="bi bi-exclamation-triangle"></i></div>
        <div><div class="st-lbl">วันที่เกินเป้า</div><div class="st-num"><?= $total-$inRange ?></div><div class="st-sub"><?= $pctHigh ?>% ของทั้งหมด</div></div>
      </div>
    </div>

    <!-- กราฟเส้นรายเดือน -->
    <div class="col-12 col-xl-5">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-graph-up"></i> ความดันบนเฉลี่ยรายเดือน</span><span class="chip-soft"><?= count($monthPts) ?> เดือนล่าสุด</span></div>
        <?php if ($monthPts):
          $LW=520;$LH=170;$lL=30;$lR=12;$lT=14;$lB=28;$lpw=$LW-$lL-$lR;$lph=$LH-$lT-$lB;
          $ymin=max(90,(min($mvals)-10));$ymax=min(180,(max($mvals)+10)); if($ymax-$ymin<20){$ymax=$ymin+20;}
          $n=count($monthPts);
          $lx=fn($i)=>$lL+($n<=1?$lpw/2:$i/($n-1)*$lpw);
          $ly=fn($v)=>$lT+($ymax-max($ymin,min($ymax,$v)))/($ymax-$ymin)*$lph;
          $line='';foreach($monthPts as $i=>$p)$line.=($i?'L':'M').sprintf('%.1f %.1f ',$lx($i),$ly($p['sys']));
          $area='M '.sprintf('%.1f %.1f',$lx(0),$ly($monthPts[0]['sys']));foreach($monthPts as $i=>$p)$area.=' L '.sprintf('%.1f %.1f',$lx($i),$ly($p['sys']));
          $area.=sprintf(' L %.1f %.1f L %.1f %.1f Z',$lx($n-1),$lT+$lph,$lx(0),$lT+$lph);
        ?>
        <div class="chart-box"><svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" class="line-svg" style="aspect-ratio:<?= $LW ?>/<?= $LH ?>">
          <defs><linearGradient id="gM" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#57b894" stop-opacity=".3"/><stop offset="1" stop-color="#57b894" stop-opacity="0"/></linearGradient></defs>
          <path d="<?= $area ?>" fill="url(#gM)"/>
          <path d="<?= trim($line) ?>" fill="none" stroke="#2e9e78" stroke-width="2.5" stroke-linejoin="round"/>
          <?php foreach($monthPts as $i=>$p): ?>
            <circle cx="<?= sprintf('%.1f',$lx($i)) ?>" cy="<?= sprintf('%.1f',$ly($p['sys'])) ?>" r="4" fill="#fff" stroke="#2e9e78" stroke-width="2" data-tip="<?= e($p['lbl'].' · '.$p['sys']) ?>"/>
            <text x="<?= sprintf('%.1f',$lx($i)) ?>" y="<?= $lT+$lph+18 ?>" text-anchor="middle" class="axl"><?= e($p['lbl']) ?></text>
          <?php endforeach; ?>
        </svg></div>
        <?php else: ?><p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p><?php endif; ?>
      </div>
    </div>

    <!-- สัดส่วนระดับความดัน (progress) -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-list-check"></i> สัดส่วนระดับความดัน</span><span class="text-secondary small"><?= $total ?> วัน</span></div>
        <?php foreach ([0,1,2,3] as $lv): $cnt=$levelCount[$lv]+($lv===3?$levelCount[4]:0); $pct=$total?round($cnt/$total*100,1):0; ?>
        <div class="res-row">
          <div class="res-top"><span class="res-name"><span class="ll-dot" style="background:<?= $lvlMeta[$lv][1] ?>"></span> <?= $lvlMeta[$lv][0] ?><?= $lv===3?'+':'' ?></span><span class="res-val"><?= $pct ?>% <b><?= $cnt ?></b></span></div>
          <div class="res-bar"><span style="width:<?= $pct ?>%;background:<?= $lvlMeta[$lv][1] ?>"></span></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ช่วงการรักษา (vacancies-style) -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-signpost-split"></i> ช่วงการรักษา</span><a href="phases.php" class="mini-more"><i class="bi bi-chevron-right"></i></a></div>
        <?php
          $phaseAvg = [];
          foreach ($days as $d) { $ph = phase_for_date($phases, $d['date']); if ($ph && $d['avg']['sys'] !== null) $phaseAvg[$ph['id']][] = $d['avg']['sys']; }
          if ($phases): ?>
          <div class="vac-grid">
            <?php foreach ($phases as $p): $av = !empty($phaseAvg[$p['id']]) ? round(array_sum($phaseAvg[$p['id']])/count($phaseAvg[$p['id']])) : null; ?>
            <div class="vac-item" style="--pc:<?= e($p['color']) ?>">
              <div class="vac-name"><?= e($p['name']) ?></div>
              <div class="vac-meta"><i class="bi bi-calendar3"></i> <?= fmt_date($p['start_date']) ?><?= $av!==null?' · เฉลี่ยบน '.$av:'' ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-secondary small py-3 mb-0"><a href="phases.php">เพิ่มช่วงการรักษา</a> เพื่อเปรียบเทียบก่อน/หลังกินยา</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bar: การวัดตามช่วง -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-bar-chart"></i> การวัดตามช่วง</span><span class="text-secondary small"><?= count($readings) ?> ครั้ง</span></div>
        <?php $pmax = max(1, max($periodCount)); ?>
        <div class="dept-bars">
          <?php foreach (periods() as $code => $m): $c=$periodCount[$code]; $h=round($c/$pmax*100); $isMax=($c===max($periodCount)&&$c>0); ?>
          <div class="dept-col" data-tip="<?= e($m[0].' · '.$c.' ครั้ง') ?>">
            <div class="dept-num"><?= $c ?></div>
            <div class="dept-bar <?= $isMax?'peak':'' ?>" style="height:<?= max(6,$h) ?>%"></div>
            <div class="dept-lbl"><?= e(mb_substr($m[0],4)) ?: e($m[0]) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ปฏิทิน (schedules) -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-calendar3"></i> ปฏิทินการวัด</span><span class="chip-soft"><?= $monthsTH[$calM-1] ?> <?= $calY ?></span></div>
        <div class="mini-cal">
          <div class="mc-dow"><?php foreach (['อา','จ','อ','พ','พฤ','ศ','ส'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
          <div class="mc-grid">
            <?php for ($i=0;$i<$firstDow;$i++) echo '<span class="mc-empty"></span>'; ?>
            <?php for ($day=1;$day<=$daysInMonth;$day++):
              $ds=sprintf('%04d-%02d-%02d',$calY,$calM,$day); $lv=$dateLevel[$ds]??null; ?>
              <span class="mc-day <?= $lv!==null?'has':'' ?> <?= $day===$latestDay?'today':'' ?>" <?= $lv!==null?'style="--dc:'.$lvlMeta[$lv][1].'"':'' ?> data-tip="<?= e(date('j/n/Y',strtotime($ds)).($lv!==null?' · '.$lvlMeta[$lv][0]:' · ไม่มีข้อมูล')) ?>"><?= $day ?></span>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ตารางการวัดล่าสุด + แท็บ -->
    <div class="col-12 col-xl-8">
      <div class="soft-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <h5 class="mb-0"><i class="bi bi-clipboard2-pulse"></i> การวัดล่าสุด</h5>
          <div class="rec-tabs">
            <button class="rec-tab active" onclick="recFilter('all',this)">ทั้งหมด</button>
            <?php foreach (periods() as $code=>$m): ?><button class="rec-tab" onclick="recFilter('<?= $code ?>',this)"><?= e($m[0]) ?></button><?php endforeach; ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table rec-table align-middle mb-0">
            <thead><tr class="text-secondary small"><th class="ps-2">วันที่</th><th>ช่วง</th><th>บน/ล่าง</th><th>ชีพจร</th><th>แปลผล</th></tr></thead>
            <tbody>
              <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-secondary py-4">ยังไม่มีข้อมูล</td></tr>
              <?php else: foreach ($recent as $x): $r=$x['r']; ?>
              <tr data-period="<?= e($r['period']) ?>">
                <td class="ps-2 text-nowrap fw-500"><?= fmt_date($x['date']) ?></td>
                <td><span class="period-badge p-<?= e($r['period']) ?>"><i class="bi <?= period_icon($r['period']) ?>"></i> <?= period_label($r['period']) ?> <?= $r['seq'] ?></span></td>
                <td class="fw-bold"><?= $r['sys']!==null?(int)$r['sys']:'-' ?>/<?= $r['dia']!==null?(int)$r['dia']:'-' ?></td>
                <td class="text-secondary"><?= $r['hr']!==null?(int)$r['hr']:'-' ?></td>
                <td><span class="badge-result <?= e($x['bp']['class']) ?>"><?= e($x['bp']['label']) ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Timeline บันทึกล่าสุด -->
    <div class="col-12 col-xl-4">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-clock-history"></i> ไทม์ไลน์ล่าสุด</span></div>
        <div class="tl">
          <?php foreach (array_slice($recent,0,5) as $x): $r=$x['r']; ?>
          <div class="tl-item">
            <span class="tl-dot" style="background:<?= $lvlMeta[$x['bp']['level']>=0?$x['bp']['level']:0][1] ?>"></span>
            <div class="tl-card">
              <div class="tl-top"><b><?= $r['sys']!==null?(int)$r['sys']:'-' ?>/<?= $r['dia']!==null?(int)$r['dia']:'-' ?></b> mmHg</div>
              <div class="tl-sub"><?= period_label($r['period']) ?> · <?= fmt_date($x['date']) ?></div>
              <span class="badge-result <?= e($x['bp']['class']) ?> mt-1"><?= e($x['bp']['label']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$recent): ?><p class="text-secondary small">ยังไม่มีข้อมูล</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Offcanvas เป้าหมาย -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsPanel">
  <div class="offcanvas-header"><h5 class="offcanvas-title"><i class="bi bi-bullseye"></i> เป้าหมายความดัน</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body">
    <form method="post" action="save.php">
      <input type="hidden" name="action" value="save_settings"><input type="hidden" name="back" value="dashboard.php">
      <div class="row g-2 align-items-end">
        <div class="col"><label class="form-label x-sm">บน (systolic)</label><input type="number" name="target_sys" class="form-control" value="<?= $tSys ?>" min="90" max="200"></div>
        <div class="col-auto pb-2">/</div>
        <div class="col"><label class="form-label x-sm">ล่าง (diastolic)</label><input type="number" name="target_dia" class="form-control" value="<?= $tDia ?>" min="50" max="130"></div>
      </div>
      <div class="form-text mb-2">ค่ามาตรฐานที่บ้าน 135/85 · ที่คลินิก 140/90</div>
      <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> บันทึกเป้าหมาย</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
