<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$phases = load_phases();

// นับจำนวนวันที่บันทึกในแต่ละช่วง (จากตาราง readings)
try { $rows = db()->query('SELECT DISTINCT record_date FROM readings')->fetchAll(PDO::FETCH_COLUMN); }
catch (Throwable $e) { $rows = []; }
$countByPhase = [];
foreach ($rows as $d) {
    $ph = phase_for_date($phases, $d);
    if ($ph) $countByPhase[$ph['id']] = ($countByPhase[$ph['id']] ?? 0) + 1;
}

// คำนวณวันสิ้นสุดของแต่ละช่วง (ช่วงถัดไปเริ่มเมื่อไร) — ช่วงล่าสุดถึงวันปัจจุบัน
$today = date('Y-m-d');
$phaseEnd = []; $phaseIsCurrent = [];
$np = count($phases);
foreach ($phases as $i => $p) {
    if ($i < $np - 1) {
        $phaseEnd[$p['id']] = date('Y-m-d', strtotime($phases[$i+1]['start_date'] . ' -1 day'));
        $phaseIsCurrent[$p['id']] = false;
    } else {
        $phaseEnd[$p['id']] = ($today >= $p['start_date']) ? $today : $p['start_date'];
        $phaseIsCurrent[$p['id']] = true;
    }
}
$durDays = function ($start, $end) {
    $n = (int) floor((strtotime($end) - strtotime($start)) / 86400) + 1;
    return $n > 0 ? $n : 0;
};

$palette = ['#57b894', '#2c5c7a', '#d99a1a', '#d1603a', '#7c5cbf', '#0d9488', '#e0699a', '#4d7c0f'];

$msg = $_GET['msg'] ?? '';
$flash = match ($msg) {
    'saved'   => ['บันทึกช่วงเรียบร้อยแล้ว', 'success', 'bi-check-circle-fill'],
    'deleted' => ['ลบช่วงเรียบร้อยแล้ว', 'secondary', 'bi-trash-fill'],
    'error'   => ['กรุณากรอกชื่อและวันเริ่มให้ถูกต้อง', 'danger', 'bi-exclamation-triangle-fill'],
    default   => null,
};

$PAGE = 'ช่วงการรักษา'; $ACTIVE = 'phases';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div>
    <h1 class="page-title mb-1"><i class="bi bi-signpost-split"></i> ช่วงการรักษา</h1>
    <p class="text-secondary mb-0">แบ่งข้อมูลเป็นช่วง เช่น ก่อนกินยา · กินยาช่วงที่ 1 · กินยาช่วงที่ 2 เพื่อเปรียบเทียบผล</p>
  </div>
  <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#phaseModal" onclick="resetPhase()">
    <i class="bi bi-plus-lg"></i> เพิ่มช่วง
  </button>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div>
<?php endif; ?>

<div class="card app-card">
  <div class="card-header"><i class="bi bi-list-ol"></i> ช่วงทั้งหมด (เรียงตามวันเริ่ม)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr class="text-secondary small">
          <th class="ps-3">ช่วง</th><th>ช่วงวันที่</th><th>ระยะเวลา</th><th>จำนวนวันที่บันทึก</th><th>หมายเหตุ</th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$phases): ?>
          <tr><td colspan="6" class="text-center text-secondary py-5">
            <i class="bi bi-signpost fs-3 d-block mb-2"></i> ยังไม่มีช่วง — กด “เพิ่มช่วง” เพื่อเริ่มต้น<br>
            <span class="small">เช่น สร้าง “ก่อนกินยา” วันเริ่ม 17/1/2026 แล้วสร้าง “กินยาช่วงที่ 1” วันที่เริ่มกินยา</span>
          </td></tr>
        <?php else: foreach ($phases as $p): ?>
          <tr>
            <td class="ps-3"><span class="phase-tag" style="--pc:<?= e($p['color']) ?>"><i class="bi bi-circle-fill"></i> <?= e($p['name']) ?></span></td>
            <td class="text-nowrap">
              <?= fmt_date($p['start_date']) ?> <i class="bi bi-arrow-right text-secondary small"></i>
              <?php if ($phaseIsCurrent[$p['id']]): ?>
                <span class="badge rounded-pill text-bg-success"><i class="bi bi-broadcast"></i> ปัจจุบัน</span>
              <?php else: ?>
                <?= fmt_date($phaseEnd[$p['id']]) ?>
              <?php endif; ?>
            </td>
            <td class="text-nowrap text-secondary"><?= $durDays($p['start_date'], $phaseEnd[$p['id']]) ?> วัน</td>
            <td><?= $countByPhase[$p['id']] ?? 0 ?> วัน</td>
            <td class="text-secondary small"><?= e($p['note'] ?? '') ?></td>
            <td class="text-nowrap text-end pe-3">
              <button class="btn btn-sm btn-outline-secondary btn-icon"
                onclick='editPhase(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
              <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบช่วง <?= e($p['name']) ?> ?')">
                <input type="hidden" name="action" value="delete_phase">
                <input type="hidden" name="phase_id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger btn-icon"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal ช่วง -->
<div class="modal fade" id="phaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php">
        <div class="modal-header">
          <h5 class="modal-title" id="phaseTitle"><i class="bi bi-plus-circle"></i> เพิ่มช่วง</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save_phase">
          <input type="hidden" name="phase_id" id="p-id" value="">
          <div class="mb-3">
            <label class="form-label fw-500">ชื่อช่วง</label>
            <input type="text" name="name" id="p-name" class="form-control" placeholder="เช่น กินยาช่วงที่ 1" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-7">
              <label class="form-label fw-500">วันเริ่มช่วง</label>
              <input type="date" name="start_date" id="p-start" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-5">
              <label class="form-label fw-500">สี</label>
              <div class="color-picks" id="p-colors">
                <?php foreach ($palette as $i => $c): ?>
                  <label class="color-pick">
                    <input type="radio" name="color" value="<?= $c ?>" <?= $i === 0 ? 'checked' : '' ?>>
                    <span style="background:<?= $c ?>"></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label fw-500">หมายเหตุ</label>
            <input type="text" name="note" id="p-note" class="form-control" placeholder="เช่น เริ่มยา Amlodipine 5mg">
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
