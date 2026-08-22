<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
  if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'type' => 'danger', 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit;
  }
  header('Location: login.php');
  exit;
}

$role = $_SESSION['role'] ?? 'guru';
$message = '';
$messageType = 'info';
$lastScannedSiswa = null;
$lastScannedStatus = '';
$lastScannedJam = '';

$selectedTanggal = $_POST['tanggal'] ?? ($_GET['tanggal'] ?? date('Y-m-d'));

// ----------------------------------------------------
// AJAX Scanner Handler (Fast immediate acceptance)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) && $_POST['ajax'] === '1')) {
  header('Content-Type: application/json; charset=utf-8');
  $token = trim($_POST['barcode_code'] ?? '');
  $selectedTanggal = $_POST['tanggal'] ?? date('Y-m-d');

  if ($token === '') {
    echo json_encode([
      'success' => false,
      'type' => 'danger',
      'message' => 'Kode barcode tidak boleh kosong. Silakan scan ulang.'
    ]);
    exit;
  }

  $stmt = $conn->prepare("SELECT * FROM siswa WHERE (barcode_code = ? OR nis = ? OR barcode_code LIKE CONCAT('%', ?, '%')) AND status = 'aktif' LIMIT 1");
  $stmt->bind_param('sss', $token, $token, $token);
  $stmt->execute();
  $siswa = $stmt->get_result()->fetch_assoc();

  if (!$siswa) {
    echo json_encode([
      'success' => false,
      'type' => 'danger',
      'message' => 'Barcode "' . htmlspecialchars($token) . '" tidak valid atau status siswa tidak aktif.'
    ]);
    exit;
  }

  $check = $conn->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
  $check->bind_param('is', $siswa['id'], $selectedTanggal);
  $check->execute();
  $existing = $check->get_result()->fetch_assoc();

  // Query stats for today
  $countQuery = $conn->prepare("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
        SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat
        FROM absensi WHERE tanggal = ?");
  $countQuery->bind_param('s', $selectedTanggal);
  $countQuery->execute();
  $statsResult = $countQuery->get_result()->fetch_assoc();

  if ($existing) {
    $jamExisting = !empty($existing['jam_scan']) ? substr($existing['jam_scan'], 0, 5) : '-';
    echo json_encode([
      'success' => false,
      'already_scanned' => true,
      'type' => 'warning',
      'message' => 'Siswa <strong>' . htmlspecialchars($siswa['nama']) . '</strong> (' . htmlspecialchars($siswa['nis']) . ') sudah melakukan absensi hari ini dengan status <strong>' . htmlspecialchars(ucfirst($existing['status'])) . '</strong> pada jam ' . htmlspecialchars($jamExisting) . '.',
      'siswa' => [
        'id' => $siswa['id'],
        'nama' => $siswa['nama'],
        'nis' => $siswa['nis'],
        'kelas' => $siswa['kelas'],
        'jurusan' => $siswa['jurusan'] ?? ''
      ],
      'absensi' => [
        'status' => $existing['status'],
        'jam_scan' => $jamExisting,
        'tanggal' => $selectedTanggal
      ],
      'stats' => [
        'total' => (int)($statsResult['total'] ?? 0),
        'hadir' => (int)($statsResult['total_hadir'] ?? 0),
        'terlambat' => (int)($statsResult['total_terlambat'] ?? 0)
      ]
    ]);
    exit;
  }

  // Determine status based on time (07:00 cut-off)
  $jam = date('H:i:s');
  $hour = (int)date('H');
  $minute = (int)date('i');
  $status = ($hour > 7 || ($hour === 7 && $minute > 0)) ? 'terlambat' : 'hadir';

  $insert = $conn->prepare("INSERT INTO absensi (siswa_id, tanggal, status, jam_scan) VALUES (?, ?, ?, ?)");
  $insert->bind_param('isss', $siswa['id'], $selectedTanggal, $status, $jam);

  if ($insert->execute()) {
    // Query updated stats
    $countQuery->execute();
    $updatedStats = $countQuery->get_result()->fetch_assoc();
    $jamFormatted = substr($jam, 0, 5);

    echo json_encode([
      'success' => true,
      'type' => 'success',
      'message' => 'Absensi berhasil dicatat untuk <strong>' . htmlspecialchars($siswa['nama']) . '</strong> (' . htmlspecialchars($siswa['kelas']) . ') - Status: <strong>' . htmlspecialchars(ucfirst($status)) . '</strong> (' . $jamFormatted . ').',
      'siswa' => [
        'id' => $siswa['id'],
        'nama' => $siswa['nama'],
        'nis' => $siswa['nis'],
        'kelas' => $siswa['kelas'],
        'jurusan' => $siswa['jurusan'] ?? ''
      ],
      'absensi' => [
        'status' => $status,
        'jam_scan' => $jamFormatted,
        'jam_full' => $jam,
        'tanggal' => $selectedTanggal
      ],
      'stats' => [
        'total' => (int)($updatedStats['total'] ?? 0),
        'hadir' => (int)($updatedStats['total_hadir'] ?? 0),
        'terlambat' => (int)($updatedStats['total_terlambat'] ?? 0)
      ]
    ]);
    exit;
  } else {
    echo json_encode([
      'success' => false,
      'type' => 'danger',
      'message' => 'Gagal menyimpan data absensi: ' . htmlspecialchars($conn->error)
    ]);
    exit;
  }
}

// ----------------------------------------------------
// Regular POST submission (Fallback for non-JS / Manual)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = trim($_POST['barcode_code'] ?? '');
  $selectedTanggal = $_POST['tanggal'] ?? date('Y-m-d');
  $status = 'hadir';

  if ($token === '') {
    $message = 'Kode barcode tidak boleh kosong. Silakan scan ulang.';
    $messageType = 'danger';
  } else {
    $stmt = $conn->prepare("SELECT * FROM siswa WHERE (barcode_code = ? OR nis = ? OR barcode_code LIKE CONCAT('%', ?, '%')) AND status = 'aktif' LIMIT 1");
    $stmt->bind_param('sss', $token, $token, $token);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();

    if (!$siswa) {
      $message = 'Barcode "' . htmlspecialchars($token) . '" tidak valid atau status siswa tidak aktif.';
      $messageType = 'danger';
    } else {
      $check = $conn->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
      $check->bind_param('is', $siswa['id'], $selectedTanggal);
      $check->execute();
      $existing = $check->get_result()->fetch_assoc();

      if ($existing) {
        $message = 'Siswa <strong>' . htmlspecialchars($siswa['nama']) . '</strong> (' . htmlspecialchars($siswa['nis']) . ') sudah melakukan absensi hari ini dengan status <strong>' . htmlspecialchars(ucfirst($existing['status'])) . '</strong> pada jam ' . htmlspecialchars(substr($existing['jam_scan'] ?? '', 0, 5)) . '.';
        $messageType = 'warning';
        $lastScannedSiswa = $siswa;
        $lastScannedStatus = $existing['status'];
        $lastScannedJam = $existing['jam_scan'];
      } else {
        $jam = date('H:i:s');
        $hour = (int)date('H');
        $minute = (int)date('i');
        if ($hour > 7 || ($hour === 7 && $minute > 0)) {
          $status = 'terlambat';
        }
        $insert = $conn->prepare("INSERT INTO absensi (siswa_id, tanggal, status, jam_scan) VALUES (?, ?, ?, ?)");
        $insert->bind_param('isss', $siswa['id'], $selectedTanggal, $status, $jam);
        if ($insert->execute()) {
          $message = 'Absensi berhasil dicatat untuk <strong>' . htmlspecialchars($siswa['nama']) . '</strong> (' . htmlspecialchars($siswa['kelas']) . ') - Status: <strong>' . htmlspecialchars(ucfirst($status)) . '</strong> (' . substr($jam, 0, 5) . ').';
          $messageType = 'success';
          $lastScannedSiswa = $siswa;
          $lastScannedStatus = $status;
          $lastScannedJam = $jam;
        } else {
          $message = 'Gagal menyimpan data absensi: ' . htmlspecialchars($conn->error);
          $messageType = 'danger';
        }
      }
    }
  }
}

// Summary count for selected date
$totalScanHariIni = 0;
$hadirHariIni = 0;
$terlambatHariIni = 0;

$countQuery = $conn->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat
    FROM absensi WHERE tanggal = ?");
$countQuery->bind_param('s', $selectedTanggal);
$countQuery->execute();
$countResult = $countQuery->get_result()->fetch_assoc();
if ($countResult) {
  $totalScanHariIni = (int)($countResult['total'] ?? 0);
  $hadirHariIni = (int)($countResult['total_hadir'] ?? 0);
  $terlambatHariIni = (int)($countResult['total_terlambat'] ?? 0);
}

// Recent scans for selected date
$stmtRecent = $conn->prepare("SELECT a.*, s.nama, s.nis, s.kelas, s.jurusan
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    WHERE a.tanggal = ?
    ORDER BY a.id DESC LIMIT 8");
$stmtRecent->bind_param('s', $selectedTanggal);
$stmtRecent->execute();
$recentScans = $stmtRecent->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scan Absensi Barcode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=1.4" rel="stylesheet">
  <style>
    .scanner-box {
      border: 2px dashed #3b82f6;
      background: #f8fafc;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      position: relative;
      transition: all 0.2s ease;
    }

    .scanner-box:focus-within {
      border-color: #2563eb;
      background: #eff6ff;
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .laser-line {
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #ef4444, #ef4444, transparent);
      box-shadow: 0 0 8px #ef4444;
      animation: scanning 2s infinite ease-in-out;
      margin: 12px 0;
    }

    @keyframes scanning {

      0%,
      100% {
        opacity: 0.2;
        transform: translateY(0);
      }

      50% {
        opacity: 1;
        transform: translateY(6px);
      }
    }

    .student-success-card {
      background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
      border: 1px solid #86efac;
      border-radius: 12px;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 16px;
      animation: fadeInCard 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes fadeInCard {
      from {
        opacity: 0;
        transform: translateY(-10px) scale(0.98);
      }

      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .nav-scan-tabs .nav-link {
      color: var(--muted);
      font-weight: 600;
      border-radius: 8px;
      padding: 8px 16px;
    }

    .nav-scan-tabs .nav-link.active {
      background: #2563eb;
      color: #fff;
    }

    /* Camera Viewfinder & Scanner Styling */
    .camera-wrapper {
      position: relative;
      width: 100%;
      max-width: 480px;
      margin: 0 auto;
      border-radius: 14px;
      overflow: hidden;
      background: #090d16;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
      border: 2px solid #334155;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .camera-wrapper.scan-success {
      border-color: #10b981 !important;
      box-shadow: 0 0 20px rgba(16, 185, 129, 0.45) !important;
    }

    .camera-wrapper.scan-warning {
      border-color: #f59e0b !important;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.45) !important;
    }

    .camera-wrapper.scan-danger {
      border-color: #ef4444 !important;
      box-shadow: 0 0 20px rgba(239, 68, 68, 0.45) !important;
    }

    #reader {
      width: 100% !important;
      border: none !important;
    }

    #reader video {
      width: 100% !important;
      height: auto !important;
      object-fit: cover;
      border-radius: 12px;
    }

    #reader__scan_region {
      background: transparent !important;
    }

    #reader__dashboard {
      display: none !important;
    }

    .camera-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      pointer-events: none;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 16px;
      z-index: 10;
    }

    .camera-reticle {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 75%;
      max-width: 280px;
      height: 180px;
      border: 2px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.45);
    }

    .camera-reticle::before,
    .camera-reticle::after {
      content: '';
      position: absolute;
      width: 22px;
      height: 22px;
      border-color: #38bdf8;
      border-style: solid;
      pointer-events: none;
    }

    .camera-reticle::before {
      top: -2px;
      left: -2px;
      border-width: 4px 0 0 4px;
      border-top-left-radius: 10px;
    }

    .camera-reticle::after {
      bottom: -2px;
      right: -2px;
      border-width: 0 4px 4px 0;
      border-bottom-right-radius: 10px;
    }

    .reticle-corner-tr {
      position: absolute;
      top: -2px;
      right: -2px;
      width: 22px;
      height: 22px;
      border-top: 4px solid #38bdf8;
      border-right: 4px solid #38bdf8;
      border-top-right-radius: 10px;
    }

    .reticle-corner-bl {
      position: absolute;
      bottom: -2px;
      left: -2px;
      width: 22px;
      height: 22px;
      border-bottom: 4px solid #38bdf8;
      border-left: 4px solid #38bdf8;
      border-bottom-left-radius: 10px;
    }

    .reticle-laser {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, transparent, #ef4444, #f87171, #ef4444, transparent);
      box-shadow: 0 0 10px #ef4444;
      animation: laserSweep 2s infinite ease-in-out;
    }

    @keyframes laserSweep {

      0%,
      100% {
        top: 6%;
        opacity: 0.3;
      }

      50% {
        top: 90%;
        opacity: 1;
      }
    }

    .camera-status-pill {
      background: rgba(15, 23, 42, 0.85);
      color: #f8fafc;
      padding: 6px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      backdrop-filter: blur(4px);
      align-self: center;
      border: 1px solid rgba(255, 255, 255, 0.15);
      transition: all 0.2s ease;
    }

    .camera-controls-bar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-top: 14px;
    }

    .recent-item-enter {
      animation: slideInRecent 0.35s ease;
    }

    @keyframes slideInRecent {
      from {
        opacity: 0;
        transform: translateX(-15px);
        background: #ecfdf5;
      }

      to {
        opacity: 1;
        transform: translateX(0);
        background: transparent;
      }
    }
  </style>
</head>

<body>
  <div class="site-shell">
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileSidebar(event)"></div>
    <aside class="sidebar">
      <div>
        <div class="brand-row">
          <div class="brand-info">
            <div class="logo-circle">BC</div>
            <div>
              <div style="font-weight:700">AbsensiBarcode</div>
              <div style="font-size:13px;color:#94a3b8">Sistem Absensi Sekolah</div>
            </div>
          </div>
          <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" onclick="closeMobileSidebar(event)" aria-label="Tutup Menu">✕</button>
        </div>
        <nav>
          <a href="dashboard.php">🏠 Dashboard</a>
          <a href="siswa.php">👥 Data Siswa</a>
          <a href="barcode.php">🔖 Barcode</a>
          <a href="absensi_barcode.php" class="active">📷 Scan Absensi</a>
          <a href="absensi_manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
          <?php if ($role === 'admin'): ?>
            <a href="users.php">🔒 Pengguna</a>
            <a href="holidays_admin.php">📅 Kelola Libur</a>
          <?php endif; ?>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px">
          <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
          <div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($role) ?></div>
        </div>
        <a href="logout.php" style="display:inline-block;padding:8px 12px;background:#ef4444;color:#fff;border-radius:8px;text-decoration:none">Keluar</a>
      </div>
    </aside>

    <main class="main">
      <header class="app-header">
        <div class="header-left">
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar(event)" aria-label="Toggle Menu" title="Buka / Tutup Menu">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
          </button>
          <div class="header-brand">
            <div class="logo-circle-sm">BC</div>
            <div class="header-brand-text">
              <span class="brand-title">AbsensiBarcode</span>
              <span class="brand-sub">Sistem Absensi Sekolah</span>
            </div>
          </div>
        </div>
        <div class="header-right">
          <div class="user-chip">
            <span class="user-avatar-sm"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
            <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
            <span class="user-role-badge"><?= htmlspecialchars($role ?? $_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Scan Absensi Barcode</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Pindai barcode kartu siswa untuk mencatat absensi</div>
          </div>
          <div style="text-align:right">
            <div id="liveClock" style="font-size:18px;font-weight:700;font-variant-numeric:tabular-nums"><?= date('H:i:s') ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,0.75)"><?= date('l, d F Y') ?></div>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card card">
            <h6>Total Scan Hari Ini</h6>
            <div id="statTotal" style="font-size:24px;font-weight:700;color:#2563eb;transition:all 0.3s"><?= $totalScanHariIni ?></div>
            <div style="color:var(--muted);font-size:12px">Siswa terdata</div>
          </div>
          <div class="stat-card card">
            <h6>Hadir Tepat Waktu</h6>
            <div id="statHadir" style="font-size:24px;font-weight:700;color:#059669;transition:all 0.3s"><?= $hadirHariIni ?></div>
            <div style="color:var(--muted);font-size:12px">Sebelum 07.00</div>
          </div>
          <div class="stat-card card">
            <h6>Terlambat</h6>
            <div id="statTerlambat" style="font-size:24px;font-weight:700;color:#b45309;transition:all 0.3s"><?= $terlambatHariIni ?></div>
            <div style="color:var(--muted);font-size:12px">Pukul 07.00 ke atas</div>
          </div>
        </div>

        <!-- Live Notification / Feedback Area -->
        <div id="liveAlertArea">
          <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert" style="border-radius:10px">
              <?= $message ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>
        </div>

        <!-- Live Student Success / Status Card -->
        <div id="liveStudentCardArea">
          <?php if ($lastScannedSiswa): ?>
            <div class="student-success-card">
              <div class="avatar" style="width:48px;height:48px;font-size:20px;font-weight:700;background:#dcfce7;color:#15803d">
                <?= strtoupper(substr($lastScannedSiswa['nama'], 0, 1)) ?>
              </div>
              <div style="flex:1">
                <div style="font-weight:700;font-size:16px;color:#14532d"><?= htmlspecialchars($lastScannedSiswa['nama']) ?></div>
                <div style="font-size:13px;color:#166534">
                  NIS: <?= htmlspecialchars($lastScannedSiswa['nis']) ?> · Kelas: <?= htmlspecialchars($lastScannedSiswa['kelas']) ?> <?= !empty($lastScannedSiswa['jurusan']) ? '(' . htmlspecialchars($lastScannedSiswa['jurusan']) . ')' : '' ?>
                </div>
              </div>
              <div style="text-align:right">
                <span class="tag <?= strtolower(str_replace(' ', '', $lastScannedStatus)) ?>" style="font-size:13px;padding:6px 14px">
                  <?= htmlspecialchars(ucfirst($lastScannedStatus)) ?>
                </span>
                <div style="font-size:12px;color:#64748b;margin-top:4px">Jam: <?= htmlspecialchars(substr($lastScannedJam, 0, 5)) ?></div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="content-grid">
          <div>
            <div class="card" style="border-radius:12px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <h6 style="margin:0;font-weight:700">Area Pemindaian Barcode</h6>
                <ul class="nav nav-pills nav-scan-tabs" id="scanTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="scanner-tab" data-bs-toggle="pill" data-bs-target="#tab-scanner" type="button" role="tab">Scanner / Manual</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link" id="camera-tab" data-bs-toggle="pill" data-bs-target="#tab-camera" type="button" role="tab">📷 Kamera</button>
                  </li>
                </ul>
              </div>

              <div class="tab-content" id="scanTabsContent">
                <!-- Tab Scanner Fisik / Keyboard -->
                <div class="tab-pane fade show active" id="tab-scanner" role="tabpanel">
                  <form method="post" id="barcodeForm">
                    <div class="row g-2 mb-3">
                      <div class="col-md-6">
                        <label class="form-label" style="font-weight:600;font-size:13px">Tanggal Absensi</label>
                        <input type="date" name="tanggal" id="tanggalInput" class="form-control" value="<?= htmlspecialchars($selectedTanggal) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" style="font-weight:600;font-size:13px">Status Jam Masuk</label>
                        <div class="form-control" style="background:#f8fafc;color:#475569;font-size:13px">
                          Batas waktu hadir: <strong>07.00 WIB</strong>
                        </div>
                      </div>
                    </div>

                    <div class="scanner-box mb-3">
                      <div style="font-size:36px;margin-bottom:6px">🔖</div>
                      <div class="laser-line"></div>
                      <div style="font-weight:600;font-size:15px;color:#1e293b;margin-bottom:4px">Siap Menerima Scan Barcode</div>
                      <div style="font-size:12px;color:var(--muted);margin-bottom:16px">Arahkan scanner barcode ke kartu siswa atau ketik kode manual di bawah</div>

                      <div class="input-group input-group-lg" style="max-width:460px;margin:0 auto">
                        <span class="input-group-text bg-white" style="border-right:0">🔍</span>
                        <input type="text" name="barcode_code" id="barcodeInput" class="form-control text-center" placeholder="Scan barcode disini..." autocomplete="off" autofocus required style="font-weight:600;letter-spacing:1px;font-size:16px">
                        <button type="submit" class="btn btn-primary px-4" id="btnSubmitScan">Scan</button>
                      </div>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                      <div style="font-size:12px;color:var(--muted)">💡 Scanner barcode otomatis menekan Enter setelah scan.</div>
                      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('barcodeInput').value='';document.getElementById('barcodeInput').focus()">Bersihkan Input</button>
                    </div>
                  </form>
                </div>

                <!-- Tab Scan Kamera Device -->
                <div class="tab-pane fade" id="tab-camera" role="tabpanel">
                  <div style="padding:4px 0">
                    <!-- Date sync note -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                      <div style="font-size:13px;color:#334155;font-weight:600">
                        📅 Tanggal Absensi: <span id="cameraTanggalDisplay" class="badge bg-light text-dark border"><?= date('d F Y', strtotime($selectedTanggal)) ?></span>
                      </div>
                      <div class="d-flex gap-2">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" id="checkContinuous" checked>
                          <label class="form-check-label" for="checkContinuous" style="font-size:12px;font-weight:600;cursor:pointer">Scan Otomatis Berlanjut</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" id="checkAudioSound" checked>
                          <label class="form-check-label" for="checkAudioSound" style="font-size:12px;font-weight:600;cursor:pointer">Suara Bip</label>
                        </div>
                      </div>
                    </div>

                    <!-- Placeholder sebelum kamera aktif -->
                    <div id="cameraPlaceholder" style="background:#f8fafc;border:2px dashed #cbd5e1;border-radius:14px;padding:36px 20px;text-align:center;margin-bottom:14px">
                      <div style="font-size:44px;margin-bottom:10px">📷</div>
                      <div style="font-weight:700;font-size:16px;color:#1e293b">Pindai dengan Kamera Device</div>
                      <div style="font-size:13px;color:var(--muted);margin:6px auto 18px auto;max-width:380px">
                        Gunakan kamera HP, laptop, atau USB webcam. Barcode akan langsung divalidasi dan absensi otomatis tercatat saat terdeteksi.
                      </div>
                      <button type="button" class="btn btn-success px-4 py-2" id="btnStartCamera" style="font-weight:700;font-size:15px;box-shadow:0 4px 12px rgba(22,163,74,0.3)">
                        ▶ Buka Kamera Sekarang
                      </button>
                    </div>

                    <!-- Viewfinder Kamera Aktif -->
                    <div id="cameraActiveBox" style="display:none">
                      <div class="camera-wrapper" id="cameraWrapper">
                        <div id="reader"></div>
                        <div class="camera-overlay">
                          <div class="camera-status-pill" id="cameraStatusBadge">
                            <span class="spinner-grow spinner-grow-sm text-success" style="width:8px;height:8px" role="status"></span>
                            <span>Kamera aktif · Arahkan ke barcode siswa</span>
                          </div>

                          <div class="camera-reticle">
                            <div class="reticle-corner-tr"></div>
                            <div class="reticle-corner-bl"></div>
                            <div class="reticle-laser"></div>
                          </div>

                          <div style="text-align:center;color:rgba(255,255,255,0.7);font-size:11px;font-weight:600;text-shadow:0 1px 2px #000">
                            Posisikan barcode di dalam bingkai
                          </div>
                        </div>
                      </div>

                      <!-- Camera Controls Bar -->
                      <div class="camera-controls-bar">
                        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width:320px">
                          <select class="form-select form-select-sm" id="cameraSelect" aria-label="Pilih Kamera">
                            <option value="">Deteksi Kamera...</option>
                          </select>
                        </div>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSwitchCamera" title="Ganti Kamera">🔄 Balik</button>
                          <button type="button" class="btn btn-sm btn-danger px-3" id="btnStopCamera" style="font-weight:600">⏹ Tutup Kamera</button>
                        </div>
                      </div>
                    </div>

                    <!-- Guidance Info -->
                    <div class="alert alert-light border mt-3 mb-0" style="font-size:12px;border-radius:10px">
                      <div style="font-weight:600;margin-bottom:2px">💡 Tips Pemindaian Cepat:</div>
                      <div>Arahkan barcode siswa ke dalam kotak pemindai dengan pencahayaan yang cukup. Sistem langsung mencatat absensi tanpa perlu menekan tombol apapun!</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <aside class="right-panel">
            <div class="card" style="border-radius:12px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h6 style="margin:0;font-weight:700">Absensi Terbaru Hari Ini</h6>
                <a href="riwayat.php" style="font-size:12px;text-decoration:none;color:#2563eb">Lihat Semua →</a>
              </div>

              <div id="recentScansContainer">
                <?php if (empty($recentScans)): ?>
                  <div id="emptyRecentPlaceholder" style="text-align:center;padding:30px 10px;color:var(--muted)">
                    <div style="font-size:32px;margin-bottom:8px">📋</div>
                    <div style="font-size:14px;font-weight:600">Belum ada absensi</div>
                    <div style="font-size:12px">Data absensi hari ini akan muncul di sini setelah scan.</div>
                  </div>
                  <div class="recent-list" id="recentList" style="padding:0;box-shadow:none;display:none"></div>
                <?php else: ?>
                  <div class="recent-list" id="recentList" style="padding:0;box-shadow:none">
                    <?php foreach ($recentScans as $r):
                      $sclass = strtolower(str_replace(' ', '', $r['status']));
                      $jamScanFormatted = !empty($r['jam_scan']) ? substr($r['jam_scan'], 0, 5) : '-';
                    ?>
                      <div class="recent-item" style="padding:10px 0">
                        <div class="avatar" style="width:34px;height:34px;font-size:13px;font-weight:700">
                          <?= strtoupper(substr($r['nama'], 0, 1)) ?>
                        </div>
                        <div style="flex:1;min-width:0">
                          <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            <?= htmlspecialchars($r['nama']) ?>
                          </div>
                          <div style="font-size:11px;color:var(--muted)">
                            NIS: <?= htmlspecialchars($r['nis']) ?> · <?= htmlspecialchars($r['kelas']) ?>
                          </div>
                        </div>
                        <div style="text-align:right">
                          <span class="tag <?= htmlspecialchars($sclass) ?>" style="font-size:11px;padding:3px 8px">
                            <?= htmlspecialchars(ucfirst($r['status'])) ?>
                          </span>
                          <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= htmlspecialchars($jamScanFormatted) ?></div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="card" style="border-radius:12px">
              <h6 style="margin:0 0 10px 0;font-weight:700;font-size:14px">Petunjuk Penggunaan</h6>
              <ul style="font-size:12px;color:#475569;margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px">
                <li>Gunakan <strong>Kamera Device</strong> untuk scan langsung dari kartu siswa atau smartphone.</li>
                <li>Gunakan tab <strong>Scanner / Manual</strong> jika menggunakan scanner barcode USB genggam.</li>
                <li>Setelah barcode terbaca dan valid, absensi otomatis diterima & disimpan.</li>
                <li>Status <strong>Hadir</strong> sebelum pukul 07.00, atau <strong>Terlambat</strong> setelah pukul 07.00.</li>
              </ul>
            </div>
          </aside>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
    // Live clock in top-hero
    function updateClock() {
      const el = document.getElementById('liveClock');
      if (!el) return;
      const now = new Date();
      const h = String(now.getHours()).padStart(2, '0');
      const m = String(now.getMinutes()).padStart(2, '0');
      const s = String(now.getSeconds()).padStart(2, '0');
      el.textContent = h + ':' + m + ':' + s;
    }
    setInterval(updateClock, 1000);

    // Audio synthesizer with Web Audio API for fast zero-latency beeps
    function playChime(type) {
      const checkSound = document.getElementById('checkAudioSound');
      if (checkSound && !checkSound.checked) return;

      try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();

        if (type === 'success') {
          // Melodic two-tone high chime (C6 -> E6)
          const osc1 = ctx.createOscillator();
          const osc2 = ctx.createOscillator();
          const gain = ctx.createGain();

          osc1.type = 'sine';
          osc1.frequency.setValueAtTime(1046.5, ctx.currentTime); // C6
          osc2.type = 'sine';
          osc2.frequency.setValueAtTime(1318.5, ctx.currentTime + 0.1); // E6

          gain.gain.setValueAtTime(0.2, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);

          osc1.connect(gain);
          osc2.connect(gain);
          gain.connect(ctx.destination);

          osc1.start(ctx.currentTime);
          osc1.stop(ctx.currentTime + 0.12);
          osc2.start(ctx.currentTime + 0.1);
          osc2.stop(ctx.currentTime + 0.35);
        } else if (type === 'warning') {
          // Caution double beep
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = 'triangle';
          osc.frequency.setValueAtTime(587.3, ctx.currentTime); // D5
          osc.frequency.setValueAtTime(440.0, ctx.currentTime + 0.12); // A4

          gain.gain.setValueAtTime(0.2, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);

          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(ctx.currentTime);
          osc.stop(ctx.currentTime + 0.3);
        } else {
          // Low buzz for error / invalid
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = 'sawtooth';
          osc.frequency.setValueAtTime(220, ctx.currentTime); // A3

          gain.gain.setValueAtTime(0.2, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.28);

          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(ctx.currentTime);
          osc.stop(ctx.currentTime + 0.28);
        }
      } catch (e) {
        console.warn('Audio playback error:', e);
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      const barcodeInput = document.getElementById('barcodeInput');
      const tanggalInput = document.getElementById('tanggalInput');
      const barcodeForm = document.getElementById('barcodeForm');
      const liveAlertArea = document.getElementById('liveAlertArea');
      const liveStudentCardArea = document.getElementById('liveStudentCardArea');
      const statTotal = document.getElementById('statTotal');
      const statHadir = document.getElementById('statHadir');
      const statTerlambat = document.getElementById('statTerlambat');
      const recentList = document.getElementById('recentList');
      const emptyRecentPlaceholder = document.getElementById('emptyRecentPlaceholder');
      const cameraTanggalDisplay = document.getElementById('cameraTanggalDisplay');

      // State flags
      let isProcessing = false;
      let lastScannedToken = '';
      let lastScannedTime = 0;

      if (barcodeInput) {
        barcodeInput.focus();
        barcodeInput.select();
      }

      // Sync date display when date input changes
      if (tanggalInput && cameraTanggalDisplay) {
        tanggalInput.addEventListener('change', function() {
          const d = new Date(this.value);
          if (!isNaN(d.getTime())) {
            const options = {
              day: 'numeric',
              month: 'long',
              year: 'numeric'
            };
            cameraTanggalDisplay.textContent = d.toLocaleDateString('id-ID', options);
          }
        });
      }

      // Render Alert Banner dynamically
      function showAlert(type, htmlMessage) {
        if (!liveAlertArea) return;
        liveAlertArea.innerHTML = `
          <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="border-radius:10px;animation:fadeInCard 0.3s ease">
            ${htmlMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        `;
      }

      // Render Student Success Card dynamically
      function showStudentCard(siswa, absensi, type) {
        if (!liveStudentCardArea || !siswa) return;
        const initial = (siswa.nama || 'S').charAt(0).toUpperCase();
        const jurusanText = siswa.jurusan ? `(${escapeHtml(siswa.jurusan)})` : '';
        const statusClass = (absensi.status || 'hadir').toLowerCase().replace(/\s+/g, '');
        const statusLabel = absensi.status ? absensi.status.charAt(0).toUpperCase() + absensi.status.slice(1) : 'Hadir';
        const jamText = absensi.jam_scan ? escapeHtml(absensi.jam_scan) : '-';

        let bgAvatar = '#dcfce7';
        let colorAvatar = '#15803d';
        let cardBg = 'linear-gradient(135deg, #f0fdf4, #ecfdf5)';
        let cardBorder = '#86efac';

        if (type === 'warning') {
          bgAvatar = '#fef3c7';
          colorAvatar = '#b45309';
          cardBg = 'linear-gradient(135deg, #fffbeb, #fefce8)';
          cardBorder = '#fde68a';
        } else if (type === 'danger') {
          bgAvatar = '#fee2e2';
          colorAvatar = '#b91c1c';
          cardBg = 'linear-gradient(135deg, #fef2f2, #fff1f2)';
          cardBorder = '#fecaca';
        }

        liveStudentCardArea.innerHTML = `
          <div class="student-success-card" style="background:${cardBg};border-color:${cardBorder}">
            <div class="avatar" style="width:48px;height:48px;font-size:20px;font-weight:700;background:${bgAvatar};color:${colorAvatar}">
              ${initial}
            </div>
            <div style="flex:1">
              <div style="font-weight:700;font-size:16px;color:#1e293b">${escapeHtml(siswa.nama)}</div>
              <div style="font-size:13px;color:#475569">
                NIS: ${escapeHtml(siswa.nis)} · Kelas: ${escapeHtml(siswa.kelas)} ${jurusanText}
              </div>
            </div>
            <div style="text-align:right">
              <span class="tag ${statusClass}" style="font-size:13px;padding:6px 14px">
                ${statusLabel}
              </span>
              <div style="font-size:12px;color:#64748b;margin-top:4px">Jam: ${jamText}</div>
            </div>
          </div>
        `;
      }

      // Update Top Stats dynamically
      function updateLiveStats(stats) {
        if (!stats) return;
        if (statTotal && stats.total !== undefined) statTotal.textContent = stats.total;
        if (statHadir && stats.hadir !== undefined) statHadir.textContent = stats.hadir;
        if (statTerlambat && stats.terlambat !== undefined) statTerlambat.textContent = stats.terlambat;
      }

      // Prepend newly scanned record to recent list dynamically
      function prependRecentItem(siswa, absensi) {
        if (!recentList) return;
        if (emptyRecentPlaceholder) {
          emptyRecentPlaceholder.style.display = 'none';
        }
        recentList.style.display = 'block';

        const initial = (siswa.nama || 'S').charAt(0).toUpperCase();
        const statusClass = (absensi.status || 'hadir').toLowerCase().replace(/\s+/g, '');
        const statusLabel = absensi.status ? absensi.status.charAt(0).toUpperCase() + absensi.status.slice(1) : 'Hadir';
        const jamText = absensi.jam_scan ? escapeHtml(absensi.jam_scan) : '-';

        const itemDiv = document.createElement('div');
        itemDiv.className = 'recent-item recent-item-enter';
        itemDiv.style.padding = '10px 0';
        itemDiv.innerHTML = `
          <div class="avatar" style="width:34px;height:34px;font-size:13px;font-weight:700">
            ${initial}
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              ${escapeHtml(siswa.nama)}
            </div>
            <div style="font-size:11px;color:var(--muted)">
              NIS: ${escapeHtml(siswa.nis)} · ${escapeHtml(siswa.kelas)}
            </div>
          </div>
          <div style="text-align:right">
            <span class="tag ${statusClass}" style="font-size:11px;padding:3px 8px">
              ${statusLabel}
            </span>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">${jamText}</div>
          </div>
        `;

        if (recentList.firstChild) {
          recentList.insertBefore(itemDiv, recentList.firstChild);
        } else {
          recentList.appendChild(itemDiv);
        }
      }

      function escapeHtml(str) {
        if (!str) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      // Core Barcode Processor via AJAX
      function processBarcode(token, source) {
        const cleanToken = (token || '').trim();
        if (!cleanToken) return;

        const now = Date.now();
        // Debounce / Cooldown check: prevent scanning same code in less than 2.5 seconds
        if (isProcessing) return;
        if (cleanToken === lastScannedToken && (now - lastScannedTime) < 2500) {
          return;
        }

        isProcessing = true;
        lastScannedToken = cleanToken;
        lastScannedTime = now;

        const cameraWrapper = document.getElementById('cameraWrapper');
        const cameraStatusBadge = document.getElementById('cameraStatusBadge');

        if (cameraStatusBadge) {
          cameraStatusBadge.innerHTML = `
            <span class="spinner-border spinner-border-sm text-info" style="width:10px;height:10px" role="status"></span>
            <span>Memproses barcode: <strong>${escapeHtml(cleanToken)}</strong>...</span>
          `;
        }

        const selectedTanggal = (tanggalInput ? tanggalInput.value : '') || new Date().toISOString().split('T')[0];
        const formData = new URLSearchParams();
        formData.append('ajax', '1');
        formData.append('barcode_code', cleanToken);
        formData.append('tanggal', selectedTanggal);

        fetch('absensi_barcode.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString()
          })
          .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
          })
          .then(data => {
            if (data.success) {
              // SUCCESS - Attendance Accepted
              playChime('success');
              if (cameraWrapper) {
                cameraWrapper.classList.add('scan-success');
                setTimeout(() => cameraWrapper.classList.remove('scan-success'), 1400);
              }
              showAlert('success', data.message);
              showStudentCard(data.siswa, data.absensi, 'success');
              updateLiveStats(data.stats);
              prependRecentItem(data.siswa, data.absensi);

              if (cameraStatusBadge) {
                cameraStatusBadge.innerHTML = `
                <span style="color:#22c55e;font-size:14px">✓</span>
                <span>Absensi Diterima: <strong>${escapeHtml(data.siswa.nama)}</strong> (${escapeHtml(data.absensi.status.toUpperCase())})</span>
              `;
              }

              // If continuous scan is unchecked, close camera
              const continuousCheck = document.getElementById('checkContinuous');
              if (continuousCheck && !continuousCheck.checked && typeof stopCameraScanner === 'function') {
                stopCameraScanner();
              }
            } else if (data.already_scanned) {
              // WARNING - Already scanned today
              playChime('warning');
              if (cameraWrapper) {
                cameraWrapper.classList.add('scan-warning');
                setTimeout(() => cameraWrapper.classList.remove('scan-warning'), 1400);
              }
              showAlert('warning', data.message);
              showStudentCard(data.siswa, data.absensi, 'warning');
              updateLiveStats(data.stats);

              if (cameraStatusBadge) {
                cameraStatusBadge.innerHTML = `
                <span style="color:#f59e0b;font-size:14px">⚠</span>
                <span>Sudah Absen Hari Ini: <strong>${escapeHtml(data.siswa.nama)}</strong></span>
              `;
              }
            } else {
              // ERROR - Invalid barcode or inactive student
              playChime('danger');
              if (cameraWrapper) {
                cameraWrapper.classList.add('scan-danger');
                setTimeout(() => cameraWrapper.classList.remove('scan-danger'), 1400);
              }
              showAlert('danger', data.message || 'Barcode tidak valid.');

              if (cameraStatusBadge) {
                cameraStatusBadge.innerHTML = `
                <span style="color:#ef4444;font-size:14px">✕</span>
                <span>Barcode tidak valid atau siswa non-aktif</span>
              `;
              }
            }
          })
          .catch(err => {
            console.error('Scan processing error:', err);
            playChime('danger');
            showAlert('danger', 'Gagal memproses absensi: ' + err.message);
            if (cameraStatusBadge) {
              cameraStatusBadge.innerHTML = `
              <span style="color:#ef4444">✕</span>
              <span>Terjadi kendala koneksi saat scan</span>
            `;
            }
          })
          .finally(() => {
            // Cooldown before next camera scan
            setTimeout(() => {
              isProcessing = false;
              if (cameraStatusBadge && html5QrcodeScanner && html5QrcodeScanner.isScanning) {
                cameraStatusBadge.innerHTML = `
                <span class="spinner-grow spinner-grow-sm text-success" style="width:8px;height:8px" role="status"></span>
                <span>Kamera aktif · Arahkan ke barcode siswa</span>
              `;
              }
            }, 2000);
          });
      }

      // Intercept Manual / Barcode Gun form submit with AJAX
      if (barcodeForm) {
        barcodeForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const token = barcodeInput ? barcodeInput.value : '';
          if (token.trim()) {
            processBarcode(token, 'manual');
            if (barcodeInput) {
              barcodeInput.value = '';
              barcodeInput.focus();
            }
          }
        });
      }

      // ==========================================
      // HTML5 CAMERA BARCODE & QR SCANNER ENGINE
      // ==========================================
      let html5QrcodeScanner = null;
      let availableCameras = [];
      let currentCameraIndex = 0;

      const btnStart = document.getElementById('btnStartCamera');
      const btnStop = document.getElementById('btnStopCamera');
      const btnSwitchCamera = document.getElementById('btnSwitchCamera');
      const cameraSelect = document.getElementById('cameraSelect');
      const cameraPlaceholder = document.getElementById('cameraPlaceholder');
      const cameraActiveBox = document.getElementById('cameraActiveBox');
      const cameraStatusBadge = document.getElementById('cameraStatusBadge');

      // Detect and populate available cameras
      function enumerateCameras() {
        if (typeof Html5Qrcode === 'undefined') return;
        Html5Qrcode.getCameras().then(devices => {
          if (devices && devices.length) {
            availableCameras = devices;
            if (cameraSelect) {
              cameraSelect.innerHTML = '';
              devices.forEach((dev, idx) => {
                const opt = document.createElement('option');
                opt.value = dev.id;
                let label = dev.label || `Kamera ${idx + 1}`;
                if (label.toLowerCase().includes('back') || label.toLowerCase().includes('rear') || label.toLowerCase().includes('belakang') || label.toLowerCase().includes('environment')) {
                  label = '📷 Kamera Belakang (' + label + ')';
                } else if (label.toLowerCase().includes('front') || label.toLowerCase().includes('depan') || label.toLowerCase().includes('user')) {
                  label = '🤳 Kamera Depan (' + label + ')';
                }
                opt.textContent = label;
                cameraSelect.appendChild(opt);
              });
            }
          }
        }).catch(e => {
          console.log('Camera enumeration notice:', e);
        });
      }

      enumerateCameras();

      // Start Camera Function
      function startCameraScanner(cameraIdOrConfig) {
        if (typeof Html5Qrcode === 'undefined') {
          alert('Library scanner kamera tidak dapat dimuat.');
          return;
        }

        if (html5QrcodeScanner && html5QrcodeScanner.isScanning) {
          html5QrcodeScanner.stop().then(() => {
            initiateCameraStart(cameraIdOrConfig);
          }).catch(() => {
            initiateCameraStart(cameraIdOrConfig);
          });
        } else {
          initiateCameraStart(cameraIdOrConfig);
        }
      }

      function initiateCameraStart(cameraIdOrConfig) {
        cameraPlaceholder.style.display = 'none';
        cameraActiveBox.style.display = 'block';

        if (cameraStatusBadge) {
          cameraStatusBadge.innerHTML = `
            <span class="spinner-grow spinner-grow-sm text-success" style="width:8px;height:8px" role="status"></span>
            <span>Kamera aktif · Arahkan ke barcode siswa</span>
          `;
        }

        // Configure scanner with full support for EAN-13, QR Codes, and 1D Barcodes
        const formatsToSupport = [
          Html5QrcodeSupportedFormats.EAN_13,
          Html5QrcodeSupportedFormats.QR_CODE,
          Html5QrcodeSupportedFormats.CODE_128,
          Html5QrcodeSupportedFormats.EAN_8,
          Html5QrcodeSupportedFormats.UPC_A,
          Html5QrcodeSupportedFormats.UPC_E,
          Html5QrcodeSupportedFormats.CODE_39,
          Html5QrcodeSupportedFormats.CODE_93,
          Html5QrcodeSupportedFormats.CODABAR,
          Html5QrcodeSupportedFormats.ITF
        ];

        html5QrcodeScanner = new Html5Qrcode("reader", {
          formatsToSupport: formatsToSupport,
          verbose: false
        });

        // Responsive scan box calculation optimized for EAN-13 & QR
        const qrboxFunction = function(viewfinderWidth, viewfinderHeight) {
          const qrboxWidth = Math.min(320, Math.floor(viewfinderWidth * 0.90));
          const qrboxHeight = Math.min(180, Math.floor(viewfinderHeight * 0.60));
          return {
            width: qrboxWidth,
            height: qrboxHeight
          };
        };

        const config = {
          fps: 20,
          qrbox: qrboxFunction,
          aspectRatio: 1.333333,
          experimentalFeatures: {
            useBarCodeDetectorIfSupported: true
          }
        };

        let targetCamera = cameraIdOrConfig || {
          facingMode: "environment"
        };
        if (cameraSelect && cameraSelect.value) {
          targetCamera = cameraSelect.value;
        }

        html5QrcodeScanner.start(
          targetCamera,
          config,
          function onScanSuccess(decodedText) {
            processBarcode(decodedText, 'camera');
          },
          function onScanError(errorMessage) {
            // ignore frame parse misses
          }
        ).then(() => {
          // Refresh camera select dropdown if needed
          if (!availableCameras.length) {
            enumerateCameras();
          }
        }).catch(function(err) {
          console.error('Camera start error:', err);
          cameraPlaceholder.style.display = 'block';
          cameraActiveBox.style.display = 'none';

          let helpMsg = 'Gagal mengakses kamera: ' + err;
          if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            helpMsg += '\n\nPerhatian: Browser membatasi akses kamera pada koneksi HTTP biasa. Silakan akses aplikasi melalui HTTPS atau localhost.';
          }
          alert(helpMsg);
        });
      }

      // Stop Camera Function
      window.stopCameraScanner = function() {
        if (html5QrcodeScanner) {
          html5QrcodeScanner.stop().then(function() {
            html5QrcodeScanner.clear();
            cameraPlaceholder.style.display = 'block';
            cameraActiveBox.style.display = 'none';
          }).catch(function() {
            cameraPlaceholder.style.display = 'block';
            cameraActiveBox.style.display = 'none';
          });
        } else {
          cameraPlaceholder.style.display = 'block';
          cameraActiveBox.style.display = 'none';
        }
      };

      if (btnStart) {
        btnStart.addEventListener('click', function() {
          startCameraScanner();
        });
      }

      if (btnStop) {
        btnStop.addEventListener('click', function() {
          stopCameraScanner();
        });
      }

      // Camera Select dropdown change
      if (cameraSelect) {
        cameraSelect.addEventListener('change', function() {
          if (this.value) {
            startCameraScanner(this.value);
          }
        });
      }

      // Switch / Flip Camera button
      if (btnSwitchCamera) {
        btnSwitchCamera.addEventListener('click', function() {
          if (availableCameras.length > 1) {
            currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
            const nextCam = availableCameras[currentCameraIndex];
            if (cameraSelect) cameraSelect.value = nextCam.id;
            startCameraScanner(nextCam.id);
          } else {
            // Fallback toggle environment / user
            startCameraScanner({
              facingMode: "user"
            });
          }
        });
      }

      // When switching between tab-scanner and tab-camera
      const scannerTabBtn = document.getElementById('scanner-tab');
      const cameraTabBtn = document.getElementById('camera-tab');

      if (scannerTabBtn) {
        scannerTabBtn.addEventListener('shown.bs.tab', function() {
          if (barcodeInput) {
            barcodeInput.focus();
            barcodeInput.select();
          }
        });
      }

      if (cameraTabBtn) {
        cameraTabBtn.addEventListener('shown.bs.tab', function() {
          // If camera is not yet active, automatically start camera for seamless user experience
          if (!html5QrcodeScanner || !html5QrcodeScanner.isScanning) {
            startCameraScanner();
          }
        });
      }
    });
  </script>
  <script src="assets/main.js?v=1.4"></script>
</body>

</html>