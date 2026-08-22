<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
// handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $tanggal = $_POST['tanggal'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        if ($tanggal && $nama) {
        // store admin-added holidays as 'school' type (no custom type)
        $stmt = $conn->prepare("INSERT INTO holidays (tanggal, nama, type, created_by) VALUES (?, ?, 'school', ?)");
            $uid = $_SESSION['user_id'];
            $stmt->bind_param('ssi', $tanggal, $nama, $uid);
            if ($stmt->execute()) $message = 'Libur ditambahkan.'; else $message = 'Gagal menambahkan.';
        } else $message = 'Tanggal dan nama diperlukan.';
    }
    if (isset($_POST['delete'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id>0) {
            $stmt = $conn->prepare("DELETE FROM holidays WHERE id = ? AND type <> 'national'");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) $message = 'Libur dihapus.'; else $message = 'Gagal menghapus.';
        }
    }
    }

$holidays = $conn->query("SELECT id, tanggal, nama, type, created_at FROM holidays ORDER BY tanggal DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kelola Libur</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=1.4" rel="stylesheet">
  <style>.small-table td{vertical-align:middle}</style>
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
          <a href="absensi_barcode.php">📷 Scan Absensi</a>
          <a href="absensi_manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
          <a href="users.php">🔒 Pengguna</a>
          <a href="holidays_admin.php" class="active">📅 Kelola Libur</a>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong><div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($_SESSION['role']) ?></div></div>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Admin') ?></span>
          </div>
          <a href="logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Kelola Hari Libur Sekolah</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Tambah, edit, atau hapus hari libur khusus sekolah</div>
          </div>
        </div>

        <?php if ($message): ?><div class="alert alert-info" style="margin-top:12px"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <!-- modal-like centered management panel -->
        <div id="holidayPanel" style="display:flex;align-items:center;justify-content:center;min-height:60vh;padding:12px 0">
          <div style="width:560px;max-width:100%;background:#f3f3f3;border-radius:12px;overflow:hidden;box-shadow:0 20px 50px rgba(2,6,23,0.6)">
            <div style="background:#111316;color:#fff;padding:14px 16px;border-top-left-radius:12px;border-top-right-radius:12px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <div style="font-weight:700">Kelola Hari Libur Sekolah</div>
                <div style="font-size:12px;color:rgba(255,255,255,0.6)">Tambah, edit, atau hapus hari libur khusus sekolah</div>
              </div>
              <button onclick="window.location='dashboard.php'" style="background:transparent;border:0;color:#cbd5db;font-size:18px">✕</button>
            </div>

            <div style="padding:12px;background:#fff">
              <form method="post" style="display:flex;gap:8px;align-items:center">
                <div style="flex:0 0 140px"><input type="date" name="tanggal" class="form-control" placeholder="dd/mm/yyyy"></div>
                <div style="flex:1"><input name="nama" class="form-control" placeholder="Nama hari libur..."></div>
                <div><button class="btn" name="add" style="background:#8b5cf6;color:#fff;padding:8px 14px;border-radius:8px">+ Tambah</button></div>
              </form>
            </div>

            <div style="padding:28px;text-align:center;background:#fff;min-height:180px;border-top:1px solid rgba(2,6,23,0.04)">
              <?php
                $schoolH = array_filter($holidays, function($x){ return $x['type'] !== 'national'; });
              ?>
              <?php if (empty($schoolH)): ?>
                <div style="color:#9ca3af;margin-bottom:12px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="14" rx="2" stroke="#c7c7c7" stroke-width="1.2"/><path d="M7 9H17" stroke="#c7c7c7" stroke-width="1.2" stroke-linecap="round"/></svg></div>
                <div style="font-weight:700">Belum ada hari libur sekolah</div>
                <div style="color:#9ca3af;font-size:13px;margin-top:6px">Tambahkan menggunakan form di atas</div>
              <?php else: ?>
                <div style="text-align:left">
                  <?php foreach($schoolH as $h): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(2,6,23,0.04)">
                      <div style="display:flex;gap:12px;align-items:center"><div style="width:44px;height:44;border-radius:8px;background:#eef2ff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#2563eb"><?= date('j',strtotime($h['tanggal'])) ?></div>
                        <div><div style="font-weight:700"><?= htmlspecialchars($h['nama']) ?></div><div style="font-size:12px;color:#9ca3af"><?= date('l, j M Y',strtotime($h['tanggal'])) ?></div></div>
                      </div>
                      <div>
                        <form method="post" style="display:inline">
                          <input type="hidden" name="id" value="<?= $h['id'] ?>">
                          <button name="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus hari libur?')">Hapus</button>
                        </form>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div style="padding:12px;background:#fff;border-top:1px solid rgba(2,6,23,0.04);display:flex;justify-content:space-between;align-items:center">
              <div style="color:#9ca3af"><?= count($schoolH) ?> hari libur sekolah tersimpan</div>
              <div><button class="btn btn-dark" onclick="window.location='dashboard.php'">Selesai</button></div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
