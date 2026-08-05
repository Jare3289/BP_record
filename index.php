<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$readings = all_readings();
$days     = group_days($readings);          // เก่า→ใหม่
$phases   = load_phases();

$allSys = $allDia = $allHr = [];
foreach ($days as $d) {
    if ($d['avg']['sys'] !== null) $allSys[] = $d['avg']['sys'];
    if ($d['avg']['dia'] !== null) $allDia[] = $d['avg']['dia'];
    if ($d['avg']['hr']  !== null) $allHr[]  = $d['avg']['hr'];
}
function bp_stat($arr) {
    if (!$arr) return ['max' => '-', 'min' => '-', 'avg' => '-'];
    return ['max' => max($arr), 'min' => min($arr), 'avg' => (int) round(array_sum($arr) / count($arr))];
}
$sSys = bp_stat($allSys);
$sDia = bp_stat($allDia);
$sHr  = bp_stat($allHr);
$totalDays = count($days);
$totalReadings = count($readings);
$overall = classify_bp(is_int($sSys['avg']) ? $sSys['avg'] : null, is_int($sDia['avg']) ? $sDia['avg'] : null);

$daysDesc = array_reverse($days);            // ใหม่→เก่า สำหรับแสดงผล

$msg = $_GET['msg'] ?? '';
$flash = match ($msg) {
    'saved'      => ['บันทึกข้อมูลเรียบร้อยแล้ว', 'success', 'bi-check-circle-fill'],
    'deleted'    => ['ลบข้อมูลเรียบร้อยแล้ว', 'secondary', 'bi-trash-fill'],
    'error_date' => ['กรุณาระบุวันที่ให้ถูกต้อง', 'danger', 'bi-exclamation-triangle-fill'],
    'error'      => ['เกิดข้อผิดพลาดในการบันทึก', 'danger', 'bi-exclamation-triangle-fill'],
    default      => null,
};

$PAGE = 'ตารางบันทึก'; $ACTIVE = 'table';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div>
    <h1 class="page-title mb-1"><i class="bi bi-clipboard2-pulse"></i> บันทึกความดันโลหิต</h1>
    <p class="text-secondary mb-0">บันทึกทีละครั้ง แยกตามช่วง (เช้า/กลางวัน/เย็น/ก่อนนอน) · แก้ไขแต่ละครั้งได้</p>
  </div>
  <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#formModal" onclick="resetForm()">
    <i class="bi bi-plus-lg"></i> เพิ่มบันทึก
  </button>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash[1] ?> d-flex align-items-center gap-2 shadow-sm"><i class="bi <?= $flash[2] ?>"></i> <?= e($flash[0]) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-primary"><i class="bi bi-calendar2-check"></i></div>
      <div><div class="stat-label">จำนวนวัน · ครั้งวัด</div>
        <div class="stat-value"><?= $totalDays ?> <span class="stat-unit">วัน / <?= $totalReadings ?> ครั้ง</span></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-danger"><i class="bi bi-arrow-up-circle"></i></div>
      <div><div class="stat-label">ความดันบน (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sSys['avg']) ?> <span class="stat-unit">mmHg</span></div>
        <div class="stat-sub">สูงสุด <?= e($sSys['max']) ?> · ต่ำสุด <?= e($sSys['min']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-info"><i class="bi bi-arrow-down-circle"></i></div>
      <div><div class="stat-label">ความดันล่าง (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sDia['avg']) ?> <span class="stat-unit">mmHg</span></div>
        <div class="stat-sub">สูงสุด <?= e($sDia['max']) ?> · ต่ำสุด <?= e($sDia['min']) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-icon bg-soft-success"><i class="bi bi-heart-pulse"></i></div>
      <div><div class="stat-label">ชีพจร (เฉลี่ย)</div>
        <div class="stat-value"><?= e($sHr['avg']) ?> <span class="stat-unit">bpm</span></div>
        <div class="stat-sub">สูงสุด <?= e($sHr['max']) ?> · ต่ำสุด <?= e($sHr['min']) ?></div></div>
    </div>
  </div>
</div>

<div class="card app-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-list-ul"></i> ประวัติการวัด (รายครั้ง)</span>
    <input type="search" id="tableSearch" class="form-control form-control-sm search-box" placeholder="🔍 ค้นหาวันที่...">
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 reading-table" id="bpTable">
        <thead>
          <tr class="text-center small text-secondary">
            <th class="text-start ps-3">วันที่</th>
            <th>ช่วง / ครั้งที่</th>
            <th>บน</th><th>ล่าง</th><th>ชีพจร</th>
            <th>น้ำหนัก</th><th>BMI</th>
            <th>แปลผล</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$daysDesc): ?>
          <tr><td colspan="9" class="text-center text-secondary py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล — กด “เพิ่มบันทึก” เพื่อเริ่มต้น
          </td></tr>
        <?php else: foreach ($daysDesc as $d):
          $ph = phase_for_date($phases, $d['date']);
          $rc = count($d['readings']);
          foreach ($d['readings'] as $idx => $r):
            $rbp = classify_bp($r['sys'] !== null ? (int)$r['sys'] : null, $r['dia'] !== null ? (int)$r['dia'] : null);
            $rbmi = calc_bmi($r['weight'] ?? null, $r['height'] ?? null);
            $rbmc = bmi_category($rbmi);
        ?>
          <tr data-date="<?= fmt_date($d['date']) ?>" class="<?= $idx === 0 ? 'day-start' : '' ?>">
            <?php if ($idx === 0): ?>
              <td class="text-start ps-3 day-cell" rowspan="<?= $rc ?>">
                <div class="fw-600 text-nowrap"><?= fmt_date($d['date']) ?></div>
                <div class="text-secondary x-sm">เฉลี่ย <?= num($d['avg']['sys']) ?>/<?= num($d['avg']['dia']) ?></div>
                <?php if ($ph): ?><span class="phase-tag sm" style="--pc:<?= e($ph['color']) ?>"><i class="bi bi-circle-fill"></i> <?= e($ph['name']) ?></span><?php endif; ?>
              </td>
            <?php endif; ?>
            <td class="text-center">
              <span class="period-badge p-<?= e($r['period']) ?>"><i class="bi <?= period_icon($r['period']) ?>"></i> <?= period_label($r['period']) ?></span>
              <span class="seq-badge">ครั้งที่ <?= $r['seq'] ?></span>
            </td>
            <td class="text-center fw-bold"><?= $r['sys'] !== null ? (int)$r['sys'] : '-' ?></td>
            <td class="text-center fw-bold"><?= $r['dia'] !== null ? (int)$r['dia'] : '-' ?></td>
            <td class="text-center text-secondary"><?= $r['hr'] !== null ? (int)$r['hr'] : '-' ?></td>
            <td class="text-center text-secondary"><?= isset($r['weight']) && $r['weight'] !== null ? e(fmt_num($r['weight'])) : '-' ?></td>
            <td class="text-center"><?= $rbmi !== null ? '<span class="badge-result '.e($rbmc[1]).'">'.e($rbmi).'</span>' : '<span class="text-secondary">-</span>' ?></td>
            <td class="text-center"><span class="badge-result <?= e($rbp['class']) ?>"><?= e($rbp['label']) ?></span></td>
            <td class="text-nowrap text-end pe-2">
              <button type="button" class="btn btn-sm btn-icon btn-outline-secondary"
                onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="แก้ไข"><i class="bi bi-pencil"></i></button>
              <form method="post" action="save.php" class="d-inline" onsubmit="return confirm('ลบการวัดนี้ (<?= period_label($r['period']) ?> ครั้งที่ <?= $r['seq'] ?> วันที่ <?= fmt_date($d['date']) ?>) ?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal บันทึกรายครั้ง -->
<div class="modal fade" id="formModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="save.php" id="bpForm">
        <div class="modal-header">
          <h5 class="modal-title" id="formTitle"><i class="bi bi-plus-circle"></i> เพิ่มบันทึก</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="f-id" value="">
          <div class="row g-3 mb-3">
            <div class="col-7">
              <label class="form-label fw-500">วันที่</label>
              <input type="date" name="record_date" id="f-date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-5">
              <label class="form-label fw-500">ช่วง</label>
              <select name="period" id="f-period" class="form-select">
                <?php foreach (periods() as $code => $meta): ?>
                  <option value="<?= $code ?>"><?= $meta[0] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="reading-box mb-3">
            <div class="row g-2 align-items-end">
              <div class="col-4">
                <label class="form-label x-sm text-secondary mb-1">ความดันบน</label>
                <input type="number" min="0" max="300" name="sys" id="f-sys" class="form-control form-control-lg text-center" placeholder="บน">
              </div>
              <div class="col-1 text-center pb-2 fw-bold text-secondary">/</div>
              <div class="col-4">
                <label class="form-label x-sm text-secondary mb-1">ความดันล่าง</label>
                <input type="number" min="0" max="200" name="dia" id="f-dia" class="form-control form-control-lg text-center" placeholder="ล่าง">
              </div>
              <div class="col-3">
                <label class="form-label x-sm text-secondary mb-1"><i class="bi bi-heart-pulse"></i> ชีพจร</label>
                <input type="number" min="0" max="250" name="hr" id="f-hr" class="form-control form-control-lg text-center" placeholder="♥">
              </div>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-6"><label class="form-label fw-500"><i class="bi bi-speedometer2"></i> น้ำหนัก (กก.)</label>
              <input type="number" step="0.1" min="0" max="400" name="weight" id="f-weight" class="form-control" placeholder="ไม่บังคับ"></div>
            <div class="col-6"><label class="form-label fw-500"><i class="bi bi-rulers"></i> ส่วนสูง (ซม.)</label>
              <input type="number" step="0.1" min="0" max="260" name="height" id="f-height" class="form-control" placeholder="ไม่บังคับ"></div>
          </div>
          <div class="mt-3"><label class="form-label fw-500">หมายเหตุ</label>
            <input type="text" name="note" id="f-note" class="form-control" placeholder="เช่น หลังออกกำลังกาย"></div>
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
