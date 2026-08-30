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
  <link href="../assets/style.css?v=2.1" rel="stylesheet">
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
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">+ Tambah Pengguna</button>
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
                          <button type="button" class="btn btn-sm btn-danger btn-delete-user" data-id="<?= $u['id'] ?>" data-username="<?= htmlspecialchars($u['username']) ?>">
                            Hapus
                          </button>
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

  <!-- Modal Tambah Pengguna (Bootstrap 5 Animated Modal) -->
  <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
      <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
        <div class="modal-header" style="background:#0f172a; color:#fff; padding: 14px 18px;">
          <h6 class="modal-title mb-0" id="userModalLabel" style="font-weight: 700; font-size: 15px;">➕ Tambah Pengguna</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post">
          <div class="modal-body" style="padding: 18px;">
            <div class="mb-2">
              <label class="form-label" style="font-weight: 600; font-size: 13px;">Nama Lengkap</label>
              <input type="text" name="name" class="form-control" placeholder="Nama lengkap">
            </div>
            <div class="mb-2">
              <label class="form-label" style="font-weight: 600; font-size: 13px;">Username</label>
              <input type="text" name="username" class="form-control" placeholder="username" required>
            </div>
            <div class="mb-2">
              <label class="form-label" style="font-weight: 600; font-size: 13px;">Password</label>
              <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="mb-2">
              <label class="form-label" style="font-weight: 600; font-size: 13px;">Role</label>
              <select name="role" class="form-select">
                <option value="guru">Guru / Petugas</option>
                <option value="admin">Administrator</option>
              </select>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end gap-2 p-3" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary btn-sm px-4" style="font-weight: 600;">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Konfirmasi Hapus Pengguna (Simpel & Elegan) -->
  <div class="modal fade" id="modalDeleteUser" tabindex="-1" aria-labelledby="modalDeleteUserLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
      <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
        <div class="modal-body text-center p-4">
          <div style="width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px;">
            🗑️
          </div>
          <h6 style="font-weight: 700; font-size: 16px; color: #1e293b; margin-bottom: 6px;">Hapus Pengguna</h6>
          <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">
            Apakah Anda yakin ingin menghapus akun pengguna <strong id="deleteUsernameText" class="text-dark">-</strong>? Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="modal-footer d-flex justify-content-center gap-2 p-3" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
          <button type="button" class="btn btn-light border px-4 btn-sm" data-bs-dismiss="modal" style="font-weight: 600;">Batal</button>
          <a href="#" id="confirmDeleteUserBtn" class="btn btn-danger px-4 btn-sm" style="font-weight: 600;">Ya, Hapus</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/main.js?v=1.4"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modalDeleteEl = document.getElementById('modalDeleteUser');
      if (modalDeleteEl) {
        const modalDelete = new bootstrap.Modal(modalDeleteEl);
        const deleteButtons = document.querySelectorAll('.btn-delete-user');
        const deleteUsernameText = document.getElementById('deleteUsernameText');
        const confirmDeleteUserBtn = document.getElementById('confirmDeleteUserBtn');

        deleteButtons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');

            deleteUsernameText.textContent = username;
            confirmDeleteUserBtn.href = 'delete.php?id=' + encodeURIComponent(id);

            modalDelete.show();
          });
        });
      }
    });
  </script>
</body>

</html>