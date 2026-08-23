<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit;
}

$message = '';
$messageType = 'success';

// Handle pesan dari parameter URL
if (!empty($_GET['msg'])) {
  if ($_GET['msg'] === 'deleted') {
    $message = 'Pengguna berhasil dihapus.';
    $messageType = 'success';
  } elseif ($_GET['msg'] === 'updated') {
    $message = 'Pengguna berhasil diperbarui.';
    $messageType = 'success';
  }
}
if (!empty($_GET['error'])) {
  if ($_GET['error'] === 'admin_protected') {
    $message = 'Akun dengan role Admin tidak dapat dihapus demi keamanan sistem.';
    $messageType = 'danger';
  } elseif ($_GET['error'] === 'self_delete') {
    $message = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan.';
    $messageType = 'warning';
  } elseif ($_GET['error'] === 'not_found') {
    $message = 'Data pengguna tidak ditemukan.';
    $messageType = 'warning';
  } elseif ($_GET['error'] === 'failed') {
    $message = 'Gagal menghapus pengguna.';
    $messageType = 'danger';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $role = $_POST['role'] ?? 'guru';

  if ($username !== '' && $password !== '') {
    // Cek duplikasi username
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param('s', $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $message = 'Username sudah digunakan, silakan pilih username lain.';
      $messageType = 'danger';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
      $stmt->bind_param('sss', $username, $hash, $role);
      if ($stmt->execute()) {
        $message = 'User berhasil ditambahkan.';
        $messageType = 'success';
      } else {
        $message = 'Gagal menambahkan user: ' . $conn->error;
        $messageType = 'danger';
      }
    }
  } else {
    $message = 'Username dan password wajib diisi.';
    $messageType = 'danger';
  }
}

$users = $conn->query("SELECT * FROM users ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css?v=1.4" rel="stylesheet">
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
          <a href="../dashboard.php">🏠 Dashboard</a>
          <a href="../siswa/index.php">👥 Data Siswa</a>
          <a href="../absensi/barcode.php">🔖 Barcode</a>
          <a href="../absensi/scan.php">📷 Scan Absensi</a>
          <a href="../absensi/manual.php">✍️ Absensi Manual</a>
          <a href="../absensi/riwayat.php">📜 Riwayat</a>
          <a href="../absensi/laporan.php">📊 Laporan</a>
          <a href="index.php" class="active">🔒 Pengguna</a>
          <a href="../holidays/index.php">📅 Kelola Libur</a>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
          <div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($_SESSION['role']) ?></div>
        </div>
        <a href="../logout.php" style="display:inline-block;padding:8px 12px;background:#ef4444;color:#fff;border-radius:8px;text-decoration:none">Keluar</a>
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
          <a href="../logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Pengguna</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Kelola akun pengguna</div>
          </div>
          <div>
            <button class="btn btn-primary" onclick="document.getElementById('userModal').style.display='flex'">+ Tambah Pengguna</button>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert" style="margin-top:12px">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div style="padding:12px">
          <div class="card">
            <div class="table-responsive">
              <table class="table table-borderless">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u):
                    $isAdmin = ($u['role'] === 'admin');
                    $isSelf = ((int)$u['id'] === (int)$_SESSION['user_id']);
                  ?>
                    <tr>
                      <td><?= htmlspecialchars($u['username']) ?></td>
                      <td>
                        <span class="tag" style="background:<?= $isAdmin ? '#f3e8ff' : '#e0f2fe' ?>;color:<?= $isAdmin ? '#6b21a8' : '#0369a1' ?>;padding:4px 10px;border-radius:999px;font-weight:600;font-size:12px">
                          <?= htmlspecialchars(ucfirst($u['role'])) ?>
                        </span>
                      </td>
                      <td>
                        <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <?php if ($isAdmin): ?>
                          <button class="btn btn-sm btn-secondary opacity-50" disabled title="<?= $isSelf ? 'Tidak dapat menghapus akun sendiri' : 'Akun Admin dilindungi dan tidak dapat dihapus' ?>" style="cursor:not-allowed">
                            Hapus
                          </button>
                        <?php else: ?>
                          <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna <?= htmlspecialchars($u['username']) ?>?')">Hapus</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Tambah Pengguna -->
  <div id="userModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(2,6,23,0.6);align-items:center;justify-content:center;z-index:9999">
    <div style="background:#fff;padding:20px;border-radius:12px;width:340px">
      <h6>Tambah Pengguna <a href="#" onclick="document.getElementById('userModal').style.display='none'" style="float:right;color:#94a3b8">×</a></h6>
      <form method="post">
        <div style="margin-top:10px"><label>Nama</label><input type="text" name="name" class="form-control" placeholder="Nama lengkap"></div>
        <div style="margin-top:10px"><label>Username</label><input type="text" name="username" class="form-control" placeholder="username" required></div>
        <div style="margin-top:10px"><label>Password</label><input type="password" name="password" class="form-control" placeholder="Password" required></div>
        <div style="margin-top:10px"><label>Role</label><select name="role" class="form-select">
            <option value="guru">Guru</option>
            <option value="admin">Admin</option>
          </select></div>
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:space-between">
          <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('userModal').style.display='none'">Batal</button>
          <button class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/main.js?v=1.4"></script>
</body>

</html>