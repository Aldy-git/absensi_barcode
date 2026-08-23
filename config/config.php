<?php
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "absensi_barcode";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

/**
 * Memeriksa apakah tanggal tertentu adalah hari libur (Minggu, Sabtu, atau libur nasional/sekolah di database).
 *
 * @param string $tanggal Format 'Y-m-d'
 * @param mysqli|null $conn
 * @param bool $includeWeekend Default true (Minggu & Sabtu dianggap libur)
 * @return array|null Info hari libur atau null jika bukan hari libur
 */
function getHolidayInfo($tanggal, $conn = null, $includeWeekend = true) {
    if (empty($tanggal)) return null;

    $timestamp = strtotime($tanggal);
    if (!$timestamp) return null;

    $dayOfWeek = (int)date('w', $timestamp); // 0 = Minggu, 6 = Sabtu

    // Cek di database holidays jika koneksi database ada
    if ($conn) {
        $stmt = $conn->prepare("SELECT nama, type FROM holidays WHERE tanggal = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $tanggal);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $typeLabel = ($row['type'] === 'national') ? 'Libur Nasional' : 'Libur Sekolah';
                return [
                    'is_holiday' => true,
                    'nama' => $row['nama'],
                    'type' => $row['type'],
                    'label' => $typeLabel . ' (' . $row['nama'] . ')'
                ];
            }
        }
    }

    // Cek akhir pekan (Minggu / Sabtu)
    if ($includeWeekend) {
        if ($dayOfWeek === 0) {
            return [
                'is_holiday' => true,
                'nama' => 'Hari Minggu',
                'type' => 'weekend',
                'label' => 'Akhir Pekan (Hari Minggu)'
            ];
        } elseif ($dayOfWeek === 6) {
            return [
                'is_holiday' => true,
                'nama' => 'Hari Sabtu',
                'type' => 'weekend',
                'label' => 'Akhir Pekan (Hari Sabtu)'
            ];
        }
    }

    return null;
}

/**
 * Mengubah nama bulan angka (1-12) menjadi nama bulan Bahasa Indonesia
 */
function getIndonesianMonthName($monthNumber) {
    $bulanArr = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $bulanArr[(int)$monthNumber] ?? '';
}

/**
 * Mengubah nama hari bahasa Inggris menjadi Bahasa Indonesia
 */
function getIndonesianDayName($dayEnglish) {
    $hariArr = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    return $hariArr[$dayEnglish] ?? $dayEnglish;
}

/**
 * Format tanggal ke Bahasa Indonesia (contoh: 'Minggu, 23 Agustus 2026')
 */
function formatTanggalIndo($tanggal, $tampilkanHari = true) {
    if (empty($tanggal)) return '';
    $timestamp = strtotime($tanggal);
    if (!$timestamp) return $tanggal;

    $hari = getIndonesianDayName(date('l', $timestamp));
    $tgl = date('j', $timestamp);
    $bulan = getIndonesianMonthName(date('n', $timestamp));
    $tahun = date('Y', $timestamp);

    if ($tampilkanHari) {
        return "$hari, $tgl $bulan $tahun";
    }
    return "$tgl $bulan $tahun";
}

/**
 * Sinkronisasi hari libur nasional otomatis dari API jika belum ada di database
 */
function syncNationalHolidays($conn, $year = null) {
    if (!$conn) return;
    $year = $year ?: date('Y');
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM holidays WHERE YEAR(tanggal) = $year AND type = 'national'");
    if ($check) {
        $count = (int)mysqli_fetch_assoc($check)['c'];
        if ($count === 0) {
            $api = "https://date.nager.at/api/v3/PublicHolidays/" . $year . "/ID";
            $resp = null;
            if (ini_get('allow_url_fopen')) {
                $opts = ['http' => ['timeout' => 3]];
                $context = stream_context_create($opts);
                $resp = @file_get_contents($api, false, $context);
            }
            if (!$resp && function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                $resp = @curl_exec($ch);
                curl_close($ch);
            }
            if ($resp) {
                $data = json_decode($resp, true);
                if (is_array($data)) {
                    $stmt = $conn->prepare("INSERT IGNORE INTO holidays (tanggal, nama, type) VALUES (?, ?, 'national')");
                    if ($stmt) {
                        foreach ($data as $d) {
                            $date = $d['date'] ?? null;
                            $localName = $d['localName'] ?? ($d['name'] ?? '');
                            if ($date && $localName) {
                                $stmt->bind_param('ss', $date, $localName);
                                @$stmt->execute();
                            }
                        }
                    }
                }
            }
        }
    }
}

