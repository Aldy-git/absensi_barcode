<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
  exit('Akses ditolak');
}

$role = $_SESSION['role'];

$message = '';
$messageType = 'info';

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

// Handle Tambah / Edit Siswa Satuan (Via Modal Pop-Up)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_siswa') {
  $id = (int)($_POST['id'] ?? 0);
  $nis = trim($_POST['nis'] ?? '');
  $nama = trim($_POST['nama'] ?? '');
  $kelas = trim($_POST['kelas'] ?? '');
  $jurusan = trim($_POST['jurusan'] ?? '');
  $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? 'L');
  $shift = trim($_POST['shift'] ?? 'pagi');
  if ($shift !== 'siang') $shift = 'pagi';
  $status = trim($_POST['status'] ?? 'aktif');

  if ($nis === '' || $nama === '' || $kelas === '' || $jurusan === '') {
    $message = 'Mohon lengkapi seluruh kolom yang bertanda bintang / wajib diisi.';
    $messageType = 'danger';
  } else {
    // Generate barcode otomatis standar EAN-13 berbasis NIS
    $barcode_code = generateEan13($nis, $id);

    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE siswa SET nis=?, nama=?, kelas=?, jurusan=?, jenis_kelamin=?, shift=?, barcode_code=?, status=? WHERE id=?");
      $stmt->bind_param('ssssssssi', $nis, $nama, $kelas, $jurusan, $jenis_kelamin, $shift, $barcode_code, $status, $id);
      if ($stmt->execute()) {
        $message = "Data siswa <strong>" . htmlspecialchars($nama) . "</strong> berhasil diperbarui.";
        $messageType = 'success';
      } else {
        $message = 'Gagal memperbarui data siswa: ' . $conn->error;
        $messageType = 'danger';
      }
    } else {
      // Cek apakah NIS sudah ada
      $checkNis = $conn->prepare("SELECT id FROM siswa WHERE nis = ? LIMIT 1");
      $checkNis->bind_param('s', $nis);
      $checkNis->execute();
      if ($checkNis->get_result()->num_rows > 0) {
        $message = "NIS <strong>" . htmlspecialchars($nis) . "</strong> sudah terdaftar di sistem. Gunakan NIS lain.";
        $messageType = 'danger';
      } else {
        $stmt = $conn->prepare("INSERT INTO siswa (nis, nama, kelas, jurusan, jenis_kelamin, shift, barcode_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssss', $nis, $nama, $kelas, $jurusan, $jenis_kelamin, $shift, $barcode_code, $status);
        if ($stmt->execute()) {
          $message = "Data siswa <strong>" . htmlspecialchars($nama) . "</strong> berhasil ditambahkan.";
          $messageType = 'success';
        } else {
          $message = 'Gagal menambahkan data siswa: ' . $conn->error;
          $messageType = 'danger';
        }
      }
    }
  }
}

// Handle Bulk Action (Edit Massal / Hapus Massal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
  $selectedIds = $_POST['selected_ids'] ?? [];
  $action = $_POST['bulk_action'] ?? '';

  if (empty($selectedIds) || !is_array($selectedIds)) {
    $message = 'Silakan centang/pilih setidaknya satu siswa terlebih dahulu.';
    $messageType = 'warning';
  } else {
    $cleanIds = array_filter(array_map('intval', $selectedIds), function ($v) {
      return $v > 0;
    });
    if (empty($cleanIds)) {
      $message = 'Data siswa yang dipilih tidak valid.';
      $messageType = 'danger';
    } else {
      $idListStr = implode(',', $cleanIds);
      $totalCount = count($cleanIds);

      if ($action === 'bulk_delete') {
        $del = $conn->query("DELETE FROM siswa WHERE id IN ($idListStr)");
        if ($del) {
          $message = "Berhasil menghapus <strong>$totalCount siswa</strong> terpilih.";
          $messageType = 'success';
        } else {
          $message = 'Gagal menghapus data siswa: ' . $conn->error;
          $messageType = 'danger';
        }
      } elseif ($action === 'bulk_edit') {
        $newShift = trim($_POST['bulk_shift'] ?? '');
        $newKelas = trim($_POST['bulk_kelas'] ?? '');
        $newJurusan = trim($_POST['bulk_jurusan'] ?? '');
        $newStatus = trim($_POST['bulk_status'] ?? '');

        $updates = [];
        $updateTypes = '';
        $updateVals = [];

        if ($newShift === 'pagi' || $newShift === 'siang') {
          $updates[] = "shift = ?";
          $updateTypes .= 's';
          $updateVals[] = $newShift;
        }
        if ($newKelas !== '') {
          $updates[] = "kelas = ?";
          $updateTypes .= 's';
          $updateVals[] = $newKelas;
        }
        if ($newJurusan !== '') {
          $updates[] = "jurusan = ?";
          $updateTypes .= 's';
          $updateVals[] = $newJurusan;
        }
        if ($newStatus === 'aktif' || $newStatus === 'nonaktif') {
          $updates[] = "status = ?";
          $updateTypes .= 's';
          $updateVals[] = $newStatus;
        }

        if (empty($updates)) {
          $message = 'Tidak ada perubahan data yang dipilih untuk diterapkan.';
          $messageType = 'warning';
        } else {
          $sqlUpd = "UPDATE siswa SET " . implode(', ', $updates) . " WHERE id IN ($idListStr)";
          $stmt = $conn->prepare($sqlUpd);
          if (!empty($updateTypes)) {
            $stmt->bind_param($updateTypes, ...$updateVals);
          }
          if ($stmt->execute()) {
            $message = "Berhasil menerapkan perubahan pada <strong>$totalCount siswa</strong> terpilih.";
            $messageType = 'success';
          } else {
            $message = 'Gagal memperbarui data siswa: ' . $conn->error;
            $messageType = 'danger';
          }
        }
      }
    }
  }
}

$search = trim($_GET['search'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');
$jurusanFilter = trim($_GET['jurusan'] ?? '');
$shiftFilter = trim($_GET['shift'] ?? '');

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
if ($shiftFilter !== '') {
  $sql .= " AND shift = ?";
  $params[] = $shiftFilter;
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
  <link href="../assets/style.css?v=1.4" rel="stylesheet">
  <style>
    /* ========================================================
       ANIMASI MODAL POP-UP (MUNCUL & KELUAR - SMOOTH & SATU KALI)
       ======================================================== */
    
    /* State Awal & Saat Menutup (Exit Animation) */
    .modal.fade .modal-dialog {
      opacity: 0;
      transform: scale(0.85) translateY(-20px);
      transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.2s ease !important;
    }

    /* State Saat Terbuka / Muncul (Entrance Animation) */
    .modal.fade.show .modal-dialog {
      opacity: 1;
      transform: scale(1) translateY(0);
      transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.24s ease !important;
    }

    /* Transisi Backdrop Halus */
    .modal-backdrop.fade {
      opacity: 0;
      transition: opacity 0.22s ease !important;
    }

    .modal-backdrop.fade.show {
      opacity: 0.55 !important;
    }

    .shift-option-card:hover {
      background: #f1f5f9 !important;
      border-color: #94a3b8 !important;
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
          <a href="../dashboard.php">🏠 Dashboard</a>
          <a href="index.php" class="active">👥 Data Siswa</a>
          <a href="../absensi/barcode.php">🔖 Barcode</a>
          <a href="../absensi/scan.php">📷 Scan Absensi</a>
          <a href="../absensi/manual.php">✍️ Absensi Manual</a>
          <a href="../absensi/riwayat.php">📜 Riwayat</a>
          <a href="../absensi/laporan.php">📊 Laporan</a>
          <?php if ($role === 'admin'): ?>
            <a href="../users/index.php">🔒 Pengguna</a>
            <a href="../holidays/index.php">📅 Kelola Libur</a>
          <?php endif; ?>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
          <div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($role) ?></div>
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
        <div class="top-hero" style="padding:12px">
          <div>
            <div style="font-size:16px;font-weight:700">Data Siswa</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Kelola daftar siswa dan pengaturan shift rombel</div>
          </div>
          <div>
            <button type="button" class="btn btn-primary" id="btnOpenAddSiswa" style="font-weight:600;box-shadow:0 4px 12px rgba(37,99,235,0.25)">
              ➕ Tambah Siswa
            </button>
          </div>
        </div>

        <?php if ($message): ?>
          <div class="alert alert-<?= $messageType ?> alert-dismissible fade show mt-3 mb-1" role="alert" style="border-radius:10px">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div style="padding:12px;border-radius:12px">
          <form method="get" class="row g-2 mb-3">
            <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Cari nama / NIS..." value="<?= htmlspecialchars($search) ?>"></div>
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
            <div class="col-md-2">
              <select name="shift" class="form-select">
                <option value="">Semua Shift</option>
                <option value="pagi" <?= $shiftFilter === 'pagi' ? 'selected' : '' ?>>🌅 Shift Pagi</option>
                <option value="siang" <?= $shiftFilter === 'siang' ? 'selected' : '' ?>>☀️ Shift Siang</option>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Filter</button></div>
            <div class="col-md-1"><a href="index.php" class="btn btn-outline-secondary w-100">Reset</a></div>
          </form>

          <div class="card">
            <!-- Floating / Top Bulk Action Bar -->
            <div id="bulkActionBar" class="p-3 mb-2" style="display:none;background:linear-gradient(135deg, #eff6ff, #f0fdf4);border:1px solid #bfdbfe;border-radius:10px;animation:fadeInBar 0.25s ease">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-primary px-3 py-2" id="selectedCountBadge" style="font-size:13px;font-weight:700">0 Siswa Dipilih</span>
                  <span style="font-size:13px;color:#334155;font-weight:600">Aksi Massal:</span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <button type="button" class="btn btn-sm btn-primary px-3" id="btnOpenBulkEdit" style="font-weight:700;box-shadow:0 2px 6px rgba(37,99,235,0.25)">
                    ✏️ Edit Terpilih
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-danger px-3" id="btnOpenBulkDelete" style="font-weight:600">
                    🗑️ Hapus Terpilih
                  </button>
                  <button type="button" class="btn btn-sm btn-light border px-2" id="btnDeselectAll">
                    ✕ Batal Pilih
                  </button>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:40px;text-align:center">
                      <input type="checkbox" id="selectAllCheckbox" class="form-check-input" style="cursor:pointer" title="Pilih Semua di Halaman Ini">
                    </th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Shift</th>
                    <th>JK</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($siswaList)): ?>
                    <tr>
                      <td colspan="9" class="text-center py-4 text-muted">Tidak ada data siswa ditemukan.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($siswaList as $s): ?>
                      <tr class="student-row" id="row-<?= $s['id'] ?>">
                        <td style="text-align:center">
                          <input type="checkbox" name="selected_ids[]" value="<?= $s['id'] ?>" class="form-check-input student-checkbox" style="cursor:pointer">
                        </td>
                        <td><?= htmlspecialchars($s['nis']) ?></td>
                        <td><strong><?= htmlspecialchars($s['nama']) ?></strong></td>
                        <td><?= htmlspecialchars($s['kelas']) ?></td>
                        <td><?= htmlspecialchars($s['jurusan']) ?></td>
                        <td>
                          <?php if (($s['shift'] ?? 'pagi') === 'siang'): ?>
                            <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:600;border:1px solid #fde68a">☀️ Siang</span>
                          <?php else: ?>
                            <span class="badge" style="background:#e0f2fe;color:#0369a1;font-weight:600;border:1px solid #bae6fd">🌅 Pagi</span>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['jenis_kelamin']) ?></td>
                        <td><span class="tag <?= strtolower($s['status']) === 'aktif' ? 'hadir' : '' ?>"><?= htmlspecialchars(ucfirst($s['status'])) ?></span></td>
                        <td>
                          <button type="button" class="btn btn-sm btn-primary btn-edit-siswa"
                            data-id="<?= $s['id'] ?>"
                            data-nis="<?= htmlspecialchars($s['nis']) ?>"
                            data-nama="<?= htmlspecialchars($s['nama']) ?>"
                            data-kelas="<?= htmlspecialchars($s['kelas']) ?>"
                            data-jurusan="<?= htmlspecialchars($s['jurusan']) ?>"
                            data-jk="<?= htmlspecialchars($s['jenis_kelamin']) ?>"
                            data-shift="<?= htmlspecialchars($s['shift'] ?? 'pagi') ?>"
                            data-status="<?= htmlspecialchars($s['status'] ?? 'aktif') ?>">
                            Edit
                          </button>
                          <button type="button" class="btn btn-sm btn-danger btn-delete-single-siswa"
                            data-id="<?= $s['id'] ?>"
                            data-nama="<?= htmlspecialchars($s['nama']) ?>"
                            data-nis="<?= htmlspecialchars($s['nis']) ?>">
                            Hapus
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div style="padding:10px 14px;color:var(--muted);font-size:13px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
              <div><?= count($siswaList) ?> siswa terdaftar</div>
              <div>💡 Centang kotak untuk memilih siswa lalu klik <strong>Edit Terpilih</strong> untuk mengubah shift/data massal</div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
  <!-- Modal Pop-Up Tambah / Edit Siswa Satuan (Dengan Animasi Muncul & Keluar) -->
  <div class="modal fade" id="modalSiswaForm" tabindex="-1" aria-labelledby="modalSiswaFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 580px;">
      <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 25px 60px rgba(15, 23, 42, 0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.08);">
          <div>
            <h6 class="modal-title mb-1" id="modalSiswaFormLabel" style="font-weight: 700; font-size: 17px; display: flex; align-items: center; gap: 8px;">
              <span id="modalSiswaIcon">➕</span> <span id="modalSiswaTitle">Tambah Data Siswa</span>
            </h6>
            <div id="modalSiswaSub" style="font-size: 12px; color: rgba(255,255,255,0.7);">Lengkapi formulir untuk mendaftarkan siswa baru ke sistem</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" id="formModalSiswa">
          <input type="hidden" name="action" value="save_siswa">
          <input type="hidden" name="id" id="siswaFormId" value="0">

          <div class="modal-body" style="padding: 22px 24px;">
            <!-- NIS & Nama -->
            <div class="row g-3 mb-3">
              <div class="col-md-5">
                <label class="form-label" style="font-weight: 600; font-size: 13px;">NIS <span class="text-danger">*</span></label>
                <input type="text" name="nis" id="siswaFormNis" class="form-control" placeholder="Contoh: 10245" required>
              </div>
              <div class="col-md-7">
                <label class="form-label" style="font-weight: 600; font-size: 13px;">Nama Lengkap <span class="text-danger">*</span></label>
                <input type="text" name="nama" id="siswaFormNama" class="form-control" placeholder="Nama lengkap siswa" required>
              </div>
            </div>

            <!-- Kelas & Jurusan -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label" style="font-weight: 600; font-size: 13px;">Kelas <span class="text-danger">*</span></label>
                <input type="text" list="kelasListOptions" name="kelas" id="siswaFormKelas" class="form-control" placeholder="Pilih / ketik kelas..." required>
                <datalist id="kelasListOptions">
                  <?php foreach ($kelasList as $k): ?>
                    <option value="<?= htmlspecialchars($k['kelas']) ?>"></option>
                  <?php endforeach; ?>
                  <option value="X RPL 1"></option>
                  <option value="X TKJ 1"></option>
                  <option value="XI RPL 1"></option>
                  <option value="XII RPL 1"></option>
                </datalist>
              </div>
              <div class="col-md-6">
                <label class="form-label" style="font-weight: 600; font-size: 13px;">Jurusan <span class="text-danger">*</span></label>
                <input type="text" list="jurusanListOptions" name="jurusan" id="siswaFormJurusan" class="form-control" placeholder="Pilih / ketik jurusan..." required>
                <datalist id="jurusanListOptions">
                  <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= htmlspecialchars($j['jurusan']) ?>"></option>
                  <?php endforeach; ?>
                  <option value="Rekayasa Perangkat Lunak"></option>
                  <option value="Teknik Komputer & Jaringan"></option>
                  <option value="Multimedia / DKV"></option>
                  <option value="Teknik Kendaraan Ringan"></option>
                </datalist>
              </div>
            </div>

            <!-- Jenis Kelamin & Status -->
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label" style="font-weight: 600; font-size: 13px; display: block;">Jenis Kelamin <span class="text-danger">*</span></label>
                <div class="d-flex gap-2 mt-1">
                  <label class="d-flex align-items-center gap-2 p-2 border rounded-3 flex-fill" style="background:#f8fafc; cursor:pointer; font-size:13px;">
                    <input type="radio" name="jenis_kelamin" id="siswaJkL" value="L" checked>
                    <span>👨 Laki-laki</span>
                  </label>
                  <label class="d-flex align-items-center gap-2 p-2 border rounded-3 flex-fill" style="background:#f8fafc; cursor:pointer; font-size:13px;">
                    <input type="radio" name="jenis_kelamin" id="siswaJkP" value="P">
                    <span>👩 Perempuan</span>
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label" style="font-weight: 600; font-size: 13px;">Status Siswa</label>
                <select name="status" id="siswaFormStatus" class="form-select">
                  <option value="aktif">🟢 Aktif</option>
                  <option value="nonaktif">🔴 Nonaktif</option>
                </select>
              </div>
            </div>

            <!-- Shift Rombel -->
            <div class="mb-3">
              <label class="form-label" style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 6px;">Shift Absensi Siswa <span class="text-danger">*</span></label>
              <div class="row g-2">
                <div class="col-6">
                  <label class="shift-option-card d-block p-2 border rounded-3" style="background:#f8fafc; cursor:pointer; font-size:13px; border-color:#cbd5e1;">
                    <div class="d-flex align-items-center gap-2">
                      <input type="radio" name="shift" id="siswaShiftPagi" value="pagi" checked>
                      <strong style="color:#0369a1;">🌅 Shift Pagi</strong>
                    </div>
                    <div style="font-size:11px; color:var(--muted); margin-top:3px; margin-left:20px;">Masuk 07.00 | Pulang 12.00</div>
                  </label>
                </div>
                <div class="col-6">
                  <label class="shift-option-card d-block p-2 border rounded-3" style="background:#f8fafc; cursor:pointer; font-size:13px; border-color:#cbd5e1;">
                    <div class="d-flex align-items-center gap-2">
                      <input type="radio" name="shift" id="siswaShiftSiang" value="siang">
                      <strong style="color:#b45309;">☀️ Shift Siang</strong>
                    </div>
                    <div style="font-size:11px; color:var(--muted); margin-top:3px; margin-left:20px;">Masuk 12.30 | Pulang 17.00</div>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid rgba(15,23,42,0.06);">
            <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" id="btnSubmitModalSiswa" class="btn btn-primary px-4" style="font-weight: 600; box-shadow: 0 4px 12px rgba(37,99,235,0.25);">
              Simpan Data Siswa
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Pop-Up Edit Massal Siswa -->
  <div class="modal fade" id="modalBulkEdit" tabindex="-1" aria-labelledby="modalBulkEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 25px 50px rgba(0,0,0,0.25)">
        <div class="modal-header" style="background:#0f172a;color:#fff;padding:16px 20px">
          <div>
            <h6 class="modal-title mb-0" id="modalBulkEditLabel" style="font-weight:700;font-size:16px">✏️ Edit Data Siswa (Terpilih)</h6>
            <div style="font-size:12px;color:rgba(255,255,255,0.7)">Terapkan perubahan ke seluruh siswa yang dicentang</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" id="formModalBulkEdit">
          <input type="hidden" name="bulk_action" value="bulk_edit">
          <div id="bulkEditIdsContainer"></div>

          <div class="modal-body" style="padding:20px">
            <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:12px;border-radius:8px">
              <span>💡</span>
              <div>Akan diterapkan pada: <strong id="modalEditCountBadge">0 Siswa</strong>. Cukup pilih bagian yang ingin diubah.</div>
            </div>

            <!-- Baris Kelas dan Jurusan (Model Select sama seperti form siswa) -->
            <div class="row g-2 mb-3">
              <div class="col-md-6">
                <label class="form-label" style="font-weight:600;font-size:13px">Kelas</label>
                <select name="bulk_kelas" class="form-select">
                  <option value="">Tetap (Tidak Diubah)</option>
                  <?php foreach ($kelasList as $k): ?>
                    <option value="<?= htmlspecialchars($k['kelas']) ?>"><?= htmlspecialchars($k['kelas']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" style="font-weight:600;font-size:13px">Jurusan</label>
                <select name="bulk_jurusan" class="form-select">
                  <option value="">Tetap (Tidak Diubah)</option>
                  <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= htmlspecialchars($j['jurusan']) ?>"><?= htmlspecialchars($j['jurusan']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Pilihan Shift Siswa (Sama seperti model kartu form siswa) -->
            <div class="mb-3">
              <label class="form-label" style="font-weight:600;font-size:13px;display:block;margin-bottom:6px">Shift Absensi Siswa</label>
              <div class="mb-2">
                <label style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;cursor:pointer;background:#f8fafc;font-size:12px;display:block">
                  <input type="radio" name="bulk_shift" value="" checked style="margin-right:6px">
                  <span>Tetap (Tidak Mengubah Shift)</span>
                </label>
              </div>
              <div style="display:flex;gap:10px">
                <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                  <input type="radio" name="bulk_shift" value="pagi" style="margin-right:6px">
                  <strong>🌅 Shift Pagi</strong>
                  <div style="font-size:11px;color:var(--muted);margin-top:2px">Masuk 07.00 | Pulang 12.00 (Jum'at 10.00)</div>
                </label>
                <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                  <input type="radio" name="bulk_shift" value="siang" style="margin-right:6px">
                  <strong>☀️ Shift Siang</strong>
                  <div style="font-size:11px;color:var(--muted);margin-top:2px">Masuk 12.30 / 13.00 | Pulang 17.00</div>
                </label>
              </div>
            </div>

            <!-- Ubah Status Massal -->
            <div class="mb-2">
              <label class="form-label" style="font-weight:600;font-size:13px">Status Siswa</label>
              <select name="bulk_status" class="form-select">
                <option value="">Tetap (Tidak Diubah)</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
              </select>
            </div>
          </div>

          <div class="modal-footer" style="padding:14px 20px;background:#f8fafc;border-top:1px solid rgba(15,23,42,0.06)">
            <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary px-4" style="font-weight:600">
              Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Pop-Up Hapus Siswa Satuan (Simpel & Elegan) -->
  <div class="modal fade" id="modalSingleDelete" tabindex="-1" aria-labelledby="modalSingleDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
      <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
        <div class="modal-body text-center p-4">
          <div style="width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px;">
            🗑️
          </div>
          <h6 style="font-weight: 700; font-size: 16px; color: #1e293b; margin-bottom: 6px;">Hapus Data Siswa</h6>
          <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">
            Apakah Anda yakin ingin menghapus siswa <strong id="singleDeleteNama" class="text-dark">-</strong> (<span id="singleDeleteNis">-</span>)? Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <div class="modal-footer d-flex justify-content-center gap-2 p-3" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
          <button type="button" class="btn btn-light border px-4 btn-sm" data-bs-dismiss="modal" style="font-weight: 600;">Batal</button>
          <a href="#" id="singleDeleteConfirmBtn" class="btn btn-danger px-4 btn-sm" style="font-weight: 600;">Ya, Hapus</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Pop-Up Hapus Massal Siswa (Simpel & Elegan) -->
  <div class="modal fade" id="modalBulkDelete" tabindex="-1" aria-labelledby="modalBulkDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
      <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
        <div class="modal-body text-center p-4">
          <div style="width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px;">
            🗑️
          </div>
          <h6 style="font-weight: 700; font-size: 16px; color: #1e293b; margin-bottom: 6px;">Hapus Siswa Terpilih</h6>
          <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">
            Apakah Anda yakin ingin menghapus <strong id="modalDeleteCountText" class="text-danger font-weight-bold">0 siswa</strong> yang dipilih? Tindakan ini tidak dapat dibatalkan.
          </p>
        </div>
        <form method="post" id="formModalBulkDelete" style="margin:0">
          <input type="hidden" name="bulk_action" value="bulk_delete">
          <div id="bulkDeleteIdsContainer"></div>
          <div class="modal-footer d-flex justify-content-center gap-2 p-3" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
            <button type="button" class="btn btn-light border px-4 btn-sm" data-bs-dismiss="modal" style="font-weight: 600;">Batal</button>
            <button type="submit" class="btn btn-danger px-4 btn-sm" style="font-weight: 600;">Ya, Hapus Semua</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/main.js?v=1.4"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // ----------------------------------------------------
      // Inisialisasi Modal Instances
      // ----------------------------------------------------
      const modalSiswaFormEl = document.getElementById('modalSiswaForm');
      const modalBulkEditEl = document.getElementById('modalBulkEdit');
      const modalBulkDeleteEl = document.getElementById('modalBulkDelete');
      const modalSingleDeleteEl = document.getElementById('modalSingleDelete');

      const modalSiswaForm = new bootstrap.Modal(modalSiswaFormEl);
      const modalBulkEdit = new bootstrap.Modal(modalBulkEditEl);
      const modalBulkDelete = new bootstrap.Modal(modalBulkDeleteEl);
      const modalSingleDelete = new bootstrap.Modal(modalSingleDeleteEl);

      // ----------------------------------------------------
      // ELEMEN FORM MODAL TAMBAH / EDIT SISWA
      // ----------------------------------------------------
      const btnOpenAddSiswa = document.getElementById('btnOpenAddSiswa');
      const btnEditSiswaList = document.querySelectorAll('.btn-edit-siswa');
      const modalSiswaIcon = document.getElementById('modalSiswaIcon');
      const modalSiswaTitle = document.getElementById('modalSiswaTitle');
      const modalSiswaSub = document.getElementById('modalSiswaSub');
      const btnSubmitModalSiswa = document.getElementById('btnSubmitModalSiswa');

      const siswaFormId = document.getElementById('siswaFormId');
      const siswaFormNis = document.getElementById('siswaFormNis');
      const siswaFormNama = document.getElementById('siswaFormNama');
      const siswaFormKelas = document.getElementById('siswaFormKelas');
      const siswaFormJurusan = document.getElementById('siswaFormJurusan');
      const siswaJkL = document.getElementById('siswaJkL');
      const siswaJkP = document.getElementById('siswaJkP');
      const siswaShiftPagi = document.getElementById('siswaShiftPagi');
      const siswaShiftSiang = document.getElementById('siswaShiftSiang');
      const siswaFormStatus = document.getElementById('siswaFormStatus');

      // Buka Modal Mode TAMBAH SISWA
      if (btnOpenAddSiswa) {
        btnOpenAddSiswa.addEventListener('click', function() {
          modalSiswaIcon.textContent = '➕';
          modalSiswaTitle.textContent = 'Tambah Data Siswa';
          modalSiswaSub.textContent = 'Lengkapi formulir untuk mendaftarkan siswa baru ke sistem';
          btnSubmitModalSiswa.textContent = 'Simpan Data Siswa';

          siswaFormId.value = '0';
          siswaFormNis.value = '';
          siswaFormNama.value = '';
          siswaFormKelas.value = '';
          siswaFormJurusan.value = '';
          if (siswaJkL) siswaJkL.checked = true;
          if (siswaShiftPagi) siswaShiftPagi.checked = true;
          if (siswaFormStatus) siswaFormStatus.value = 'aktif';

          modalSiswaForm.show();
          setTimeout(() => { if (siswaFormNis) siswaFormNis.focus(); }, 150);
        });
      }

      // Buka Modal Mode EDIT SISWA
      btnEditSiswaList.forEach(function(btn) {
        btn.addEventListener('click', function() {
          modalSiswaIcon.textContent = '✏️';
          modalSiswaTitle.textContent = 'Edit Data Siswa';
          modalSiswaSub.textContent = 'Perbarui data profil, kelas, jurusan, atau shift siswa';
          btnSubmitModalSiswa.textContent = 'Perbarui Data Siswa';

          const id = this.getAttribute('data-id') || '0';
          const nis = this.getAttribute('data-nis') || '';
          const nama = this.getAttribute('data-nama') || '';
          const kelas = this.getAttribute('data-kelas') || '';
          const jurusan = this.getAttribute('data-jurusan') || '';
          const jk = this.getAttribute('data-jk') || 'L';
          const shift = this.getAttribute('data-shift') || 'pagi';
          const status = this.getAttribute('data-status') || 'aktif';

          siswaFormId.value = id;
          siswaFormNis.value = nis;
          siswaFormNama.value = nama;
          siswaFormKelas.value = kelas;
          siswaFormJurusan.value = jurusan;

          if (jk === 'P') {
            if (siswaJkP) siswaJkP.checked = true;
          } else {
            if (siswaJkL) siswaJkL.checked = true;
          }

          if (shift === 'siang') {
            if (siswaShiftSiang) siswaShiftSiang.checked = true;
          } else {
            if (siswaShiftPagi) siswaShiftPagi.checked = true;
          }

          if (siswaFormStatus) siswaFormStatus.value = status;

          modalSiswaForm.show();
          setTimeout(() => { if (siswaFormNama) siswaFormNama.focus(); }, 150);
        });
      });

      // ----------------------------------------------------
      // KONTROL CHECKBOX & AKSI MASSAL
      // ----------------------------------------------------
      const selectAllCheckbox = document.getElementById('selectAllCheckbox');
      const studentCheckboxes = document.querySelectorAll('.student-checkbox');
      const bulkActionBar = document.getElementById('bulkActionBar');
      const selectedCountBadge = document.getElementById('selectedCountBadge');
      const btnDeselectAll = document.getElementById('btnDeselectAll');

      const btnOpenBulkEdit = document.getElementById('btnOpenBulkEdit');
      const btnOpenBulkDelete = document.getElementById('btnOpenBulkDelete');
      const bulkEditIdsContainer = document.getElementById('bulkEditIdsContainer');
      const bulkDeleteIdsContainer = document.getElementById('bulkDeleteIdsContainer');
      const modalEditCountBadge = document.getElementById('modalEditCountBadge');
      const modalDeleteCountText = document.getElementById('modalDeleteCountText');

      const singleDeleteNama = document.getElementById('singleDeleteNama');
      const singleDeleteNis = document.getElementById('singleDeleteNis');
      const singleDeleteConfirmBtn = document.getElementById('singleDeleteConfirmBtn');
      const singleDeleteButtons = document.querySelectorAll('.btn-delete-single-siswa');

      // Handle single student delete
      singleDeleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const nama = this.getAttribute('data-nama');
          const nis = this.getAttribute('data-nis');

          singleDeleteNama.textContent = nama;
          singleDeleteNis.textContent = 'NIS: ' + nis;
          singleDeleteConfirmBtn.href = 'delete.php?id=' + encodeURIComponent(id);

          modalSingleDelete.show();
        });
      });

      function getSelectedStudentIds() {
        const ids = [];
        studentCheckboxes.forEach(function(cb) {
          if (cb.checked) {
            ids.push(cb.value);
          }
        });
        return ids;
      }

      function updateBulkSelection() {
        let selectedCount = 0;
        studentCheckboxes.forEach(function(cb) {
          const row = cb.closest('tr');
          if (cb.checked) {
            selectedCount++;
            if (row) row.style.backgroundColor = '#ecfdf5';
          } else {
            if (row) row.style.backgroundColor = '';
          }
        });

        if (selectedCountBadge) {
          selectedCountBadge.textContent = selectedCount + ' Siswa Dipilih';
        }

        if (bulkActionBar) {
          if (selectedCount > 0) {
            bulkActionBar.style.display = 'block';
          } else {
            bulkActionBar.style.display = 'none';
          }
        }

        if (selectAllCheckbox) {
          if (studentCheckboxes.length > 0) {
            selectAllCheckbox.checked = (selectedCount === studentCheckboxes.length);
            selectAllCheckbox.indeterminate = (selectedCount > 0 && selectedCount < studentCheckboxes.length);
          }
        }
      }

      // Handle Select All checkbox
      if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
          const isChecked = this.checked;
          studentCheckboxes.forEach(function(cb) {
            cb.checked = isChecked;
          });
          updateBulkSelection();
        });
      }

      // Handle individual checkboxes
      studentCheckboxes.forEach(function(cb) {
        cb.addEventListener('change', updateBulkSelection);
      });

      // Handle Batal Pilih
      if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', function() {
          if (selectAllCheckbox) selectAllCheckbox.checked = false;
          studentCheckboxes.forEach(function(cb) {
            cb.checked = false;
          });
          updateBulkSelection();
        });
      }

      // Handle Open Bulk Edit Modal
      if (btnOpenBulkEdit) {
        btnOpenBulkEdit.addEventListener('click', function() {
          const ids = getSelectedStudentIds();
          if (ids.length === 0) {
            alert('Silakan pilih setidaknya satu siswa terlebih dahulu.');
            return;
          }

          bulkEditIdsContainer.innerHTML = '';
          ids.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = id;
            bulkEditIdsContainer.appendChild(input);
          });

          if (modalEditCountBadge) {
            modalEditCountBadge.textContent = ids.length + ' Siswa';
          }

          modalBulkEdit.show();
        });
      }

      // Handle Open Bulk Delete Modal
      if (btnOpenBulkDelete) {
        btnOpenBulkDelete.addEventListener('click', function() {
          const ids = getSelectedStudentIds();
          if (ids.length === 0) {
            alert('Silakan pilih setidaknya satu siswa terlebih dahulu.');
            return;
          }

          bulkDeleteIdsContainer.innerHTML = '';
          ids.forEach(function(id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = id;
            bulkDeleteIdsContainer.appendChild(input);
          });

          if (modalDeleteCountText) {
            modalDeleteCountText.textContent = ids.length + ' siswa';
          }

          modalBulkDelete.show();
        });
      }
    });
  </script>
</body>

</html>