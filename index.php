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
[$dateLevel, $years, $curYear] = pixel_data($days);

/** วาด 3 ช่อง บน/ล่าง/หัวใจ ของการวัด 1 ครั้ง (คลิกแก้ไข) หรือช่องว่าง (คลิกเพิ่ม) */
function render_slot(?array $r, string $date, string $period): string
{
    if ($r) {
        $j = htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
        $c = "onclick='editRow($j)'";
        return "<td class=\"v-cell\" $c>" . ($r['sys'] !== null ? (int)$r['sys'] : '-') . "</td>"
             . "<td class=\"v-cell\" $c>" . ($r['dia'] !== null ? (int)$r['dia'] : '-') . "</td>"
             . "<td class=\"v-cell dim\" $c>" . ($r['hr'] !== null ? (int)$r['hr'] : '-') . "</td>";
    }
    return "<td class=\"v-empty\" colspan=\"3\" onclick=\"addForDay('" . e($date) . "','" . $period . "')\"><i class=\"bi bi-plus\"></i></td>";
}

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

<!-- ปฏิทินสุขภาพ -->
<div class="card app-card mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-grid-3x3-gap-fill"></i> ปฏิทินสุขภาพรายวัน</span>
    <?php if (count($years) > 1): ?><span class="year-chips">
      <?php foreach ($years as $y): ?><button class="year-chip <?= $y===$curYear?'active':'' ?>" onclick="showYear('<?= $y ?>',this)"><?= $y ?></button><?php endforeach; ?>
    </span><?php else: ?><span class="chip-soft"><?= e($curYear) ?></span><?php endif; ?>
  </div>
  <div class="card-body"><?= pixel_calendar_html($dateLevel, $years, $curYear) ?></div>
</div>

<!-- ตารางบันทึก (หัวตารางแบบเดิม) -->
<div class="card app-card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-table"></i> ประวัติการวัด</span>
    <input type="search" id="tableSearch" class="form-control form-control-sm search-box" placeholder="🔍 ค้นหาวันที่...">
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0 bp-table grid-table" id="bpTable">
        <thead>
          <tr class="text-center">
            <th rowspan="3" class="ps-3 text-start align-middle">วันที่</th>
            <th colspan="6" class="grp-morning"><i class="bi bi-sunrise"></i> เช้า</th>
            <th colspan="6" class="grp-night"><i class="bi bi-moon-stars"></i> ก่อนนอน</th>
            <th colspan="3" class="grp-avg">ค่าเฉลี่ยรายวัน</th>
            <th rowspan="3" class="align-middle">แปลผล</th>
            <th rowspan="3" class="align-middle"></th>
          </tr>
          <tr class="text-center small text-secondary">
            <th colspan="3" class="grp-morning">ครั้งที่ 1</th><th colspan="3" class="grp-morning">ครั้งที่ 2</th>
            <th colspan="3" class="grp-night">ครั้งที่ 1</th><th colspan="3" class="grp-night">ครั้งที่ 2</th>
            <th colspan="3" class="grp-avg"></th>
          </tr>
          <tr class="text-center small text-secondary">
            <?php for ($i=0;$i<5;$i++): ?><th>บน</th><th>ล่าง</th><th>♥</th><?php endfor; ?>
          </tr>
        </thead>
        <tbody>
        <?php if (!$daysDesc): ?>
          <tr><td colspan="19" class="text-center text-secondary py-5">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i> ยังไม่มีข้อมูล — กด “เพิ่มบันทึก” เพื่อเริ่มต้น
          </td></tr>
        <?php else: foreach ($daysDesc as $d):
          $ph = phase_for_date($phases, $d['date']);
          $a = $d['avg']; $bp = $d['bp'];
          $morn = array_values(array_filter($d['readings'], fn($r) => $r['period'] === 'morning'));
          $bed  = array_values(array_filter($d['readings'], fn($r) => $r['period'] === 'bedtime'));
          $chosen = array_filter([$morn[0] ?? null, $morn[1] ?? null, $bed[0] ?? null, $bed[1] ?? null]);
          $chosenIds = array_map(fn($r) => $r['id'], $chosen);
          $extra = array_values(array_filter($d['readings'], fn($r) => !in_array($r['id'], $chosenIds)));
          $notes = array_values(array_unique(array_filter(array_map(fn($r) => trim((string)($r['note'] ?? '')), $d['readings']), fn($n) => $n !== '')));
        ?>
          <tr data-date="<?= fmt_date($d['date']) ?>" class="day-start text-center">
            <td class="ps-3 text-start day-cell">
              <div class="fw-600 text-nowrap"><?= fmt_date($d['date']) ?></div>
              <?php if ($ph || $notes): ?>
              <div class="day-meta">
                <?php if ($ph): ?><span class="phase-tag sm" style="--pc:<?= e($ph['color']) ?>"><i class="bi bi-circle-fill"></i> <?= e($ph['name']) ?></span><?php endif; ?>
                <?php foreach ($notes as $n): ?><span class="day-note"><i class="bi bi-sticky"></i> <?= e($n) ?></span><?php endforeach; ?>
              </div>
              <?php endif; ?>
            </td>
            <?= render_slot($morn[0] ?? null, $d['date'], 'morning') ?>
            <?= render_slot($morn[1] ?? null, $d['date'], 'morning') ?>
            <?= render_slot($bed[0] ?? null, $d['date'], 'bedtime') ?>
            <?= render_slot($bed[1] ?? null, $d['date'], 'bedtime') ?>
            <td class="fw-bold avg-cell <?= e($bp['class']) ?>"><?= num($a['sys']) ?></td>
            <td class="fw-bold"><?= num($a['dia']) ?></td>
            <td class="fw-bold text-secondary"><?= num($a['hr']) ?></td>
            <td><span class="badge-result <?= e($bp['class']) ?>"><?= e($bp['label']) ?></span></td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" onclick="addForDay('<?= e($d['date']) ?>')" title="เพิ่มการวัดในวันนี้"><i class="bi bi-plus-lg"></i></button>
            </td>
          </tr>
          <?php if ($extra): ?>
          <tr data-date="<?= fmt_date($d['date']) ?>" class="extra-row">
            <td></td>
            <td colspan="18" class="text-start">
              <span class="extra-lbl"><i class="bi bi-plus-circle-dotted"></i> การวัดเพิ่มเติม:</span>
              <?php foreach ($extra as $r): ?>
                <button type="button" class="rc rc-extra" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <span class="period-badge p-<?= e($r['period']) ?>"><i class="bi <?= period_icon($r['period']) ?>"></i> <?= period_label($r['period']) ?> <?= $r['seq'] ?></span>
                  <span class="rc-bp"><?= $r['sys'] !== null ? (int)$r['sys'] : '-' ?><span class="sep">/</span><?= $r['dia'] !== null ? (int)$r['dia'] : '-' ?></span>
                  <?php if ($r['hr'] !== null): ?><span class="rc-hr">♥<?= (int)$r['hr'] ?></span><?php endif; ?>
                </button>
              <?php endforeach; ?>
            </td>
          </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
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
          <div class="mt-1"><label class="form-label fw-500">หมายเหตุ</label>
            <input type="text" name="note" id="f-note" class="form-control" placeholder="เช่น หลังออกกำลังกาย"></div>
          <p class="form-text mt-2 mb-0"><i class="bi bi-info-circle"></i> น้ำหนัก · ส่วนสูง · รอบเอว บันทึกได้ที่หน้า <a href="health.php">สุขภาพ</a></p>
        </div>
        <div class="modal-footer">
          <button type="button" id="btnDelete" class="btn btn-outline-danger me-auto" style="display:none" onclick="deleteCurrent()"><i class="bi bi-trash"></i> ลบ</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button>
        </div>
      </form>
    </div>
  </div>
</div>

<form method="post" action="save.php" id="deleteForm" style="display:none">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" id="del-id">
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
