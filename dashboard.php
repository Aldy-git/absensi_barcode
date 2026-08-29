<?php
session_start();
require_once __DIR__ . '/config/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

$todayHoliday = getHolidayInfo(date('Y-m-d'), $conn);

$totalSiswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa"))['total'];
$totalSiswaPagi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa WHERE shift = 'pagi' AND status = 'aktif'"))['total'] ?? 0;
$totalSiswaSiang = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa WHERE shift = 'siang' AND status = 'aktif'"))['total'] ?? 0;

$hadirHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'hadir'"))['total'];
$terlambatHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'terlambat'"))['total'];
$izinHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'izin'"))['total'];
$sakitHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'sakit'"))['total'];
$alpaHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'alpa'"))['total'];

$pagiHadirHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND shift = 'pagi' AND status = 'hadir'"))['total'] ?? 0;
$pagiTerlambatHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND shift = 'pagi' AND status = 'terlambat'"))['total'] ?? 0;
$siangHadirHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND shift = 'siang' AND status = 'hadir'"))['total'] ?? 0;
$siangTerlambatHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND shift = 'siang' AND status = 'terlambat'"))['total'] ?? 0;

$currentShift = detectCurrentShift();
$shiftPagiRules = getShiftRules('pagi', date('Y-m-d'));
$shiftSiangRules = getShiftRules('siang', date('Y-m-d'));

// Rekap 7 hari terakhir per status
$labels = [];
$hadirData = $terlambatData = $izinData = $sakitData = $alpaData = [];
$dates = [];
for ($i = 6; $i >= 0; $i--) {
  $d = date('Y-m-d', strtotime("-$i days"));
  $dates[] = $d;
  $labels[] = date('D, j M', strtotime($d));
  $hadirData[$d] = 0; $terlambatData[$d] = 0; $izinData[$d] = 0; $sakitData[$d] = 0; $alpaData[$d] = 0;
}

$start = $dates[0]; $end = end($dates);
$sql = "SELECT tanggal, status, COUNT(*) AS cnt FROM absensi WHERE tanggal BETWEEN '$start' AND '$end' GROUP BY tanggal, status";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
  $t = $row['tanggal']; $s = $row['status']; $c = (int)$row['cnt'];
  if (!isset($hadirData[$t])) continue;
  // Hari libur tidak dianggap masuk / dikosongkan
  if (getHolidayInfo($t, $conn)) continue;

  if ($s === 'hadir') $hadirData[$t] = $c;
  if ($s === 'terlambat') $terlambatData[$t] = $c;
  if ($s === 'izin') $izinData[$t] = $c;
  if ($s === 'sakit') $sakitData[$t] = $c;
  if ($s === 'alpa') $alpaData[$t] = $c;
}

// prepare arrays for JS
$labels_js = json_encode(array_values($labels));
$hadir_js = json_encode(array_values($hadirData));
$terlambat_js = json_encode(array_values($terlambatData));
$izin_js = json_encode(array_values($izinData));
$sakit_js = json_encode(array_values($sakitData));
$alpa_js = json_encode(array_values($alpaData));
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Absensi Barcode</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=1.7" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
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
          <a href="dashboard.php" class="active">🏠 Dashboard</a>
          <a href="siswa/index.php">👥 Data Siswa</a>
          <a href="absensi/barcode.php">🔖 Barcode</a>
          <a href="absensi/scan.php">📷 Scan Absensi</a>
          <a href="absensi/manual.php">✍️ Absensi Manual</a>
          <a href="absensi/riwayat.php">📜 Riwayat</a>
          <a href="absensi/laporan.php">📊 Laporan</a>
          <?php if ($role === 'admin'): ?>
            <a href="users/index.php">🔒 Pengguna</a>
            <a href="holidays/index.php">📅 Kelola Libur</a>
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
        <div class="top-hero">
          <div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Total Siswa Aktif</div>
            <div style="font-size:28px;font-weight:700;"><?= $totalSiswa ?> <span style="font-size:13px;font-weight:400;opacity:0.85">(🌅 <?= $totalSiswaPagi ?> Pagi · ☀️ <?= $totalSiswaSiang ?> Siang)</span></div>
          </div>
          <div style="text-align:right;color:rgba(255,255,255,0.85)">
            <?php if ($todayHoliday): ?>
              <div style="font-size:13px">Status hari ini</div>
              <div style="font-size:18px;font-weight:700;color:#fde047"><?= htmlspecialchars($todayHoliday['nama']) ?></div>
            <?php else: ?>
              <div style="font-size:13px">Absensi hari ini</div>
              <div style="font-size:20px;font-weight:700"><?= $hadirHariIni + $terlambatHariIni ?> / <?= $totalSiswa ?> siswa</div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($todayHoliday): ?>
          <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert" style="border-radius:10px;margin-top:12px">
            <span style="font-size:18px">📅</span>
            <div>
              <strong>Pemberitahuan Hari Libur:</strong> Hari ini adalah <strong><?= htmlspecialchars($todayHoliday['label']) ?></strong>. Sistem tidak mencatat kehadiran (absensi dikosongkan).
            </div>
          </div>
        <?php endif; ?>

        <div class="stats-grid">
          <div class="stat-card card">
            <h6>Hadir Tepat Waktu</h6>
            <div style="font-size:22px;font-weight:700;color:#059669"><?= $hadirHariIni ?></div>
            <div style="color:var(--muted);font-size:12px;margin-top:2px">🌅 Pagi: <?= $pagiHadirHariIni ?> · ☀️ Siang: <?= $siangHadirHariIni ?></div>
          </div>
          <div class="stat-card card">
            <h6>Terlambat</h6>
            <div style="font-size:22px;font-weight:700;color:#b45309"><?= $terlambatHariIni ?></div>
            <div style="color:var(--muted);font-size:12px;margin-top:2px">🌅 Pagi: <?= $pagiTerlambatHariIni ?> · ☀️ Siang: <?= $siangTerlambatHariIni ?></div>
          </div>
          <div class="stat-card card">
            <h6>Izin / Sakit / Alpa</h6>
            <div style="font-size:22px;font-weight:700;color:#0ea5a0"><?= $izinHariIni + $sakitHariIni + $alpaHariIni ?></div>
            <div style="color:var(--muted);font-size:12px;margin-top:2px">Izin: <?= $izinHariIni ?> · Sakit: <?= $sakitHariIni ?> · Alpa: <?= $alpaHariIni ?></div>
          </div>
        </div>

        <!-- Shift Schedule Quick Widget -->
        <div class="card mb-3" style="border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;padding:12px 16px">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div style="font-size:13px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:6px">
              <span>⏱️</span>
              <span>Jadwal Absensi Shift Sekolah (<?= htmlspecialchars($shiftPagiRules['hari']) ?>)</span>
            </div>
            <div>
              <span class="badge" style="background:<?= $currentShift === 'siang' ? '#fef3c7' : '#e0f2fe' ?>;color:<?= $currentShift === 'siang' ? '#b45309' : '#0369a1' ?>;font-size:11px;padding:4px 10px;border:1px solid <?= $currentShift === 'siang' ? '#fde68a' : '#bae6fd' ?>">
                Shift Sekarang: <strong><?= $currentShift === 'siang' ? '☀️ Shift Siang' : '🌅 Shift Pagi' ?></strong>
              </span>
            </div>
          </div>
          <div class="row g-2" style="font-size:12px">
            <div class="col-md-6">
              <div style="background:#fff;padding:8px 12px;border-radius:8px;border:1px solid #e0f2fe">
                <span style="font-weight:700;color:#0369a1">🌅 Shift Pagi</span>: Buka <strong>06.00</strong> &nbsp;|&nbsp; Masuk <strong>07.00</strong> &nbsp;|&nbsp; Pulang <strong><?= $shiftPagiRules['jam_pulang_str'] ?></strong>
              </div>
            </div>
            <div class="col-md-6">
              <div style="background:#fff;padding:8px 12px;border-radius:8px;border:1px solid #fef3c7">
                <span style="font-weight:700;color:#b45309">☀️ Shift Siang</span>: Buka <strong>12.00</strong> &nbsp;|&nbsp; Masuk <strong><?= $shiftSiangRules['jam_masuk_str'] ?></strong> &nbsp;|&nbsp; Pulang <strong>17.00 WIB</strong>
              </div>
            </div>
          </div>
        </div>

        <div class="content-grid">
          <div>
            <div class="chart-card card">
              <h6>Rekap 7 Hari Terakhir</h6>
              <div class="chart-container">
                <canvas id="chart7"></canvas>
              </div>
            </div>
            <div class="chart-card card" style="margin-top:12px">
              <h6>Tren Kehadiran</h6>
              <div class="chart-container">
                <canvas id="chartTrend"></canvas>
              </div>
            </div>

            <div class="recent-list" style="margin-top:12px">
              <h6>Absensi Terbaru</h6>
              <?php
                $recent = $conn->query("SELECT a.*, s.nama, s.shift as siswa_shift FROM absensi a JOIN siswa s ON a.siswa_id = s.id ORDER BY a.id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
              ?>
              <?php foreach ($recent as $r):
                $sclass = strtolower(str_replace(' ','', $r['status']));
                $rShift = ($r['shift'] ?? ($r['siswa_shift'] ?? 'pagi')) === 'siang' ? '☀️ Siang' : '🌅 Pagi';
                $rShiftBadge = ($r['shift'] ?? ($r['siswa_shift'] ?? 'pagi')) === 'siang' ? 'background:#fef3c7;color:#b45309;border:1px solid #fde68a' : 'background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd';
              ?>
                <div class="recent-item">
                  <div class="avatar"><?= strtoupper(substr($r['nama'],0,1)) ?></div>
                  <div style="flex:1">
                    <div style="font-weight:700;display:flex;align-items:center;gap:6px">
                      <span><?= htmlspecialchars($r['nama']) ?></span>
                      <span class="badge" style="<?= $rShiftBadge ?>;font-size:10px;padding:2px 6px"><?= $rShift ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['tanggal']) ?> · <?= htmlspecialchars(substr($r['jam_scan'] ?? '', 0, 5)) ?> WIB</div>
                  </div>
                  <div>
                    <span class="tag <?= htmlspecialchars($sclass) ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <aside class="right-panel">
            <div class="calendar-card card">
              <div class="calendar-header-meta">
                <div class="calendar-header-title">
                  <span class="calendar-icon">📅</span>
                  <span>Kalender Akademik</span>
                </div>
                <div class="calendar-today-badge">
                  <?= formatTanggalIndo(date('Y-m-d')) ?>
                </div>
              </div>

              <div id="calendar"></div>

              <!-- Legend / Keterangan Warna Libur -->
              <div class="calendar-legend-card">
                <div class="calendar-legend-items">
                  <div class="legend-item" title="Hari libur resmi nasional">
                    <span class="legend-dot national"></span>
                    <span>Libur Nasional</span>
                  </div>
                  <div class="legend-item" title="Libur khusus kegiatan sekolah">
                    <span class="legend-dot school"></span>
                    <span>Libur Sekolah</span>
                  </div>
                </div>
                <div class="calendar-legend-actions">
                  <button type="button" class="btn-manage-holidays" id="btnSyncHolidays" title="Update dan sinkronkan hari libur nasional untuk tahun yang aktif">
                    <span>🔄</span> <span>Update Libur Nasional</span>
                  </button>
                  <?php if ($role === 'admin'): ?>
                    <a href="holidays/index.php" class="btn-manage-holidays" title="Kelola libur sekolah dan tanggal libur">
                      <span>⚙️</span> <span>Kelola Libur</span>
                    </a>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Modal Tambah Hari Libur (Admin) -->
              <div id="holidayModal" class="custom-modal-overlay">
                <div class="custom-modal-card">
                  <div class="custom-modal-header">
                    <div class="custom-modal-title">
                      <span>📅</span> Tambah Libur Sekolah
                    </div>
                    <button type="button" class="custom-modal-close" id="holidayCancelBtn" aria-label="Tutup">&times;</button>
                  </div>
                  <form id="holidayForm">
                    <div class="custom-modal-body">
                      <div class="mb-3 text-start">
                        <label class="form-label" for="holidayDate">Tanggal Libur</label>
                        <input id="holidayDate" name="tanggal" type="date" class="form-control" required>
                      </div>
                      <div class="mb-2 text-start">
                        <label class="form-label" for="holidayName">Nama / Keterangan Libur</label>
                        <input id="holidayName" name="nama" type="text" class="form-control" placeholder="Contoh: Libur Semester Ganjil" required>
                      </div>
                    </div>
                    <div class="custom-modal-footer">
                      <button type="button" id="holidayCancel" class="btn btn-light border btn-sm">Batal</button>
                      <button type="submit" class="btn btn-primary btn-sm">Simpan Libur</button>
                    </div>
                  </form>
                </div>
              </div>

              <?php
                // Sync national holidays if needed so list is always populated
                syncNationalHolidays($conn);

                // Fetch DB holidays for this month (excluding weekends from DB list)
                $res = $conn->query("SELECT tanggal, nama, type FROM holidays WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE()) ORDER BY tanggal");
                $dbH = [];
                if ($res) {
                  while ($r = mysqli_fetch_assoc($res)) { $dbH[$r['tanggal']] = $r; }
                }

                $first = date('Y-m-01'); $last = date('Y-m-t');
                $period = new DatePeriod(new DateTime($first), new DateInterval('P1D'), (new DateTime($last))->modify('+1 day'));
                $monthHolidays = [];
                foreach ($period as $d) {
                  $ds = $d->format('Y-m-d');
                  if (isset($dbH[$ds])) {
                    $w = (int)$d->format('w');
                    if ($w === 0 || $w === 6) continue; // skip Sundays/Saturdays
                    $monthHolidays[] = $dbH[$ds];
                  }
                }

                $currentMonthName = getIndonesianMonthName((int)date('n')) . ' ' . date('Y');
              ?>

              <div class="month-holidays">
                <div class="month-holidays-header">
                  <h6>Hari Libur Bulan Ini</h6>
                  <span class="badge-month-pill"><?= $currentMonthName ?></span>
                </div>
                <?php if (empty($monthHolidays)): ?>
                  <div class="empty-holidays-box">
                    <span class="empty-holidays-icon">🏖️</span>
                    <span>Tidak ada hari libur di luar akhir pekan bulan ini.</span>
                  </div>
                <?php else: ?>
                  <div class="holidays-scroll-list">
                    <?php foreach ($monthHolidays as $h):
                      $dnum = date('j', strtotime($h['tanggal']));
                      $ttype = ($h['type'] === 'national') ? 'national' : 'school';
                      $tlabel = ($ttype === 'national') ? 'Nasional' : 'Sekolah';
                      $fullDateIndo = formatTanggalIndo($h['tanggal'], true);
                    ?>
                      <div class="holiday-item <?= $ttype ?>">
                        <div class="holiday-date-pill <?= $ttype ?>">
                          <span class="date-num"><?= $dnum ?></span>
                          <span class="date-month"><?= substr(getIndonesianMonthName((int)date('n', strtotime($h['tanggal']))), 0, 3) ?></span>
                        </div>
                        <div class="holiday-title">
                          <div class="holiday-main-name" title="<?= htmlspecialchars($h['nama']) ?>"><?= htmlspecialchars($h['nama']) ?></div>
                          <div class="holiday-sub-date"><?= $fullDateIndo ?></div>
                        </div>
                        <div class="holiday-badge <?= $ttype ?>"><?= $tlabel ?></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="chart-card card">
              <h6>Ringkasan Hari Ini</h6>
              <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                <div style="display:flex;justify-content:space-between"><div>Hadir</div><div><?= $hadirHariIni ?></div></div>
                <div style="display:flex;justify-content:space-between"><div>Terlambat</div><div><?= $terlambatHariIni ?></div></div>
                <div style="display:flex;justify-content:space-between"><div>Izin</div><div><?= $izinHariIni ?></div></div>
                <div style="display:flex;justify-content:space-between"><div>Sakit</div><div><?= $sakitHariIni ?></div></div>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"></script>
  <script>
    const labels = <?= $labels_js ?>;
    const hadir = <?= $hadir_js ?>;
    const terlambat = <?= $terlambat_js ?>;
    const izin = <?= $izin_js ?>;
    const sakit = <?= $sakit_js ?>;
    const alpa = <?= $alpa_js ?>;

    const ctx7 = document.getElementById('chart7');
    if (ctx7) {
      new Chart(ctx7, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            {label:'Hadir', data: hadir, backgroundColor:'#10b981'},
            {label:'Terlambat', data: terlambat, backgroundColor:'#f59e0b'},
            {label:'Izin', data: izin, backgroundColor:'#06b6d4'},
            {label:'Sakit', data: sakit, backgroundColor:'#8b5cf6'},
            {label:'Alpa', data: alpa, backgroundColor:'#ef4444'}
          ]
        },
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
      });
    }

    const ctxT = document.getElementById('chartTrend');
    if (ctxT) {
      new Chart(ctxT, {type:'line',data:{labels:labels,datasets:[{label:'Hadir',data:hadir,borderColor:'#06b6d4',fill:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
    }

    // FullCalendar init
    document.addEventListener('DOMContentLoaded', function() {
      const calendarEl = document.getElementById('calendar');
      if (!calendarEl) return;
      const isAdmin = <?= ($role === 'admin') ? 'true' : 'false' ?>;

      const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'id',
        height: 'auto',
        contentHeight: 'auto',
        aspectRatio: 1.18,
        headerToolbar: {
          left: 'prev,next',
          center: 'title',
          right: 'today'
        },
        buttonText: {
          today: 'Hari Ini'
        },
        fixedWeekCount: false,
        showNonCurrentDates: true,
        dayMaxEvents: 2,
        datesSet: function(info) {
          // Ketika user berpindah bulan atau tahun, pastikan event holiday ter-sync
          const curYear = info.view.currentStart ? info.view.currentStart.getFullYear() : new Date().getFullYear();
          const startYear = info.start.getFullYear();
          const endYear = info.end.getFullYear();
          // FullCalendar will automatically fetch includes/holidays.php with ?start=...&end=...
        },
        eventSources: [
          { url: 'includes/holidays.php' }
        ],
        dateClick: function(info) {
          if (!isAdmin) return;
          const modal = document.getElementById('holidayModal');
          if (!modal) return;
          document.getElementById('holidayDate').value = info.dateStr;
          document.getElementById('holidayName').value = '';
          modal.classList.add('show');
          setTimeout(() => {
            document.getElementById('holidayName').focus();
          }, 100);
        },
        eventClick: function(info) {
          const ev = info.event;
          const type = ev.extendedProps?.type || '';
          if (type === 'national') {
            alert('🎉 ' + ev.title + '\n📅 ' + ev.startStr + ' (Libur Nasional)');
            return;
          }
          if (isAdmin) {
            if (confirm('Hapus hari libur "' + ev.title + '" (' + ev.startStr + ')?')) {
              const form = new FormData();
              form.append('action', 'delete');
              form.append('id', ev.id);
              fetch('includes/holidays.php', { method: 'POST', body: form })
                .then(r => r.json())
                .then(j => {
                  if (j.success) {
                    ev.remove();
                    window.location.reload();
                  } else {
                    alert('Gagal menghapus libur');
                  }
                })
                .catch(() => alert('Gagal menghapus libur'));
            }
          } else {
            alert('📌 ' + ev.title + '\n📅 ' + ev.startStr + ' (Libur Sekolah)');
          }
        },
        eventContent: function(arg) {
          const type = arg.event.extendedProps?.type || 'school';
          const title = arg.event.title ? arg.event.title.replace(/"/g, '&quot;') : '';
          return {
            html: '<span class="fc-event-dot ' + type + '" title="' + title + '"></span>'
          };
        }
      });

      calendar.render();

      // Tombol Sinkronisasi / Update Libur Nasional
      const btnSyncHolidays = document.getElementById('btnSyncHolidays');
      if (btnSyncHolidays) {
        btnSyncHolidays.addEventListener('click', function() {
          const viewDate = calendar.getDate();
          const activeYear = viewDate ? viewDate.getFullYear() : new Date().getFullYear();
          const origText = btnSyncHolidays.innerHTML;
          btnSyncHolidays.innerHTML = '⏳ Mengupdate (' + activeYear + ')...';
          btnSyncHolidays.disabled = true;

          const form = new FormData();
          form.append('action', 'sync_national');
          form.append('year', activeYear);
          form.append('force', '1');

          fetch('includes/holidays.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => {
              btnSyncHolidays.innerHTML = origText;
              btnSyncHolidays.disabled = false;
              if (data.success) {
                alert('✅ ' + (data.message || 'Hari libur nasional berhasil diperbarui.'));
                calendar.refetchEvents();
                setTimeout(() => window.location.reload(), 500);
              } else {
                alert('Gagal memperbarui libur nasional: ' + (data.error || 'Terjadi kesalahan'));
              }
            })
            .catch(err => {
              btnSyncHolidays.innerHTML = origText;
              btnSyncHolidays.disabled = false;
              alert('Gagal menghubungi server untuk sinkronisasi libur.');
            });
        });
      }

      // Modal controls
      const modal = document.getElementById('holidayModal');
      const closeBtn = document.getElementById('holidayCancelBtn');
      const cancelBtn = document.getElementById('holidayCancel');
      function hideModal() {
        if (modal) modal.classList.remove('show');
      }
      if (closeBtn) closeBtn.addEventListener('click', hideModal);
      if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
      if (modal) {
        modal.addEventListener('click', function(e) {
          if (e.target === modal) hideModal();
        });
      }

      const holidayForm = document.getElementById('holidayForm');
      if (holidayForm) {
        holidayForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const form = new FormData(e.target);
          form.append('action', 'add');
          fetch('includes/holidays.php', { method: 'POST', body: form })
            .then(r => r.json())
            .then(js => {
              if (js.success) {
                hideModal();
                calendar.refetchEvents();
                window.location.reload();
              } else {
                alert(js.error || 'Gagal menambahkan libur');
              }
            })
            .catch(() => alert('Gagal menambahkan libur'));
        });
      }
    });

  </script>
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
