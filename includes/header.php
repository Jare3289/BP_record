<?php
/**
 * ส่วนหัวของทุกหน้า (Bootstrap 5 + ธีมการแพทย์)
 * ตัวแปรที่ใช้: $PAGE (ชื่อหน้า), $ACTIVE ('dashboard' | 'chart')
 */
$PAGE   = $PAGE   ?? 'บันทึกความดันโลหิต';
$ACTIVE = $ACTIVE ?? '';
$profile  = @require __DIR__ . '/profile.php';
if (!is_array($profile)) $profile = ['name' => 'ผู้ใช้', 'role' => '', 'photo' => 'assets/profile.jpg'];
$hasPhoto = is_file(__DIR__ . '/../' . $profile['photo']);
?>
<!DOCTYPE html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0d9488">
<title><?= htmlspecialchars($PAGE) ?> · BP Record</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Noto+Sans+Thai:wght@300..700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="assets/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container-fluid px-lg-4">
    <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
      <span class="brand-badge"><i class="bi bi-heart-pulse-fill"></i></span>
      <span class="brand-text">BP<span class="fw-light">Record</span></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $ACTIVE === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
            <i class="bi bi-grid-1x2"></i> แดชบอร์ด
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $ACTIVE === 'table' ? 'active' : '' ?>" href="index.php">
            <i class="bi bi-table"></i> ตารางบันทึก
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $ACTIVE === 'chart' ? 'active' : '' ?>" href="chart.php">
            <i class="bi bi-graph-up"></i> กราฟแนวโน้ม
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $ACTIVE === 'phases' ? 'active' : '' ?>" href="phases.php">
            <i class="bi bi-signpost-split"></i> ช่วงการรักษา
          </a>
        </li>
      </ul>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-theme" id="themeToggle" type="button" title="สลับโหมดสว่าง/มืด">
          <i class="bi bi-moon-stars-fill"></i>
        </button>
        <a href="dashboard.php" class="nav-avatar" title="<?= htmlspecialchars($profile['name']) ?>">
          <?php if ($hasPhoto): ?>
            <img src="<?= htmlspecialchars($profile['photo']) ?>" alt="<?= htmlspecialchars($profile['name']) ?>">
          <?php else: ?>
            <span><?= htmlspecialchars(mb_substr($profile['name'], 0, 1)) ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</nav>
<main class="container-fluid px-lg-4 py-4">
