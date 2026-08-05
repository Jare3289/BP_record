<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$rows = db()->query('SELECT * FROM bp_readings ORDER BY record_date DESC, id DESC')->fetchAll();

$allSys = $allDia = $allHr = [];
$prepared = [];
foreach ($rows as $r) {
    $avg = daily_average($r);
    $bp  = classify_bp($avg['sys'], $avg['dia']);
    $prepared[] = ['row' => $r, 'avg' => $avg, 'bp' => $bp];
    if ($avg['sys'] !== null) $allSys[] = $avg['sys'];
    if ($avg['dia'] !== null) $allDia[] = $avg['dia'];
    if ($avg['hr']  !== null) $allHr[]  = $avg['hr'];
}
function bp_stat($arr) {
    if (!$arr) return ['max' => '-', 'min' => '-', 'avg' => '-'];
    return ['max' => max($arr), 'min' => min($arr), 'avg' => (int) round(array_sum($arr) / count($arr))];
}
$sSys = bp_stat($allSys);
$sDia = bp_stat($allDia);
$sHr  = bp_stat($allHr);
$total = count($prepared);

// การแปลผลของค่าเฉลี่ยรวมทั้งหมด
$overall = classify_bp(is_int($sSys['avg']) ? $sSys['avg'] : null, is_int($sDia['avg']) ? $sDia['avg'] : null);

$msg = $_GET['msg'] ?? '';
$flash = match ($msg) {
    'saved'      => ['บันทึกข้อมูลเรียบร้อยแล้ว', 'success', 'bi-check-circle-fill'],
    'deleted'    => ['ลบข้อมูลเรียบร้อยแล้ว', 'secondary', 'bi-trash-fill'],
    'error_date' => ['กรุณาระบุวันที่ให้ถูกต้อง', 'danger', 'bi-exclamation-triangle-fill'],
    'error'      => ['เกิดข้อผิดพลาดในการบันทึก', 'danger', 'bi-exclamation-triangle-fill'],
    default      => null,
};

$PAGE = 'ตารางบันทึก'; $ACTIVE = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div>
    <h1 class="page-title mb-1"><i class="bi bi-clipboard2-pulse"></i> บันทึกความดันโลหิต</h1>
    <p class="text-secondary mb-0">วัดตอนเช้าและก่อนนอน เวลาละ 2 ครั้ง · คำนวณค่าเฉลี่ยและแปลผลอัตโนมัติ</p>
  </div>
  <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#formModal" onclick="resetForm()">
    <i class="bi bi-plus-lg"></i> เพิ่มบันทึก
  </button>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm" role="alert">
    <i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?>
  </div>
<?php endif; ?>

<!-- การ์ดสรุป -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-primary"><i class="bi bi-calendar2-check"></i></div>
      <div>
        <div class="stat-label">จำนวนวันที่บันทึก</div>
        <div class="stat-value"><?= $total ?> <span class="stat-unit">วัน</span></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-danger"><i class="bi bi-arrow-up-circle"></i></div>
      <div>
        <div class="stat-label">ความดันบน (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sSys['avg']) ?> <span class="stat-unit">mmHg</span></div>
        <div class="stat-sub">สูงสุด <?= e($sSys['max']) ?> · ต่ำสุด <?= e($sSys['min']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-info"><i class="bi bi-arrow-down-circle"></i></div>
      <div>
        <div class="stat-label">ความดันล่าง (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sDia['avg']) ?> <span class="stat-unit">mmHg</span></div>
        <div class="stat-sub">สูงสุด <?= e($sDia['max']) ?> · ต่ำสุด <?= e($sDia['min']) ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-success"><i class="bi bi-heart-pulse"></i></div>
      <div>
        <div class="stat-label">ชีพจร (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sHr['avg']) ?> <span class="stat-unit">bpm</span></div>
        <div class="stat-sub">สูงสุด <?= e($sHr['max']) ?> · ต่ำสุด <?= e($sHr['min']) ?></div>
      </div>
    </div>
  </div>
</div>

<?php if ($overall['level'] >= 0): ?>
<div class="overall-banner <?= e($overall['class']) ?> mb-4">
  <i class="bi bi-activity"></i>
  <span>ภาพรวมค่าเฉลี่ยทั้งหมด: <strong><?= e($sSys['avg']) ?>/<?= e($sDia['avg']) ?></strong> mmHg</span>
  <span class="badge-result"><?= e($overall['label']) ?></span>
</div>
<?php endif; ?>

<!-- ตารางข้อมูล -->
<div class="card app-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-table"></i> ประวัติการวัด</span>
    <input type="search" id="tableSearch" class="form-control form-control-sm search-box" placeholder="🔍 ค้นหาวันที่...">
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover bp-table align-middle mb-0" id="bpTable">
        <thead>
          <tr class="text-center">
            <th rowspan="2" class="sticky-col">#</th>
            <th rowspan="2">วันที่</th>
            <th colspan="6" class="grp-morning"><i class="bi bi-sunrise"></i> เช้า</th>
            <th colspan="6" class="grp-night"><i class="bi bi-moon-stars"></i> ก่อนนอน</th>
            <th colspan="3" class="grp-avg">ค่าเฉลี่ยรายวัน</th>
            <th rowspan="2">แปลผล</th>
            <th rowspan="2"></th>
          </tr>
          <tr class="text-center small text-secondary">
            <th>บน<sub>1</sub></th><th>ล่าง<sub>1</sub></th><th>♥<sub>1</sub></th>
            <th>บน<sub>2</sub></th><th>ล่าง<sub>2</sub></th><th>♥<sub>2</sub></th>
            <th>บน<sub>1</sub></th><th>ล่าง<sub>1</sub></th><th>♥<sub>1</sub></th>
            <th>บน<sub>2</sub></th><th>ล่าง<sub>2</sub></th><th>♥<sub>2</sub></th>
            <th>บน</th><th>ล่าง</th><th>♥</th>
          </tr>
        </thead>
        <tbody class="text-center">
        <?php if (!$prepared): ?>
          <tr><td colspan="20" class="text-secondary py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล — กด “เพิ่มบันทึก” เพื่อเริ่มต้น
          </td></tr>
        <?php else: ?>
          <?php $seq = $total; foreach ($prepared as $p): $r = $p['row']; $a = $p['avg']; $bp = $p['bp']; ?>
          <tr data-date="<?= fmt_date($r['record_date']) ?>">
            <td class="text-secondary sticky-col"><?= $seq-- ?></td>
            <td class="text-nowrap fw-500"><?= fmt_date($r['record_date']) ?></td>
            <td><?= num($r['m1_sys']) ?></td><td><?= num($r['m1_dia']) ?></td><td class="text-secondary"><?= num($r['m1_hr']) ?></td>
            <td><?= num($r['m2_sys']) ?></td><td><?= num($r['m2_dia']) ?></td><td class="text-secondary"><?= num($r['m2_hr']) ?></td>
            <td><?= num($r['n1_sys']) ?></td><td><?= num($r['n1_dia']) ?></td><td class="text-secondary"><?= num($r['n1_hr']) ?></td>
            <td><?= num($r['n2_sys']) ?></td><td><?= num($r['n2_dia']) ?></td><td class="text-secondary"><?= num($r['n2_hr']) ?></td>
            <td class="fw-bold avg-cell <?= e($bp['class']) ?>"><?= num($a['sys']) ?></td>
            <td class="fw-bold"><?= num($a['dia']) ?></td>
            <td class="fw-bold"><?= num($a['hr']) ?></td>
            <td class="text-start"><span class="badge-result <?= e($bp['class']) ?>"><?= e($bp['label']) ?></span></td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-outline-secondary"
                onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="แก้ไข">
                <i class="bi bi-pencil"></i>
              </button>
              <form method="post" action="save.php" class="d-inline"
                onsubmit="return confirm('ต้องการลบข้อมูลวันที่ <?= fmt_date($r['record_date']) ?> ?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal ฟอร์ม เพิ่ม/แก้ไข -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php" id="bpForm">
        <div class="modal-header">
          <h5 class="modal-title" id="formTitle"><i class="bi bi-plus-circle"></i> เพิ่มบันทึกใหม่</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f-id" value="">
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label fw-500">วันที่</label>
              <input type="date" name="record_date" id="f-date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-500">หมายเหตุ</label>
              <input type="text" name="note" id="f-note" class="form-control" placeholder="เช่น หลังออกกำลังกาย">
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="session-box morning">
                <div class="session-head"><i class="bi bi-sunrise"></i> ตอนเช้า</div>
                <?php foreach (['m1' => 'ครั้งที่ 1', 'm2' => 'ครั้งที่ 2'] as $p => $lbl): ?>
                <div class="mb-2">
                  <span class="reading-lbl"><?= $lbl ?></span>
                  <div class="input-group input-group-sm reading-inputs">
                    <input type="number" class="form-control" name="<?= $p ?>_sys" id="f-<?= $p ?>_sys" placeholder="บน" min="0" max="300">
                    <span class="input-group-text">/</span>
                    <input type="number" class="form-control" name="<?= $p ?>_dia" id="f-<?= $p ?>_dia" placeholder="ล่าง" min="0" max="200">
                    <input type="number" class="form-control hr-input" name="<?= $p ?>_hr" id="f-<?= $p ?>_hr" placeholder="♥" min="0" max="250">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="session-box night">
                <div class="session-head"><i class="bi bi-moon-stars"></i> ก่อนนอน</div>
                <?php foreach (['n1' => 'ครั้งที่ 1', 'n2' => 'ครั้งที่ 2'] as $p => $lbl): ?>
                <div class="mb-2">
                  <span class="reading-lbl"><?= $lbl ?></span>
                  <div class="input-group input-group-sm reading-inputs">
                    <input type="number" class="form-control" name="<?= $p ?>_sys" id="f-<?= $p ?>_sys" placeholder="บน" min="0" max="300">
                    <span class="input-group-text">/</span>
                    <input type="number" class="form-control" name="<?= $p ?>_dia" id="f-<?= $p ?>_dia" placeholder="ล่าง" min="0" max="200">
                    <input type="number" class="form-control hr-input" name="<?= $p ?>_hr" id="f-<?= $p ?>_hr" placeholder="♥" min="0" max="250">
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
