<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$metrics  = health_metrics();
$allLogs  = load_health_logs();                 // ใหม่→เก่า
$byMetric = [];
foreach ($allLogs as $l) $byMetric[$l['metric']][] = $l;

$catalog  = checkup_catalog();
$checkups = load_checkups();                     // ใหม่→เก่า
$openId   = (int) ($_GET['open'] ?? 0);

// โหลดค่าตรวจของแต่ละครั้ง (เก็บไว้ใช้ทั้ง trend และ accordion)
$ckValues = [];
foreach ($checkups as $c) $ckValues[$c['id']] = load_checkup_values((int)$c['id']);

// แนวโน้มค่าตรวจสำคัญ (เรียงเก่า→ใหม่)
$trendCodes = ['chol' => 'โคเลสเตอรอล', 'ldl' => 'LDL', 'sugar' => 'น้ำตาล', 'hba1c' => 'HbA1c'];
$labTrend = [];
foreach (array_reverse($checkups) as $c) {
    foreach ($trendCodes as $code => $lbl) {
        $v = $ckValues[$c['id']][$code] ?? null;
        if ($v !== null && is_numeric($v)) $labTrend[$code][] = (float)$v;
    }
}

$latestCk = $checkups[0] ?? null;
$latestCkAbn = 0;
if ($latestCk) {
    foreach (checkup_tests_map() as $code => $t) {
        $vv = $ckValues[$latestCk['id']][$code] ?? '';
        if ($vv !== '' && in_array(checkup_flag($t, $vv), ['low','high'])) $latestCkAbn++;
    }
}

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

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div>
    <h1 class="page-title mb-1"><i class="bi bi-heart-pulse-fill"></i> สุขภาพของฉัน</h1>
    <p class="text-secondary mb-0">แดชบอร์ดสุขภาพ · บันทึกค่าประจำวัน · ผลตรวจสุขภาพ พร้อมค่าอ้างอิง</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#checkupModal" onclick="resetCheckup()"><i class="bi bi-clipboard2-plus"></i> เพิ่มผลตรวจ</button>
    <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('glucose')"><i class="bi bi-plus-lg"></i> บันทึกค่าสุขภาพ</button>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div>
<?php endif; ?>

<!-- เลือกบันทึกค่าแบบเร็ว -->
<div class="card app-card mb-4">
  <div class="card-header"><i class="bi bi-lightning-charge-fill"></i> บันทึกค่าสุขภาพวันนี้ — เลือกแล้วใส่ค่าได้เลย</div>
  <div class="card-body">
    <div class="metric-picker">
      <?php foreach ($metrics as $code => $m): ?>
      <button class="metric-pick" style="--mc:<?= e($m[3]) ?>" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('<?= $code ?>')">
        <span class="mp-icon"><i class="bi <?= $m[2] ?>"></i></span>
        <span class="mp-name"><?= e($m[0]) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Dashboard: การ์ดค่าสุขภาพล่าสุด -->
<?php
$withData = array_filter($metrics, fn($m, $code) => !empty($byMetric[$code]), ARRAY_FILTER_USE_BOTH);
if ($withData): ?>
<div class="row g-3 mb-4">
  <?php foreach ($withData as $code => $m):
    $logs = $byMetric[$code];              // desc
    $latest = $logs[0];
    $flag = metric_flag($m, $latest['val']);
    $series = array_reverse(array_map(fn($l) => (float)$l['val'], array_slice($logs, 0, 20)));
  ?>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="metric-card" style="--mc:<?= e($m[3]) ?>">
      <div class="mc-top">
        <span class="mc-ic"><i class="bi <?= $m[2] ?>"></i></span>
        <span class="mc-name"><?= e($m[0]) ?></span>
        <button class="mc-add" title="บันทึกเพิ่ม" data-bs-toggle="modal" data-bs-target="#logModal" onclick="quickLog('<?= $code ?>')"><i class="bi bi-plus"></i></button>
      </div>
      <div class="mc-val <?= $flag==='high'?'f-high':($flag==='low'?'f-low':'') ?>"><?= e(fmt_num($latest['val'])) ?><span class="mc-u"><?= e($m[1]) ?></span></div>
      <div class="mc-spark"><?= sparkline_svg($series, $m[3]) ?></div>
      <div class="mc-date"><i class="bi bi-clock"></i> <?= fmt_date($latest['log_date']) ?> · <?= count($logs) ?> ครั้ง</div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <!-- คอลัมน์ซ้าย -->
  <div class="col-12 col-lg-8">
    <!-- สรุปผลตรวจล่าสุด + แนวโน้มค่าตรวจ -->
    <div class="card app-card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard2-data"></i> สรุปผลตรวจสุขภาพ</span>
        <?php if ($latestCk): ?><a href="#ckHistory" class="chip-soft text-decoration-none">ล่าสุด <?= fmt_date($latestCk['checkup_date']) ?></a><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$latestCk): ?>
          <p class="text-center text-secondary py-4"><i class="bi bi-clipboard2-heart fs-3 d-block mb-2"></i> ยังไม่มีผลตรวจ — กด “เพิ่มผลตรวจ” เพื่อบันทึก</p>
        <?php else: ?>
        <div class="row g-3 align-items-center">
          <div class="col-md-4">
            <div class="ck-summary <?= $latestCkAbn>0?'has-abn':'all-ok' ?>">
              <div class="ck-sum-n"><?= $latestCkAbn ?></div>
              <div class="ck-sum-l"><?= $latestCkAbn>0 ? 'รายการผิดปกติ' : 'ปกติทั้งหมด' ?></div>
              <div class="ck-sum-d"><?= fmt_date($latestCk['checkup_date']) ?> · <?= $latestCk['hospital'] ? e($latestCk['hospital']) : 'ตรวจสุขภาพ' ?></div>
            </div>
          </div>
          <div class="col-md-8">
            <div class="row g-2">
              <?php foreach ($trendCodes as $code => $lbl):
                if (empty($labTrend[$code])) continue;
                $tm = checkup_tests_map()[$code];
                $cur = end($labTrend[$code]);
                $fl = checkup_flag($tm, $cur); ?>
              <div class="col-6 col-xl-3">
                <div class="trend-mini">
                  <div class="tm-lbl"><?= e($lbl) ?></div>
                  <div class="tm-val <?= $fl==='high'?'f-high':($fl==='low'?'f-low':'') ?>"><?= e(fmt_num($cur)) ?></div>
                  <?= sparkline_svg($labTrend[$code], '#2c5c7a', 90, 26) ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <p class="text-secondary x-sm mb-0 mt-2"><i class="bi bi-info-circle"></i> แนวโน้มค่าตรวจสำคัญจากการตรวจแต่ละครั้ง</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ประวัติการตรวจ (accordion) -->
    <div id="ckHistory"></div>
    <?php if ($checkups): ?>
    <div class="accordion checkup-acc" id="checkupAcc">
      <?php foreach ($checkups as $c):
        $vals = $ckValues[$c['id']];
        $abn = 0; foreach (checkup_tests_map() as $code => $t) { if (isset($vals[$code]) && in_array(checkup_flag($t, $vals[$code]), ['low','high'])) $abn++; }
        $bmi = calc_bmi($c['weight'] ?? null, $c['height'] ?? null);
        $open = ((int)$c['id'] === $openId);
      ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button <?= $open ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#ck<?= (int)$c['id'] ?>">
            <div class="ck-head">
              <div class="ck-date"><i class="bi bi-calendar2-check"></i> <?= fmt_date($c['checkup_date']) ?></div>
              <div class="ck-hosp"><?= $c['hospital'] ? e($c['hospital']) : 'ตรวจสุขภาพ' ?></div>
              <div class="ck-vitals">
                <?php if ($c['sbp'] !== null): ?><span class="ck-chip"><i class="bi bi-heart-pulse"></i> <?= (int)$c['sbp'] ?>/<?= (int)$c['dbp'] ?></span><?php endif; ?>
                <?php if ($bmi !== null): ?><span class="ck-chip">BMI <?= e($bmi) ?></span><?php endif; ?>
                <?php if ($abn > 0): ?><span class="ck-chip abn"><i class="bi bi-exclamation-triangle-fill"></i> ผิดปกติ <?= $abn ?></span>
                <?php else: ?><span class="ck-chip ok"><i class="bi bi-check-circle-fill"></i> ปกติ</span><?php endif; ?>
              </div>
            </div>
          </button>
        </h2>
        <div id="ck<?= (int)$c['id'] ?>" class="accordion-collapse collapse <?= $open ? 'show' : '' ?>" data-bs-parent="#checkupAcc">
          <div class="accordion-body">
            <div class="d-flex flex-wrap gap-2 mb-3 justify-content-between">
              <div class="ck-basic">
                <?php foreach ([['น้ำหนัก', $c['weight'] !== null ? fmt_num($c['weight']).' กก.' : '-'], ['ส่วนสูง', $c['height'] !== null ? fmt_num($c['height']).' ซม.' : '-'], ['BMI', $bmi ?? '-'], ['ความดัน', $c['sbp'] !== null ? (int)$c['sbp'].'/'.(int)$c['dbp'] : '-'], ['ชีพจร', $c['pulse'] !== null ? (int)$c['pulse'] : '-'], ['แพทย์', $c['doctor'] ?: '-']] as $b): ?>
                  <div class="ck-b"><span><?= $b[0] ?></span><b><?= e($b[1]) ?></b></div>
                <?php endforeach; ?>
              </div>
              <div>
                <button class="btn btn-sm btn-outline-secondary" onclick='editCheckup(<?= json_encode(array_merge($c, ['v' => $vals]), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i> แก้ไข</button>
                <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบผลตรวจวันที่ <?= fmt_date($c['checkup_date']) ?> ?')">
                  <input type="hidden" name="action" value="delete_checkup"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </div>
            </div>
            <?php foreach ([['ผลเอ็กซเรย์ (Chest X-ray)', $c['xray']], ['คลื่นไฟฟ้าหัวใจ (EKG)', $c['ekg']], ['HB Typing', $c['hbtyping']], ['สรุปผลการตรวจโดยแพทย์', $c['summary']]] as $tx): ?>
              <?php if (trim((string)$tx[1]) !== ''): ?><div class="ck-text"><span class="ck-text-lbl"><?= $tx[0] ?></span> <?= nl2br(e($tx[1])) ?></div><?php endif; ?>
            <?php endforeach; ?>
            <div class="row g-3 mt-1">
              <?php foreach ($catalog as $group => $tests):
                $has = false; foreach ($tests as $t) if (isset($vals[$t[0]]) && $vals[$t[0]] !== '') { $has = true; break; }
                if (!$has) continue; ?>
              <div class="col-12 col-lg-6">
                <div class="lab-group">
                  <div class="lab-group-title"><?= e($group) ?></div>
                  <table class="lab-table">
                    <?php foreach ($tests as $t): $v = $vals[$t[0]] ?? ''; if ($v === '') continue; $flag = checkup_flag($t, $v); ?>
                    <tr>
                      <td class="lab-name"><?= e($t[1]) ?></td>
                      <td class="lab-val <?= $flag ? 'f-'.$flag : '' ?>"><?= e($v) ?><?= $t[2] ? ' <span class="lab-u">'.e($t[2]).'</span>' : '' ?><?php if (isset($flagBadge[$flag])): ?><span class="lab-flag <?= $flagBadge[$flag][1] ?>"><?= $flagBadge[$flag][0] ?></span><?php endif; ?></td>
                      <td class="lab-ref"><?= e($t[3]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </table>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- คอลัมน์ขวา -->
  <div class="col-12 col-lg-4">
    <!-- บันทึกล่าสุด -->
    <div class="card app-card mb-4">
      <div class="card-header"><i class="bi bi-clock-history"></i> บันทึกล่าสุด</div>
      <div class="card-body p-0">
        <?php if (!$allLogs): ?>
          <p class="text-center text-secondary py-4 mb-0 small">ยังไม่มีบันทึก — เลือกค่าด้านบนเพื่อเริ่ม</p>
        <?php else: ?>
        <div class="log-list">
          <?php foreach (array_slice($allLogs, 0, 12) as $l): $m = $metrics[$l['metric']] ?? null; if (!$m) continue; $fl = metric_flag($m, $l['val']); ?>
          <div class="log-row">
            <span class="log-ic" style="--mc:<?= e($m[3]) ?>"><i class="bi <?= $m[2] ?>"></i></span>
            <div class="flex-grow-1">
              <div class="log-name"><?= e($m[0]) ?> <b class="<?= $fl==='high'?'f-high':($fl==='low'?'f-low':'') ?>"><?= e(fmt_num($l['val'])) ?></b> <span class="log-u"><?= e($m[1]) ?></span></div>
              <div class="log-date"><?= fmt_date($l['log_date']) ?><?= $l['note'] ? ' · '.e($l['note']) : '' ?></div>
            </div>
            <button class="log-edit" onclick='editHealth(<?= json_encode($l, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบบันทึกนี้?')">
              <input type="hidden" name="action" value="delete_health"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="log-edit del"><i class="bi bi-trash"></i></button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- แหล่งอ้างอิงค่าปกติ -->
    <div class="card app-card ref-card">
      <div class="card-header"><i class="bi bi-journal-medical"></i> แหล่งอ้างอิงค่าปกติ</div>
      <div class="card-body">
        <?php foreach (reference_sources() as $rs): ?>
        <div class="ref-item"><div class="ref-topic"><i class="bi bi-bookmark-check-fill"></i> <?= e($rs[0]) ?></div><div class="ref-src"><?= e($rs[1]) ?></div></div>
        <?php endforeach; ?>
        <div class="ref-foot"><i class="bi bi-info-circle"></i> ค่าอ้างอิงในผลตรวจแต่ละใบยึดตามช่วงอ้างอิงของห้องปฏิบัติการที่ตรวจเป็นหลัก · ข้อมูลนี้เพื่อการติดตามเบื้องต้น ไม่ทดแทนคำวินิจฉัยของแพทย์</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal บันทึกค่าสุขภาพ (ง่าย) -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php" id="logForm">
        <input type="hidden" name="action" value="save_health">
        <input type="hidden" name="id" id="l-id" value="">
        <input type="hidden" name="metric" id="l-metric" value="glucose">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title"><span id="l-ic" class="log-modal-ic"><i class="bi bi-droplet-half"></i></span> <span id="l-title">บันทึกค่า</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <div class="big-input-wrap">
            <input type="number" step="any" name="val" id="l-val" class="big-input" placeholder="0" required autofocus>
            <span class="big-unit" id="l-unit">mg/dl</span>
          </div>
          <div class="ref-hint mb-3" id="l-ref"></div>
          <div class="row g-2 text-start">
            <div class="col-7"><label class="form-label x-sm">วันที่</label><input type="date" name="log_date" id="l-date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
            <div class="col-5"><label class="form-label x-sm">&nbsp;</label><input type="text" name="note" id="l-note" class="form-control" placeholder="หมายเหตุ"></div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save"></i> บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal ผลตรวจสุขภาพ (แบบแท็บ ใส่ง่าย) -->
<div class="modal fade" id="checkupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="save.php" id="checkupForm">
        <div class="modal-header">
          <h5 class="modal-title" id="ckTitle"><i class="bi bi-plus-circle"></i> เพิ่มผลตรวจสุขภาพ</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save_checkup">
          <input type="hidden" name="id" id="c-id" value="">
          <?php
            $tabs = array_merge(['พื้นฐาน', 'ร่างกาย/ภาพ'], array_keys($catalog));
            $tabId = fn($i) => 'cktab' . $i;
          ?>
          <ul class="nav nav-pills ck-tabs flex-wrap mb-3" role="tablist">
            <?php foreach ($tabs as $i => $tab): ?>
            <li class="nav-item"><button class="nav-link <?= $i===0?'active':'' ?>" data-bs-toggle="pill" data-bs-target="#<?= $tabId($i) ?>" type="button"><?= e($tab) ?></button></li>
            <?php endforeach; ?>
          </ul>
          <div class="tab-content">
            <div class="tab-pane fade show active" id="<?= $tabId(0) ?>">
              <div class="row g-3">
                <div class="col-6 col-md-3"><label class="form-label">วันที่ตรวจ *</label><input type="date" name="checkup_date" id="c-checkup_date" class="form-control form-control-lg" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label">โรงพยาบาล</label><input type="text" name="hospital" id="c-hospital" class="form-control form-control-lg" placeholder="รพ.ครู"></div>
                <div class="col-6 col-md-3"><label class="form-label">แพทย์</label><input type="text" name="doctor" id="c-doctor" class="form-control form-control-lg"></div>
                <div class="col-6 col-md-3"><label class="form-label">ชีพจร</label><input type="number" name="pulse" id="c-pulse" class="form-control form-control-lg"></div>
                <div class="col-6 col-md-3"><label class="form-label">น้ำหนัก (กก.)</label><input type="number" step="0.1" name="weight" id="c-weight" class="form-control form-control-lg"></div>
                <div class="col-6 col-md-3"><label class="form-label">ส่วนสูง (ซม.)</label><input type="number" step="0.1" name="height" id="c-height" class="form-control form-control-lg"></div>
                <div class="col-6 col-md-3"><label class="form-label">ความดันบน</label><input type="number" name="sbp" id="c-sbp" class="form-control form-control-lg"></div>
                <div class="col-6 col-md-3"><label class="form-label">ความดันล่าง</label><input type="number" name="dbp" id="c-dbp" class="form-control form-control-lg"></div>
              </div>
            </div>
            <div class="tab-pane fade" id="<?= $tabId(1) ?>">
              <div class="row g-3">
                <div class="col-md-6"><label class="form-label">ผลเอ็กซเรย์ (Chest X-ray)</label><input type="text" name="xray" id="c-xray" class="form-control form-control-lg" placeholder="เช่น ปกติ"></div>
                <div class="col-md-6"><label class="form-label">คลื่นไฟฟ้าหัวใจ (EKG)</label><input type="text" name="ekg" id="c-ekg" class="form-control form-control-lg"></div>
                <div class="col-md-6"><label class="form-label">HB Typing</label><input type="text" name="hbtyping" id="c-hbtyping" class="form-control form-control-lg"></div>
                <div class="col-md-6"><label class="form-label">สรุปผลการตรวจโดยแพทย์</label><textarea name="summary" id="c-summary" class="form-control" rows="2"></textarea></div>
              </div>
            </div>
            <?php $gidx = 2; foreach ($catalog as $group => $tests): ?>
            <div class="tab-pane fade" id="<?= $tabId($gidx) ?>">
              <div class="row g-3">
                <?php foreach ($tests as $t): ?>
                <div class="col-6 col-md-4 col-xl-3">
                  <label class="form-label"><?= e($t[1]) ?><?= $t[3] ? ' <span class="ref-hint">'.e($t[3]).'</span>' : '' ?></label>
                  <div class="input-group">
                    <input type="<?= ($t[6] ?? 'num') === 'text' ? 'text' : 'number' ?>" step="any" name="v[<?= $t[0] ?>]" id="vc-<?= $t[0] ?>" class="form-control">
                    <?php if ($t[2]): ?><span class="input-group-text"><?= e($t[2]) ?></span><?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php $gidx++; endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> บันทึกผลตรวจ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>window.HEALTH_METRICS = <?= json_encode($metrics, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
