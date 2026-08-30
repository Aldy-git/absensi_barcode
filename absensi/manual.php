<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

$role = $_SESSION['role'];

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $siswa_id = (int)($_POST['siswa_id'] ?? 0);
  $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
  $status = $_POST['status'] ?? 'hadir';
  $shift = trim($_POST['shift'] ?? '');

  $holidayInfo = getHolidayInfo($tanggal, $conn);
  if ($holidayInfo) {
    $message = 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' adalah <strong>' . htmlspecialchars($holidayInfo['label']) . '</strong>. Hari libur tidak dianggap masuk dan absensi dikosongkan.';
    $messageType = 'warning';
  } elseif ($siswa_id <= 0) {
    $message = 'Silakan pilih siswa terlebih dahulu.';
    $messageType = 'danger';
  } else {
    // Ambil shift siswa jika tidak diset
    if ($shift !== 'pagi' && $shift !== 'siang') {
      $stS = $conn->prepare("SELECT shift FROM siswa WHERE id = ?");
      $stS->bind_param('i', $siswa_id);
      $stS->execute();
      $rowS = $stS->get_result()->fetch_assoc();
      $shift = $rowS['shift'] ?? 'pagi';
    }

    $check = $conn->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ?");
    $check->bind_param('is', $siswa_id, $tanggal);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $message = 'Absensi untuk siswa ini sudah ada pada tanggal tersebut.';
      $messageType = 'warning';
    } else {
      $insert = $conn->prepare("INSERT INTO absensi (siswa_id, tanggal, status, shift, jam_scan) VALUES (?, ?, ?, ?, ?)");
      $jam = date('H:i:s');
      $insert->bind_param('issss', $siswa_id, $tanggal, $status, $shift, $jam);
      $insert->execute();
      $shiftLabel = $shift === 'siang' ? 'Shift Siang' : 'Shift Pagi';
      $message = 'Absensi manual (' . $shiftLabel . ') berhasil disimpan.';
      $messageType = 'success';
    }
  }
}

$siswaList = $conn->query("SELECT id, nis, nama, kelas, jurusan, shift FROM siswa WHERE status = 'aktif' ORDER BY nama")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Absensi Manual</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css?v=2.1" rel="stylesheet">
  <style>
    .siswa-row {
      padding: 10px 12px;
      border-bottom: 1px solid rgba(15, 23, 42, 0.04);
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      border-radius: 6px;
      transition: background .15s ease, border-color .15s ease;
    }

    .siswa-row:hover {
      background: #f8fafc;
    }

    .siswa-row.selected {
      background: #ecfdf5 !important;
      border: 1px solid #6ee7b7 !important;
    }

    .siswa-row.selected .siswa-nama {
      color: #065f46;
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
          <a href="../siswa/index.php">👥 Data Siswa</a>
          <a href="barcode.php">🔖 Barcode</a>
          <a href="scan.php">📷 Scan Absensi</a>
          <a href="manual.php" class="active">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php">📊 Laporan</a>
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
            <div style="font-size:16px;font-weight:700">Absensi Manual</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Masukkan data absensi</div>
          </div>
        </div>

        <div style="padding:12px;display:flex;justify-content:center">
          <div class="card" style="width:640px;border-radius:12px">
            <div style="padding:18px">
              <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                  <?= $message ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <form method="post" id="formAbsensiManual">
                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Tanggal Absensi</label>
                  <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Pilih Siswa</label>

                  <!-- Kotak Siswa Terpilih (Ditampilkan saat siswa dipilih) -->
                  <div id="selectedSiswaCard" class="p-3 mb-2" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:10px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                      <div style="display:flex;align-items:center;gap:12px">
                        <div style="width:38px;height:38px;border-radius:50%;background:#bbf7d0;color:#15803d;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;flex-shrink:0">✓</div>
                        <div>
                          <div style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:0.5px">Siswa Terpilih:</div>
                          <div id="selectedNama" style="font-weight:700;color:#14532d;font-size:15px">-</div>
                          <div id="selectedNisKelas" style="font-size:12px;color:#166534">-</div>
                        </div>
                      </div>
                      <button type="button" id="btnClearSelection" class="btn btn-sm btn-outline-danger" style="font-size:12px;padding:4px 10px;white-space:nowrap">Ganti Siswa</button>
                    </div>
                  </div>

                  <!-- Input Hidden ID Siswa untuk Form Submit -->
                  <input type="hidden" name="siswa_id" id="selectedSiswaId" value="" required>

                  <!-- Search Bar & Daftar Siswa -->
                  <div id="siswaPickerContainer">
                    <div class="input-group mb-2">
                      <span class="input-group-text bg-white" style="border-right:0;color:#64748b">🔍</span>
                      <input type="text" id="searchSiswa" class="form-control" placeholder="Cari nama, NIS, atau kelas siswa..." style="border-left:0" autocomplete="off">
                      <button type="button" class="btn btn-outline-secondary" id="btnClearSearch" style="display:none" title="Hapus pencarian">&times;</button>
                    </div>

                    <div id="siswaListWrapper" style="border:1px solid rgba(15,23,42,0.08);border-radius:8px;max-height:200px;overflow-y:auto;padding:4px;background:#fff">
                      <?php if (empty($siswaList)): ?>
                        <div class="text-center p-3 text-muted" style="font-size:13px">Belum ada data siswa aktif.</div>
                      <?php else: ?>
                        <?php foreach ($siswaList as $s):
                          $sShift = ($s['shift'] ?? 'pagi') === 'siang' ? 'siang' : 'pagi';
                          $sShiftText = $sShift === 'siang' ? '☀️ Siang' : '🌅 Pagi';
                          $sShiftBadge = $sShift === 'siang' ? 'background:#fef3c7;color:#b45309;border:1px solid #fde68a' : 'background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd';
                        ?>
                          <div class="siswa-row"
                            data-id="<?= $s['id'] ?>"
                            data-nama="<?= htmlspecialchars($s['nama']) ?>"
                            data-nis="<?= htmlspecialchars($s['nis']) ?>"
                            data-kelas="<?= htmlspecialchars($s['kelas'] ?? '') ?>"
                            data-jurusan="<?= htmlspecialchars($s['jurusan'] ?? '') ?>"
                            data-shift="<?= $sShift ?>">
                            <div>
                              <div class="siswa-nama" style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px">
                                <span><?= htmlspecialchars($s['nama']) ?></span>
                                <span class="badge" style="<?= $sShiftBadge ?>;font-size:10px;padding:2px 6px"><?= $sShiftText ?></span>
                              </div>
                              <div class="siswa-meta" style="font-size:12px;color:var(--muted)">
                                NIS: <?= htmlspecialchars($s['nis']) ?>
                                <?= !empty($s['kelas']) ? ' · Kelas: ' . htmlspecialchars($s['kelas']) : '' ?>
                                <?= !empty($s['jurusan']) ? ' (' . htmlspecialchars($s['jurusan']) . ')' : '' ?>
                              </div>
                            </div>
                            <div class="siswa-check-indicator" style="display:none">
                              <span class="badge bg-success">Terpilih</span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                        <div id="noResults" style="display:none;padding:16px;text-align:center;color:var(--muted);font-size:13px">
                          Tidak ada siswa yang cocok dengan kata kunci pencarian.
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Shift Absensi</label>
                  <div style="display:flex;gap:12px">
                    <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                      <input type="radio" name="shift" id="shiftPagiRadio" value="pagi" checked style="margin-right:6px">
                      <strong>🌅 Shift Pagi</strong>
                      <div style="font-size:11px;color:var(--muted);margin-top:2px">Masuk 07.00 | Pulang 12.00 (Jum'at 10.00)</div>
                    </label>
                    <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                      <input type="radio" name="shift" id="shiftSiangRadio" value="siang" style="margin-right:6px">
                      <strong>☀️ Shift Siang</strong>
                      <div style="font-size:11px;color:var(--muted);margin-top:2px">Masuk 12.30 / 13.00 | Pulang 17.00</div>
                    </label>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label" style="font-weight:600">Status Kehadiran</label>
                  <div style="display:flex;gap:8px;margin-top:4px;align-items:center;flex-wrap:wrap">
                    <label style="cursor:pointer"><input type="radio" name="status" value="hadir" checked style="display:none"><span class="tag hadir">Hadir</span></label>
                    <label style="cursor:pointer"><input type="radio" name="status" value="terlambat" style="display:none"><span class="tag terlambat">Terlambat</span></label>
                    <label style="cursor:pointer"><input type="radio" name="status" value="izin" style="display:none"><span class="tag izin">Izin</span></label>
                    <label style="cursor:pointer"><input type="radio" name="status" value="sakit" style="display:none"><span class="tag sakit">Sakit</span></label>
                    <label style="cursor:pointer"><input type="radio" name="status" value="alpa" style="display:none"><span class="tag alpa">Alpa</span></label>
                  </div>
                </div>

                <div style="display:flex;justify-content:center;margin-top:20px">
                  <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;font-weight:600">Simpan Absensi</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchSiswa');
      const btnClearSearch = document.getElementById('btnClearSearch');
      const siswaRows = document.querySelectorAll('.siswa-row');
      const noResults = document.getElementById('noResults');
      const selectedIdInput = document.getElementById('selectedSiswaId');
      const selectedCard = document.getElementById('selectedSiswaCard');
      const selectedNama = document.getElementById('selectedNama');
      const selectedNisKelas = document.getElementById('selectedNisKelas');
      const btnClearSelection = document.getElementById('btnClearSelection');
      const form = document.getElementById('formAbsensiManual');

      // Live search filter
      function filterSiswa() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        if (query.length > 0) {
          btnClearSearch.style.display = 'block';
        } else {
          btnClearSearch.style.display = 'none';
        }

        siswaRows.forEach(function(row) {
          const nama = (row.getAttribute('data-nama') || '').toLowerCase();
          const nis = (row.getAttribute('data-nis') || '').toLowerCase();
          const kelas = (row.getAttribute('data-kelas') || '').toLowerCase();
          const jurusan = (row.getAttribute('data-jurusan') || '').toLowerCase();

          if (nama.includes(query) || nis.includes(query) || kelas.includes(query) || jurusan.includes(query)) {
            row.style.display = 'flex';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });

        if (noResults) {
          noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
      }

      if (searchInput) {
        searchInput.addEventListener('input', filterSiswa);
      }

      if (btnClearSearch) {
        btnClearSearch.addEventListener('click', function() {
          searchInput.value = '';
          filterSiswa();
          searchInput.focus();
        });
      }

      // Handle selecting a student
      function selectStudent(row) {
        const id = row.getAttribute('data-id');
        const nama = row.getAttribute('data-nama');
        const nis = row.getAttribute('data-nis');
        const kelas = row.getAttribute('data-kelas');
        const jurusan = row.getAttribute('data-jurusan');
        const shift = row.getAttribute('data-shift') || 'pagi';

        // Update hidden input
        selectedIdInput.value = id;

        // Auto select shift radio
        if (shift === 'siang') {
          const rSiang = document.getElementById('shiftSiangRadio');
          if (rSiang) rSiang.checked = true;
        } else {
          const rPagi = document.getElementById('shiftPagiRadio');
          if (rPagi) rPagi.checked = true;
        }

        // Update selected display card
        selectedNama.textContent = nama;
        let info = 'NIS: ' + nis;
        if (kelas) info += ' · Kelas: ' + kelas;
        if (jurusan) info += ' (' + jurusan + ')';
        info += ' · ' + (shift === 'siang' ? '☀️ Shift Siang' : '🌅 Shift Pagi');
        selectedNisKelas.textContent = info;
        selectedCard.style.display = 'block';

        // Update rows UI
        siswaRows.forEach(function(r) {
          r.classList.remove('selected');
          const indicator = r.querySelector('.siswa-check-indicator');
          if (indicator) indicator.style.display = 'none';
        });

        row.classList.add('selected');
        const indicator = row.querySelector('.siswa-check-indicator');
        if (indicator) indicator.style.display = 'block';
      }

      siswaRows.forEach(function(row) {
        row.addEventListener('click', function() {
          selectStudent(row);
        });
      });

      // Clear selection button
      if (btnClearSelection) {
        btnClearSelection.addEventListener('click', function() {
          selectedIdInput.value = '';
          selectedCard.style.display = 'none';
          siswaRows.forEach(function(r) {
            r.classList.remove('selected');
            const indicator = r.querySelector('.siswa-check-indicator');
            if (indicator) indicator.style.display = 'none';
          });
          if (searchInput) {
            searchInput.focus();
          }
        });
      }

      // Form validation before submit
      if (form) {
        form.addEventListener('submit', function(e) {
          if (!selectedIdInput.value || selectedIdInput.value === '0') {
            e.preventDefault();
            alert('Silakan pilih siswa terlebih dahulu dari daftar!');
            if (searchInput) {
              searchInput.focus();
            }
          }
        });
      }

      // Status badge focus handling
      function refreshStatusFocus() {
        document.querySelectorAll('.tag').forEach(function(t) {
          t.classList.remove('selected');
        });
        const checked = document.querySelector('input[name="status"]:checked');
        if (checked) {
          const sp = checked.closest('label')?.querySelector('.tag');
          if (sp) sp.classList.add('selected');
        }
      }

      document.querySelectorAll('input[name="status"]').forEach(function(inp) {
        inp.addEventListener('change', refreshStatusFocus);
      });

      // Init
      refreshStatusFocus();
    });
  </script>
  <script src="../assets/main.js?v=1.4"></script>
</body>

</html>