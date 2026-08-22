<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$mode = $_GET['mode'] ?? 'add';
$id = (int)($_GET['id'] ?? 0);
$message = '';

if ($mode === 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM siswa WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $siswa = $stmt->get_result()->fetch_assoc();
}

function generateEan13($nis, $id = 0) {
    $num = preg_replace('/\D/', '', $nis);
    if (empty($num)) {
        $num = str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }
    $base = substr('899' . str_pad($num, 9, '0', STR_PAD_LEFT), 0, 12);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$base[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    $check = (10 - ($sum % 10)) % 10;
    return $base . $check;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nis = trim($_POST['nis'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? 'L');
    $status = trim($_POST['status'] ?? 'aktif');
    $barcode_code = trim($_POST['barcode_code'] ?? '');

    if ($barcode_code === '') {
        $barcode_code = generateEan13($nis, $id);
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE siswa SET nis=?, nama=?, kelas=?, jurusan=?, jenis_kelamin=?, barcode_code=?, status=? WHERE id=?" );
        $stmt->bind_param('sssssssi', $nis, $nama, $kelas, $jurusan, $jenis_kelamin, $barcode_code, $status, $id);
        $stmt->execute();
        $message = 'Data siswa berhasil diubah.';
    } else {
        $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, jurusan, jenis_kelamin, barcode_code, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $nis, $nama, $kelas, $jurusan, $jenis_kelamin, $barcode_code, $status);
        $stmt->execute();
        $message = 'Data siswa berhasil ditambahkan.';
    }

    header('Location: siswa.php');
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Form Siswa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=1.4" rel="stylesheet">
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
          <a href="siswa.php" class="active">👥 Data Siswa</a>
          <a href="barcode.php">🔖 Barcode</a>
          <a href="absensi_barcode.php">📷 Scan Absensi</a>
          <a href="absensi_manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
          <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <a href="users.php">🔒 Pengguna</a>
            <a href="holidays_admin.php">📅 Kelola Libur</a>
          <?php endif; ?>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong><div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></div></div>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <!-- empty background, modal will overlay -->
      </div>
    </main>
  </div>

  <div id="siswaModal" style="display:flex;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(2,6,23,0.7);align-items:center;justify-content:center;z-index:9999">
    <div class="modal-card card" style="width:420px;padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div style="font-weight:700"><?= $mode === 'edit' ? 'Edit Siswa' : 'Tambah Siswa' ?></div>
        <a href="siswa.php" style="color:var(--muted)">✕</a>
      </div>
      <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="id" value="<?= isset($siswa['id']) ? $siswa['id'] : 0 ?>">
        <div style="margin-bottom:8px"><label>NIS</label><input type="text" name="nis" class="form-control" value="<?= htmlspecialchars($siswa['nis'] ?? '') ?>" required></div>
        <div style="margin-bottom:8px"><label>Nama Lengkap</label><input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($siswa['nama'] ?? '') ?>" required></div>
        <div style="display:flex;gap:8px;margin-bottom:8px">
          <div style="flex:1"><label>Kelas</label><input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($siswa['kelas'] ?? '') ?>" required></div>
          <div style="flex:1"><label>Jurusan</label><input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($siswa['jurusan'] ?? '') ?>" required></div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px">
          <div><label>Jenis Kelamin</label><div><label style="margin-right:8px"><input type="radio" name="jenis_kelamin" value="L" <?= ($siswa['jenis_kelamin'] ?? 'L') === 'L' ? 'checked' : '' ?>> Laki-laki</label><label><input type="radio" name="jenis_kelamin" value="P" <?= ($siswa['jenis_kelamin'] ?? 'L') === 'P' ? 'checked' : '' ?>> Perempuan</label></div></div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
          <a href="siswa.php" class="btn btn-outline-secondary">Batal</a>
          <button class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
