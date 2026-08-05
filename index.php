<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

// ดึงข้อมูลทั้งหมด (ล่าสุดก่อน)
$rows = db()->query('SELECT * FROM bp_readings ORDER BY record_date DESC, id DESC')->fetchAll();

// เตรียมข้อมูลพร้อมค่าเฉลี่ย/แปลผล และเก็บค่าไว้คำนวณสรุป
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

// สรุป สูงสุด/ต่ำสุด/เฉลี่ย ของค่าเฉลี่ยรายวัน
function bp_stat($arr) {
    if (!$arr) return ['max' => '-', 'min' => '-', 'avg' => '-'];
    return ['max' => max($arr), 'min' => min($arr), 'avg' => (int) round(array_sum($arr) / count($arr))];
}
$sSys = bp_stat($allSys);
$sDia = bp_stat($allDia);
$sHr  = bp_stat($allHr);
$total = count($prepared);

$msg = $_GET['msg'] ?? '';
$msgText = match ($msg) {
    'saved'      => ['บันทึกข้อมูลเรียบร้อยแล้ว', 'ok'],
    'deleted'    => ['ลบข้อมูลเรียบร้อยแล้ว', 'ok'],
    'error_date' => ['กรุณาระบุวันที่ให้ถูกต้อง', 'err'],
    'error'      => ['เกิดข้อผิดพลาดในการบันทึก', 'err'],
    default      => null,
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>บันทึกความดันโลหิต</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
  <header class="page-head">
    <h1>🩺 บันทึกความดันโลหิต</h1>
    <p class="sub">วัดตอนเช้าและก่อนนอน เวลาละ 2 ครั้ง · ค่าเฉลี่ยและการแปลผลคำนวณอัตโนมัติ</p>
  </header>

  <?php if ($msgText): ?>
    <div class="flash <?= $msgText[1] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= e($msgText[0]) ?></div>
  <?php endif; ?>

  <!-- การ์ดสรุป -->
  <section class="cards">
    <div class="card">
      <div class="card-title">จำนวนวันที่บันทึก</div>
      <div class="card-value"><?= $total ?></div>
      <div class="card-unit">วัน</div>
    </div>
    <div class="card">
      <div class="card-title">ความดันบน (เฉลี่ย)</div>
      <div class="card-value"><?= e($sSys['avg']) ?></div>
      <div class="card-unit">สูงสุด <?= e($sSys['max']) ?> · ต่ำสุด <?= e($sSys['min']) ?></div>
    </div>
    <div class="card">
      <div class="card-title">ความดันล่าง (เฉลี่ย)</div>
      <div class="card-value"><?= e($sDia['avg']) ?></div>
      <div class="card-unit">สูงสุด <?= e($sDia['max']) ?> · ต่ำสุด <?= e($sDia['min']) ?></div>
    </div>
    <div class="card">
      <div class="card-title">ชีพจร (เฉลี่ย)</div>
      <div class="card-value"><?= e($sHr['avg']) ?></div>
      <div class="card-unit">สูงสุด <?= e($sHr['max']) ?> · ต่ำสุด <?= e($sHr['min']) ?></div>
    </div>
  </section>

  <!-- ฟอร์มเพิ่มข้อมูล -->
  <section class="panel" id="form-panel">
    <h2 id="form-title">➕ เพิ่มบันทึกใหม่</h2>
    <form method="post" action="save.php" id="bp-form">
      <input type="hidden" name="id" id="f-id" value="">
      <div class="form-top">
        <label class="field">
          <span>วันที่</span>
          <input type="date" name="record_date" id="f-date" value="<?= e(date('Y-m-d')) ?>" required>
        </label>
        <label class="field note-field">
          <span>หมายเหตุ</span>
          <input type="text" name="note" id="f-note" placeholder="เช่น หลังออกกำลังกาย">
        </label>
      </div>

      <div class="grid-inputs">
        <fieldset class="session morning">
          <legend>🌅 ตอนเช้า</legend>
          <?php foreach (['m1' => 'ครั้งที่ 1', 'm2' => 'ครั้งที่ 2'] as $p => $lbl): ?>
          <div class="reading">
            <span class="reading-label"><?= $lbl ?></span>
            <input type="number" name="<?= $p ?>_sys" id="f-<?= $p ?>_sys" min="0" max="300" placeholder="บน">
            <input type="number" name="<?= $p ?>_dia" id="f-<?= $p ?>_dia" min="0" max="200" placeholder="ล่าง">
            <input type="number" name="<?= $p ?>_hr"  id="f-<?= $p ?>_hr"  min="0" max="250" placeholder="หัวใจ">
          </div>
          <?php endforeach; ?>
        </fieldset>

        <fieldset class="session night">
          <legend>🌙 ก่อนนอน</legend>
          <?php foreach (['n1' => 'ครั้งที่ 1', 'n2' => 'ครั้งที่ 2'] as $p => $lbl): ?>
          <div class="reading">
            <span class="reading-label"><?= $lbl ?></span>
            <input type="number" name="<?= $p ?>_sys" id="f-<?= $p ?>_sys" min="0" max="300" placeholder="บน">
            <input type="number" name="<?= $p ?>_dia" id="f-<?= $p ?>_dia" min="0" max="200" placeholder="ล่าง">
            <input type="number" name="<?= $p ?>_hr"  id="f-<?= $p ?>_hr"  min="0" max="250" placeholder="หัวใจ">
          </div>
          <?php endforeach; ?>
        </fieldset>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 บันทึก</button>
        <button type="button" class="btn btn-ghost" id="btn-reset" onclick="resetForm()">ล้างฟอร์ม</button>
      </div>
    </form>
  </section>

  <!-- ตารางข้อมูล -->
  <section class="panel">
    <h2>📋 ประวัติการวัด</h2>
    <div class="table-scroll">
      <table class="bp-table">
        <thead>
          <tr>
            <th rowspan="2">ครั้งที่</th>
            <th rowspan="2">วันที่</th>
            <th colspan="6" class="grp morning">🌅 เช้า</th>
            <th colspan="6" class="grp night">🌙 ก่อนนอน</th>
            <th colspan="3" class="grp avg">ค่าเฉลี่ยรายวัน</th>
            <th rowspan="2">แปลผล</th>
            <th rowspan="2">จัดการ</th>
          </tr>
          <tr>
            <th>บน1</th><th>ล่าง1</th><th>หัวใจ1</th>
            <th>บน2</th><th>ล่าง2</th><th>หัวใจ2</th>
            <th>บน1</th><th>ล่าง1</th><th>หัวใจ1</th>
            <th>บน2</th><th>ล่าง2</th><th>หัวใจ2</th>
            <th>บน</th><th>ล่าง</th><th>หัวใจ</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$prepared): ?>
          <tr><td colspan="20" class="empty">ยังไม่มีข้อมูล — เพิ่มบันทึกแรกได้เลย</td></tr>
        <?php else: ?>
          <?php $seq = $total; foreach ($prepared as $p): $r = $p['row']; $a = $p['avg']; $bp = $p['bp']; ?>
          <tr>
            <td class="dim"><?= $seq-- ?></td>
            <td class="nowrap"><?= fmt_date($r['record_date']) ?></td>
            <td><?= num($r['m1_sys']) ?></td><td><?= num($r['m1_dia']) ?></td><td class="dim"><?= num($r['m1_hr']) ?></td>
            <td><?= num($r['m2_sys']) ?></td><td><?= num($r['m2_dia']) ?></td><td class="dim"><?= num($r['m2_hr']) ?></td>
            <td><?= num($r['n1_sys']) ?></td><td><?= num($r['n1_dia']) ?></td><td class="dim"><?= num($r['n1_hr']) ?></td>
            <td><?= num($r['n2_sys']) ?></td><td><?= num($r['n2_dia']) ?></td><td class="dim"><?= num($r['n2_hr']) ?></td>
            <td class="strong <?= e($bp['class']) ?>"><?= num($a['sys']) ?></td>
            <td class="strong"><?= num($a['dia']) ?></td>
            <td class="strong"><?= num($a['hr']) ?></td>
            <td class="result"><span class="badge <?= e($bp['class']) ?>"><?= e($bp['label']) ?></span></td>
            <td class="actions">
              <button type="button" class="link-btn" onclick='editRow(<?= json_encode($r, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>แก้ไข</button>
              <form method="post" action="save.php" onsubmit="return confirm('ต้องการลบข้อมูลวันที่ <?= fmt_date($r['record_date']) ?> ?')" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="link-btn danger">ลบ</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <footer class="page-foot">
    เกณฑ์การแปลผลอ้างอิงจาก ACC/AHA · ข้อมูลนี้ใช้เพื่อการติดตามเบื้องต้น ไม่ทดแทนคำวินิจฉัยของแพทย์
  </footer>
</div>

<script src="assets/app.js"></script>
</body>
</html>
