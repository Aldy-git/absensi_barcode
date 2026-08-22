<?php
session_start();
require 'config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$role = $_SESSION['role'];

// ----------------------------------------------------
// Direct Barcode Image Download Handler
// ----------------------------------------------------
if (isset($_GET['download']) && !empty($_GET['code'])) {
  $code = trim($_GET['code']);
  $type = trim($_GET['type'] ?? 'EAN13');
  $nama = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($_GET['nama'] ?? 'siswa'));
  $nis = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($_GET['nis'] ?? ''));
  $filename = "barcode_" . strtolower($type) . "_" . ($nis ? $nis . "_" : "") . $nama . ".png";
  $url = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($code) . "&code=" . urlencode($type) . "&dpi=300";

  $context = stream_context_create([
    'http' => [
      'timeout' => 12,
      'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ],
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false
    ]
  ]);

  $imgData = @file_get_contents($url, false, $context);
  if ($imgData !== false && strlen($imgData) > 0) {
    header('Content-Description: File Transfer');
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($imgData));
    echo $imgData;
    exit;
  } else {
    // Fallback: Redirect to the image directly
    header('Location: ' . $url);
    exit;
  }
}

$search = trim($_GET['search'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');
$jurusanFilter = trim($_GET['jurusan'] ?? '');
$barcodeType = trim($_GET['type'] ?? 'EAN13'); // Default EAN-13

// Validasi barcode type
$allowedTypes = [
  'EAN13' => ['label' => 'EAN-13', 'icon' => '🏷️', 'desc' => 'Standar 13 Digit Barcode'],
  'QRCode' => ['label' => 'QR Code', 'icon' => '📱', 'desc' => 'Matrix 2D Code (Cepat & Akurat)'],
  'Code128' => ['label' => 'Code 128', 'icon' => '🔖', 'desc' => 'Alfanumerik Barcode 1D']
];

if (!array_key_exists($barcodeType, $allowedTypes)) {
  $barcodeType = 'EAN13';
}

function buildBarcodeQuery($type, $search, $kelas, $jurusan)
{
  $p = ['type' => $type];
  if ($search !== '') $p['search'] = $search;
  if ($kelas !== '') $p['kelas'] = $kelas;
  if ($jurusan !== '') $p['jurusan'] = $jurusan;
  return 'barcode.php?' . http_build_query($p);
}

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
  <title>Barcode Siswa</title>
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
          <a href="barcode.php" class="active">🔖 Barcode</a>
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
        <div style="margin-bottom:10px"><strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
          <div style="font-size:13px;color:#8898a6"><?= htmlspecialchars($_SESSION['role']) ?></div>
        </div>
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
        <div class="top-hero" style="padding:16px 20px">
          <div>
            <div style="font-size:16px;font-weight:700">Barcode & QR Code Siswa</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.7)">Format aktif: <strong><?= htmlspecialchars($allowedTypes[$barcodeType]['label'] ?? $barcodeType) ?></strong> (<?= htmlspecialchars($allowedTypes[$barcodeType]['desc'] ?? '') ?>)</div>
          </div>
        </div>

        <div style="padding:4px 0">
          <!-- Format Switcher Quick Tabs -->
          <div class="card mb-3 p-3" style="border-radius:12px">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div>
                <span style="font-size:13px;font-weight:700;color:#1e293b;margin-right:8px">Pilih Format Kode:</span>
              </div>
              <div class="btn-group" role="group" aria-label="Pilih Jenis Barcode / QR Code">
                <?php foreach ($allowedTypes as $typeKey => $tInfo):
                  $isActive = ($barcodeType === $typeKey);
                  $url = buildBarcodeQuery($typeKey, $search, $kelasFilter, $jurusanFilter);
                ?>
                  <a href="<?= htmlspecialchars($url) ?>" class="btn <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>" style="font-weight:600;font-size:13px;padding:6px 14px">
                    <?= $tInfo['icon'] ?> <?= htmlspecialchars($tInfo['label']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="card mb-3" style="border-radius:12px;padding:16px">
            <form method="get" class="row g-2 align-items-center">
              <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari nama / NIS siswa..." value="<?= htmlspecialchars($search) ?>">
              </div>
              <div class="col-md-2">
                <select name="type" class="form-select" onchange="this.form.submit()" style="font-weight:600;background:#eff6ff;border-color:#bfdbfe;color:#1e40af">
                  <?php foreach ($allowedTypes as $tKey => $tInfo): ?>
                    <option value="<?= $tKey ?>" <?= $barcodeType === $tKey ? 'selected' : '' ?>>
                      <?= $tInfo['icon'] ?> <?= htmlspecialchars($tInfo['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="kelas" class="form-select">
                  <option value="">Semua Kelas</option>
                  <?php foreach ($kelasList as $k): ?>
                    <option value="<?= htmlspecialchars($k['kelas']) ?>" <?= $kelasFilter === $k['kelas'] ? 'selected' : '' ?>><?= htmlspecialchars($k['kelas']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="jurusan" class="form-select">
                  <option value="">Semua Jurusan</option>
                  <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= htmlspecialchars($j['jurusan']) ?>" <?= $jurusanFilter === $j['jurusan'] ? 'selected' : '' ?>><?= htmlspecialchars($j['jurusan']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
              </div>
              <div class="col-md-1">
                <a href="barcode.php" class="btn btn-outline-secondary w-100">Reset</a>
              </div>
            </form>
          </div>

          <?php if (empty($siswaList)): ?>
            <div class="card text-center p-5" style="border-radius:12px;color:var(--muted)">
              <div style="font-size:40px;margin-bottom:10px">🔍</div>
              <div style="font-weight:700;font-size:16px">Tidak ada data siswa ditemukan</div>
              <div style="font-size:13px;margin-top:4px">Coba ubah kata kunci pencarian atau filter kelas/jurusan.</div>
            </div>
          <?php else: ?>
            <div class="barcode-grid">
              <?php foreach ($siswaList as $s):
                $jurusanStr = !empty($s['jurusan']) ? ' (' . htmlspecialchars($s['jurusan']) . ')' : '';
                $kelasInfo = htmlspecialchars($s['kelas'] ?? '') . $jurusanStr;
                $codeVal = $s['barcode_code'];
                $imgUrl = "https://barcode.tec-it.com/barcode.ashx?data=" . urlencode($codeVal) . "&code=" . urlencode($barcodeType) . "&dpi=96";
                $imgHeight = ($barcodeType === 'QRCode') ? '110px' : '80px';
                $imgWidth = ($barcodeType === 'QRCode') ? '110px' : '100%';
              ?>
                <div class="barcode-card card" style="position:relative">
                  <span class="badge bg-light text-primary border" style="position:absolute;top:10px;right:10px;font-size:11px;font-weight:600">
                    <?= $allowedTypes[$barcodeType]['icon'] ?> <?= htmlspecialchars($allowedTypes[$barcodeType]['label']) ?>
                  </span>
                  <div style="min-height:115px;display:flex;align-items:center;justify-content:center;width:100%;padding-top:12px">
                    <img src="<?= $imgUrl ?>" alt="Barcode <?= htmlspecialchars($s['nama']) ?>" style="max-height:<?= $imgHeight ?>;max-width:<?= $imgWidth ?>;object-fit:contain" loading="lazy">
                  </div>
                  <div class="barcode-name"><?= htmlspecialchars($s['nama']) ?></div>
                  <div class="barcode-sub"><?= htmlspecialchars($s['nis']) ?> · <?= $kelasInfo ?: 'Siswa' ?></div>
                  <div class="barcode-actions">
                    <button type="button" class="btn-download"
                      data-name="<?= htmlspecialchars($s['nama']) ?>"
                      data-nis="<?= htmlspecialchars($s['nis']) ?>"
                      data-code="<?= htmlspecialchars($codeVal) ?>"
                      data-type="<?= htmlspecialchars($barcodeType) ?>"
                      title="Download Gambar Barcode">
                      📥 Download
                    </button>
                    <button type="button" class="btn-print"
                      data-name="<?= htmlspecialchars($s['nama']) ?>"
                      data-nis="<?= htmlspecialchars($s['nis']) ?>"
                      data-code="<?= htmlspecialchars($codeVal) ?>"
                      data-type="<?= htmlspecialchars($barcodeType) ?>"
                      data-kelas="<?= htmlspecialchars($s['kelas'] ?? '') ?>"
                      data-jurusan="<?= htmlspecialchars($s['jurusan'] ?? '') ?>"
                      title="Cetak Barcode">
                      🖨️ Print
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Handle Download Barcode
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.btn-download');
      if (!btn) return;

      const name = btn.getAttribute('data-name') || '';
      const nis = btn.getAttribute('data-nis') || '';
      const code = btn.getAttribute('data-code') || '';
      const type = btn.getAttribute('data-type') || 'EAN13';

      if (!code) {
        alert('Kode barcode tidak valid.');
        return;
      }

      // Visual feedback on button
      const originalText = btn.innerHTML;
      btn.innerHTML = '⏳ Mengunduh...';
      btn.disabled = true;

      // Construct download URL
      const downloadUrl = 'barcode.php?download=1&code=' + encodeURIComponent(code) + '&type=' + encodeURIComponent(type) + '&nama=' + encodeURIComponent(name) + '&nis=' + encodeURIComponent(nis);

      // Trigger download via hidden link
      const link = document.createElement('a');
      link.href = downloadUrl;
      link.setAttribute('download', 'barcode_' + type.toLowerCase() + '_' + (nis ? nis + '_' : '') + name.replace(/[^a-zA-Z0-9_-]/g, '_') + '.png');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      setTimeout(function() {
        btn.innerHTML = originalText;
        btn.disabled = false;
      }, 1500);
    });

    // Handle Print Barcode
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.btn-print');
      if (!btn) return;

      const name = btn.getAttribute('data-name') || '';
      const nis = btn.getAttribute('data-nis') || '';
      const code = btn.getAttribute('data-code') || '';
      const type = btn.getAttribute('data-type') || 'EAN13';
      const kelas = btn.getAttribute('data-kelas') || '';
      const jurusan = btn.getAttribute('data-jurusan') || '';
      const kelasInfo = kelas + (jurusan ? ' (' + jurusan + ')' : '');

      const src = 'https://barcode.tec-it.com/barcode.ashx?data=' + encodeURIComponent(code) + '&code=' + encodeURIComponent(type) + '&dpi=300';

      try {
        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);

        const idoc = iframe.contentWindow.document;
        const html = `<!doctype html>
        <html>
        <head>
          <title>Cetak Barcode - ${name}</title>
          <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; padding: 24px; text-align: center; color: #111; }
            .card-box { display: inline-block; border: 2px solid #000; border-radius: 12px; padding: 18px 24px; max-width: 340px; margin: 0 auto; }
            .school-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; color: #333; }
            img { max-width: 100%; height: auto; display: block; margin: 0 auto; }
            .meta-name { margin-top: 10px; font-size: 16px; font-weight: 700; color: #000; }
            .meta-sub { font-size: 13px; color: #444; margin-top: 2px; }
          </style>
        </head>
        <body>
          <div class="card-box">
            <div class="school-title">KARTU ABSENSI SISWA</div>
            <img src="${src}" alt="barcode">
            <div class="meta-name">${name}</div>
            <div class="meta-sub">NIS: ${nis} ${kelasInfo ? ' · ' + kelasInfo : ''}</div>
          </div>
        </body>
        </html>`;

        idoc.open();
        idoc.write(html);
        idoc.close();

        iframe.onload = function() {
          setTimeout(function() {
            try {
              iframe.contentWindow.focus();
              iframe.contentWindow.print();
            } catch (err) {}
          }, 350);
        };

        setTimeout(function() {
          try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
          } catch (err) {}
        }, 900);
      } catch (ex) {
        alert('Gagal membuka jendela cetak: ' + ex.message);
      }
    });
  </script>
  <script src="assets/main.js?v=1.4"></script>
</body>

</html>