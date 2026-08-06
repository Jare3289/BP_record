<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$catalog  = checkup_catalog();
$checkups = load_checkups();
$openId   = (int) ($_GET['open'] ?? 0);

$msg = $_GET['msg'] ?? '';
$flash = match ($msg) {
    'saved'   => ['บันทึกผลตรวจเรียบร้อยแล้ว', 'success', 'bi-check-circle-fill'],
    'deleted' => ['ลบผลตรวจเรียบร้อยแล้ว', 'secondary', 'bi-trash-fill'],
    'error'   => ['เกิดข้อผิดพลาด กรุณาตรวจสอบข้อมูล', 'danger', 'bi-exclamation-triangle-fill'],
    default   => null,
};

$flagBadge = ['low' => ['ต่ำ', 'flag-low'], 'high' => ['สูง', 'flag-high']];

$PAGE = 'ตรวจสุขภาพ'; $ACTIVE = 'checkups';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div>
    <h1 class="page-title mb-1"><i class="bi bi-clipboard2-pulse-fill"></i> ประวัติการตรวจสุขภาพ</h1>
    <p class="text-secondary mb-0">บันทึกผลตรวจสุขภาพประจำ · เคมีในเลือด · CBC · ปัสสาวะ · อุจจาระ พร้อมค่าอ้างอิงและไฮไลต์ค่าผิดปกติ</p>
  </div>
  <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#checkupModal" onclick="resetCheckup()">
    <i class="bi bi-plus-lg"></i> เพิ่มผลตรวจ
  </button>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div>
<?php endif; ?>

<?php if (!$checkups): ?>
  <div class="card app-card"><div class="card-body text-center text-secondary py-5">
    <i class="bi bi-clipboard2-heart fs-1 d-block mb-3"></i>
    ยังไม่มีประวัติการตรวจสุขภาพ — กด “เพิ่มผลตรวจ” เพื่อบันทึกครั้งแรก
  </div></div>
<?php else: ?>
<div class="accordion checkup-acc" id="checkupAcc">
  <?php foreach ($checkups as $c):
    $vals = load_checkup_values((int)$c['id']);
    $abn = 0;
    foreach (checkup_tests_map() as $code => $t) {
        if (isset($vals[$code]) && in_array(checkup_flag($t, $vals[$code]), ['low','high'])) $abn++;
    }
    $bmi = calc_bmi($c['weight'] ?? null, $c['height'] ?? null);
    $bp  = ($c['sbp'] !== null && $c['dbp'] !== null) ? classify_bp((int)$c['sbp'], (int)$c['dbp']) : null;
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
            <?php if ($c['pulse'] !== null): ?><span class="ck-chip">♥ <?= (int)$c['pulse'] ?></span><?php endif; ?>
            <?php if ($bmi !== null): ?><span class="ck-chip">BMI <?= e($bmi) ?></span><?php endif; ?>
            <?php if ($abn > 0): ?><span class="ck-chip abn"><i class="bi bi-exclamation-triangle-fill"></i> ผิดปกติ <?= $abn ?></span>
            <?php else: ?><span class="ck-chip ok"><i class="bi bi-check-circle-fill"></i> ปกติทั้งหมด</span><?php endif; ?>
          </div>
        </div>
      </button>
    </h2>
    <div id="ck<?= (int)$c['id'] ?>" class="accordion-collapse collapse <?= $open ? 'show' : '' ?>" data-bs-parent="#checkupAcc">
      <div class="accordion-body">
        <!-- ข้อมูลพื้นฐาน + สรุป -->
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <div class="ck-basic">
              <?php foreach ([['น้ำหนัก', $c['weight'] !== null ? fmt_num($c['weight']).' กก.' : '-'],
                              ['ส่วนสูง', $c['height'] !== null ? fmt_num($c['height']).' ซม.' : '-'],
                              ['BMI', $bmi !== null ? $bmi : '-'],
                              ['ความดัน', $c['sbp'] !== null ? (int)$c['sbp'].'/'.(int)$c['dbp'] : '-'],
                              ['ชีพจร', $c['pulse'] !== null ? (int)$c['pulse'] : '-'],
                              ['แพทย์', $c['doctor'] ? $c['doctor'] : '-']] as $b): ?>
                <div class="ck-b"><span><?= $b[0] ?></span><b><?= e($b[1]) ?></b></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-4 text-md-end">
            <button class="btn btn-sm btn-outline-secondary" onclick='editCheckup(<?= json_encode(array_merge($c, ['v' => $vals]), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i> แก้ไข</button>
            <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบผลตรวจวันที่ <?= fmt_date($c['checkup_date']) ?> ?')">
              <input type="hidden" name="action" value="delete_checkup"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button>
            </form>
          </div>
        </div>

        <?php foreach ([['ผลเอ็กซเรย์ (Chest X-ray)', $c['xray']], ['คลื่นไฟฟ้าหัวใจ (EKG)', $c['ekg']], ['HB Typing', $c['hbtyping']], ['สรุปผลการตรวจโดยแพทย์', $c['summary']]] as $tx): ?>
          <?php if (trim((string)$tx[1]) !== ''): ?><div class="ck-text"><span class="ck-text-lbl"><?= $tx[0] ?></span> <?= nl2br(e($tx[1])) ?></div><?php endif; ?>
        <?php endforeach; ?>

        <!-- ผลตรวจตามกลุ่ม -->
        <div class="row g-3 mt-1">
          <?php foreach ($catalog as $group => $tests):
            $has = false; foreach ($tests as $t) if (isset($vals[$t[0]]) && $vals[$t[0]] !== '') { $has = true; break; }
            if (!$has) continue; ?>
          <div class="col-12 col-lg-6">
            <div class="lab-group">
              <div class="lab-group-title"><?= e($group) ?></div>
              <table class="lab-table">
                <?php foreach ($tests as $t): $v = $vals[$t[0]] ?? ''; if ($v === '') continue;
                  $flag = checkup_flag($t, $v); ?>
                <tr>
                  <td class="lab-name"><?= e($t[1]) ?></td>
                  <td class="lab-val <?= $flag ? 'f-'.$flag : '' ?>"><?= e($v) ?><?= $t[2] ? ' <span class="lab-u">'.e($t[2]).'</span>' : '' ?>
                    <?php if (isset($flagBadge[$flag])): ?><span class="lab-flag <?= $flagBadge[$flag][1] ?>"><?= $flagBadge[$flag][0] ?></span><?php endif; ?>
                  </td>
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

<!-- Modal เพิ่ม/แก้ไขผลตรวจ -->
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

          <div class="lab-form-group">
            <div class="lab-form-title"><i class="bi bi-clipboard-data"></i> ข้อมูลพื้นฐาน</div>
            <div class="row g-2">
              <div class="col-6 col-md-3"><label class="form-label x-sm">วันที่ตรวจ *</label><input type="date" name="checkup_date" id="c-checkup_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">โรงพยาบาล</label><input type="text" name="hospital" id="c-hospital" class="form-control" placeholder="เช่น รพ.ครู"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">แพทย์</label><input type="text" name="doctor" id="c-doctor" class="form-control"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">ชีพจร (bpm)</label><input type="number" name="pulse" id="c-pulse" class="form-control"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">น้ำหนัก (กก.)</label><input type="number" step="0.1" name="weight" id="c-weight" class="form-control"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">ส่วนสูง (ซม.)</label><input type="number" step="0.1" name="height" id="c-height" class="form-control"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">ความดันบน</label><input type="number" name="sbp" id="c-sbp" class="form-control"></div>
              <div class="col-6 col-md-3"><label class="form-label x-sm">ความดันล่าง</label><input type="number" name="dbp" id="c-dbp" class="form-control"></div>
            </div>
          </div>

          <div class="lab-form-group">
            <div class="lab-form-title"><i class="bi bi-file-medical"></i> ผลตรวจร่างกาย / ภาพ</div>
            <div class="row g-2">
              <div class="col-md-6"><label class="form-label x-sm">ผลเอ็กซเรย์ (Chest X-ray)</label><input type="text" name="xray" id="c-xray" class="form-control" placeholder="เช่น ปกติ"></div>
              <div class="col-md-6"><label class="form-label x-sm">คลื่นไฟฟ้าหัวใจ (EKG)</label><input type="text" name="ekg" id="c-ekg" class="form-control"></div>
              <div class="col-md-6"><label class="form-label x-sm">HB Typing</label><input type="text" name="hbtyping" id="c-hbtyping" class="form-control"></div>
              <div class="col-md-6"><label class="form-label x-sm">สรุปผลการตรวจโดยแพทย์</label><textarea name="summary" id="c-summary" class="form-control" rows="1"></textarea></div>
            </div>
          </div>

          <?php foreach ($catalog as $group => $tests): ?>
          <div class="lab-form-group">
            <div class="lab-form-title"><i class="bi bi-droplet-half"></i> <?= e($group) ?></div>
            <div class="row g-2">
              <?php foreach ($tests as $t): ?>
              <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label x-sm"><?= e($t[1]) ?><?= $t[3] ? ' <span class="ref-hint">('.e($t[3]).')</span>' : '' ?></label>
                <div class="input-group input-group-sm">
                  <input type="<?= ($t[6] ?? 'num') === 'text' ? 'text' : 'number' ?>" step="any" name="v[<?= $t[0] ?>]" id="vc-<?= $t[0] ?>" class="form-control">
                  <?php if ($t[2]): ?><span class="input-group-text"><?= e($t[2]) ?></span><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> บันทึกผลตรวจ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
