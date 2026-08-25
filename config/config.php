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
 * Mengambil informasi aturan jadwal shift (Pagi / Siang)
 *
 * Shift Pagi:
 * - Senin - Kamis : Masuk 07.00, Pulang 12.00 | Waktu Absen: 06.00 - 07.00 (cut-off)
 * - Jum'at        : Masuk 07.00, Pulang 10.00 | Waktu Absen: 06.00 - 07.00 (cut-off)
 *
 * Shift Siang:
 * - Senin - Kamis : Masuk 12.30, Pulang 17.00 | Waktu Absen: 12.00 - 12.30 (cut-off)
 * - Jum'at        : Masuk 13.00, Pulang 17.00 | Waktu Absen: 12.00 - 13.00 (cut-off)
 *
 * @param string $shift 'pagi' | 'siang'
 * @param string|null $tanggal Format 'Y-m-d'
 * @return array
 */
function getShiftRules($shift = 'pagi', $tanggal = null) {
    $shift = strtolower(trim((string)$shift));
    if ($shift !== 'siang') $shift = 'pagi';

    $tanggal = $tanggal ?: date('Y-m-d');
    $ts = strtotime($tanggal);
    if (!$ts) $ts = time();

    $dayOfWeek = (int)date('w', $ts); // 0 = Minggu, 5 = Jumat, 6 = Sabtu
    $isFriday = ($dayOfWeek === 5);

    if ($shift === 'pagi') {
        $jamBuka = '06:00:00';
        $jamMasuk = '07:00:00';
        $jamPulang = $isFriday ? '10:00:00' : '12:00:00';
        $label = 'Shift Pagi';
        $jamBukaDisplay = '06:00';
        $jamMasukDisplay = '07:00';
        $jamPulangDisplay = $isFriday ? '10:00' : '12:00';
    } else { // siang
        $jamBuka = '12:00:00';
        $jamMasuk = $isFriday ? '13:00:00' : '12:30:00';
        $jamPulang = '17:00:00';
        $label = 'Shift Siang';
        $jamBukaDisplay = '12:00';
        $jamMasukDisplay = $isFriday ? '13:00' : '12:30';
        $jamPulangDisplay = '17:00';
    }

    return [
        'shift' => $shift,
        'label' => $label,
        'tanggal' => $tanggal,
        'hari' => getIndonesianDayName(date('l', $ts)),
        'is_friday' => $isFriday,
        'jam_buka' => $jamBuka,
        'jam_masuk' => $jamMasuk,
        'jam_pulang' => $jamPulang,
        'jam_buka_str' => $jamBukaDisplay . ' WIB',
        'jam_masuk_str' => $jamMasukDisplay . ' WIB',
        'jam_pulang_str' => $jamPulangDisplay . ' WIB',
        'deskripsi' => $label . ' (' . ($isFriday ? 'Jum\'at' : 'Senin-Kamis') . ': Masuk ' . $jamMasukDisplay . ', Pulang ' . $jamPulangDisplay . ' | Absen: ' . $jamBukaDisplay . ' - ' . $jamMasukDisplay . ')'
    ];
}

/**
 * Mengevaluasi status kehadiran siswa berdasarkan shift dan waktu scan
 *
 * @param string $shift 'pagi' | 'siang'
 * @param string|null $jam Format 'H:i:s'
 * @param string|null $tanggal Format 'Y-m-d'
 * @return array
 */
function evaluateAttendanceStatus($shift = 'pagi', $jam = null, $tanggal = null) {
    $rules = getShiftRules($shift, $tanggal);
    $jamScan = $jam ? (strlen($jam) === 5 ? $jam . ':00' : $jam) : date('H:i:s');

    $isBeforeOpening = ($jamScan < $rules['jam_buka']);
    $isLate = ($jamScan > $rules['jam_masuk']);

    if ($isBeforeOpening) {
        return [
            'status' => 'hadir',
            'is_allowed' => false,
            'is_early' => true,
            'is_late' => false,
            'message' => 'Waktu absensi untuk <strong>' . htmlspecialchars($rules['label']) . '</strong> belum dibuka. Absen dibuka mulai pukul <strong>' . $rules['jam_buka_str'] . '</strong>.',
            'rules' => $rules,
            'jam_scan' => $jamScan
        ];
    }

    if ($isLate) {
        return [
            'status' => 'terlambat',
            'is_allowed' => true,
            'is_early' => false,
            'is_late' => true,
            'message' => 'Absensi tercatat <strong>TERLAMBAT</strong> (melewati batas waktu masuk ' . $rules['jam_masuk_str'] . ').',
            'rules' => $rules,
            'jam_scan' => $jamScan
        ];
    }

    return [
        'status' => 'hadir',
        'is_allowed' => true,
        'is_early' => false,
        'is_late' => false,
        'message' => 'Absensi tercatat <strong>HADIR TEPAT WAKTU</strong>.',
        'rules' => $rules,
        'jam_scan' => $jamScan
    ];
}

/**
 * Mendeteksi perkiraan shift saat ini berdasarkan jam sistem
 */
function detectCurrentShift($jam = null) {
    $current = $jam ? substr($jam, 0, 5) : date('H:i');
    if ($current >= '11:30') {
        return 'siang';
    }
    return 'pagi';
}

/**
 * Helper cURL / file_get_contents dengan timeout cepat
 */
function fetchUrlWithTimeout($url, $timeout = 3) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AbsensiBarcode/2.0');
        $result = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 200 && $result) {
            return $result;
        }
    }
    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => ['timeout' => $timeout, 'user_agent' => 'AbsensiBarcode/2.0'],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($opts);
        $res = @file_get_contents($url, false, $context);
        if ($res) return $res;
    }
    return null;
}

/**
 * Sinkronisasi hari libur nasional otomatis dari API jika belum ada di database
 */
function syncNationalHolidays($conn, $year = null) {
    if (!$conn) return 0;
    $year = (int)($year ?: date('Y'));
    if ($year < 2000 || $year > 2100) return 0;

    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM holidays WHERE YEAR(tanggal) = $year AND type = 'national'");
    if ($check) {
        $count = (int)mysqli_fetch_assoc($check)['c'];
        if ($count > 0) {
            return $count;
        }
    }

    $holidaysList = [];

    // 1. Coba Nager.Date API
    $apiNager = "https://date.nager.at/api/v3/PublicHolidays/" . $year . "/ID";
    $resp = fetchUrlWithTimeout($apiNager, 3);
    if ($resp) {
        $data = json_decode($resp, true);
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $d) {
                $date = $d['date'] ?? null;
                $name = $d['localName'] ?? ($d['name'] ?? '');
                if ($date && $name) {
                    $holidaysList[$date] = $name;
                }
            }
        }
    }

    // 2. Coba API Hari Libur Indonesia
    if (empty($holidaysList)) {
        $apiIndo = "https://dayoffapi.vercel.app/api?year=" . $year;
        $resp2 = fetchUrlWithTimeout($apiIndo, 3);
        if ($resp2) {
            $data2 = json_decode($resp2, true);
            if (is_array($data2)) {
                foreach ($data2 as $d) {
                    $date = $d['holiday_date'] ?? ($d['tanggal'] ?? null);
                    $name = $d['holiday_name'] ?? ($d['keterangan'] ?? '');
                    $isNat = $d['is_national_holiday'] ?? true;
                    if ($date && $name && $isNat) {
                        $holidaysList[$date] = $name;
                    }
                }
            }
        }
    }

    // 3. Fallback jika offline/tidak ada internet
    if (empty($holidaysList)) {
        $holidaysList[$year . '-01-01'] = 'Tahun Baru Masehi';
        $holidaysList[$year . '-05-01'] = 'Hari Buruh Internasional';
        $holidaysList[$year . '-06-01'] = 'Hari Lahir Pancasila';
        $holidaysList[$year . '-08-17'] = 'Hari Kemerdekaan Republik Indonesia';
        $holidaysList[$year . '-12-25'] = 'Hari Raya Natal';
    }

    // Insert ke database
    $stmt = $conn->prepare("INSERT IGNORE INTO holidays (tanggal, nama, type) VALUES (?, ?, 'national')");
    $inserted = 0;
    if ($stmt) {
        foreach ($holidaysList as $tgl => $nm) {
            $stmt->bind_param('ss', $tgl, $nm);
            if ($stmt->execute()) {
                $inserted++;
            }
        }
    }

    return $inserted;
}

