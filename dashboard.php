<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$days   = group_days(all_readings());   // เก่า→ใหม่
$phases = load_phases();
$tSys = target_sys(); $tDia = target_dia();

$sumS = $sumD = $sumH = 0; $nS = $nD = $nH = 0;
$levelCount = [0=>0,1=>0,2=>0,3=>0,4=>0];
$inRange = 0;
$dateLevel = [];               // 'Y-m-d' => level (สำหรับ pixel chart)
$years = [];
foreach ($days as $d) {
    $a = $d['avg']; $bp = $d['bp'];
    if ($a['sys'] !== null) { $sumS += $a['sys']; $nS++; }
    if ($a['dia'] !== null) { $sumD += $a['dia']; $nD++; }
    if ($a['hr']  !== null) { $sumH += $a['hr'];  $nH++; }
    if ($bp['level'] >= 0) $levelCount[$bp['level']]++;
    if (in_target($a['sys'], $a['dia'])) $inRange++;
    $dateLevel[$d['date']] = $bp['level'];
    $years[substr($d['date'], 0, 4)] = true;
}
$total = count($days);
$avgS = $nS ? round($sumS/$nS) : null;
$avgD = $nD ? round($sumD/$nD) : null;
$avgH = $nH ? round($sumH/$nH) : null;
$overall = classify_bp($avgS, $avgD);
$daysDesc = array_reverse($days);
$latest = $daysDesc[0] ?? null;
// การวัดครั้งล่าสุดจริง (ครั้งท้ายสุดของวันล่าสุด)
$lr = ($latest && !empty($latest['readings'])) ? end($latest['readings']) : null;
$lrBp = $lr ? classify_bp($lr['sys'] !== null ? (int)$lr['sys'] : null, $lr['dia'] !== null ? (int)$lr['dia'] : null) : null;
$pctRange = $total ? round($inRange/$total*100) : 0;
$pctHigh  = 100 - $pctRange;
$currentPhase = $latest ? phase_for_date($phases, $latest['date']) : null;

$years = array_keys($years); rsort($years);
$curYear = $years[0] ?? date('Y');

$last14 = array_slice($days, -14);

$greet = (int)date('H') < 12 ? 'สวัสดีตอนเช้า' : ((int)date('H') < 18 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนค่ำ');

$msg = $_GET['msg'] ?? '';
$flash = $msg === 'saved' ? 'บันทึกการตั้งค่าแล้ว' : null;

$PAGE = 'แดชบอร์ด'; $ACTIVE = 'dashboard';
require __DIR__ . '/includes/header.php';

$monthsTH = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$lvlClass = [0=>'px-normal',1=>'px-elevated',2=>'px-stage1',3=>'px-stage2',4=>'px-crisis'];
?>

<div class="dash">
  <?php if ($flash): ?><div class="alert alert-success d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill"></i> <?= e($flash) ?></div><?php endif; ?>

  <!-- HEADER โปรไฟล์ -->
  <section class="profile-hero mb-4">
    <div class="ph-glow"></div>
    <div class="ph-main">
      <div class="ph-photo <?= $hasPhoto ? '' : 'noimg' ?>">
        <?php if ($hasPhoto): ?><img src="<?= e($profile['photo']) ?>" alt="<?= e($profile['name']) ?>"><?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
        <span class="ph-status"><i class="bi bi-heart-pulse-fill"></i></span>
      </div>
      <div class="ph-info">
        <div class="ph-greet"><?= $greet ?> 👋</div>
        <h1 class="ph-name"><?= e($profile['name']) ?></h1>
        <span class="ph-role"><i class="bi bi-patch-check-fill"></i> <?= e($profile['role']) ?></span>
        <?php if ($currentPhase): ?><span class="ph-role ms-2" style="background:color-mix(in srgb, <?= e($currentPhase['color']) ?> 45%, transparent)"><i class="bi bi-signpost-split"></i> <?= e($currentPhase['name']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="ph-side">
      <div class="ph-stats">
        <div class="ph-stat"><div class="ph-stat-n"><?= $total ?></div><div class="ph-stat-l">วันที่บันทึก</div></div>
        <div class="ph-stat"><div class="ph-stat-n"><?= e($avgS) ?>/<?= e($avgD) ?></div><div class="ph-stat-l">ค่าเฉลี่ยรวม</div></div>
        <div class="ph-stat"><div class="ph-stat-n"><?= $pctRange ?>%</div><div class="ph-stat-l">คุมได้</div></div>
      </div>
      <div class="ph-actions">
        <button class="pill-btn glass" data-bs-toggle="offcanvas" data-bs-target="#settingsPanel"><i class="bi bi-sliders"></i> ปรับแดชบอร์ด</button>
        <a href="index.php" class="pill-btn pill-primary"><i class="bi bi-plus-lg"></i> เพิ่มบันทึก</a>
      </div>
    </div>
  </section>

  <div class="row g-3">
    <div class="col-12 col-xxl-9">
      <div class="row g-3">
        <!-- ค่าล่าสุด = เครื่องวัดความดันสากล -->
        <div class="col-12 col-lg-4 widget" data-widget="latest">
          <div class="bp-device h-100">
            <div class="dev-top"><span class="dev-brand"><i class="bi bi-heart-pulse-fill"></i> BP MONITOR</span><span class="dev-mem">MEM · ค่าล่าสุด</span></div>
            <div class="dev-screen">
              <?php if ($lr): ?>
              <div class="dev-status <?= e($lrBp['class']) ?>"><i class="bi bi-record-circle"></i> <?= e($lrBp['label']) ?></div>
              <div class="dev-rows">
                <div class="dev-r"><span class="dev-lbl">SYS</span><span class="dev-val"><?= $lr['sys'] !== null ? (int)$lr['sys'] : '--' ?></span><span class="dev-u">mmHg</span></div>
                <div class="dev-r"><span class="dev-lbl">DIA</span><span class="dev-val"><?= $lr['dia'] !== null ? (int)$lr['dia'] : '--' ?></span><span class="dev-u">mmHg</span></div>
                <div class="dev-r pulse"><span class="dev-lbl">PULSE</span><i class="bi bi-heart-fill beat"></i><span class="dev-val pv"><?= $lr['hr'] !== null ? (int)$lr['hr'] : '--' ?></span><span class="dev-u">/min</span></div>
              </div>
              <div class="dev-foot"><i class="bi bi-calendar-check"></i> <?= fmt_date($latest['date']) ?> · <?= period_label($lr['period']) ?> ครั้งที่ <?= $lr['seq'] ?></div>
              <?php else: ?>
              <div class="dev-empty">-- / --<div class="dev-u mt-2">ยังไม่มีข้อมูล</div></div>
              <?php endif; ?>
            </div>
            <div class="dev-btns"><a href="index.php" class="dev-btn"><i class="bi bi-plus-lg"></i></a><a href="chart.php" class="dev-btn"><i class="bi bi-graph-up"></i></a><a href="index.php" class="dev-btn"><i class="bi bi-list-ul"></i></a></div>
          </div>
        </div>

        <!-- PIXEL CHART -->
        <div class="col-12 col-lg-8 widget" data-widget="pixel">
          <div class="soft-card h-100">
            <div class="card-mini-head">
              <span><i class="bi bi-grid-3x3-gap-fill"></i> ปฏิทินสุขภาพรายวัน (Pixel)</span>
              <?php if (count($years) > 1): ?>
              <span class="year-chips">
                <?php foreach ($years as $y): ?><button class="year-chip <?= $y===$curYear?'active':'' ?>" onclick="showYear('<?= $y ?>',this)"><?= $y ?></button><?php endforeach; ?>
              </span>
              <?php else: ?><span class="chip-soft"><?= e($curYear) ?></span><?php endif; ?>
            </div>
            <?= pixel_calendar_html($dateLevel, $years, $curYear) ?>
          </div>
        </div>

        <!-- Gauge -->
        <div class="col-12 col-lg-6 widget" data-widget="gauge">
          <div class="soft-card h-100">
            <div class="card-mini-head"><span>สรุปผลการวัด</span><a href="index.php" class="mini-more"><i class="bi bi-chevron-right"></i></a></div>
            <h5 class="mb-3">ระดับความดันรวม</h5>
            <?php
              $segments = [
                ['n'=>$levelCount[0],'c'=>'#16a34a','label'=>'ปกติ'],
                ['n'=>$levelCount[1],'c'=>'#65a30d','label'=>'สูงเล็กน้อย'],
                ['n'=>$levelCount[2],'c'=>'#d99a1a','label'=>'ระยะที่ 1'],
                ['n'=>$levelCount[3]+$levelCount[4],'c'=>'#d1603a','label'=>'ระยะที่ 2+'],
              ];
              $sumSeg = max(1, array_sum(array_column($segments,'n')));
              $cx=130;$cy=118;$rad=92;$sw=22;
              $arc = function($f0,$f1) use ($cx,$cy,$rad){
                $a0=M_PI*(1+$f0);$a1=M_PI*(1+$f1);
                return sprintf('M %.1f %.1f A %d %d 0 0 1 %.1f %.1f',$cx+$rad*cos($a0),$cy+$rad*sin($a0),$rad,$rad,$cx+$rad*cos($a1),$cy+$rad*sin($a1));};
            ?>
            <div class="gauge-wrap"><svg viewBox="0 0 260 150" class="gauge-svg">
              <path d="<?= $arc(0,1) ?>" fill="none" stroke="var(--bs-tertiary-bg)" stroke-width="<?= $sw ?>" stroke-linecap="round"/>
              <?php $acc=0; foreach($segments as $sg){ if($sg['n']<=0)continue; $f0=$acc/$sumSeg;$acc+=$sg['n'];$f1=$acc/$sumSeg; echo '<path d="'.$arc($f0,$f1).'" fill="none" stroke="'.$sg['c'].'" stroke-width="'.$sw.'" stroke-linecap="round" data-tip="'.htmlspecialchars($sg['label'].' · '.$sg['n'].' วัน',ENT_QUOTES).'"/>'; } ?>
              <text x="130" y="112" text-anchor="middle" class="gauge-num"><?= $total ?></text>
              <text x="130" y="132" text-anchor="middle" class="gauge-cap">วันที่บันทึก</text>
            </svg></div>
            <div class="legend-list">
              <?php foreach($segments as $sg): ?><div class="ll-row"><span class="ll-dot" style="background:<?= $sg['c'] ?>"></span><span class="ll-name"><?= $sg['label'] ?></span><span class="ll-val"><?= $sg['n'] ?> วัน</span></div><?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- คุมได้ vs เกินเป้า -->
        <div class="col-12 col-lg-6 widget" data-widget="control">
          <div class="soft-card h-100">
            <div class="card-mini-head"><span>สถิติการควบคุม</span>
              <button class="chip-soft border-0" data-bs-toggle="offcanvas" data-bs-target="#settingsPanel"><i class="bi bi-gear"></i> เป้า &lt; <?= $tSys ?>/<?= $tDia ?></button></div>
            <h5 class="mb-3">คุมได้ vs เกินเป้า</h5>
            <div class="row g-2 mb-3">
              <div class="col-6"><div class="kpi-box green"><div class="kpi-n"><?= $inRange ?></div><div class="kpi-l"><i class="bi bi-emoji-smile"></i> วันที่คุมได้ (<?= $pctRange ?>%)</div></div></div>
              <div class="col-6"><div class="kpi-box gray"><div class="kpi-n"><?= $total-$inRange ?></div><div class="kpi-l"><i class="bi bi-emoji-frown"></i> วันที่เกินเป้า (<?= $pctHigh ?>%)</div></div></div>
            </div>
            <div class="barchart">
              <?php foreach (array_slice($days,-16) as $d): $s=$d['avg']['sys'];$dd=$d['avg']['dia'];
                $ok=in_target($s,$dd); $h=$s===null?6:max(10,min(100,($s-90)/80*100)); ?>
                <div class="bar <?= $ok?'ok':'no' ?>" style="height:<?= round($h) ?>%" data-tip="<?= e(fmt_date($d['date']).' · '.num($s).'/'.num($dd)) ?>"></div>
              <?php endforeach; ?>
            </div>
            <div class="d-flex gap-3 mt-2 x-sm text-secondary">
              <span><span class="ll-dot" style="background:#57b894"></span> คุมได้</span>
              <span><span class="ll-dot" style="background:#cbd5e1"></span> เกินเป้า</span>
            </div>
          </div>
        </div>

        <!-- แนวโน้ม -->
        <div class="col-12 widget" data-widget="trend">
          <div class="soft-card">
            <div class="card-mini-head"><span>แนวโน้มค่าเฉลี่ยรายวัน (14 วันล่าสุด)</span><span class="chip-soft"><?= e($avgS) ?>/<?= e($avgD) ?> เฉลี่ยรวม</span></div>
            <?php
              $pts=[]; foreach($last14 as $d) if($d['avg']['sys']!==null) $pts[]=['date'=>$d['date'],'sys'=>$d['avg']['sys'],'dia'=>$d['avg']['dia']];
              if($pts){
                $LW=960;$LH=240;$lL=38;$lR=14;$lT=16;$lB=34;$lpw=$LW-$lL-$lR;$lph=$LH-$lT-$lB;$ymin=60;$ymax=175;
                $lx=fn($i)=>$lL+(count($pts)<=1?$lpw/2:$i/(count($pts)-1)*$lpw);
                $ly=fn($v)=>$lT+($ymax-max($ymin,min($ymax,$v)))/($ymax-$ymin)*$lph;
                $mk=function($key)use($pts,$lx,$ly){$d='';foreach($pts as $i=>$p)$d.=($i?'L':'M').sprintf('%.1f %.1f ',$lx($i),$ly($p[$key]));return trim($d);};
                $areaD='M '.sprintf('%.1f %.1f',$lx(0),$ly($pts[0]['sys']));foreach($pts as $i=>$p)$areaD.=' L '.sprintf('%.1f %.1f',$lx($i),$ly($p['sys']));
                $areaD.=sprintf(' L %.1f %.1f L %.1f %.1f Z',$lx(count($pts)-1),$lT+$lph,$lx(0),$lT+$lph);
                echo '<div class="chart-box"><svg viewBox="0 0 '.$LW.' '.$LH.'" class="line-svg" style="aspect-ratio:'.$LW.'/'.$LH.'">';
                echo '<defs><linearGradient id="gS" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#57b894" stop-opacity=".28"/><stop offset="1" stop-color="#57b894" stop-opacity="0"/></linearGradient></defs>';
                foreach([80,120,135,160] as $g){$yy=$ly($g);echo '<line x1="'.$lL.'" y1="'.$yy.'" x2="'.($lL+$lpw).'" y2="'.$yy.'" stroke="var(--bs-border-color)" stroke-width="1" stroke-dasharray="3 4"/><text x="'.($lL-6).'" y="'.($yy+4).'" text-anchor="end" class="axl">'.$g.'</text>';}
                echo '<path d="'.$areaD.'" fill="url(#gS)"/>';
                echo '<path d="'.$mk('dia').'" fill="none" stroke="#2c5c7a" stroke-width="2.5" stroke-linejoin="round"/>';
                echo '<path d="'.$mk('sys').'" fill="none" stroke="#57b894" stroke-width="3" stroke-linejoin="round"/>';
                foreach($pts as $i=>$p)echo '<circle class="tip-pt" cx="'.sprintf('%.1f',$lx($i)).'" cy="'.sprintf('%.1f',$ly($p['sys'])).'" r="4" fill="#fff" stroke="#57b894" stroke-width="2" data-tip="'.htmlspecialchars(fmt_date($p['date']).' · บน '.$p['sys'].'/'.$p['dia'],ENT_QUOTES).'"/>';
                $n=count($pts);foreach($pts as $i=>$p){if($n>1&&$i%max(1,intval($n/7))!==0&&$i!==$n-1)continue;echo '<text x="'.sprintf('%.1f',$lx($i)).'" y="'.($lT+$lph+22).'" text-anchor="middle" class="axl">'.date('j/n',strtotime($p['date'])).'</text>';}
                echo '</svg></div>';
              } else echo '<p class="text-center text-secondary py-4">ยังไม่มีข้อมูล</p>';
            ?>
            <div class="d-flex gap-3 mt-1 x-sm text-secondary"><span><span class="ll-dot" style="background:#57b894"></span> ความดันบน</span><span><span class="ll-dot" style="background:#2c5c7a"></span> ความดันล่าง</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- คอลัมน์ขวา -->
    <div class="col-12 col-xxl-3">
      <div class="widget" data-widget="recent">
      <div class="soft-card mb-3">
        <div class="text-secondary small mb-1">การวัดล่าสุด</div><h5 class="mb-3">รายการบันทึก</h5>
        <div class="recent-list">
          <?php foreach (array_slice($daysDesc,0,6) as $d): $bp=$d['bp'];
            $statusMap=[0=>['ปกติ','ok'],1=>['เฝ้าระวัง','warn'],2=>['ระยะที่ 1','warn'],3=>['ระยะที่ 2','bad'],4=>['วิกฤต','bad']];
            $st=$statusMap[$bp['level']]??['-','muted']; ?>
          <div class="recent-row">
            <span class="ava <?= e($bp['class']) ?>"><i class="bi bi-droplet-half"></i></span>
            <div class="flex-grow-1"><div class="rr-name"><?= num($d['avg']['sys']) ?>/<?= num($d['avg']['dia']) ?> <span class="rr-unit">mmHg</span></div><div class="rr-sub"><?= fmt_date($d['date']) ?> · <?= count($d['readings']) ?> ครั้ง</div></div>
            <span class="status-badge s-<?= $st[1] ?>"><?= $st[0] ?></span>
          </div>
          <?php endforeach; ?>
          <?php if(!$daysDesc): ?><p class="text-secondary small">ยังไม่มีข้อมูล</p><?php endif; ?>
        </div>
      </div>
      </div>
      <div class="widget" data-widget="summary">
      <div class="navy-card">
        <div class="text-white-50 small mb-1"><i class="bi bi-clipboard2-pulse"></i> สรุปค่าเฉลี่ยรวม</div>
        <div class="nc-row green"><span>ความดันบน</span><b><?= e($avgS) ?></b></div>
        <div class="nc-row"><span>ความดันล่าง</span><b><?= e($avgD) ?></b></div>
        <div class="nc-row"><span>ชีพจร</span><b><?= e($avgH) ?></b></div>
        <div class="nc-level">ระดับโดยรวม <span class="badge-result <?= e($overall['class']) ?>"><?= e($overall['label']) ?></span></div>
        <div class="nc-big"><div class="text-white-50 small">ค่าเฉลี่ยความดัน</div><div class="nc-bp"><?= e($avgS) ?>/<?= e($avgD) ?></div></div>
      </div>
      </div>
      <!-- เกณฑ์สมาคมความดันโลหิตสูงแห่งประเทศไทย -->
      <div class="widget" data-widget="ref">
      <div class="soft-card acc-ref mt-3 h-100">
        <div class="acc-title"><i class="bi bi-clipboard2-heart"></i> เกณฑ์ความดัน <span>สมาคมความดันฯ ไทย</span></div>
        <table class="acc-table">
          <thead><tr><th>ระดับ</th><th>บน</th><th>ล่าง</th></tr></thead>
          <tbody>
          <tr><td><span class="acc-dot" style="background:#16a34a"></span> ปกติ</td><td>&lt;130</td><td>&lt;80</td></tr>
          <tr><td><span class="acc-dot" style="background:#65a30d"></span> เริ่มเสี่ยง</td><td>130–139</td><td>80–89</td></tr>
          <tr><td><span class="acc-dot" style="background:#d99a1a"></span> สูงระดับ 1</td><td>140–159</td><td>90–99</td></tr>
          <tr><td><span class="acc-dot" style="background:#d1603a"></span> สูงระดับ 2</td><td>160–179</td><td>100–109</td></tr>
          <tr><td><span class="acc-dot" style="background:#b91c1c"></span> สูงระดับ 3</td><td>≥180</td><td>≥110</td></tr>
          </tbody>
        </table>
        <div class="acc-note"><i class="bi bi-info-circle"></i> หน่วย mmHg · เข้าเกณฑ์เมื่อค่าใดค่าหนึ่ง (และ/หรือ) ถึงระดับ</div>
      </div>
      </div>
    </div>
  </div>
</div>

<!-- Offcanvas ตั้งค่าแดชบอร์ด -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsPanel">
  <div class="offcanvas-header"><h5 class="offcanvas-title"><i class="bi bi-sliders"></i> ปรับแดชบอร์ด</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body">
    <h6 class="text-secondary"><i class="bi bi-bullseye"></i> เป้าหมายความดัน (คุมได้เมื่อต่ำกว่า)</h6>
    <form method="post" action="save.php" class="mb-4">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="back" value="dashboard.php">
      <div class="row g-2 align-items-end">
        <div class="col"><label class="form-label x-sm">บน (systolic)</label><input type="number" name="target_sys" class="form-control" value="<?= $tSys ?>" min="90" max="200"></div>
        <div class="col-auto pb-2">/</div>
        <div class="col"><label class="form-label x-sm">ล่าง (diastolic)</label><input type="number" name="target_dia" class="form-control" value="<?= $tDia ?>" min="50" max="130"></div>
      </div>
      <div class="form-text mb-2">ค่ามาตรฐานที่บ้าน 135/85 · ที่คลินิก 140/90</div>
      <button class="btn btn-primary btn-sm w-100"><i class="bi bi-save"></i> บันทึกเป้าหมาย</button>
    </form>
    <hr>
    <h6 class="text-secondary"><i class="bi bi-grid-1x2"></i> แสดง/ซ่อน widget</h6>
    <div id="widgetToggles" class="widget-toggles">
      <?php foreach ([
        'latest'=>'ค่าล่าสุด','pixel'=>'ปฏิทิน Pixel','gauge'=>'สรุประดับความดัน',
        'control'=>'คุมได้ vs เกินเป้า','trend'=>'กราฟแนวโน้ม','recent'=>'รายการล่าสุด','summary'=>'สรุปค่าเฉลี่ย','ref'=>'เกณฑ์ความดัน (ไทย)'] as $wid=>$lbl): ?>
        <label class="wt-row"><span><?= $lbl ?></span>
          <input type="checkbox" class="form-check-input" data-widget-toggle="<?= $wid ?>" checked></label>
      <?php endforeach; ?>
    </div>
    <p class="form-text mt-2">การตั้งค่าการแสดงผลถูกเก็บไว้ในเบราว์เซอร์นี้</p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
