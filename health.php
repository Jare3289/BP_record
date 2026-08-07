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

$labMap   = checkup_tests_map();
$latestCk = $checkups[0] ?? null;

// ---- สรุปผลตรวจล่าสุด: ปกติ / ผิดปกติ (เฉพาะค่าตัวเลข) ----
$ckTotal = 0; $ckAbn = 0;
if ($latestCk) foreach ($labMap as $code => $t) {
    $v = $ckValues[$latestCk['id']][$code] ?? '';
    if ($v === '' || ($t[6] ?? 'num') === 'text' || !is_numeric($v)) continue;
    $ckTotal++;
    if (in_array(checkup_flag($t, $v), ['low','high'])) $ckAbn++;
}
$ckOk = $ckTotal - $ckAbn;
$latestCkAbn = $ckAbn;

// ---- ค่าตรวจเด่น ๆ + แนวโน้มข้ามการตรวจ (เก่า→ใหม่) ----
$featOrder = ['sugar','hba1c','chol','ldl','hdl','tg','uric','bun','creatinine','sgpt'];
$labSeries = [];  // code => [floats ...] ตามลำดับวันตรวจ
foreach (array_reverse($checkups) as $c)
    foreach ($featOrder as $code) { $v = $ckValues[$c['id']][$code] ?? null; if ($v !== null && is_numeric($v)) $labSeries[$code][] = (float)$v; }
$labCards = [];
if ($latestCk) foreach ($featOrder as $code) {
    $v = $ckValues[$latestCk['id']][$code] ?? '';
    if ($v === '' || !is_numeric($v)) continue;
    $t = $labMap[$code];
    $ser = $labSeries[$code] ?? [(float)$v];
    $prev = count($ser) >= 2 ? $ser[count($ser)-2] : null;
    $labCards[] = ['code'=>$code, 'name'=>$t[1], 'unit'=>$t[2], 'ref'=>$t[3], 'val'=>(float)$v,
        'low'=>$t[4], 'high'=>$t[5], 'flag'=>checkup_flag($t,$v), 'series'=>$ser, 'prev'=>$prev];
}

$monthsTH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

// ---- ร่างกาย: น้ำหนัก / ส่วนสูง / รอบเอว / BMI ----
$hl = health_latest();
$latestWeight = isset($hl['weight']) ? (float)$hl['weight']['val'] : (($latestCk && $latestCk['weight']!==null) ? (float)$latestCk['weight'] : null);
$latestHeight = isset($hl['height']) ? (float)$hl['height']['val'] : (($latestCk && $latestCk['height']!==null) ? (float)$latestCk['height'] : null);
$latestWaist  = isset($hl['waist'])  ? (float)$hl['waist']['val']  : null;
$bmiVal = calc_bmi($latestWeight, $latestHeight);
$bmiCat = bmi_category($bmiVal);

$totalLogs = count($allLogs);
$withData = array_filter($metrics, fn($m, $code) => !empty($byMetric[$code]), ARRAY_FILTER_USE_BOTH);
$tabMetrics = array_keys($withData);

// แนวโน้มน้ำหนัก (เก่า→ใหม่ ไม่เกิน 10 จุด)
$wTrend = [];
foreach (array_reverse(array_slice($byMetric['weight'] ?? [], 0, 10)) as $l) $wTrend[] = ['lbl'=>date('j/n',strtotime($l['log_date'])), 'v'=>(float)$l['val']];

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
      <button class="pill-btn pill-primary" data-bs-toggle="modal" data-bs-target="#checkupModal" onclick="resetCheckup()"><i class="bi bi-clipboard2-plus"></i> เพิ่มผลตรวจ</button>
      <button class="pill-btn" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('weight')"><i class="bi bi-plus-lg"></i> บันทึกร่างกาย</button>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div><?php endif; ?>

  <!-- ===== สรุปผลตรวจสุขภาพ (เด่น) ===== -->
  <div class="card app-card ckx-card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="fs-15"><i class="bi bi-clipboard2-pulse-fill"></i> สรุปผลตรวจสุขภาพล่าสุด</span>
      <?php if ($latestCk): ?><span class="chip-soft"><i class="bi bi-calendar2-check"></i> <?= fmt_date($latestCk['checkup_date']) ?> · <?= $latestCk['hospital']?e($latestCk['hospital']):'ตรวจสุขภาพ' ?></span><?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (!$latestCk): ?>
        <div class="text-center text-secondary py-5">
          <i class="bi bi-clipboard2-heart fs-1 d-block mb-2"></i>
          ยังไม่มีผลตรวจสุขภาพ
          <div class="mt-3"><button class="pill-btn pill-primary" data-bs-toggle="modal" data-bs-target="#checkupModal" onclick="resetCheckup()"><i class="bi bi-plus-lg"></i> เพิ่มผลตรวจแรก</button></div>
        </div>
      <?php else:
        $C = 2 * pi() * 52;
        $okFrac = $ckTotal > 0 ? $ckOk / $ckTotal : 1;
        $okLen  = $okFrac * $C;
        $abnLen = $C - $okLen;
      ?>
      <div class="ckx-grid">
        <!-- โดนัทสรุป ปกติ / ผิดปกติ -->
        <div class="ckx-donut">
          <svg viewBox="0 0 120 120" class="donut-svg">
            <circle cx="60" cy="60" r="52" fill="none" stroke="var(--bs-border-color)" stroke-width="13"/>
            <?php if ($ckTotal > 0): ?>
              <circle cx="60" cy="60" r="52" fill="none" stroke="#16a34a" stroke-width="13" stroke-linecap="round"
                stroke-dasharray="<?= sprintf('%.1f %.1f', max(0.1,$okLen), $C) ?>" transform="rotate(-90 60 60)"/>
              <?php if ($ckAbn > 0): ?>
              <circle cx="60" cy="60" r="52" fill="none" stroke="#dc3545" stroke-width="13" stroke-linecap="round"
                stroke-dasharray="<?= sprintf('%.1f %.1f', $abnLen, $C) ?>" stroke-dashoffset="<?= sprintf('%.1f', -$okLen) ?>" transform="rotate(-90 60 60)"/>
              <?php endif; ?>
            <?php endif; ?>
            <text x="60" y="55" text-anchor="middle" class="donut-big <?= $ckAbn>0?'txt-abn':'txt-ok' ?>"><?= $ckAbn ?></text>
            <text x="60" y="74" text-anchor="middle" class="donut-sub">ผิดปกติ</text>
          </svg>
          <div class="ckx-legend">
            <span><span class="dot" style="background:#16a34a"></span> ปกติ <b><?= $ckOk ?></b></span>
            <span><span class="dot" style="background:#dc3545"></span> ผิดปกติ <b><?= $ckAbn ?></b></span>
            <span class="text-secondary">จาก <?= $ckTotal ?> ค่าตรวจ</span>
          </div>
        </div>

        <!-- แถบช่วงอ้างอิง (อ่านง่าย) -->
        <div class="ckx-bars">
          <?php if (!$labCards): ?>
            <p class="text-secondary small mb-0 align-self-center">ยังไม่มีค่าตรวจตัวเลขในใบล่าสุด</p>
          <?php else: foreach ($labCards as $b):
            $lo = $b['low']; $hi = $b['high']; $val = $b['val'];
            $loD = $lo !== null ? $lo : ($hi !== null ? $hi * 0.5 : $val * 0.6);
            $hiD = $hi !== null ? $hi : ($lo !== null ? $lo * 1.8 : $val * 1.4);
            $dmin = min($loD, $val); $dmax = max($hiD, $val);
            $pad = (($dmax - $dmin) ?: 1) * 0.18; $dmin -= $pad; $dmax += $pad;
            $span = ($dmax - $dmin) ?: 1;
            $pos = fn($x) => max(0, min(100, ($x - $dmin) / $span * 100));
            $bandL = $lo !== null ? $pos($lo) : 0;
            $bandR = $hi !== null ? $pos($hi) : 100;
            $markL = $pos($val);
            $arrow = $b['prev'] !== null ? ($val > $b['prev'] ? '▲' : ($val < $b['prev'] ? '▼' : '=')) : '';
            $fcls = $b['flag'] === 'high' ? 'f-high' : ($b['flag'] === 'low' ? 'f-low' : 'f-ok');
          ?>
          <div class="refbar" data-tip="<?= e($b['name'].' · ปกติ '.$b['ref']) ?>">
            <div class="refbar-top">
              <span class="refbar-name"><?= e($b['name']) ?></span>
              <span class="refbar-val <?= $fcls ?>"><?= e(fmt_num($val)) ?><small><?= e($b['unit']) ?></small>
                <?php if ($arrow && $arrow!=='='): ?><i class="refbar-arrow <?= $val>$b['prev']?'up':'down' ?>"><?= $arrow ?></i><?php endif; ?>
              </span>
            </div>
            <div class="refbar-track">
              <span class="refbar-band" style="left:<?= sprintf('%.1f',$bandL) ?>%;width:<?= sprintf('%.1f',max(0,$bandR-$bandL)) ?>%"></span>
              <span class="refbar-mark <?= $fcls ?>" style="left:<?= sprintf('%.1f',$markL) ?>%"></span>
            </div>
            <div class="refbar-ref">เกณฑ์ปกติ <?= e($b['ref']) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== ร่างกาย (ย่อ) ===== -->
  <div class="row g-3 mb-4">
    <!-- BMI hero -->
    <div class="col-12 col-md-5 col-xl-4">
      <div class="bento-hero h-100">
        <div class="bh-icon"><i class="bi bi-person-badge-fill"></i></div>
        <?php if ($bmiVal !== null): ?>
          <div class="bh-bignum"><?= e($bmiVal) ?></div>
          <div class="bh-trend <?= $bmiCat[1]==='bp-normal'?'good':'bad' ?>"><i class="bi bi-person-arms-up"></i> <?= e($bmiCat[0]) ?></div>
          <div class="bh-label">ดัชนีมวลกาย (BMI)</div>
        <?php else: ?>
          <div class="bh-bignum">—</div>
          <div class="bh-label">บันทึกน้ำหนัก + ส่วนสูง เพื่อคำนวณ BMI</div>
        <?php endif; ?>
      </div>
    </div>
    <!-- ค่าร่างกายล่าสุด -->
    <div class="col-12 col-md-7 col-xl-4">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-rulers"></i> ค่าร่างกายล่าสุด</span></div>
        <div class="body-grid">
          <?php
          $bodyTiles = [
            ['weight', $latestWeight], ['height', $latestHeight], ['waist', $latestWaist],
          ];
          foreach ($bodyTiles as [$code,$vv]): $m=$metrics[$code]; $fl = $vv!==null?metric_flag($m,$vv):''; ?>
          <div class="body-tile" style="--pc:<?= e($m[3]) ?>">
            <div class="bt-ic"><i class="bi <?= $m[2] ?>"></i></div>
            <div class="bt-name"><?= e($m[0]) ?></div>
            <div class="bt-val <?= $fl==='high'?'f-high':($fl==='low'?'f-low':'') ?>"><?= $vv!==null?e(fmt_num($vv)):'—' ?><small><?= e($m[1]) ?></small></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <!-- แนวโน้มน้ำหนัก -->
    <div class="col-12 col-xl-4">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-graph-up"></i> แนวโน้มน้ำหนัก</span><?php if ($latestWeight!==null): ?><span class="chip-soft"><?= e(fmt_num($latestWeight)) ?> กก.</span><?php endif; ?></div>
        <?php if (count($wTrend) >= 2):
          $mc='#0d9488';
          $vs=array_map(fn($p)=>$p['v'],$wTrend);
          $LW=520;$LH=140;$lL=30;$lR=12;$lT=12;$lB=24;$lpw=$LW-$lL-$lR;$lph=$LH-$lT-$lB;
          $rng=(max($vs)-min($vs))?:1;$ymin=min($vs)-$rng*0.2;$ymax=max($vs)+$rng*0.2;if($ymax-$ymin<0.1)$ymax=$ymin+1;
          $n=count($wTrend);
          $lx=fn($i)=>$lL+($n<=1?$lpw/2:$i/($n-1)*$lpw);
          $ly=fn($v)=>$lT+($ymax-max($ymin,min($ymax,$v)))/($ymax-$ymin)*$lph;
          $ln='';foreach($wTrend as $i=>$p)$ln.=($i?'L':'M').sprintf('%.1f %.1f ',$lx($i),$ly($p['v']));
          $ar='M '.sprintf('%.1f %.1f',$lx(0),$ly($wTrend[0]['v']));foreach($wTrend as $i=>$p)$ar.=' L '.sprintf('%.1f %.1f',$lx($i),$ly($p['v']));
          $ar.=sprintf(' L %.1f %.1f L %.1f %.1f Z',$lx($n-1),$lT+$lph,$lx(0),$lT+$lph);
        ?>
        <div class="chart-box"><svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" class="line-svg" style="aspect-ratio:<?= $LW ?>/<?= $LH ?>">
          <defs><linearGradient id="gW" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="<?= $mc ?>" stop-opacity=".28"/><stop offset="1" stop-color="<?= $mc ?>" stop-opacity="0"/></linearGradient></defs>
          <path d="<?= $ar ?>" fill="url(#gW)"/><path d="<?= trim($ln) ?>" fill="none" stroke="<?= $mc ?>" stroke-width="2.5" stroke-linejoin="round"/>
          <?php foreach($wTrend as $i=>$p): ?><circle cx="<?= sprintf('%.1f',$lx($i)) ?>" cy="<?= sprintf('%.1f',$ly($p['v'])) ?>" r="4" fill="#fff" stroke="<?= $mc ?>" stroke-width="2" data-tip="<?= e($p['lbl'].' · '.fmt_num($p['v']).' กก.') ?>"/>
          <text x="<?= sprintf('%.1f',$lx($i)) ?>" y="<?= $lT+$lph+16 ?>" text-anchor="middle" class="axl"><?= e($p['lbl']) ?></text><?php endforeach; ?>
        </svg></div>
        <?php else: ?><p class="text-center text-secondary py-4 mb-0">บันทึกน้ำหนักอย่างน้อย 2 ครั้งเพื่อดูแนวโน้ม</p><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===== บันทึกร่างกายล่าสุด + ปฏิทิน ===== -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
      <div class="soft-card h-100">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
          <h5 class="mb-0"><i class="bi bi-clock-history"></i> บันทึกร่างกายล่าสุด</h5>
          <div class="rec-tabs">
            <button class="rec-tab active" onclick="healthTab('all',this)">ทั้งหมด</button>
            <?php foreach ($tabMetrics as $c): ?><button class="rec-tab" onclick="healthTab('<?= $c ?>',this)"><?= e($metrics[$c][0]) ?></button><?php endforeach; ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table rec-table hl-table align-middle mb-0">
            <thead><tr class="text-secondary small"><th class="ps-2">ค่า</th><th>ผล</th><th>วันที่</th><th>หมายเหตุ</th><th></th></tr></thead>
            <tbody>
              <?php if (!$allLogs): ?><tr><td colspan="5" class="text-center text-secondary py-4">ยังไม่มีบันทึก — เลือกค่าด้านล่างเพื่อเริ่ม</td></tr>
              <?php else: foreach (array_slice($allLogs,0,12) as $l): $m=$metrics[$l['metric']]??null; if(!$m)continue; $fl=metric_flag($m,$l['val']); ?>
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
    <div class="col-12 col-xl-4">
      <div class="soft-card h-100">
        <div class="card-mini-head"><span><i class="bi bi-calendar3"></i> ปฏิทินการบันทึก</span><span class="chip-soft"><?= $monthsTH[$calM-1] ?> <?= $calY ?></span></div>
        <div class="mini-cal">
          <div class="mc-dow"><?php foreach (['อา','จ','อ','พ','พฤ','ศ','ส'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
          <div class="mc-grid">
            <?php for ($i=0;$i<$firstDow;$i++) echo '<span class="mc-empty"></span>'; ?>
            <?php for ($day=1;$day<=$daysInMonth;$day++): $ds=sprintf('%04d-%02d-%02d',$calY,$calM,$day); $has=isset($logDates[$ds]); ?>
              <span class="mc-day <?= $has?'has':'' ?> <?= $day===$latestDay?'today':'' ?>" <?= $has?'style="--dc:#0d9488"':'' ?> data-tip="<?= e(date('j/n/Y',strtotime($ds)).($has?' · มีบันทึก':'')) ?>"><?= $day ?></span>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== ประวัติผลตรวจสุขภาพ ===== -->
  <div class="row g-4">
    <div class="col-12">
      <h5 class="mb-3"><i class="bi bi-clipboard2-data"></i> ประวัติผลตรวจสุขภาพทั้งหมด</h5>
      <?php if ($checkups): ?>
      <div class="accordion checkup-acc" id="checkupAcc">
        <?php foreach ($checkups as $c): $vals=$ckValues[$c['id']]; $abn=0; foreach ($labMap as $code=>$t){ if(isset($vals[$code])&&in_array(checkup_flag($t,$vals[$code]),['low','high']))$abn++; } $bmi=calc_bmi($c['weight']??null,$c['height']??null); $open=((int)$c['id']===$openId); ?>
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
      <?php else: ?>
      <div class="card app-card"><div class="card-body text-center text-secondary py-4"><i class="bi bi-clipboard2-heart fs-3 d-block mb-2"></i> ยังไม่มีผลตรวจ — กด “เพิ่มผลตรวจ”</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal บันทึกค่าร่างกาย (ง่าย) -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php" id="logForm">
        <input type="hidden" name="action" value="save_health"><input type="hidden" name="id" id="l-id" value=""><input type="hidden" name="metric" id="l-metric" value="weight">
        <div class="modal-header border-0 pb-1"><h5 class="modal-title"><i class="bi bi-clipboard2-heart-fill text-teal"></i> บันทึกค่าร่างกาย</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body text-center pt-1">
          <div class="log-metric-choose mb-3">
            <?php foreach ($metrics as $code => $m): ?>
            <button type="button" class="lmc-btn<?= $code==='weight'?' active':'' ?>" data-metric="<?= $code ?>" style="--mc:<?= e($m[3]) ?>" onclick="quickLog('<?= $code ?>')">
              <span class="lmc-ic"><i class="bi <?= $m[2] ?>"></i></span>
              <span class="lmc-name"><?= e($m[0]) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
          <div class="log-what text-secondary small mb-2">กำลังบันทึก: <b id="l-title" class="text-body">น้ำหนัก</b></div>
          <div class="big-input-wrap"><input type="number" step="any" name="val" id="l-val" class="big-input" placeholder="0" required autofocus><span class="big-unit" id="l-unit">กก.</span></div>
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
            <div class="tab-pane fade" id="<?= $tabId($gidx) ?>">
              <div class="lab-form-head"><span><?= e($group) ?></span><span class="text-secondary">ค่าอ้างอิง</span></div>
              <div class="lab-form-grid">
              <?php foreach ($tests as $t): $opts=$t[7]??null; $isText=($t[6]??'num')==='text'; ?>
              <div class="lab-form-row">
                <div class="lfr-name"><?= e($t[1]) ?><?php if($t[3]):?><span class="lfr-ref">ปกติ <?= e($t[3]) ?></span><?php endif;?></div>
                <div class="lfr-field">
                  <?php if ($opts): ?>
                  <select name="v[<?= $t[0] ?>]" id="vc-<?= $t[0] ?>" class="form-select form-select-sm">
                    <option value="">—</option>
                    <?php foreach ($opts as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?>
                  </select>
                  <?php else: ?>
                  <input type="<?= $isText?'text':'number' ?>" step="any" name="v[<?= $t[0] ?>]" id="vc-<?= $t[0] ?>" class="form-control form-control-sm"<?= (!$isText && $t[3]) ? ' placeholder="'.e($t[3]).'"' : '' ?>>
                  <?php if($t[2]):?><span class="lfr-unit"><?= e($t[2]) ?></span><?php endif;?>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              </div>
            </div>
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
