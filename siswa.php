<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    exit('Akses ditolak');
}

$role = $_SESSION['role'];

$search = trim($_GET['search'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');
$jurusanFilter = trim($_GET['jurusan'] ?? '');

$sql = "SELECT * FROM siswa WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (nis LIKE ? OR nama LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
if ($kelasFilter !== '') {
    $sql .= " AND kelas = ?";
    $params[] = $kelasFilter;
    $types .= 's';
}
if ($jurusanFilter !== '') {
    $sql .= " AND jurusan = ?";
    $params[] = $jurusanFilter;
    $types .= 's';
}

$sql .= " ORDER BY nama ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$siswaList = $result->fetch_all(MYSQLI_ASSOC);

$kelasList = $conn->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetch_all(MYSQLI_ASSOC);
$jurusanList = $conn->query("SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Data Siswa</title>
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
          <?php if ($role === 'admin'): ?>
            <a href="users.php">🔒 Pengguna</a>
            <a href="holidays_admin.php">📅 Kelola Libur</a>
          <?php endif; ?>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong><div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($role) ?></div></div>
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
        <div class="top-hero" style="padding:12px">
          <div>
            <div style="font-size:16px;font-weight:700">Data Siswa</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Kelola daftar siswa</div>
          </div>
          <div>
            <a href="siswa_form.php?mode=add" class="btn btn-primary">+ Tambah Siswa</a>
          </div>
        </div>

        <div style="padding:12px;border-radius:12px">
          <form method="get" class="row g-2 mb-3">
            <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Cari nama / NIS..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="col-md-2">
              <select name="kelas" class="form-select">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?><option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $kelasFilter === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <select name="jurusan" class="form-select">
                <option value="">Semua Jurusan</option>
                <?php foreach ($jurusanList as $j): ?><option value="<?= htmlspecialchars($j['jurusan']) ?>" <?= $jurusanFilter === $j['jurusan'] ? 'selected' : '' ?>><?= htmlspecialchars($j['jurusan']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="siswa.php" class="btn btn-outline-secondary w-100">Reset</a></div>
          </form>

          <div class="card">
            <div class="table-responsive">
              <table class="table table-borderless">
                <thead>
                  <tr>
                    <th>NIS</th><th>Nama</th><th>Kelas</th><th>Jurusan</th><th>JK</th><th>Status</th><th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($siswaList as $s): ?>
                    <tr>
                      <td><?= htmlspecialchars($s['nis']) ?></td>
                      <td><?= htmlspecialchars($s['nama']) ?></td>
                      <td><?= htmlspecialchars($s['kelas']) ?></td>
                      <td><?= htmlspecialchars($s['jurusan']) ?></td>
                      <td><?= htmlspecialchars($s['jenis_kelamin']) ?></td>
                      <td><span class="tag <?= $s['status'] === 'Aktif' ? 'hadir' : '' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                      <td>
                        <a href="siswa_form.php?mode=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="siswa_delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus siswa ini?')">Hapus</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div style="padding:8px;color:var(--muted)"><?= count($siswaList) ?> dari <?= count($siswaList) ?> siswa</div>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
