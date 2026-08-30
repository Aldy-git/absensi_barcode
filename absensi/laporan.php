<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$kelas = $_GET['kelas'] ?? '';
$jurusan = $_GET['jurusan'] ?? '';
$shift = $_GET['shift'] ?? '';

$sql = "SELECT s.nis, s.nama, s.kelas, s.jurusan, s.shift,
        SUM(CASE WHEN a.status='hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status='terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status='izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status='sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status='alpa' THEN 1 ELSE 0 END) AS alpa
        FROM siswa s LEFT JOIN absensi a ON s.id = a.siswa_id";
$where = [];
if ($from !== '') { $where[] = "a.tanggal >= '$from'"; }
if ($to !== '') { $where[] = "a.tanggal <= '$to'"; }
if ($kelas !== '') { $where[] = "s.kelas = '$kelas'"; }
if ($jurusan !== '') { $where[] = "s.jurusan = '$jurusan'"; }
if ($shift !== '') { $where[] = "s.shift = '" . mysqli_real_escape_string($conn, $shift) . "'"; }
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " GROUP BY s.id ORDER BY s.nama";
$result = $conn->query($sql);
$laporan = $result->fetch_all(MYSQLI_ASSOC);

// Export CSV if requested
if (isset($_GET['export']) && $_GET['export'] == '1') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=laporan_absensi.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['NIS','Nama','Kelas','Jurusan','Shift','Hadir','Terlambat','Izin','Sakit','Alpa','Total']);
  foreach ($laporan as $r) {
    $total = (int)$r['hadir'] + (int)$r['terlambat'] + (int)$r['izin'] + (int)$r['sakit'] + (int)$r['alpa'];
    $sLabel = ($r['shift'] ?? 'pagi') === 'siang' ? 'Shift Siang' : 'Shift Pagi';
    fputcsv($out, [ $r['nis'] ?? '', $r['nama'], $r['kelas'], $r['jurusan'], $sLabel, $r['hadir'], $r['terlambat'], $r['izin'], $r['sakit'], $r['alpa'], $total ]);
  }
  fclose($out);
  exit;
}

// compute totals for stat cards
$totalHadir = 0; $totalTerlambat = 0; $totalIzin = 0; $totalSakit = 0; $totalAlpa = 0;
foreach ($laporan as $r) { $totalHadir += (int)$r['hadir']; $totalTerlambat += (int)$r['terlambat']; $totalIzin += (int)$r['izin']; $totalSakit += (int)$r['sakit']; $totalAlpa += (int)$r['alpa']; }

// prepare 7-day or range labels and daily totals
if ($from !== '' && $to !== '') {
  $start = $from; $end = $to;
} else {
  $start = date('Y-m-d', strtotime('-6 days'));
  $end = date('Y-m-d');
}
$period = new DatePeriod(new DateTime($start), new DateInterval('P1D'), (new DateTime($end))->modify('+1 day'));
$labels = [];
$daily = ['hadir'=>[], 'terlambat'=>[], 'izin'=>[], 'sakit'=>[], 'alpa'=>[]];
foreach ($period as $d) {
  $ds = $d->format('Y-m-d'); $labels[] = $d->format('j M');
  // count per day
  $q = $conn->query("SELECT status, COUNT(*) AS cnt FROM absensi WHERE tanggal = '{$ds}' GROUP BY status");
  $map = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpa'=>0];
  while ($rr = mysqli_fetch_assoc($q)) { $map[$rr['status']] = (int)$rr['cnt']; }
  foreach ($daily as $k=>$_) { $daily[$k][] = $map[$k]; }
}

$labels_js = json_encode($labels);
$hadir_js = json_encode($daily['hadir']);
$terlambat_js = json_encode($daily['terlambat']);
$izin_js = json_encode($daily['izin']);
$sakit_js = json_encode($daily['sakit']);
$alpa_js = json_encode($daily['alpa']);

$kelasList = $conn->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetch_all(MYSQLI_ASSOC);
$jurusanList = $conn->query("SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Absensi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css?v=2.1" rel="stylesheet">
  <style>
    /* ========================================================
       STATISTIC CARDS LAPORAN - WARNA JELAS & KONTRAS TINGGI
       ======================================================== */
    .laporan-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-top: 14px;
    }

    .report-stat-card {
      padding: 16px 18px;
      border-radius: 14px;
      position: relative;
      overflow: hidden;
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    }

    .report-stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    .report-stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }

    .report-stat-title {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .report-stat-icon {
      font-size: 18px;
      opacity: 0.9;
    }

    .report-stat-value {
      font-size: 32px;
      font-weight: 800;
      line-height: 1.1;
      margin-bottom: 4px;
    }

    .report-stat-sub {
      font-size: 12px;
      font-weight: 600;
      opacity: 0.85;
    }

    /* 1. HADIR - Emerald Vibrant */
    .stat-card-hadir {
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border: 1.5px solid #86efac;
    }
    .stat-card-hadir .report-stat-title { color: #166534; }
    .stat-card-hadir .report-stat-value { color: #15803d; }
    .stat-card-hadir .report-stat-sub   { color: #14532d; }

    /* 2. TERLAMBAT - Amber / Orange */
    .stat-card-terlambat {
      background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
      border: 1.5px solid #fcd34d;
    }
    .stat-card-terlambat .report-stat-title { color: #92400e; }
    .stat-card-terlambat .report-stat-value { color: #b45309; }
    .stat-card-terlambat .report-stat-sub   { color: #78350f; }

    /* 3. IZIN - Cyan / Teal Vibrant (Sangat Jelas) */
    .stat-card-izin {
      background: linear-gradient(135deg, #ecfeff 0%, #cffafe 100%);
      border: 1.5px solid #67e8f9;
    }
    .stat-card-izin .report-stat-title { color: #155e75; }
    .stat-card-izin .report-stat-value { color: #0891b2; }
    .stat-card-izin .report-stat-sub   { color: #164e63; }

    /* 4. SAKIT - Purple / Violet Vibrant (Sangat Jelas) */
    .stat-card-sakit {
      background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
      border: 1.5px solid #c4b5fd;
    }
    .stat-card-sakit .report-stat-title { color: #5b21b6; }
    .stat-card-sakit .report-stat-value { color: #7c3aed; }
    .stat-card-sakit .report-stat-sub   { color: #4c1d95; }

    /* 5. ALPA - Ruby Red Vibrant (Sangat Jelas & Tegas) */
    .stat-card-alpa {
      background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
      border: 1.5px solid #fca5a5;
    }
    .stat-card-alpa .report-stat-title { color: #991b1b; }
    .stat-card-alpa .report-stat-value { color: #dc2626; }
    .stat-card-alpa .report-stat-sub   { color: #7f1d1d; }

    /* Responsive Filter Controls */
    .filter-card {
      background: #ffffff;
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 14px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }
    
    .report-btn-group {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    @media (max-width: 768px) {
      .laporan-stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 576px) {
      .report-btn-group {
        width: 100%;
      }
      .report-btn-group button,
      .report-btn-group a {
        width: 100%;
      }
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
          <a href="manual.php">✍️ Absensi Manual</a>
          <a href="riwayat.php">📜 Riwayat</a>
          <a href="laporan.php" class="active">📊 Laporan</a>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="../users/index.php">🔒 Pengguna</a>
            <a href="../holidays/index.php">📅 Kelola Libur</a>
          <?php endif; ?>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="../logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Laporan</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Ringkasan kehadiran</div>
          </div>
        </div>

        <div class="card filter-card" style="padding:18px;margin-top:14px;border-radius:14px">
          <form method="get" class="row g-3">
            <div class="col-6 col-sm-4 col-md-4 col-lg-2">
              <label class="form-label fw-semibold" style="font-size:13px">📅 Dari</label>
              <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-6 col-sm-4 col-md-4 col-lg-2">
              <label class="form-label fw-semibold" style="font-size:13px">📅 Sampai</label>
              <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-6 col-sm-4 col-md-4 col-lg-2">
              <label class="form-label fw-semibold" style="font-size:13px">🏫 Kelas</label>
              <select name="kelas" class="form-select">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $k): ?>
                  <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $kelas === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-sm-6 col-md-6 col-lg-3">
              <label class="form-label fw-semibold" style="font-size:13px">🎓 Jurusan</label>
              <select name="jurusan" class="form-select">
                <option value="">Semua Jurusan</option>
                <?php foreach ($jurusanList as $j): ?>
                  <option value="<?= htmlspecialchars($j['jurusan']) ?>" <?= $jurusan === $j['jurusan'] ? 'selected' : '' ?>><?= htmlspecialchars($j['jurusan']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-lg-3">
              <label class="form-label fw-semibold" style="font-size:13px">⏰ Shift</label>
              <select name="shift" class="form-select">
                <option value="">Semua Shift</option>
                <option value="pagi" <?= $shift === 'pagi' ? 'selected' : '' ?>>🌅 Shift Pagi</option>
                <option value="siang" <?= $shift === 'siang' ? 'selected' : '' ?>>☀️ Shift Siang</option>
              </select>
            </div>

            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top">
              <div class="d-flex align-items-center gap-2">
                <a href="laporan.php" class="btn btn-outline-secondary btn-sm px-3" style="font-weight:600">
                  🔄 Reset
                </a>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap report-btn-group">
                <button type="submit" name="export" value="1" class="btn btn-success px-3" title="Unduh CSV" style="font-weight:600;box-shadow:0 2px 6px rgba(16,185,129,0.25)">
                  📥 CSV
                </button>
                <button type="button" class="btn btn-dark px-3" onclick="printReport()" style="font-weight:600;box-shadow:0 2px 6px rgba(15,23,42,0.2)">
                  🖨️ Print
                </button>
                <button type="submit" class="btn btn-primary px-4" style="font-weight:600;box-shadow:0 2px 6px rgba(37,99,235,0.25)">
                  🔍 Filter
                </button>
              </div>
            </div>
          </form>
        </div>

        <div class="laporan-stats-grid">
          <!-- Stat Hadir -->
          <div class="report-stat-card stat-card-hadir">
            <div class="report-stat-header">
              <span class="report-stat-title">Hadir</span>
              <span class="report-stat-icon">✅</span>
            </div>
            <div class="report-stat-value"><?= number_format($totalHadir) ?></div>
            <div class="report-stat-sub">Siswa Tepat Waktu</div>
          </div>

          <!-- Stat Terlambat -->
          <div class="report-stat-card stat-card-terlambat">
            <div class="report-stat-header">
              <span class="report-stat-title">Terlambat</span>
              <span class="report-stat-icon">⏰</span>
            </div>
            <div class="report-stat-value"><?= number_format($totalTerlambat) ?></div>
            <div class="report-stat-sub">Lewat Jam 07.00</div>
          </div>

          <!-- Stat Izin -->
          <div class="report-stat-card stat-card-izin">
            <div class="report-stat-header">
              <span class="report-stat-title">Izin</span>
              <span class="report-stat-icon">📝</span>
            </div>
            <div class="report-stat-value"><?= number_format($totalIzin) ?></div>
            <div class="report-stat-sub">Dispensasi / Surat</div>
          </div>

          <!-- Stat Sakit -->
          <div class="report-stat-card stat-card-sakit">
            <div class="report-stat-header">
              <span class="report-stat-title">Sakit</span>
              <span class="report-stat-icon">🩺</span>
            </div>
            <div class="report-stat-value"><?= number_format($totalSakit) ?></div>
            <div class="report-stat-sub">Surat Keterangan</div>
          </div>

          <!-- Stat Alpa -->
          <div class="report-stat-card stat-card-alpa">
            <div class="report-stat-header">
              <span class="report-stat-title">Alpa</span>
              <span class="report-stat-icon">❌</span>
            </div>
            <div class="report-stat-value"><?= number_format($totalAlpa) ?></div>
            <div class="report-stat-sub">Tanpa Keterangan</div>
          </div>
        </div>

        <div class="chart-card card" style="margin-top:12px">
          <h6>Tren Absensi per Hari</h6>
          <div class="chart-container">
            <canvas id="lapChart"></canvas>
          </div>
        </div>

        <div class="card" style="margin-top:12px">
          <h6>Rekap Per Siswa</h6>
          <div class="table-responsive">
            <table class="table table-borderless">
              <thead>
                <tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Shift</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($laporan as $row): $total = (int)$row['hadir']+(int)$row['terlambat']+(int)$row['izin']+(int)$row['sakit']+(int)$row['alpa']; ?>
                  <tr>
                    <td><?= htmlspecialchars($row['nis'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['kelas']) ?></td>
                    <td>
                      <?php if (($row['shift'] ?? 'pagi') === 'siang'): ?>
                        <span class="badge" style="background:#fef3c7;color:#b45309;font-weight:600;border:1px solid #fde68a">☀️ Siang</span>
                      <?php else: ?>
                        <span class="badge" style="background:#e0f2fe;color:#0369a1;font-weight:600;border:1px solid #bae6fd">🌅 Pagi</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="tag hadir"><?= (int)$row['hadir'] ?></span></td>
                    <td><span class="tag terlambat"><?= (int)$row['terlambat'] ?></span></td>
                    <td><span class="tag izin"><?= (int)$row['izin'] ?></span></td>
                    <td><span class="tag sakit"><?= (int)$row['sakit'] ?></span></td>
                    <td><span class="tag alpa"><?= (int)$row['alpa'] ?></span></td>
                    <td><?= $total ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <iframe id="printFrame" style="display:none;width:0;height:0;border:0"></iframe>
  <script>
    const labels = <?= $labels_js ?>;
    const hadir = <?= $hadir_js ?>;
    const terlambat = <?= $terlambat_js ?>;
    const izin = <?= $izin_js ?>;
    const sakit = <?= $sakit_js ?>;
    const alpa = <?= $alpa_js ?>;
    const ctx = document.getElementById('lapChart');
    if (ctx) {
      new Chart(ctx, { type: 'bar', data: { labels: labels, datasets: [ { label:'Hadir', data: hadir, backgroundColor:'#10b981' }, { label:'Terlambat', data: terlambat, backgroundColor:'#f59e0b' }, { label:'Izin', data: izin, backgroundColor:'#06b6d4' }, { label:'Sakit', data: sakit, backgroundColor:'#8b5cf6' }, { label:'Alpa', data: alpa, backgroundColor:'#ef4444' } ] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}} });
    }

    function printReport(){
      // build printable document from main content
      const mainInner = document.querySelector('.main-inner');
      if (!mainInner) return alert('Tidak ada konten untuk dicetak');
      const base = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/,'/');
      const doc = document.getElementById('printFrame').contentWindow.document;
      doc.open();
      doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Print Laporan</title>');
      doc.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">');
      doc.write('<link rel="stylesheet" href="'+base+'../assets/style.css?v=2.1">');
      doc.write('</head><body>');
      // clone content and remove interactive controls
      const clone = mainInner.cloneNode(true);
      clone.querySelectorAll('form, button, input, select, textarea, .manage-holidays, .fc').forEach(n=>n.remove());
      doc.write(clone.outerHTML);
      doc.write('<script>window.onload=function(){ setTimeout(function(){ window.print(); },200); }<\/script>');
      doc.write('</body></html>');
      doc.close();
    }
  </script>
  <script src="../assets/main.js?v=1.4"></script>
</body>
</html>
