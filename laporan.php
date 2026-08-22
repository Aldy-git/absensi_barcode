<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$kelas = $_GET['kelas'] ?? '';
$jurusan = $_GET['jurusan'] ?? '';

$sql = "SELECT s.nis, s.nama, s.kelas, s.jurusan,
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
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " GROUP BY s.id ORDER BY s.nama";
$result = $conn->query($sql);
$laporan = $result->fetch_all(MYSQLI_ASSOC);

// Export CSV if requested
if (isset($_GET['export']) && $_GET['export'] == '1') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=laporan_absensi.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['NIS','Nama','Kelas','Jurusan','Hadir','Terlambat','Izin','Sakit','Alpa','Total']);
  foreach ($laporan as $r) {
    $total = (int)$r['hadir'] + (int)$r['terlambat'] + (int)$r['izin'] + (int)$r['sakit'] + (int)$r['alpa'];
    fputcsv($out, [ $r['nis'] ?? '', $r['nama'], $r['kelas'], $r['jurusan'], $r['hadir'], $r['terlambat'], $r['izin'], $r['sakit'], $r['alpa'], $total ]);
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
          <a href="laporan.php" class="active">📊 Laporan</a>
          <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="users.php">🔒 Pengguna</a>
            <a href="holidays_admin.php">📅 Kelola Libur</a>
          <?php endif; ?>
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
            <span class="user-role-badge"><?= htmlspecialchars($_SESSION['role'] ?? 'Petugas') ?></span>
          </div>
          <a href="logout.php" class="btn-logout-header" title="Keluar dari sistem">Keluar</a>
        </div>
      </header>
      <div class="main-inner">
        <div class="top-hero">
          <div>
            <div style="font-size:16px;font-weight:700">Laporan</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Ringkasan kehadiran</div>
          </div>
        </div>

        <div class="card" style="padding:16px;margin-top:12px">
          <form method="get" class="row g-2 align-items-center">
            <div class="col-md-2"><label class="form-label" style="font-size:13px">Dari</label><input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>"></div>
            <div class="col-md-2"><label class="form-label" style="font-size:13px">Sampai</label><input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>"></div>
            <div class="col-md-2"><label class="form-label" style="font-size:13px">Kelas</label><select name="kelas" class="form-select"><option value="">Semua</option><?php foreach ($kelasList as $k): ?><option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $kelas === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" style="font-size:13px">Jurusan</label><select name="jurusan" class="form-select"><option value="">Semua</option><?php foreach ($jurusanList as $j): ?><option value="<?= htmlspecialchars($j['jurusan']) ?>" <?= $jurusan === $j['jurusan'] ? 'selected' : '' ?>><?= htmlspecialchars($j['jurusan']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2" style="display:flex;gap:8px">
              <button type="submit" name="export" value="1" class="btn btn-success" style="align-self:end">Export CSV</button>
              <button type="button" class="btn btn-outline-secondary" onclick="printReport()" style="align-self:end">Print</button>
            </div>
            <div class="col-md-2" style="text-align:right;align-self:end"><button class="btn btn-primary">Filter</button></div>
          </form>
        </div>

        <div class="stats-grid" style="margin-top:12px">
          <div class="stat-card stat success">
            <h6>Hadir</h6>
            <div style="font-size:28px;font-weight:700;color:#059669"><?= $totalHadir ?></div>
          </div>
          <div class="stat-card stat warn">
            <h6>Terlambat</h6>
            <div style="font-size:28px;font-weight:700;color:#b45309"><?= $totalTerlambat ?></div>
          </div>
          <div class="stat-card stat" style="background:linear-gradient(90deg,#eef2ff,#f0f9ff)">
            <h6>Izin</h6>
            <div style="font-size:28px;font-weight:700;color:#0ea5a0"><?= $totalIzin ?></div>
          </div>
          <div class="stat-card stat" style="background:linear-gradient(90deg,#f3e8ff,#faf5ff)">
            <h6>Sakit</h6>
            <div style="font-size:28px;font-weight:700;color:#6d28d9"><?= $totalSakit ?></div>
          </div>
          <div class="stat-card stat" style="background:linear-gradient(90deg,#fee2e2,#fff1f2)">
            <h6>Alpa</h6>
            <div style="font-size:28px;font-weight:700;color:#b91c1c"><?= $totalAlpa ?></div>
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
                <tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alpa</th><th>Total</th></tr>
              </thead>
              <tbody>
                <?php foreach ($laporan as $row): $total = (int)$row['hadir']+(int)$row['terlambat']+(int)$row['izin']+(int)$row['sakit']+(int)$row['alpa']; ?>
                  <tr>
                    <td><?= htmlspecialchars($row['nis'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['kelas']) ?></td>
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
      doc.write('<link rel="stylesheet" href="'+base+'assets/style.css">');
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
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
