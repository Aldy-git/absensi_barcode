<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
// handle add, delete, sync
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sync_national'])) {
        $y = (int)($_POST['sync_year'] ?? date('Y'));
        if (!empty($_POST['force_sync'])) {
            mysqli_query($conn, "DELETE FROM holidays WHERE YEAR(tanggal) = $y AND type = 'national'");
        }
        $c = syncNationalHolidays($conn, $y);
        $message = "Hari libur nasional tahun $y berhasil diperbarui ($c hari).";
    }
    if (isset($_POST['add'])) {
        $tanggal = $_POST['tanggal'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        if ($tanggal && $nama) {
            $stmt = $conn->prepare("INSERT INTO holidays (tanggal, nama, type, created_by) VALUES (?, ?, 'school', ?)");
            $uid = $_SESSION['user_id'];
            $stmt->bind_param('ssi', $tanggal, $nama, $uid);
            if ($stmt->execute()) $message = 'Libur sekolah berhasil ditambahkan.'; else $message = 'Gagal menambahkan libur.';
        } else $message = 'Tanggal dan nama diperlukan.';
    }
    if (isset($_POST['delete'])) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM holidays WHERE id = ? AND type <> 'national'");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) $message = 'Libur berhasil dihapus.'; else $message = 'Gagal menghapus.';
        }
    }
}

$selectedYear = (int)($_GET['year'] ?? date('Y'));
$holidays = $conn->query("SELECT id, tanggal, nama, type, created_at FROM holidays WHERE YEAR(tanggal) = $selectedYear ORDER BY tanggal ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kelola Libur</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css?v=1.4" rel="stylesheet">
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
          <a href="../dashboard.php">🏠 Dashboard</a>
          <a href="../siswa/index.php">👥 Data Siswa</a>
          <a href="../absensi/barcode.php">🔖 Barcode</a>
          <a href="../absensi/scan.php">📷 Scan Absensi</a>
          <a href="../absensi/manual.php">✍️ Absensi Manual</a>
          <a href="../absensi/riwayat.php">📜 Riwayat</a>
          <a href="../absensi/laporan.php">📊 Laporan</a>
          <a href="../users/index.php">🔒 Pengguna</a>
          <a href="index.php" class="active">📅 Kelola Libur</a>
        </nav>
      </div>
      <div class="footer">
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong><div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($_SESSION['role']) ?></div></div>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Admin') ?></span>
          </div>
          <a href="../logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Kelola Hari Libur & Kalender Akademik</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Sinkronisasi libur nasional dan kelola libur khusus sekolah</div>
          </div>
          <div>
            <a href="../dashboard.php" class="btn btn-outline-light btn-sm">← Kembali ke Dashboard</a>
          </div>
        </div>

        <?php if ($message): ?><div class="alert alert-info mt-3"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <!-- Sync National Holidays Bar -->
        <div class="card my-3 p-3" style="border-radius:12px;background:#f8fafc">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <div style="font-weight:700;font-size:14px;color:#1e293b">🔄 Sinkronisasi Hari Libur Nasional</div>
              <div style="font-size:12px;color:var(--muted)">Perbarui hari libur nasional otomatis setiap pergantian tahun</div>
            </div>
            <form method="post" class="d-flex align-items-center gap-2">
              <input type="number" name="sync_year" class="form-control form-control-sm" style="width:90px" value="<?= $selectedYear ?>" min="2020" max="2035" required>
              <input type="hidden" name="force_sync" value="1">
              <button type="submit" name="sync_national" class="btn btn-sm btn-primary" style="white-space:nowrap">
                ⚡ Sinkronkan Libur Nasional
              </button>
            </form>
          </div>
        </div>

        <!-- modal-like centered management panel -->
        <div id="holidayPanel" style="display:flex;align-items:flex-start;justify-content:center;padding:12px 0">
          <div style="width:680px;max-width:100%;background:#f3f3f3;border-radius:12px;overflow:hidden;box-shadow:0 15px 40px rgba(2,6,23,0.15)">
            <div style="background:#111316;color:#fff;padding:14px 18px;border-top-left-radius:12px;border-top-right-radius:12px;display:flex;justify-content:space-between;align-items:center">
              <div>
                <div style="font-weight:700">Daftar Hari Libur Tahun <?= $selectedYear ?></div>
                <div style="font-size:12px;color:rgba(255,255,255,0.6)">Kelola libur sekolah dan lihat libur nasional</div>
              </div>
              <div class="d-flex gap-2 align-items-center">
                <form method="get" class="d-flex align-items-center gap-1">
                  <select name="year" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 3; $y++): ?>
                      <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                </form>
                <button onclick="window.location='../dashboard.php'" style="background:transparent;border:0;color:#cbd5db;font-size:18px">✕</button>
              </div>
            </div>

            <div style="padding:14px;background:#fff;border-bottom:1px solid #e2e8f0">
              <div style="font-weight:600;font-size:13px;margin-bottom:8px">Tambah Libur Khusus Sekolah:</div>
              <form method="post" style="display:flex;gap:8px;align-items:center">
                <div style="flex:0 0 150px"><input type="date" name="tanggal" class="form-control form-control-sm" required></div>
                <div style="flex:1"><input name="nama" class="form-control form-control-sm" placeholder="Nama hari libur sekolah..." required></div>
                <div><button class="btn btn-sm" name="add" style="background:#8b5cf6;color:#fff;padding:6px 14px;border-radius:6px">+ Tambah</button></div>
              </form>
            </div>

            <div style="padding:16px;background:#fff;min-height:220px;max-height:480px;overflow-y:auto">
              <?php if (empty($holidays)): ?>
                <div style="text-align:center;padding:30px;color:var(--muted)">
                  <div style="font-size:36px;margin-bottom:8px">🏖️</div>
                  <div style="font-weight:700">Belum ada hari libur di tahun <?= $selectedYear ?></div>
                  <div style="font-size:12px;margin-top:4px">Gunakan tombol "Sinkronkan Libur Nasional" di atas atau form tambah di atas.</div>
                </div>
              <?php else: ?>
                <div>
                  <?php foreach($holidays as $h):
                    $isNat = ($h['type'] === 'national');
                    $typeBadge = $isNat ? '<span class="badge" style="background:#fee2e2;color:#b91c1c;font-size:10px">Nasional</span>' : '<span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:10px">Sekolah</span>';
                  ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid rgba(2,6,23,0.06)">
                      <div style="display:flex;gap:12px;align-items:center">
                        <div style="width:40px;height:40px;border-radius:8px;background:<?= $isNat ? '#fef2f2' : '#eef2ff' ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:700;color:<?= $isNat ? '#dc2626' : '#2563eb' ?>">
                          <span style="font-size:14px;line-height:1"><?= date('j', strtotime($h['tanggal'])) ?></span>
                          <span style="font-size:9px;text-transform:uppercase"><?= substr(getIndonesianMonthName(date('n', strtotime($h['tanggal']))), 0, 3) ?></span>
                        </div>
                        <div>
                          <div style="font-weight:700;font-size:13px;display:flex;align-items:center;gap:6px">
                            <span><?= htmlspecialchars($h['nama']) ?></span>
                            <?= $typeBadge ?>
                          </div>
                          <div style="font-size:12px;color:#9ca3af"><?= formatTanggalIndo($h['tanggal'], true) ?></div>
                        </div>
                      </div>
                      <div>
                        <?php if (!$isNat): ?>
                          <button type="button" class="btn btn-sm btn-outline-danger btn-delete-holiday"
                            data-id="<?= $h['id'] ?>"
                            data-nama="<?= htmlspecialchars($h['nama']) ?>"
                            data-tanggal="<?= htmlspecialchars(formatTanggalIndo($h['tanggal'], true)) ?>"
                            style="font-size:11px;padding:3px 8px">
                            Hapus
                          </button>
                        <?php else: ?>
                          <span style="font-size:11px;color:#94a3b8">Otomatis</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div style="padding:12px 18px;background:#fff;border-top:1px solid rgba(2,6,23,0.06);display:flex;justify-content:space-between;align-items:center">
              <div style="font-size:12px;color:#9ca3af">Total <?= count($holidays) ?> hari libur di tahun <?= $selectedYear ?></div>
              <div><button class="btn btn-sm btn-dark" onclick="window.location='../dashboard.php'">Selesai</button></div>
            </div>
          </div>
        </div>
          </div>
        </div>

      </div>
    </main>
  </div>
  <!-- Modal Konfirmasi Hapus Libur (Simpel & Elegan) -->
  <div class="modal fade" id="modalDeleteHoliday" tabindex="-1" aria-labelledby="modalDeleteHolidayLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
      <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden;">
        <div class="modal-body text-center p-4">
          <div style="width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px;">
            🗑️
          </div>
          <h6 style="font-weight: 700; font-size: 16px; color: #1e293b; margin-bottom: 6px;">Hapus Hari Libur</h6>
          <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">
            Apakah Anda yakin ingin menghapus libur <strong id="deleteHolidayNama" class="text-dark">-</strong> (<span id="deleteHolidayTanggal">-</span>)?
          </p>
        </div>
        <form method="post" id="formDeleteHoliday" style="margin:0">
          <input type="hidden" name="delete" value="1">
          <input type="hidden" name="id" id="deleteHolidayId" value="">
          <div class="modal-footer d-flex justify-content-center gap-2 p-3" style="background: #f8fafc; border-top: 1px solid #f1f5f9;">
            <button type="button" class="btn btn-light border px-4 btn-sm" data-bs-dismiss="modal" style="font-weight: 600;">Batal</button>
            <button type="submit" class="btn btn-danger px-4 btn-sm" style="font-weight: 600;">Ya, Hapus</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/main.js?v=1.4"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modalDeleteEl = document.getElementById('modalDeleteHoliday');
      if (modalDeleteEl) {
        const modalDelete = new bootstrap.Modal(modalDeleteEl);
        const deleteButtons = document.querySelectorAll('.btn-delete-holiday');
        const deleteHolidayId = document.getElementById('deleteHolidayId');
        const deleteHolidayNama = document.getElementById('deleteHolidayNama');
        const deleteHolidayTanggal = document.getElementById('deleteHolidayTanggal');

        deleteButtons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const tanggal = this.getAttribute('data-tanggal');

            deleteHolidayId.value = id;
            deleteHolidayNama.textContent = nama;
            deleteHolidayTanggal.textContent = tanggal;

            modalDelete.show();
          });
        });
      }
    });
  </script>
</body>
</html>
