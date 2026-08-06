<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$metrics  = health_metrics();
$allLogs  = load_health_logs();                 // ใหม่→เก่า
$byMetric = [];
foreach ($allLogs as $l) $byMetric[$l['metric']][] = $l;

$catalog  = checkup_catalog();
$checkups = load_checkups();
$openId   = (int) ($_GET['open'] ?? 0);
$ckValues = [];
foreach ($checkups as $c) $ckValues[$c['id']] = load_checkup_values((int)$c['id']);

$trendCodes = ['chol' => 'โคเลสเตอรอล', 'ldl' => 'LDL', 'sugar' => 'น้ำตาล', 'hba1c' => 'HbA1c'];
$labTrend = [];
foreach (array_reverse($checkups) as $c)
    foreach ($trendCodes as $code => $lbl) { $v = $ckValues[$c['id']][$code] ?? null; if ($v !== null && is_numeric($v)) $labTrend[$code][] = (float)$v; }

$latestCk = $checkups[0] ?? null;
$latestCkAbn = 0;
if ($latestCk) foreach (checkup_tests_map() as $code => $t) { $vv = $ckValues[$latestCk['id']][$code] ?? ''; if ($vv !== '' && in_array(checkup_flag($t, $vv), ['low','high'])) $latestCkAbn++; }

$monthsTH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// ---- สรุปสำหรับ bento ----
$withData = array_filter($metrics, fn($m, $code) => !empty($byMetric[$code]), ARRAY_FILTER_USE_BOTH);
$totalLogs = count($allLogs);
$metricsTracked = count($withData);
$wLog = $byMetric['weight'][0]['val'] ?? null;
$latestWeight = $wLog !== null ? (float)$wLog : ($latestCk['weight'] ?? null);
$heightVal = $latestCk['height'] ?? null;
$bmiVal = calc_bmi($latestWeight, $heightVal);
$bmiCat = bmi_category($bmiVal);

$trendMetric = null;
foreach (['weight','glucose'] as $tc) if (!empty($byMetric[$tc])) { $trendMetric = $tc; break; }
if (!$trendMetric && $withData) $trendMetric = array_key_first($withData);

// กราฟรายเดือนของค่าหลัก
$pMonthly = [];
if ($trendMetric) {
    $byMo = [];
    foreach ($byMetric[$trendMetric] as $l) $byMo[substr($l['log_date'],0,7)][] = (float)$l['val'];
    ksort($byMo);
    foreach (array_slice($byMo, -6, null, true) as $ym => $vv) $pMonthly[] = ['lbl' => $monthsTH[(int)substr($ym,5,2)-1], 'v' => round(array_sum($vv)/count($vv),1)];
}
if (count($pMonthly) < 2 && $trendMetric) {   // ถ้าน้อยกว่า 2 เดือน ใช้ค่ารายครั้งล่าสุดแทน
    $pMonthly = [];
    foreach (array_reverse(array_slice($byMetric[$trendMetric], 0, 8)) as $i => $l) $pMonthly[] = ['lbl' => date('j/n', strtotime($l['log_date'])), 'v' => (float)$l['val']];
}

// สัดส่วน/จำนวนบันทึกต่อค่า
$metricCounts = []; foreach ($withData as $c => $m) $metricCounts[$c] = count($byMetric[$c]);
arsort($metricCounts);
$maxCount = $metricCounts ? max($metricCounts) : 1;
$tabMetrics = array_slice(array_keys($metricCounts), 0, 4);

// ค่าหลักล่าสุด (vacancies)
$prefer = ['weight','glucose','sleep','steps','water','spo2','waist','exercise','temp','mood'];
$keyMetrics = [];
foreach ($prefer as $c) { if (!empty($byMetric[$c])) { $keyMetrics[] = $c; if (count($keyMetrics) >= 4) break; } }

// ปฏิทินการบันทึก
$logDates = []; foreach ($allLogs as $l) $logDates[$l['log_date']] = true;
$calDate = $allLogs[0]['log_date'] ?? date('Y-m-d');
$calY = (int)substr($calDate,0,4); $calM = (int)substr($calDate,5,2);
$firstDow = (int)date('w', mktime(0,0,0,$calM,1,$calY));
$daysInMonth = (int)date('t', mktime(0,0,0,$calM,1,$calY));
$latestDay = (int)substr($calDate,8,2);

$msg = $_GET['msg'] ?? '';
$flash = match ($msg) {
    'saved'   => ['บันทึกเรียบร้อยแล้ว', 'success', 'bi-check-circle-fill'],
    'deleted' => ['ลบเรียบร้อยแล้ว', 'secondary', 'bi-trash-fill'],
    'error'   => ['เกิดข้อผิดพลาด กรุณาตรวจสอบข้อมูล', 'danger', 'bi-exclamation-triangle-fill'],
    default   => null,
};
$flagBadge = ['low' => ['ต่ำ', 'flag-low'], 'high' => ['สูง', 'flag-high']];

$PAGE = 'สุขภาพ'; $ACTIVE = 'health';
require __DIR__ . '/includes/header.php';
?>

<div class="dash">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="text-secondary small"><i class="bi bi-house-door"></i> แดชบอร์ด / สุขภาพ</div>
      <h1 class="page-title mb-0"><i class="bi bi-heart-pulse-fill"></i> สุขภาพของฉัน</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="pill-btn" data-bs-toggle="modal" data-bs-target="#checkupModal" onclick="resetCheckup()"><i class="bi bi-clipboard2-plus"></i> เพิ่มผลตรวจ</button>
      <button class="pill-btn pill-primary" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('glucose')"><i class="bi bi-plus-lg"></i> บันทึกค่าสุขภาพ</button>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div><?php endif; ?>

  <div class="row g-3">
    <!-- HERO -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="bento-hero h-100">
        <div class="bh-icon"><i class="bi bi-clipboard2-heart-fill"></i></div>
        <?php if ($bmiVal !== null): ?>
          <div class="bh-bignum"><?= e($bmiVal) ?></div>
          <div class="bh-trend <?= in_array($bmiCat[1],['bp-normal'])?'good':'bad' ?>"><i class="bi bi-person-arms-up"></i> <?= e($bmiCat[0]) ?></div>
          <div class="bh-label">ดัชนีมวลกาย (BMI)<?= $latestWeight!==null?' · '.e(fmt_num($latestWeight)).' กก.':'' ?></div>
        <?php else: ?>
          <div class="bh-bignum"><?= $totalLogs ?></div>
          <div class="bh-label">บันทึกค่าสุขภาพทั้งหมด</div>
        <?php endif; ?>
      </div>
    </div>
    <!-- stat tiles -->
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-tile h-100"><div class="st-ic ok"><i class="bi bi-journal-text"></i></div>
        <div><div class="st-lbl">บันทึกทั้งหมด</div><div class="st-num"><?= $totalLogs ?></div><div class="st-sub"><?= $metricsTracked ?> ชนิดค่า</div></div></div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="stat-tile h-100"><div class="st-ic <?= $latestCkAbn>0?'warn':'ok' ?>"><i class="bi <?= $latestCkAbn>0?'bi-exclamation-triangle':'bi-clipboard2-check' ?>"></i></div>
        <div><div class="st-lbl">ผลตรวจล่าสุด</div><div class="st-num"><?= $latestCk?$latestCkAbn:'-' ?></div><div class="st-sub"><?= $latestCk?'รายการผิดปกติ':'ยังไม่มี' ?></div></div></div>
    </div>
    <!-- line chart -->
    <div class="col-12 col-xl-5">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-graph-up"></i> แนวโน้ม<?= $trendMetric?e($metrics[$trendMetric][0]):'ค่าสุขภาพ' ?></span><?php if ($trendMetric): ?><span class="chip-soft"><?= e(fmt_num($byMetric[$trendMetric][0]['val'])) ?> <?= e($metrics[$trendMetric][1]) ?></span><?php endif; ?></div>
        <?php if (count($pMonthly) >= 2):
          $mc = $trendMetric ? $metrics[$trendMetric][3] : '#57b894';
          $vs = array_map(fn($p)=>$p['v'],$pMonthly);
          $LW=520;$LH=150;$lL=30;$lR=12;$lT=12;$lB=26;$lpw=$LW-$lL-$lR;$lph=$LH-$lT-$lB;
          $ymin=min($vs)-($max=(max($vs)-min($vs))?:1)*0.15;$ymax=max($vs)+$max*0.15; if($ymax-$ymin<0.1)$ymax=$ymin+1;
          $n=count($pMonthly);
          $lx=fn($i)=>$lL+($n<=1?$lpw/2:$i/($n-1)*$lpw);
          $ly=fn($v)=>$lT+($ymax-max($ymin,min($ymax,$v)))/($ymax-$ymin)*$lph;
          $ln='';foreach($pMonthly as $i=>$p)$ln.=($i?'L':'M').sprintf('%.1f %.1f ',$lx($i),$ly($p['v']));
          $ar='M '.sprintf('%.1f %.1f',$lx(0),$ly($pMonthly[0]['v']));foreach($pMonthly as $i=>$p)$ar.=' L '.sprintf('%.1f %.1f',$lx($i),$ly($p['v']));
          $ar.=sprintf(' L %.1f %.1f L %.1f %.1f Z',$lx($n-1),$lT+$lph,$lx(0),$lT+$lph);
        ?>
        <div class="chart-box"><svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" class="line-svg" style="aspect-ratio:<?= $LW ?>/<?= $LH ?>">
          <defs><linearGradient id="gH" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="<?= $mc ?>" stop-opacity=".28"/><stop offset="1" stop-color="<?= $mc ?>" stop-opacity="0"/></linearGradient></defs>
          <path d="<?= $ar ?>" fill="url(#gH)"/><path d="<?= trim($ln) ?>" fill="none" stroke="<?= $mc ?>" stroke-width="2.5" stroke-linejoin="round"/>
          <?php foreach($pMonthly as $i=>$p): ?><circle cx="<?= sprintf('%.1f',$lx($i)) ?>" cy="<?= sprintf('%.1f',$ly($p['v'])) ?>" r="4" fill="#fff" stroke="<?= $mc ?>" stroke-width="2" data-tip="<?= e($p['lbl'].' · '.fmt_num($p['v'])) ?>"/>
          <text x="<?= sprintf('%.1f',$lx($i)) ?>" y="<?= $lT+$lph+18 ?>" text-anchor="middle" class="axl"><?= e($p['lbl']) ?></text><?php endforeach; ?>
        </svg></div>
        <?php else: ?><p class="text-center text-secondary py-4 mb-0">บันทึกอย่างน้อย 2 ครั้งเพื่อดูแนวโน้ม</p><?php endif; ?>
      </div>
    </div>

    <!-- resources: สัดส่วนการบันทึก -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-list-check"></i> สัดส่วนการบันทึก</span><span class="text-secondary small"><?= $totalLogs ?> ครั้ง</span></div>
        <?php if ($metricCounts): foreach (array_slice($metricCounts,0,5,true) as $c=>$cnt): $m=$metrics[$c]; $pct=$totalLogs?round($cnt/$totalLogs*100,1):0; ?>
        <div class="res-row"><div class="res-top"><span class="res-name"><span class="ll-dot" style="background:<?= e($m[3]) ?>"></span> <?= e($m[0]) ?></span><span class="res-val"><?= $pct ?>% <b><?= $cnt ?></b></span></div>
          <div class="res-bar"><span style="width:<?= $pct ?>%;background:<?= e($m[3]) ?>"></span></div></div>
        <?php endforeach; else: ?><p class="text-secondary small py-3 mb-0">ยังไม่มีบันทึก</p><?php endif; ?>
      </div>
    </div>

    <!-- vacancies: ค่าสุขภาพล่าสุด -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-clipboard2-data"></i> ค่าสุขภาพล่าสุด</span></div>
        <?php if ($keyMetrics): ?>
        <div class="vac-grid">
          <?php foreach ($keyMetrics as $c): $m=$metrics[$c]; $l=$byMetric[$c][0]; ?>
          <div class="vac-item" style="--pc:<?= e($m[3]) ?>"><div class="vac-name"><i class="bi <?= $m[2] ?>"></i> <?= e($m[0]) ?></div>
            <div class="vac-meta"><b class="text-body"><?= e(fmt_num($l['val'])) ?></b> <?= e($m[1]) ?> · <?= fmt_date($l['log_date']) ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php else: ?><p class="text-secondary small py-3 mb-0">เลือกค่าด้านล่างเพื่อเริ่มบันทึก</p><?php endif; ?>
      </div>
    </div>

    <!-- dept bars: จำนวนบันทึกต่อค่า -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-bar-chart"></i> จำนวนบันทึกต่อค่า</span></div>
        <?php if ($withData): ?>
        <div class="dept-bars">
          <?php foreach (array_slice($withData,0,6,true) as $c=>$m): $cnt=$metricCounts[$c]; $h=round($cnt/$maxCount*100); $isMax=($cnt===$maxCount); ?>
          <div class="dept-col" data-tip="<?= e($m[0].' · '.$cnt.' ครั้ง') ?>"><div class="dept-num"><?= $cnt ?></div>
            <div class="dept-bar <?= $isMax?'peak':'' ?>" style="height:<?= max(6,$h) ?>%;<?= $isMax?'':'background:'.$m[3] ?>"></div>
            <div class="dept-lbl"><i class="bi <?= $m[2] ?>"></i></div></div>
          <?php endforeach; ?>
        </div>
        <?php else: ?><p class="text-secondary small py-3 mb-0">ยังไม่มีบันทึก</p><?php endif; ?>
      </div>
    </div>

    <!-- calendar -->
    <div class="col-12 col-md-6 col-xl-3">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-calendar3"></i> ปฏิทินการบันทึก</span><span class="chip-soft"><?= $monthsTH[$calM-1] ?> <?= $calY ?></span></div>
        <div class="mini-cal">
          <div class="mc-dow"><?php foreach (['อา','จ','อ','พ','พฤ','ศ','ส'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
          <div class="mc-grid">
            <?php for ($i=0;$i<$firstDow;$i++) echo '<span class="mc-empty"></span>'; ?>
            <?php for ($day=1;$day<=$daysInMonth;$day++): $ds=sprintf('%04d-%02d-%02d',$calY,$calM,$day); $has=isset($logDates[$ds]); ?>
              <span class="mc-day <?= $has?'has':'' ?> <?= $day===$latestDay?'today':'' ?>" <?= $has?'style="--dc:#57b894"':'' ?> data-tip="<?= e(date('j/n/Y',strtotime($ds)).($has?' · มีบันทึก':'')) ?>"><?= $day ?></span>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- table + tabs -->
    <div class="col-12 col-xl-8">
      <div class="soft-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <h5 class="mb-0"><i class="bi bi-clock-history"></i> บันทึกล่าสุด</h5>
          <div class="rec-tabs">
            <button class="rec-tab active" onclick="healthTab('all',this)">ทั้งหมด</button>
            <?php foreach ($tabMetrics as $c): ?><button class="rec-tab" onclick="healthTab('<?= $c ?>',this)"><?= e($metrics[$c][0]) ?></button><?php endforeach; ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table rec-table hl-table align-middle mb-0">
            <thead><tr class="text-secondary small"><th class="ps-2">ค่า</th><th>ผล</th><th>วันที่</th><th>หมายเหตุ</th><th></th></tr></thead>
            <tbody>
              <?php if (!$allLogs): ?><tr><td colspan="5" class="text-center text-secondary py-4">ยังไม่มีบันทึก</td></tr>
              <?php else: foreach (array_slice($allLogs,0,14) as $l): $m=$metrics[$l['metric']]??null; if(!$m)continue; $fl=metric_flag($m,$l['val']); ?>
              <tr data-metric="<?= e($l['metric']) ?>">
                <td class="ps-2"><span class="log-ic sm" style="--mc:<?= e($m[3]) ?>"><i class="bi <?= $m[2] ?>"></i></span> <?= e($m[0]) ?></td>
                <td class="fw-bold <?= $fl==='high'?'f-high':($fl==='low'?'f-low':'') ?>"><?= e(fmt_num($l['val'])) ?> <span class="log-u"><?= e($m[1]) ?></span><?php if (isset($flagBadge[$fl])): ?> <span class="lab-flag <?= $flagBadge[$fl][1] ?>"><?= $flagBadge[$fl][0] ?></span><?php endif; ?></td>
                <td class="text-nowrap text-secondary"><?= fmt_date($l['log_date']) ?></td>
                <td class="text-secondary small"><?= e($l['note'] ?? '') ?></td>
                <td class="text-nowrap text-end pe-2">
                  <button class="log-edit" onclick='editHealth(<?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                  <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบบันทึกนี้?')"><input type="hidden" name="action" value="delete_health"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="log-edit del"><i class="bi bi-trash"></i></button></form>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- timeline -->
    <div class="col-12 col-xl-4">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-list-ul"></i> ไทม์ไลน์ล่าสุด</span></div>
        <div class="tl">
          <?php foreach (array_slice($allLogs,0,5) as $l): $m=$metrics[$l['metric']]??null; if(!$m)continue; ?>
          <div class="tl-item"><span class="tl-dot" style="background:<?= e($m[3]) ?>"></span>
            <div class="tl-card"><div class="tl-top"><i class="bi <?= $m[2] ?>" style="color:<?= e($m[3]) ?>"></i> <b><?= e(fmt_num($l['val'])) ?></b> <?= e($m[1]) ?></div>
              <div class="tl-sub"><?= e($m[0]) ?> · <?= fmt_date($l['log_date']) ?></div></div></div>
          <?php endforeach; ?>
          <?php if (!$allLogs): ?><p class="text-secondary small">ยังไม่มีบันทึก</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- เลือกบันทึกค่าแบบเร็ว -->
  <div class="card app-card mt-4">
    <div class="card-header"><i class="bi bi-lightning-charge-fill"></i> บันทึกค่าสุขภาพวันนี้ — เลือกแล้วใส่ค่าได้เลย</div>
    <div class="card-body">
      <div class="metric-picker">
        <?php foreach ($metrics as $code => $m): ?>
        <button class="metric-pick" style="--mc:<?= e($m[3]) ?>" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('<?= $code ?>')">
          <span class="mp-icon"><i class="bi <?= $m[2] ?>"></i></span><span class="mp-name"><?= e($m[0]) ?></span>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ===== ผลตรวจสุขภาพ ===== -->
  <div class="row g-4 mt-1">
    <div class="col-12 col-lg-8">
      <div class="card app-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-clipboard2-data"></i> สรุปผลตรวจสุขภาพ</span><?php if ($latestCk): ?><span class="chip-soft">ล่าสุด <?= fmt_date($latestCk['checkup_date']) ?></span><?php endif; ?></div>
        <div class="card-body">
          <?php if (!$latestCk): ?><p class="text-center text-secondary py-4 mb-0"><i class="bi bi-clipboard2-heart fs-3 d-block mb-2"></i> ยังไม่มีผลตรวจ — กด “เพิ่มผลตรวจ”</p>
          <?php else: ?>
          <div class="row g-3 align-items-center">
            <div class="col-md-4"><div class="ck-summary <?= $latestCkAbn>0?'has-abn':'all-ok' ?>"><div class="ck-sum-n"><?= $latestCkAbn ?></div><div class="ck-sum-l"><?= $latestCkAbn>0?'รายการผิดปกติ':'ปกติทั้งหมด' ?></div><div class="ck-sum-d"><?= fmt_date($latestCk['checkup_date']) ?> · <?= $latestCk['hospital']?e($latestCk['hospital']):'ตรวจสุขภาพ' ?></div></div></div>
            <div class="col-md-8"><div class="row g-2">
              <?php foreach ($trendCodes as $code=>$lbl): if (empty($labTrend[$code])) continue; $tm=checkup_tests_map()[$code]; $cur=end($labTrend[$code]); $fl=checkup_flag($tm,$cur); ?>
              <div class="col-6 col-xl-3"><div class="trend-mini"><div class="tm-lbl"><?= e($lbl) ?></div><div class="tm-val <?= $fl==='high'?'f-high':($fl==='low'?'f-low':'') ?>"><?= e(fmt_num($cur)) ?></div><?= sparkline_svg($labTrend[$code],'#2c5c7a',90,26) ?></div></div>
              <?php endforeach; ?>
            </div></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($checkups): ?>
      <div class="accordion checkup-acc" id="checkupAcc">
        <?php foreach ($checkups as $c): $vals=$ckValues[$c['id']]; $abn=0; foreach (checkup_tests_map() as $code=>$t){ if(isset($vals[$code])&&in_array(checkup_flag($t,$vals[$code]),['low','high']))$abn++; } $bmi=calc_bmi($c['weight']??null,$c['height']??null); $open=((int)$c['id']===$openId); ?>
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button <?= $open?'':'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ck<?= (int)$c['id'] ?>">
            <div class="ck-head"><div class="ck-date"><i class="bi bi-calendar2-check"></i> <?= fmt_date($c['checkup_date']) ?></div><div class="ck-hosp"><?= $c['hospital']?e($c['hospital']):'ตรวจสุขภาพ' ?></div>
              <div class="ck-vitals"><?php if($c['sbp']!==null):?><span class="ck-chip"><i class="bi bi-heart-pulse"></i> <?= (int)$c['sbp'] ?>/<?= (int)$c['dbp'] ?></span><?php endif;?><?php if($bmi!==null):?><span class="ck-chip">BMI <?= e($bmi) ?></span><?php endif;?><?php if($abn>0):?><span class="ck-chip abn"><i class="bi bi-exclamation-triangle-fill"></i> ผิดปกติ <?= $abn ?></span><?php else:?><span class="ck-chip ok"><i class="bi bi-check-circle-fill"></i> ปกติ</span><?php endif;?></div>
            </div></button></h2>
          <div id="ck<?= (int)$c['id'] ?>" class="accordion-collapse collapse <?= $open?'show':'' ?>" data-bs-parent="#checkupAcc">
            <div class="accordion-body">
              <div class="d-flex flex-wrap gap-2 mb-3 justify-content-between">
                <div class="ck-basic"><?php foreach ([['น้ำหนัก',$c['weight']!==null?fmt_num($c['weight']).' กก.':'-'],['ส่วนสูง',$c['height']!==null?fmt_num($c['height']).' ซม.':'-'],['BMI',$bmi??'-'],['ความดัน',$c['sbp']!==null?(int)$c['sbp'].'/'.(int)$c['dbp']:'-'],['ชีพจร',$c['pulse']!==null?(int)$c['pulse']:'-'],['แพทย์',$c['doctor']?:'-']] as $b): ?><div class="ck-b"><span><?= $b[0] ?></span><b><?= e($b[1]) ?></b></div><?php endforeach; ?></div>
                <div><button class="btn btn-sm btn-outline-secondary" onclick='editCheckup(<?= json_encode(array_merge($c,['v'=>$vals]), JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i> แก้ไข</button>
                  <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบผลตรวจ?')"><input type="hidden" name="action" value="delete_checkup"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div>
              </div>
              <?php foreach ([['ผลเอ็กซเรย์ (Chest X-ray)',$c['xray']],['คลื่นไฟฟ้าหัวใจ (EKG)',$c['ekg']],['HB Typing',$c['hbtyping']],['สรุปผลการตรวจโดยแพทย์',$c['summary']]] as $tx): if (trim((string)$tx[1])!==''): ?><div class="ck-text"><span class="ck-text-lbl"><?= $tx[0] ?></span> <?= nl2br(e($tx[1])) ?></div><?php endif; endforeach; ?>
              <div class="row g-3 mt-1">
                <?php foreach ($catalog as $group=>$tests): $hasv=false; foreach($tests as $t) if(isset($vals[$t[0]])&&$vals[$t[0]]!==''){$hasv=true;break;} if(!$hasv)continue; ?>
                <div class="col-12 col-lg-6"><div class="lab-group"><div class="lab-group-title"><?= e($group) ?></div><table class="lab-table">
                  <?php foreach ($tests as $t): $v=$vals[$t[0]]??''; if($v==='')continue; $flag=checkup_flag($t,$v); ?>
                  <tr><td class="lab-name"><?= e($t[1]) ?></td><td class="lab-val <?= $flag?'f-'.$flag:'' ?>"><?= e($v) ?><?= $t[2]?' <span class="lab-u">'.e($t[2]).'</span>':'' ?><?php if(isset($flagBadge[$flag])):?><span class="lab-flag <?= $flagBadge[$flag][1] ?>"><?= $flagBadge[$flag][0] ?></span><?php endif;?></td><td class="lab-ref"><?= e($t[3]) ?></td></tr>
                  <?php endforeach; ?>
                </table></div></div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- อ้างอิง -->
    <div class="col-12 col-lg-4">
      <div class="card app-card ref-card">
        <div class="card-header"><i class="bi bi-journal-medical"></i> แหล่งอ้างอิงค่าปกติ</div>
        <div class="card-body">
          <?php foreach (reference_sources() as $rs): ?><div class="ref-item"><div class="ref-topic"><i class="bi bi-bookmark-check-fill"></i> <?= e($rs[0]) ?></div><div class="ref-src"><?= e($rs[1]) ?></div></div><?php endforeach; ?>
          <div class="ref-foot"><i class="bi bi-info-circle"></i> ค่าอ้างอิงในผลตรวจแต่ละใบยึดตามช่วงอ้างอิงของห้องปฏิบัติการที่ตรวจเป็นหลัก · ข้อมูลนี้เพื่อการติดตามเบื้องต้น ไม่ทดแทนคำวินิจฉัยของแพทย์</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal บันทึกค่าสุขภาพ (ง่าย) -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php" id="logForm">
        <input type="hidden" name="action" value="save_health"><input type="hidden" name="id" id="l-id" value=""><input type="hidden" name="metric" id="l-metric" value="glucose">
        <div class="modal-header border-0 pb-0"><h5 class="modal-title"><span id="l-ic" class="log-modal-ic"><i class="bi bi-droplet-half"></i></span> <span id="l-title">บันทึกค่า</span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center">
          <div class="big-input-wrap"><input type="number" step="any" name="val" id="l-val" class="big-input" placeholder="0" required autofocus><span class="big-unit" id="l-unit">mg/dl</span></div>
          <div class="ref-hint mb-3" id="l-ref"></div>
          <div class="row g-2 text-start"><div class="col-7"><label class="form-label x-sm">วันที่</label><input type="date" name="log_date" id="l-date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div><div class="col-5"><label class="form-label x-sm">&nbsp;</label><input type="text" name="note" id="l-note" class="form-control" placeholder="หมายเหตุ"></div></div>
        </div>
        <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> บันทึก</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal ผลตรวจสุขภาพ (แบบแท็บ) -->
<div class="modal fade" id="checkupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="save.php" id="checkupForm">
        <div class="modal-header"><h5 class="modal-title" id="ckTitle"><i class="bi bi-plus-circle"></i> เพิ่มผลตรวจสุขภาพ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save_checkup"><input type="hidden" name="id" id="c-id" value="">
          <?php $tabs=array_merge(['พื้นฐาน','ร่างกาย/ภาพ'],array_keys($catalog)); $tabId=fn($i)=>'cktab'.$i; ?>
          <ul class="nav nav-pills ck-tabs flex-wrap mb-3" role="tablist">
            <?php foreach ($tabs as $i=>$tab): ?><li class="nav-item"><button class="nav-link <?= $i===0?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#<?= $tabId($i) ?>" type="button"><?= e($tab) ?></button></li><?php endforeach; ?>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="<?= $tabId(0) ?>"><div class="row g-3">
              <div class="col-6 col-md-3"><label class="form-label">วันที่ตรวจ *</label><input type="date" name="checkup_date" id="c-checkup_date" class="form-control form-control-lg" value="<?= e(date('Y-m-d')) ?>" required></div>
              <div class="col-6 col-md-3"><label class="form-label">โรงพยาบาล</label><input type="text" name="hospital" id="c-hospital" class="form-control form-control-lg" placeholder="รพ.ครู"></div>
              <div class="col-6 col-md-3"><label class="form-label">แพทย์</label><input type="text" name="doctor" id="c-doctor" class="form-control form-control-lg"></div>
              <div class="col-6 col-md-3"><label class="form-label">ชีพจร</label><input type="number" name="pulse" id="c-pulse" class="form-control form-control-lg"></div>
              <div class="col-6 col-md-3"><label class="form-label">น้ำหนัก (กก.)</label><input type="number" step="0.1" name="weight" id="c-weight" class="form-control form-control-lg"></div>
              <div class="col-6 col-md-3"><label class="form-label">ส่วนสูง (ซม.)</label><input type="number" step="0.1" name="height" id="c-height" class="form-control form-control-lg"></div>
              <div class="col-6 col-md-3"><label class="form-label">ความดันบน</label><input type="number" name="sbp" id="c-sbp" class="form-control form-control-lg"></div>
              <div class="col-6 col-md-3"><label class="form-label">ความดันล่าง</label><input type="number" name="dbp" id="c-dbp" class="form-control form-control-lg"></div>
            </div></div>
            <div class="tab-pane fade" id="<?= $tabId(1) ?>"><div class="row g-3">
              <div class="col-md-6"><label class="form-label">ผลเอ็กซเรย์ (Chest X-ray)</label><input type="text" name="xray" id="c-xray" class="form-control form-control-lg" placeholder="เช่น ปกติ"></div>
              <div class="col-md-6"><label class="form-label">คลื่นไฟฟ้าหัวใจ (EKG)</label><input type="text" name="ekg" id="c-ekg" class="form-control form-control-lg"></div>
              <div class="col-md-6"><label class="form-label">HB Typing</label><input type="text" name="hbtyping" id="c-hbtyping" class="form-control form-control-lg"></div>
              <div class="col-md-6"><label class="form-label">สรุปผลการตรวจโดยแพทย์</label><textarea name="summary" id="c-summary" class="form-control" rows="2"></textarea></div>
            </div></div>
            <?php $gidx=2; foreach ($catalog as $group=>$tests): ?>
            <div class="tab-pane fade" id="<?= $tabId($gidx) ?>"><div class="row g-3">
              <?php foreach ($tests as $t): ?><div class="col-6 col-md-4 col-xl-3"><label class="form-label"><?= e($t[1]) ?><?= $t[3]?' <span class="ref-hint">'.e($t[3]).'</span>':'' ?></label><div class="input-group"><input type="<?= ($t[6]??'num')==='text'?'text':'number' ?>" step="any" name="v[<?= $t[0] ?>]" id="vc-<?= $t[0] ?>" class="form-control"><?php if($t[2]):?><span class="input-group-text"><?= e($t[2]) ?></span><?php endif;?></div></div><?php endforeach; ?>
            </div></div>
            <?php $gidx++; endforeach; ?>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> บันทึกผลตรวจ</button></div>
      </form>
    </div>
  </div>
</div>

<script>window.HEALTH_METRICS = <?= json_encode($metrics, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
