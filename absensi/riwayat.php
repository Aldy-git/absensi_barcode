<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = 'info';

// Handle pesan dari parameter URL (misal dari delete atau redirect)
if (!empty($_GET['msg'])) {
  if ($_GET['msg'] === 'deleted') {
    $message = 'Data absensi berhasil dihapus.';
    $messageType = 'success';
  } elseif ($_GET['msg'] === 'updated') {
    $message = 'Data absensi berhasil diperbarui.';
    $messageType = 'success';
  }
}
if (!empty($_GET['error'])) {
  if ($_GET['error'] === 'forbidden') {
    $message = 'Akses ditolak. Anda tidak memiliki izin untuk aksi tersebut.';
    $messageType = 'danger';
  } elseif ($_GET['error'] === 'csrf_invalid') {
    $message = 'Token keamanan tidak valid. Silakan coba lagi.';
    $messageType = 'danger';
  } elseif ($_GET['error'] === 'not_found') {
    $message = 'Data absensi tidak ditemukan.';
    $messageType = 'warning';
  } elseif ($_GET['error'] === 'id_invalid') {
    $message = 'ID absensi tidak valid.';
    $messageType = 'danger';
  } elseif ($_GET['error'] === 'duplicate') {
    $message = 'Absensi untuk siswa ini sudah ada pada tanggal tersebut.';
    $messageType = 'warning';
  }
}

// Proses Edit Absensi (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_absensi') {
  $token = $_POST['csrf_token'] ?? '';
  if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    $message = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    $messageType = 'danger';
  } else {
    $id = (int)($_POST['id'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $status = trim($_POST['status'] ?? 'hadir');

    $allowedStatus = ['hadir', 'terlambat', 'izin', 'sakit', 'alpa'];

    $holidayInfo = getHolidayInfo($tanggal, $conn);

    if ($id <= 0) {
      $message = 'ID absensi tidak valid.';
      $messageType = 'danger';
    } elseif ($tanggal === '') {
      $message = 'Tanggal absensi wajib diisi.';
      $messageType = 'danger';
    } elseif ($holidayInfo) {
      $message = 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' adalah ' . htmlspecialchars($holidayInfo['label']) . '. Hari libur tidak dianggap masuk dan absensi dikosongkan.';
      $messageType = 'warning';
    } elseif (!in_array($status, $allowedStatus, true)) {
      $message = 'Status absensi tidak valid.';
      $messageType = 'danger';
    } else {
      // Periksa keberadaan data absensi
      $check = $conn->prepare("SELECT id, siswa_id FROM absensi WHERE id = ?");
      $check->bind_param('i', $id);
      $check->execute();
      $existing = $check->get_result()->fetch_assoc();

      if (!$existing) {
        $message = 'Data absensi tidak ditemukan.';
        $messageType = 'danger';
      } else {
        $siswa_id = (int)$existing['siswa_id'];

        // Cek duplikasi absensi pada tanggal yang sama untuk siswa yang sama (kecuali record ini sendiri)
        $dupCheck = $conn->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ? AND id != ?");
        $dupCheck->bind_param('isi', $siswa_id, $tanggal, $id);
        $dupCheck->execute();
        if ($dupCheck->get_result()->num_rows > 0) {
          $message = 'Absensi untuk siswa ini sudah ada pada tanggal ' . htmlspecialchars($tanggal) . '.';
          $messageType = 'warning';
        } else {
          $update = $conn->prepare("UPDATE absensi SET tanggal = ?, status = ? WHERE id = ?");
          $update->bind_param('ssi', $tanggal, $status, $id);
          if ($update->execute()) {
            $message = 'Data absensi berhasil diperbarui.';
            $messageType = 'success';
          } else {
            $message = 'Gagal memperbarui data absensi: ' . $conn->error;
            $messageType = 'danger';
          }
        }
      }
    }
  }
}

$search = trim($_GET['search'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT a.*, s.nis, s.nama, s.kelas FROM absensi a JOIN siswa s ON a.siswa_id = s.id WHERE 1=1";
$params = [];
$types = '';
if ($search !== '') {
  $sql .= " AND (s.nis LIKE ? OR s.nama LIKE ?)";
  $term = "%$search%";
  $params[] = $term;
  $params[] = $term;
  $types .= 'ss';
}
if ($kelasFilter !== '') {
  $sql .= " AND s.kelas = ?";
  $params[] = $kelasFilter;
  $types .= 's';
}
if ($statusFilter !== '') {
  $sql .= " AND a.status = ?";
  $params[] = $statusFilter;
  $types .= 's';
}

$sql .= " ORDER BY a.tanggal DESC, a.id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$absensiList = $res->fetch_all(MYSQLI_ASSOC);

$kelasList = $conn->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetch_all(MYSQLI_ASSOC);
$statusList = $conn->query("SELECT DISTINCT status FROM absensi ORDER BY status")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Riwayat Absensi</title>
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
          <a href="barcode.php">🔖 Barcode</a>
          <a href="scan.php">📷 Scan Absensi</a>
          <a href="manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php" class="active">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="../users/index.php">🔒 Pengguna</a>
            <a href="../holidays/index.php">📅 Kelola Libur</a>
          <?php endif; ?>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px">
          <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="../logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Riwayat Absensi</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Daftar riwayat kehadiran siswa</div>
          </div>
          <div>
            <a href="manual.php" class="btn btn-primary">+ Absensi Manual</a>
          </div>
        </div>

        <div style="padding:12px;border-radius:12px">
          <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mb-3" role="alert">
              <?= htmlspecialchars($message) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="get" class="row g-2 mb-3">
            <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Cari nama / NIS..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="col-md-2">
              <select name="kelas" class="form-select">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?><option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $kelasFilter === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <?php foreach ($statusList as $s): ?><option value="<?= htmlspecialchars($s['status']) ?>" <?= $statusFilter === $s['status'] ? 'selected' : '' ?>><?= htmlspecialchars($s['status']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="riwayat.php" class="btn btn-outline-secondary w-100">Reset</a></div>
          </form>

          <div class="card">
            <div class="table-responsive">
              <table class="table table-borderless">
                <thead>
                  <tr>
                    <th>Tanggal</th>
                    <th>Jam</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($absensiList)): ?>
                    <tr>
                      <td colspan="7" class="text-center py-4 text-muted">Tidak ada data absensi ditemukan.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($absensiList as $a):
                      $jamValue = !empty($a['jam_scan']) ? substr($a['jam_scan'], 0, 8) : (!empty($a['created_at']) ? substr($a['created_at'], 11, 8) : '');
                      $jamDisplay = $jamValue ? substr($jamValue, 0, 5) : '-';
                    ?>
                      <tr>
                        <td><?= htmlspecialchars($a['tanggal']) ?></td>
                        <td><?= htmlspecialchars($jamDisplay) ?></td>
                        <td><?= htmlspecialchars($a['nis']) ?></td>
                        <td><?= htmlspecialchars($a['nama']) ?></td>
                        <td><?= htmlspecialchars($a['kelas']) ?></td>
                        <td><span class="tag <?= strtolower(str_replace(' ', '', $a['status'])) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                        <td>
                          <button type="button" class="btn btn-sm btn-outline-primary btn-edit-absensi"
                            data-id="<?= $a['id'] ?>"
                            data-nama="<?= htmlspecialchars($a['nama']) ?>"
                            data-nis="<?= htmlspecialchars($a['nis']) ?>"
                            data-kelas="<?= htmlspecialchars($a['kelas']) ?>"
                            data-tanggal="<?= htmlspecialchars($a['tanggal']) ?>"
                            data-status="<?= htmlspecialchars($a['status']) ?>">
                            Edit
                          </button>
                          <?php if ($_SESSION['role'] === 'admin'): ?>
                            <form action="delete.php" method="post" style="display:inline" onsubmit="return confirm('Hapus data absensi ini?')">
                              <input type="hidden" name="id" value="<?= $a['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                              <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div style="padding:8px;color:var(--muted)"><?= count($absensiList) ?> data · Halaman 1 dari 1</div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Modal Edit Absensi -->
  <div class="modal fade" id="modalEditAbsensi" tabindex="-1" aria-labelledby="modalEditAbsensiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;box-shadow:0 20px 40px rgba(0,0,0,0.15)">
        <div class="modal-header" style="background:#0f1724;color:#fff;padding:16px 20px">
          <div>
            <h6 class="modal-title mb-0" id="modalEditAbsensiLabel" style="font-weight:700">Edit Data Absensi</h6>
            <div style="font-size:12px;color:rgba(255,255,255,0.7)">Ubah detail kehadiran siswa</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" id="formEditAbsensi">
          <div class="modal-body" style="padding:20px">
            <input type="hidden" name="action" value="edit_absensi">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="id" id="edit_id" value="" required>

            <!-- Info Siswa -->
            <div class="p-3 mb-3" style="background:#f8fafc;border:1px solid rgba(15,23,42,0.08);border-radius:10px">
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Siswa</div>
              <div id="edit_nama" style="font-weight:700;font-size:15px;color:#0f172a;margin-top:2px">-</div>
              <div id="edit_meta" style="font-size:12px;color:#64748b">-</div>
            </div>

            <!-- Tanggal Absensi -->
            <div class="mb-3">
              <label class="form-label" style="font-weight:600;font-size:13px">Tanggal Absensi</label>
              <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
            </div>

            <!-- Status Kehadiran -->
            <div class="mb-2">
              <label class="form-label" style="font-weight:600;font-size:13px">Status Kehadiran</label>
              <div style="display:flex;gap:8px;margin-top:4px;align-items:center;flex-wrap:wrap">
                <label style="cursor:pointer">
                  <input type="radio" name="status" value="hadir" id="status_hadir" style="display:none">
                  <span class="tag hadir">Hadir</span>
                </label>
                <label style="cursor:pointer">
                  <input type="radio" name="status" value="terlambat" id="status_terlambat" style="display:none">
                  <span class="tag terlambat">Terlambat</span>
                </label>
                <label style="cursor:pointer">
                  <input type="radio" name="status" value="izin" id="status_izin" style="display:none">
                  <span class="tag izin">Izin</span>
                </label>
                <label style="cursor:pointer">
                  <input type="radio" name="status" value="sakit" id="status_sakit" style="display:none">
                  <span class="tag sakit">Sakit</span>
                </label>
                <label style="cursor:pointer">
                  <input type="radio" name="status" value="alpa" id="status_alpa" style="display:none">
                  <span class="tag alpa">Alpa</span>
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="padding:12px 20px;background:#f8fafc;border-top:1px solid rgba(15,23,42,0.06)">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary" style="font-weight:600">Simpan Perubahan</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modalEl = document.getElementById('modalEditAbsensi');
      const modal = new bootstrap.Modal(modalEl);
      const editButtons = document.querySelectorAll('.btn-edit-absensi');

      const editIdInput = document.getElementById('edit_id');
      const editNamaDisplay = document.getElementById('edit_nama');
      const editMetaDisplay = document.getElementById('edit_meta');
      const editTanggalInput = document.getElementById('edit_tanggal');

      // Fungsi untuk refresh highlight status radio badge
      function updateStatusSelection() {
        modalEl.querySelectorAll('.tag').forEach(function(tag) {
          tag.classList.remove('selected');
        });
        const checked = modalEl.querySelector('input[name="status"]:checked');
        if (checked) {
          const tag = checked.closest('label')?.querySelector('.tag');
          if (tag) tag.classList.add('selected');
        }
      }

      modalEl.querySelectorAll('input[name="status"]').forEach(function(radio) {
        radio.addEventListener('change', updateStatusSelection);
      });

      // Handle klik tombol Edit pada baris tabel
      editButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const nama = this.getAttribute('data-nama');
          const nis = this.getAttribute('data-nis');
          const kelas = this.getAttribute('data-kelas');
          const tanggal = this.getAttribute('data-tanggal');
          const status = this.getAttribute('data-status');

          editIdInput.value = id;
          editNamaDisplay.textContent = nama;
          editMetaDisplay.textContent = 'NIS: ' + nis + (kelas ? ' · Kelas: ' + kelas : '');
          editTanggalInput.value = tanggal;

          // Set radio status
          const targetRadio = modalEl.querySelector('input[name="status"][value="' + status + '"]');
          if (targetRadio) {
            targetRadio.checked = true;
          } else {
            const defaultRadio = modalEl.querySelector('input[name="status"][value="hadir"]');
            if (defaultRadio) defaultRadio.checked = true;
          }

          updateStatusSelection();
          modal.show();
        });
      });
    });
  </script>
  <script src="../assets/main.js?v=1.4"></script>
</body>

</html>