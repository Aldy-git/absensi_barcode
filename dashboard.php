<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

$totalSiswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM siswa"))['total'];
$hadirHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'hadir'"))['total'];
$terlambatHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'terlambat'"))['total'];
$izinHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'izin'"))['total'];
$sakitHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'sakit'"))['total'];
$alpaHariIni = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM absensi WHERE tanggal = CURDATE() AND status = 'alpa'"))['total'];

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
  <link href="assets/style.css?v=1.4" rel="stylesheet">
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
          <a href="siswa.php">👥 Data Siswa</a>
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
        <div class="top-hero">
          <div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Total Siswa Aktif</div>
            <div style="font-size:28px;font-weight:700;"><?= $totalSiswa ?></div>
          </div>
          <div style="text-align:right;color:rgba(255,255,255,0.85)">
            <div style="font-size:13px">Absensi hari ini</div>
            <div style="font-size:20px;font-weight:700"><?= $hadirHariIni ?> siswa</div>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card card">
            <h6>Hadir</h6>
            <div style="font-size:22px;font-weight:700;color:#059669"><?= $hadirHariIni ?></div>
            <div style="color:var(--muted);font-size:13px">Hari ini</div>
          </div>
          <div class="stat-card card">
            <h6>Terlambat</h6>
            <div style="font-size:22px;font-weight:700;color:#b45309"><?= $terlambatHariIni ?></div>
            <div style="color:var(--muted);font-size:13px">Hari ini</div>
          </div>
          <div class="stat-card card">
            <h6>Izin</h6>
            <div style="font-size:22px;font-weight:700;color:#0ea5a0"><?= $izinHariIni ?></div>
            <div style="color:var(--muted);font-size:13px">Hari ini</div>
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
                $recent = $conn->query("SELECT a.*, s.nama FROM absensi a JOIN siswa s ON a.siswa_id = s.id ORDER BY a.id DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
              ?>
              <?php foreach ($recent as $r):
                $sclass = strtolower(str_replace(' ','', $r['status']));
              ?>
                <div class="recent-item">
                  <div class="avatar"><?= strtoupper(substr($r['nama'],0,1)) ?></div>
                  <div style="flex:1">
                    <div style="font-weight:700"><?= htmlspecialchars($r['nama']) ?></div>
                    <div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($r['tanggal']) ?> · <?= htmlspecialchars($r['jam_scan']) ?></div>
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
              <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:8px">
                <div style="font-size:13px;color:var(--muted)">Minggu, <?= date('d F Y') ?></div>
              </div>
              <div id="calendar" style="background:transparent;border-radius:8px"></div>
              <!-- add holiday modal -->
              <div id="holidayModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.4);align-items:center;justify-content:center">
                <div style="background:#fff;padding:16px;border-radius:8px;width:320px">
                  <h6>Tambahkan Hari Libur</h6>
                  <form id="holidayForm">
                    <div style="margin:8px 0"><label>Nama</label><input id="holidayName" name="nama" class="form-control"></div>
                    <div style="margin:8px 0"><label>Tanggal</label><input id="holidayDate" name="tanggal" type="date" class="form-control"></div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
                      <button type="button" id="holidayCancel" class="btn btn-outline-secondary">Batal</button>
                      <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                  </form>
                </div>
                </div>

                <?php
                  // fetch DB holidays for this month
                  $res = $conn->query("SELECT tanggal, nama, type FROM holidays WHERE MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE()) ORDER BY tanggal");
                  $dbH = [];
                  while ($r = mysqli_fetch_assoc($res)) { $dbH[$r['tanggal']] = $r; }
                  // build month holidays: include DB holidays only (exclude weekends)
                  $first = date('Y-m-01'); $last = date('Y-m-t');
                  $period = new DatePeriod(new DateTime($first), new DateInterval('P1D'), (new DateTime($last))->modify('+1 day'));
                  $monthHolidays = [];
                  foreach ($period as $d) {
                    $ds = $d->format('Y-m-d');
                    // include DB holiday entries only; skip weekend-only items (Sat/Sun)
                    if (isset($dbH[$ds])) {
                      $w = (int)$d->format('w');
                      if ($w === 0 || $w === 6) continue; // skip Sundays/Saturdays in the month list
                      $monthHolidays[] = $dbH[$ds];
                    }
                  }
                ?>

                <div class="calendar-legend" style="display:flex;gap:12px;align-items:center;margin-top:10px">
                  <div class="legend-item"><span class="legend-dot national"></span><div style="margin-left:6px;font-size:13px;color:var(--muted)">Libur Nasional</div></div>
                  <div class="legend-item"><span class="legend-dot school"></span><div style="margin-left:6px;font-size:13px;color:var(--muted)">Libur Sekolah</div></div>
                  <a href="holidays_admin.php" class="manage-holidays">Kelola Libur Sekolah</a>
                </div>

                <div class="month-holidays">
                  <h6>Hari libur bulan ini</h6>
                  <?php if (empty($monthHolidays)): ?>
                    <div style="color:var(--muted)">Tidak ada hari libur bulan ini.</div>
                  <?php else: foreach ($monthHolidays as $h): $dnum = date('j', strtotime($h['tanggal']));
                      $ttype = ($h['type'] === 'national') ? 'national' : 'school';
                      $tlabel = ($ttype === 'national') ? 'Libur' : 'Sekolah';
                  ?>
                    <div class="holiday-item">
                      <div class="holiday-date-pill"><?= $dnum ?></div>
                      <div class="holiday-title"><?= htmlspecialchars($h['nama']) ?><div style="font-size:12px;color:var(--muted)"><?= date('l', strtotime($h['tanggal'])) ?></div></div>
                      <div class="holiday-badge <?= $ttype ?>"><?= $tlabel ?></div>
                    </div>
                  <?php endforeach; endif; ?>
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
        height: 250,
        locale: 'id',
        headerToolbar: { left: 'prev,next', center: 'title', right: '' },
        dayMaxEventRows: 3,
        selectable: false,
        eventSources: [
          { url: 'holidays.php' },
          function(fetchInfo, successCallback, failureCallback) {
            // generate Saturday events as school holidays (auto)
            try {
              const events = [];
              const cur = new Date(fetchInfo.start);
              const end = new Date(fetchInfo.end);
              function localDateString(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2,'0');
                const day = String(d.getDate()).padStart(2,'0');
                return y + '-' + m + '-' + day;
              }
              while (cur <= end) {
                if (cur.getDay() === 6) { // Saturday
                  // mark as weekend event (no purple dot)
                  const dstr = localDateString(cur);
                  events.push({ id: 'sat-'+dstr, start: dstr, title: 'Akhir Pekan', allDay: true, extendedProps: { type: 'weekend', auto: true } });
                }
                cur.setDate(cur.getDate() + 1);
              }
              successCallback(events);
            } catch (e) { failureCallback(e); }
          }
        ],
        dateClick: function(info) {
          if (!isAdmin) { alert('Hanya admin yang dapat menambah hari libur.'); return; }
          document.getElementById('holidayModal').style.display = 'flex';
          document.getElementById('holidayDate').value = info.dateStr;
          document.getElementById('holidayName').focus();
        },
        eventClick: function(info) {
          const ev = info.event;
          const type = ev.extendedProps.type || '';
          // auto-generated weekend events are informational
          if (ev.extendedProps && ev.extendedProps.auto && type === 'weekend') { alert('Hari akhir pekan.'); return; }
          // auto-generated events (like other auto holidays) are not deletable
          if (ev.extendedProps && ev.extendedProps.auto) { alert('Libur ini otomatis dan tidak dapat dihapus.'); return; }
          if (!isAdmin) return;
          if (type === 'national') { alert('Hari nasional tidak dapat dihapus.'); return; }
          if (confirm('Hapus hari libur "' + ev.title + '" pada ' + ev.startStr + ' ?')) {
            const form = new FormData(); form.append('action','delete'); form.append('id', ev.id);
            fetch('holidays.php',{method:'POST',body:form}).then(r=>r.json()).then(j=>{ if (j.success) info.event.remove(); else alert('Gagal menghapus'); }).catch(()=>alert('Gagal menghapus'));
          }
        },
        eventContent: function(arg) {
          const type = arg.event.extendedProps.type || 'school';
          if (type === 'weekend') return { html: '' }; // no dot for weekend
          return { html: '<span class="fc-event-dot ' + type + '"></span>' };
        },
        eventDidMount: function(info) {
          // keep container minimal; dot is rendered by eventContent. No extra text.
          try {
            info.el.style.padding = '2px 0';
            info.el.style.background = 'transparent';
            info.el.style.border = '0';
          } catch(e){}
        }
      });
      calendar.render();

      // legend is rendered server-side

      // modal controls
      document.getElementById('holidayCancel').addEventListener('click', function(){ document.getElementById('holidayModal').style.display='none'; });
      document.getElementById('holidayForm').addEventListener('submit', function(e){
        e.preventDefault();
        const form = new FormData(e.target);
        form.append('action','add');
        fetch('holidays.php',{method:'POST',body:form}).then(r=>r.json()).then(js=>{
          if (js.success) { calendar.refetchEvents(); document.getElementById('holidayModal').style.display='none'; }
          else alert('Gagal menambahkan libur');
        }).catch(()=>alert('Gagal menambahkan libur'));
      });
    });
  </script>
  <script src="assets/main.js?v=1.4"></script>
</body>
</html>
