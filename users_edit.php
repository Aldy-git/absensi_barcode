<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$message = '';
$messageType = 'danger';

if ($id <= 0) {
    header('Location: users.php?error=not_found');
    exit;
}

// Ambil data user
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php?error=not_found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'guru';

    if ($username === '') {
        $message = 'Username tidak boleh kosong.';
    } else {
        // Cek username unik untuk user lain
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->bind_param('si', $username, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'Username sudah digunakan oleh akun lain.';
        } else {
            // Mencegah admin mengubah role akunnya sendiri menjadi guru jika hanya dia adminnya
            if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $message = 'Anda tidak dapat mengubah role akun Anda sendiri menjadi bukan admin.';
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                    $upd->bind_param('sssi', $username, $hash, $role, $id);
                } else {
                    $upd = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $upd->bind_param('ssi', $username, $role, $id);
                }

                if ($upd->execute()) {
                    header('Location: users.php?msg=updated');
                    exit;
                } else {
                    $message = 'Gagal menyimpan data: ' . $conn->error;
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Pengguna</title>
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
          <a href="siswa.php">👥 Data Siswa</a>
          <a href="barcode.php">🔖 Barcode</a>
          <a href="absensi_barcode.php">📷 Scan Absensi</a>
          <a href="absensi_manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
          <a href="users.php" class="active">🔒 Pengguna</a>
          <a href="holidays_admin.php">📅 Kelola Libur</a>
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
            <span class="user-role-badge"><?= htmlspecialchars($role ?? $_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero" style="padding:16px 20px">
          <div>
            <div style="font-size:16px;font-weight:700">Edit Pengguna</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Perbarui data akun pengguna</div>
          </div>
          <a href="users.php" class="btn btn-outline-light btn-sm">← Kembali</a>
        </div>

        <div style="padding:12px;display:flex;justify-content:center">
          <div class="card" style="width:480px;border-radius:12px">
            <div style="padding:18px">
              <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                  <?= htmlspecialchars($message) ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="post">
                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Username</label>
                  <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Password Baru</label>
                  <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                  <div class="form-text" style="font-size:12px">Biarkan kosong jika tidak ingin mengubah password.</div>
                </div>
                <div class="mb-4">
                  <label class="form-label" style="font-weight:600">Role</label>
                  <select name="role" class="form-select">
                    <option value="guru" <?= $user['role'] === 'guru' ? 'selected' : '' ?>>Guru</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                  <a href="users.php" class="btn btn-outline-secondary">Batal</a>
                  <button type="submit" class="btn btn-primary" style="font-weight:600">Simpan Perubahan</button>
                </div>
              </form>
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
