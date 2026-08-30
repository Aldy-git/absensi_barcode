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

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$message = '';
$messageType = 'danger';

if ($id <= 0) {
    header('Location: riwayat.php?error=id_invalid');
    exit;
}

// Ambil data absensi saat ini
$stmt = $conn->prepare("SELECT a.*, s.nis, s.nama, s.kelas, s.jurusan FROM absensi a JOIN siswa s ON a.siswa_id = s.id WHERE a.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$absensi = $stmt->get_result()->fetch_assoc();

if (!$absensi) {
    header('Location: riwayat.php?error=not_found');
    exit;
}

// Proses form edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $message = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $tanggal = trim($_POST['tanggal'] ?? '');
        $status = trim($_POST['status'] ?? 'hadir');
        $shift = trim($_POST['shift'] ?? 'pagi');
        if ($shift !== 'siang') $shift = 'pagi';
        $allowedStatus = ['hadir', 'terlambat', 'izin', 'sakit', 'alpa'];

        $holidayInfo = getHolidayInfo($tanggal, $conn);
        if ($tanggal === '') {
            $message = 'Tanggal absensi wajib diisi.';
        } elseif ($holidayInfo) {
            $message = 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' adalah ' . htmlspecialchars($holidayInfo['label']) . '. Hari libur tidak dianggap masuk dan absensi dikosongkan.';
        } elseif (!in_array($status, $allowedStatus, true)) {
            $message = 'Status absensi tidak valid.';
        } else {
            $siswa_id = (int)$absensi['siswa_id'];
            $dup = $conn->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ? AND id != ?");
            $dup->bind_param('isi', $siswa_id, $tanggal, $id);
            $dup->execute();
            if ($dup->get_result()->num_rows > 0) {
                $message = 'Absensi untuk siswa ini sudah ada pada tanggal ' . htmlspecialchars($tanggal) . '.';
            } else {
                $update = $conn->prepare("UPDATE absensi SET tanggal = ?, status = ?, shift = ? WHERE id = ?");
                $update->bind_param('sssi', $tanggal, $status, $shift, $id);
                if ($update->execute()) {
                    header('Location: riwayat.php?msg=updated');
                    exit;
                } else {
                    $message = 'Gagal menyimpan perubahan: ' . $conn->error;
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
    <title>Edit Data Absensi</title>
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
                <div class="top-hero" style="padding:16px 20px">
                    <div>
                        <div style="font-size:16px;font-weight:700">Edit Absensi</div>
                        <div style="font-size:13px;color:rgba(255,255,255,0.7)">Ubah data kehadiran siswa</div>
                    </div>
                    <a href="riwayat.php" class="btn btn-outline-light btn-sm">← Kembali ke Riwayat</a>
                </div>

                <div style="padding:12px;display:flex;justify-content:center">
                    <div class="card" style="width:540px;border-radius:12px">
                        <div style="padding:18px">
                            <?php if ($message): ?>
                                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                    <?= $message ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="id" value="<?= $absensi['id'] ?>">

                                <!-- Info Siswa (Readonly) -->
                                <div class="p-3 mb-3" style="background:#f8fafc;border:1px solid rgba(15,23,42,0.08);border-radius:10px">
                                    <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Data Siswa</div>
                                    <div style="font-weight:700;font-size:16px;color:#0f172a;margin-top:2px"><?= htmlspecialchars($absensi['nama']) ?></div>
                                    <div style="font-size:13px;color:#64748b">
                                        NIS: <?= htmlspecialchars($absensi['nis']) ?> · Kelas: <?= htmlspecialchars($absensi['kelas']) ?>
                                        <?= !empty($absensi['jurusan']) ? ' (' . htmlspecialchars($absensi['jurusan']) . ')' : '' ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" style="font-weight:600">Tanggal Absensi</label>
                                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($absensi['tanggal']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" style="font-weight:600">Shift Absensi</label>
                                    <div style="display:flex;gap:12px">
                                        <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                                            <input type="radio" name="shift" value="pagi" <?= ($absensi['shift'] ?? 'pagi') === 'pagi' ? 'checked' : '' ?> style="margin-right:6px">
                                            <strong>🌅 Shift Pagi</strong>
                                        </label>
                                        <label style="flex:1;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;cursor:pointer;background:#f8fafc;font-size:13px">
                                            <input type="radio" name="shift" value="siang" <?= ($absensi['shift'] ?? 'pagi') === 'siang' ? 'checked' : '' ?> style="margin-right:6px">
                                            <strong>☀️ Shift Siang</strong>
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label" style="font-weight:600">Status Kehadiran</label>
                                    <div style="display:flex;gap:8px;margin-top:4px;align-items:center;flex-wrap:wrap">
                                        <label style="cursor:pointer">
                                            <input type="radio" name="status" value="hadir" <?= $absensi['status'] === 'hadir' ? 'checked' : '' ?> style="display:none">
                                            <span class="tag hadir <?= $absensi['status'] === 'hadir' ? 'selected' : '' ?>">Hadir</span>
                                        </label>
                                        <label style="cursor:pointer">
                                            <input type="radio" name="status" value="terlambat" <?= $absensi['status'] === 'terlambat' ? 'checked' : '' ?> style="display:none">
                                            <span class="tag terlambat <?= $absensi['status'] === 'terlambat' ? 'selected' : '' ?>">Terlambat</span>
                                        </label>
                                        <label style="cursor:pointer">
                                            <input type="radio" name="status" value="izin" <?= $absensi['status'] === 'izin' ? 'checked' : '' ?> style="display:none">
                                            <span class="tag izin <?= $absensi['status'] === 'izin' ? 'selected' : '' ?>">Izin</span>
                                        </label>
                                        <label style="cursor:pointer">
                                            <input type="radio" name="status" value="sakit" <?= $absensi['status'] === 'sakit' ? 'checked' : '' ?> style="display:none">
                                            <span class="tag sakit <?= $absensi['status'] === 'sakit' ? 'selected' : '' ?>">Sakit</span>
                                        </label>
                                        <label style="cursor:pointer">
                                            <input type="radio" name="status" value="alpa" <?= $absensi['status'] === 'alpa' ? 'checked' : '' ?> style="display:none">
                                            <span class="tag alpa <?= $absensi['status'] === 'alpa' ? 'selected' : '' ?>">Alpa</span>
                                        </label>
                                    </div>
                                </div>

                                <div style="display:flex;gap:10px;justify-content:flex-end">
                                    <a href="riwayat.php" class="btn btn-outline-secondary">Batal</a>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            refreshStatusFocus();
        });
    </script>
    <script src="../assets/main.js?v=1.4"></script>
</body>

</html>