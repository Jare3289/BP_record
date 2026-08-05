<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$rows = db()->query('SELECT * FROM bp_readings ORDER BY record_date DESC, id DESC')->fetchAll();

// เตรียมค่าเฉลี่ย + แปลผลของแต่ละวัน
$days = [];
$sumS = $sumD = $sumH = 0; $nS = $nD = $nH = 0;
$levelCount = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
$inRange = 0; // คุมได้ (บ้าน <135/85)
foreach ($rows as $r) {
    $a = daily_average($r);
    $bp = classify_bp($a['sys'], $a['dia']);
    $days[] = ['row' => $r, 'avg' => $a, 'bp' => $bp];
    if ($a['sys'] !== null) { $sumS += $a['sys']; $nS++; }
    if ($a['dia'] !== null) { $sumD += $a['dia']; $nD++; }
    if ($a['hr']  !== null) { $sumH += $a['hr'];  $nH++; }
    if ($bp['level'] >= 0) $levelCount[$bp['level']]++;
    if ($a['sys'] !== null && $a['dia'] !== null && $a['sys'] < 135 && $a['dia'] < 85) $inRange++;
}
$total = count($days);
$avgS = $nS ? round($sumS / $nS) : null;
$avgD = $nD ? round($sumD / $nD) : null;
$avgH = $nH ? round($sumH / $nH) : null;
$overall = classify_bp($avgS, $avgD);

$latest = $days[0] ?? null;                       // วันล่าสุด
$pctRange = $total ? round($inRange / $total * 100) : 0;   // % คุมได้
$pctHigh  = 100 - $pctRange;                        // % สูง

// ลำดับเวลา (เก่า→ใหม่) สำหรับกราฟ
$chrono = array_reverse($days);
$last30 = array_slice($chrono, -30);
$last14 = array_slice($chrono, -14);
$last16 = array_slice($chrono, -16);

$greet = (int)date('H') < 12 ? 'สวัสดีตอนเช้า' : ((int)date('H') < 18 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนค่ำ');

$PAGE = 'แดชบอร์ด'; $ACTIVE = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="dash">
  <!-- ===== HEADER โปรไฟล์ (ใหญ่ ชัด เท่) ===== -->
  <section class="profile-hero mb-4">
    <div class="ph-glow"></div>
    <div class="ph-main">
      <div class="ph-photo <?= $hasPhoto ? '' : 'noimg' ?>">
        <?php if ($hasPhoto): ?>
          <img src="<?= e($profile['photo']) ?>" alt="<?= e($profile['name']) ?>">
        <?php else: ?>
          <i class="bi bi-person-fill"></i>
        <?php endif; ?>
        <span class="ph-status"><i class="bi bi-heart-pulse-fill"></i></span>
      </div>
      <div class="ph-info">
        <div class="ph-greet"><?= $greet ?> 👋</div>
        <h1 class="ph-name"><?= e($profile['name']) ?></h1>
        <span class="ph-role"><i class="bi bi-patch-check-fill"></i> <?= e($profile['role']) ?></span>
      </div>
    </div>
    <div class="ph-side">
      <div class="ph-stats">
        <div class="ph-stat">
          <div class="ph-stat-n"><?= $total ?></div>
          <div class="ph-stat-l">วันที่บันทึก</div>
        </div>
        <div class="ph-stat">
          <div class="ph-stat-n"><?= e($avgS) ?>/<?= e($avgD) ?></div>
          <div class="ph-stat-l">ค่าเฉลี่ยรวม</div>
        </div>
        <div class="ph-stat">
          <div class="ph-stat-n"><?= $pctRange ?>%</div>
          <div class="ph-stat-l">คุมได้</div>
        </div>
      </div>
      <div class="ph-actions">
        <span class="pill-btn glass"><i class="bi bi-calendar3"></i> <?= $total ? fmt_date($chrono[max(0,$total-7)]['row']['record_date']) . ' – ' . fmt_date($latest['row']['record_date']) : '—' ?></span>
        <a href="index.php" class="pill-btn glass"><i class="bi bi-table"></i> ตาราง</a>
        <a href="index.php" class="pill-btn pill-primary"><i class="bi bi-plus-lg"></i> เพิ่มบันทึก</a>
      </div>
    </div>
  </section>

  <div class="row g-3">
    <!-- ===== คอลัมน์หลัก ===== -->
    <div class="col-12 col-xxl-9">
      <div class="row g-3">

        <!-- การ์ดค่าล่าสุด (hero) -->
        <div class="col-12 col-lg-4">
          <div class="hero-card h-100">
            <div class="hero-top">
              <span class="hero-tag"><i class="bi bi-clock-history"></i> ค่าล่าสุด</span>
              <?php if ($latest): ?>
                <span class="badge-result <?= e($latest['bp']['class']) ?>"><?= e($latest['bp']['label']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($latest): ?>
            <div class="hero-body">
              <div class="hero-bp"><?= num($latest['avg']['sys']) ?><span>/</span><?= num($latest['avg']['dia']) ?></div>
              <div class="hero-unit">mmHg · <i class="bi bi-heart-pulse"></i> <?= num($latest['avg']['hr']) ?> bpm</div>
              <div class="hero-date"><i class="bi bi-calendar-check"></i> <?= fmt_date($latest['row']['record_date']) ?></div>
            </div>
            <div class="hero-foot">
              <a href="chart.php" class="hero-circle" title="ดูกราฟ"><i class="bi bi-graph-up"></i></a>
              <a href="index.php" class="hero-circle dark" title="ดูตาราง"><i class="bi bi-list-ul"></i></a>
              <span class="hero-name">อัปเดตล่าสุด</span>
            </div>
            <?php else: ?>
              <div class="hero-body"><div class="hero-unit">ยังไม่มีข้อมูล — <a href="index.php" class="text-white text-decoration-underline">เพิ่มบันทึก</a></div></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- การ์ดวิเคราะห์ค่าเฉลี่ย + dotted matrix -->
        <div class="col-12 col-lg-8">
          <div class="soft-card h-100">
            <div class="row g-0">
              <div class="col-8 pe-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <span class="round-ico"><i class="bi bi-activity"></i></span>
                  <span class="big-num"><?= e($avgS) ?></span>
                  <span class="chip-up"><i class="bi bi-arrow-up"></i> บน</span>
                </div>
                <div class="text-secondary small mb-3">ความดันบนเฉลี่ย (mmHg) · จาก <?= $total ?> วัน</div>
                <!-- dotted matrix 30 วันล่าสุด -->
                <div class="dotmatrix">
                  <?php foreach ($last30 as $d):
                    $s = $d['avg']['sys'];
                    $n = $s === null ? 0 : max(1, min(8, (int)round(($s - 100) / 8)));
                    $cls = $d['bp']['class'];
                  ?>
                  <div class="dm-col" title="<?= fmt_date($d['row']['record_date']) ?> · <?= num($s) ?>">
                    <?php for ($i = 8; $i >= 1; $i--): ?>
                      <span class="dm-dot <?= $i <= $n ? 'on '.$cls : '' ?>"></span>
                    <?php endfor; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="d-flex justify-content-between text-secondary x-sm mt-2">
                  <span>30 วันก่อน</span><span>วันนี้</span>
                </div>
              </div>
              <div class="col-4">
                <div class="mini-navy mb-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="mn-ico"><i class="bi bi-check2-circle"></i></span>
                    <span class="mn-delta up"><i class="bi bi-arrow-up"></i></span>
                  </div>
                  <div class="mn-pct"><?= $pctRange ?>%</div>
                  <div class="mn-lbl">คุมได้ (&lt;135/85)</div>
                </div>
                <div class="mini-navy light">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="mn-ico"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="mn-delta"><i class="bi bi-dot"></i></span>
                  </div>
                  <div class="mn-pct"><?= $pctHigh ?>%</div>
                  <div class="mn-lbl">เกินเป้า</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Gauge สรุประดับความดัน -->
        <div class="col-12 col-lg-6">
          <div class="soft-card h-100">
            <div class="card-mini-head">
              <span>สรุปผลการวัด</span><a href="index.php" class="mini-more"><i class="bi bi-chevron-right"></i></a>
            </div>
            <h5 class="mb-3">ระดับความดันรวม</h5>
            <?php
              // gauge semicircle แบ่งตามระดับ
              $segments = [
                ['n' => $levelCount[0], 'c' => '#16a34a', 'label' => 'ปกติ'],
                ['n' => $levelCount[1], 'c' => '#65a30d', 'label' => 'สูงเล็กน้อย'],
                ['n' => $levelCount[2], 'c' => '#d99a1a', 'label' => 'ระยะที่ 1'],
                ['n' => $levelCount[3] + $levelCount[4], 'c' => '#d1603a', 'label' => 'ระยะที่ 2+'],
              ];
              $sumSeg = max(1, array_sum(array_column($segments, 'n')));
              $cx = 130; $cy = 118; $rad = 92; $sw = 22;
              $arc = function ($f0, $f1) use ($cx, $cy, $rad) {
                  $a0 = M_PI * (1 + $f0); $a1 = M_PI * (1 + $f1);
                  $x0 = $cx + $rad * cos($a0); $y0 = $cy + $rad * sin($a0);
                  $x1 = $cx + $rad * cos($a1); $y1 = $cy + $rad * sin($a1);
                  return sprintf('M %.1f %.1f A %d %d 0 0 1 %.1f %.1f', $x0, $y0, $rad, $rad, $x1, $y1);
              };
            ?>
            <div class="gauge-wrap">
              <svg viewBox="0 0 260 150" class="gauge-svg">
                <path d="<?= $arc(0, 1) ?>" fill="none" stroke="var(--bs-tertiary-bg)" stroke-width="<?= $sw ?>" stroke-linecap="round"/>
                <?php $acc = 0; foreach ($segments as $sg): if ($sg['n'] <= 0) continue;
                  $f0 = $acc / $sumSeg; $acc += $sg['n']; $f1 = $acc / $sumSeg; ?>
                  <path d="<?= $arc($f0, $f1) ?>" fill="none" stroke="<?= $sg['c'] ?>" stroke-width="<?= $sw ?>" stroke-linecap="round"/>
                <?php endforeach; ?>
                <text x="130" y="112" text-anchor="middle" class="gauge-num"><?= $total ?></text>
                <text x="130" y="132" text-anchor="middle" class="gauge-cap">วันที่บันทึก</text>
              </svg>
            </div>
            <div class="legend-list">
              <?php foreach ($segments as $sg): ?>
              <div class="ll-row">
                <span class="ll-dot" style="background:<?= $sg['c'] ?>"></span>
                <span class="ll-name"><?= $sg['label'] ?></span>
                <span class="ll-val"><?= $sg['n'] ?> วัน</span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- สถิติคุมได้ vs เกินเป้า -->
        <div class="col-12 col-lg-6">
          <div class="soft-card h-100">
            <div class="card-mini-head">
              <span>สถิติการควบคุม</span><a href="chart.php" class="mini-more"><i class="bi bi-chevron-right"></i></a>
            </div>
            <h5 class="mb-3">คุมได้ vs เกินเป้า</h5>
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="kpi-box green">
                  <div class="kpi-n"><?= $inRange ?></div>
                  <div class="kpi-l"><i class="bi bi-emoji-smile"></i> วันที่คุมได้</div>
                </div>
              </div>
              <div class="col-6">
                <div class="kpi-box gray">
                  <div class="kpi-n"><?= $total - $inRange ?></div>
                  <div class="kpi-l"><i class="bi bi-emoji-frown"></i> วันที่เกินเป้า</div>
                </div>
              </div>
            </div>
            <!-- bar chart 16 วันล่าสุด -->
            <div class="barchart">
              <?php foreach ($last16 as $d):
                $s = $d['avg']['sys']; $dd = $d['avg']['dia'];
                $ok = ($s !== null && $dd !== null && $s < 135 && $dd < 85);
                $h = $s === null ? 6 : max(10, min(100, ($s - 90) / 80 * 100));
              ?>
                <div class="bar <?= $ok ? 'ok' : 'no' ?>" style="height:<?= round($h) ?>%"
                     title="<?= fmt_date($d['row']['record_date']) ?> · <?= num($s) ?>/<?= num($dd) ?>"></div>
              <?php endforeach; ?>
            </div>
            <div class="d-flex gap-3 mt-2 x-sm text-secondary">
              <span><span class="ll-dot" style="background:#57b894"></span> คุมได้</span>
              <span><span class="ll-dot" style="background:#cbd5e1"></span> เกินเป้า</span>
            </div>
          </div>
        </div>

        <!-- กราฟเส้นแนวโน้ม -->
        <div class="col-12">
          <div class="soft-card">
            <div class="card-mini-head">
              <span>แนวโน้มค่าเฉลี่ยรายวัน (14 วันล่าสุด)</span>
              <span class="chip-soft"><?= $avgS ?>/<?= $avgD ?> เฉลี่ยรวม</span>
            </div>
            <?php
              $pts = [];
              foreach ($last14 as $d) if ($d['avg']['sys'] !== null) $pts[] = ['date' => $d['row']['record_date'], 'sys' => $d['avg']['sys'], 'dia' => $d['avg']['dia']];
              if ($pts):
                $LW = 900; $LH = 220; $lL = 34; $lR = 14; $lT = 16; $lB = 34;
                $lpw = $LW - $lL - $lR; $lph = $LH - $lT - $lB;
                $ymin = 60; $ymax = 175;
                $lx = fn($i) => $lL + (count($pts) <= 1 ? $lpw / 2 : $i / (count($pts) - 1) * $lpw);
                $ly = fn($v) => $lT + ($ymax - max($ymin, min($ymax, $v))) / ($ymax - $ymin) * $lph;
                $area = function ($key) use ($pts, $lx, $ly, $lT, $lph) {
                    $d = 'M ' . sprintf('%.1f %.1f', $lx(0), $ly($pts[0][$key]));
                    foreach ($pts as $i => $p) $d .= ' L ' . sprintf('%.1f %.1f', $lx($i), $ly($p[$key]));
                    $d .= sprintf(' L %.1f %.1f L %.1f %.1f Z', $lx(count($pts)-1), $lT+$lph, $lx(0), $lT+$lph);
                    return $d;
                };
                $line = function ($key) use ($pts, $lx, $ly) {
                    $d = '';
                    foreach ($pts as $i => $p) $d .= ($i ? 'L' : 'M') . sprintf('%.1f %.1f ', $lx($i), $ly($p[$key]));
                    return trim($d);
                };
            ?>
            <div class="chart-box">
            <svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" class="line-svg" preserveAspectRatio="none">
              <defs>
                <linearGradient id="gS" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0" stop-color="#57b894" stop-opacity=".28"/><stop offset="1" stop-color="#57b894" stop-opacity="0"/>
                </linearGradient>
              </defs>
              <?php foreach ([80,120,135,160] as $g): $y=$ly($g); ?>
                <line x1="<?= $lL ?>" y1="<?= $y ?>" x2="<?= $lL+$lpw ?>" y2="<?= $y ?>" stroke="var(--bs-border-color)" stroke-width="1" stroke-dasharray="3 4"/>
                <text x="<?= $lL-6 ?>" y="<?= $y+4 ?>" text-anchor="end" class="axl"><?= $g ?></text>
              <?php endforeach; ?>
              <path d="<?= $area('sys') ?>" fill="url(#gS)"/>
              <path d="<?= $line('dia') ?>" fill="none" stroke="#2c5c7a" stroke-width="2.5" stroke-linejoin="round"/>
              <path d="<?= $line('sys') ?>" fill="none" stroke="#57b894" stroke-width="3" stroke-linejoin="round"/>
              <?php foreach ($pts as $i => $p): ?>
                <circle cx="<?= sprintf('%.1f',$lx($i)) ?>" cy="<?= sprintf('%.1f',$ly($p['sys'])) ?>" r="3.5" fill="#fff" stroke="#57b894" stroke-width="2"><title><?= fmt_date($p['date']) ?> บน <?= $p['sys'] ?></title></circle>
              <?php endforeach; ?>
              <?php $n=count($pts); foreach ($pts as $i=>$p): if ($n>1 && $i % max(1,intval($n/7)) !== 0 && $i !== $n-1) continue; ?>
                <text x="<?= sprintf('%.1f',$lx($i)) ?>" y="<?= $lT+$lph+22 ?>" text-anchor="middle" class="axl"><?= date('j/n', strtotime($p['date'])) ?></text>
              <?php endforeach; ?>
            </svg>
            </div>
            <div class="d-flex gap-3 mt-1 x-sm text-secondary">
              <span><span class="ll-dot" style="background:#57b894"></span> ความดันบน</span>
              <span><span class="ll-dot" style="background:#2c5c7a"></span> ความดันล่าง</span>
            </div>
            <?php else: ?><p class="text-secondary py-4 text-center">ยังไม่มีข้อมูล</p><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== คอลัมน์ขวา ===== -->
    <div class="col-12 col-xxl-3">
      <div class="soft-card mb-3">
        <div class="text-secondary small mb-1">การวัดล่าสุด</div>
        <h5 class="mb-3">รายการบันทึก</h5>
        <div class="recent-list">
          <?php foreach (array_slice($days, 0, 6) as $d):
            $bp = $d['bp'];
            $statusMap = [0 => ['ปกติ','ok'], 1 => ['เฝ้าระวัง','warn'], 2 => ['ระยะที่ 1','warn'], 3 => ['ระยะที่ 2','bad'], 4 => ['วิกฤต','bad']];
            $st = $statusMap[$bp['level']] ?? ['-','muted'];
          ?>
          <div class="recent-row">
            <span class="ava <?= e($bp['class']) ?>"><i class="bi bi-droplet-half"></i></span>
            <div class="flex-grow-1">
              <div class="rr-name"><?= num($d['avg']['sys']) ?>/<?= num($d['avg']['dia']) ?> <span class="rr-unit">mmHg</span></div>
              <div class="rr-sub"><?= fmt_date($d['row']['record_date']) ?></div>
            </div>
            <span class="status-badge s-<?= $st[1] ?>"><?= $st[0] ?></span>
          </div>
          <?php endforeach; ?>
          <?php if (!$days): ?><p class="text-secondary small">ยังไม่มีข้อมูล</p><?php endif; ?>
        </div>
      </div>

      <!-- การ์ดสรุปกรมท่า -->
      <div class="navy-card">
        <div class="text-white-50 small mb-1"><i class="bi bi-clipboard2-pulse"></i> สรุปค่าเฉลี่ยรวม</div>
        <div class="nc-row green"><span>ความดันบน</span><b><?= e($avgS) ?></b></div>
        <div class="nc-row"><span>ความดันล่าง</span><b><?= e($avgD) ?></b></div>
        <div class="nc-row"><span>ชีพจร</span><b><?= e($avgH) ?></b></div>
        <div class="nc-level">
          ระดับโดยรวม
          <span class="badge-result <?= e($overall['class']) ?>"><?= e($overall['label']) ?></span>
        </div>
        <div class="nc-big">
          <div class="text-white-50 small">ค่าเฉลี่ยความดัน</div>
          <div class="nc-bp"><?= e($avgS) ?>/<?= e($avgD) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
