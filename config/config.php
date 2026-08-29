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
 * Daftar Master Hari Libur Nasional & Hari Besar Agama Islam Lengkap di Indonesia
 */
function getIndonesianNationalHolidays($year) {
    $year = (int)$year;
    $list = [
        // Hari Libur Nasional Berdasarkan Tanggal Tetap Masehi
        $year . '-01-01' => 'Tahun Baru ' . $year . ' Masehi',
        $year . '-05-01' => 'Hari Buruh Internasional',
        $year . '-06-01' => 'Hari Lahir Pancasila',
        $year . '-08-17' => 'Hari Kemerdekaan Republik Indonesia',
        $year . '-12-25' => 'Hari Raya Natal (Kelahiran Yesus Kristus)',
    ];

    // Daftar Lengkap Hari Besar Islam & Hari Libur Nasional per Tahun
    switch ($year) {
        case 2023:
            $list['2023-01-22'] = 'Tahun Baru Imlek 2574 Kongzili';
            $list['2023-02-18'] = 'Isra Mikraj Nabi Muhammad SAW';
            $list['2023-03-22'] = 'Hari Suci Nyepi Tahun Baru Saka 1945';
            $list['2023-04-07'] = 'Wafat Isa Almasih';
            $list['2023-04-22'] = 'Hari Raya Idul Fitri 1444 Hijriah';
            $list['2023-04-23'] = 'Hari Raya Idul Fitri 1444 Hijriah';
            $list['2023-05-18'] = 'Kenaikan Isa Almasih';
            $list['2023-06-04'] = 'Hari Raya Waisak 2567 BE';
            $list['2023-06-29'] = 'Hari Raya Idul Adha 1444 Hijriah';
            $list['2023-07-19'] = 'Tahun Baru Islam 1445 Hijriah (1 Muharram)';
            $list['2023-09-28'] = 'Maulid Nabi Muhammad SAW';
            break;

        case 2024:
            $list['2024-02-08'] = 'Isra Mikraj Nabi Muhammad SAW';
            $list['2024-02-10'] = 'Tahun Baru Imlek 2575 Kongzili';
            $list['2024-03-11'] = 'Hari Suci Nyepi Tahun Baru Saka 1946';
            $list['2024-03-29'] = 'Wafat Isa Almasih';
            $list['2024-03-31'] = 'Hari Paskah';
            $list['2024-04-10'] = 'Hari Raya Idul Fitri 1445 Hijriah';
            $list['2024-04-11'] = 'Hari Raya Idul Fitri 1445 Hijriah';
            $list['2024-05-09'] = 'Kenaikan Isa Almasih';
            $list['2024-05-23'] = 'Hari Raya Waisak 2568 BE';
            $list['2024-06-17'] = 'Hari Raya Idul Adha 1445 Hijriah';
            $list['2024-07-07'] = 'Tahun Baru Islam 1446 Hijriah (1 Muharram)';
            $list['2024-09-16'] = 'Maulid Nabi Muhammad SAW';
            break;

        case 2025:
            $list['2025-01-27'] = 'Isra Mikraj Nabi Muhammad SAW';
            $list['2025-01-29'] = 'Tahun Baru Imlek 2576 Kongzili';
            $list['2025-03-29'] = 'Hari Suci Nyepi Tahun Baru Saka 1947';
            $list['2025-03-31'] = 'Hari Raya Idul Fitri 1446 Hijriah';
            $list['2025-04-01'] = 'Hari Raya Idul Fitri 1446 Hijriah';
            $list['2025-04-18'] = 'Wafat Yesus Kristus';
            $list['2025-04-20'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2025-05-12'] = 'Hari Raya Waisak 2569 BE';
            $list['2025-05-29'] = 'Kenaikan Yesus Kristus';
            $list['2025-06-06'] = 'Hari Raya Idul Adha 1446 Hijriah';
            $list['2025-06-27'] = 'Tahun Baru Islam 1447 Hijriah (1 Muharram)';
            $list['2025-09-05'] = 'Maulid Nabi Muhammad SAW';
            break;

        case 2026:
            $list['2026-01-16'] = 'Isra Mikraj Nabi Muhammad SAW';
            $list['2026-02-17'] = 'Tahun Baru Imlek 2577 Kongzili';
            $list['2026-03-19'] = 'Hari Suci Nyepi Tahun Baru Saka 1948';
            $list['2026-03-21'] = 'Hari Raya Idul Fitri 1447 Hijriah';
            $list['2026-03-22'] = 'Hari Raya Idul Fitri 1447 Hijriah';
            $list['2026-04-03'] = 'Wafat Yesus Kristus';
            $list['2026-04-05'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2026-05-14'] = 'Kenaikan Yesus Kristus';
            $list['2026-05-27'] = 'Hari Raya Idul Adha 1447 Hijriah';
            $list['2026-05-31'] = 'Hari Raya Waisak 2570 BE';
            $list['2026-06-16'] = 'Tahun Baru Islam 1448 Hijriah (1 Muharram)';
            $list['2026-08-25'] = 'Maulid Nabi Muhammad SAW';
            break;

        case 2027:
            $list['2027-01-05'] = 'Isra Mikraj Nabi Muhammad SAW';
            $list['2027-02-06'] = 'Tahun Baru Imlek 2578 Kongzili';
            $list['2027-03-09'] = 'Hari Suci Nyepi Tahun Baru Saka 1949';
            $list['2027-03-10'] = 'Hari Raya Idul Fitri 1448 Hijriah';
            $list['2027-03-11'] = 'Hari Raya Idul Fitri 1448 Hijriah';
            $list['2027-03-26'] = 'Wafat Yesus Kristus';
            $list['2027-03-28'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2027-05-06'] = 'Kenaikan Yesus Kristus';
            $list['2027-05-17'] = 'Hari Raya Idul Adha 1448 Hijriah';
            $list['2027-05-20'] = 'Hari Raya Waisak 2571 BE';
            $list['2027-06-06'] = 'Tahun Baru Islam 1449 Hijriah (1 Muharram)';
            $list['2027-08-15'] = 'Maulid Nabi Muhammad SAW';
            $list['2027-12-26'] = 'Isra Mikraj Nabi Muhammad SAW 1449 H';
            break;

        case 2028:
            $list['2028-01-26'] = 'Tahun Baru Imlek 2579 Kongzili';
            $list['2028-02-27'] = 'Hari Raya Idul Fitri 1449 Hijriah';
            $list['2028-02-28'] = 'Hari Raya Idul Fitri 1449 Hijriah';
            $list['2028-03-26'] = 'Hari Suci Nyepi Tahun Baru Saka 1950';
            $list['2028-04-14'] = 'Wafat Yesus Kristus';
            $list['2028-04-16'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2028-05-05'] = 'Hari Raya Idul Adha 1449 Hijriah';
            $list['2028-05-09'] = 'Hari Raya Waisak 2572 BE';
            $list['2028-05-25'] = 'Kenaikan Yesus Kristus';
            $list['2028-05-25'] = 'Tahun Baru Islam 1450 Hijriah (1 Muharram)';
            $list['2028-08-03'] = 'Maulid Nabi Muhammad SAW';
            $list['2028-12-14'] = 'Isra Mikraj Nabi Muhammad SAW';
            break;

        case 2029:
            $list['2029-02-13'] = 'Tahun Baru Imlek 2580 Kongzili';
            $list['2029-02-14'] = 'Hari Raya Idul Fitri 1450 Hijriah';
            $list['2029-02-15'] = 'Hari Raya Idul Fitri 1450 Hijriah';
            $list['2029-03-15'] = 'Hari Suci Nyepi Tahun Baru Saka 1951';
            $list['2029-03-30'] = 'Wafat Yesus Kristus';
            $list['2029-04-01'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2029-04-24'] = 'Hari Raya Idul Adha 1450 Hijriah';
            $list['2029-05-10'] = 'Kenaikan Yesus Kristus';
            $list['2029-05-14'] = 'Tahun Baru Islam 1451 Hijriah (1 Muharram)';
            $list['2029-05-27'] = 'Hari Raya Waisak 2573 BE';
            $list['2029-07-24'] = 'Maulid Nabi Muhammad SAW';
            $list['2029-12-03'] = 'Isra Mikraj Nabi Muhammad SAW';
            break;

        case 2030:
            $list['2030-02-03'] = 'Tahun Baru Imlek 2581 Kongzili';
            $list['2030-02-04'] = 'Hari Raya Idul Fitri 1451 Hijriah';
            $list['2030-02-05'] = 'Hari Raya Idul Fitri 1451 Hijriah';
            $list['2030-03-05'] = 'Hari Suci Nyepi Tahun Baru Saka 1952';
            $list['2030-04-13'] = 'Hari Raya Idul Adha 1451 Hijriah';
            $list['2030-04-19'] = 'Wafat Yesus Kristus';
            $list['2030-04-21'] = 'Kebangkitan Yesus Kristus (Paskah)';
            $list['2030-05-03'] = 'Tahun Baru Islam 1452 Hijriah (1 Muharram)';
            $list['2030-05-16'] = 'Hari Raya Waisak 2574 BE';
            $list['2030-05-30'] = 'Kenaikan Yesus Kristus';
            $list['2030-07-13'] = 'Maulid Nabi Muhammad SAW';
            $list['2030-11-23'] = 'Isra Mikraj Nabi Muhammad SAW';
            break;
    }

    ksort($list);
    return $list;
}

/**
 * Sinkronisasi hari libur nasional otomatis (termasuk Hari Besar Agama Islam)
 */
function syncNationalHolidays($conn, $year = null) {
    if (!$conn) return 0;
    $year = (int)($year ?: date('Y'));
    if ($year < 2000 || $year > 2100) return 0;

    // Ambil daftar master hari libur nasional & hari besar Islam
    $masterHolidays = getIndonesianNationalHolidays($year);
    $holidaysList = $masterHolidays;

    // 1. Coba Nager.Date API untuk update/pelengkap tambahan
    $apiNager = "https://date.nager.at/api/v3/PublicHolidays/" . $year . "/ID";
    $resp = fetchUrlWithTimeout($apiNager, 3);
    if ($resp) {
        $data = json_decode($resp, true);
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $d) {
                $date = $d['date'] ?? null;
                $name = $d['localName'] ?? ($d['name'] ?? '');
                if ($date && $name && !isset($holidaysList[$date])) {
                    $holidaysList[$date] = $name;
                }
            }
        }
    }

    // 2. Coba API Hari Libur Indonesia
    $apiIndo = "https://dayoffapi.vercel.app/api?year=" . $year;
    $resp2 = fetchUrlWithTimeout($apiIndo, 3);
    if ($resp2) {
        $data2 = json_decode($resp2, true);
        if (is_array($data2)) {
            foreach ($data2 as $d) {
                $date = $d['holiday_date'] ?? ($d['tanggal'] ?? null);
                $name = $d['holiday_name'] ?? ($d['keterangan'] ?? '');
                $isNat = $d['is_national_holiday'] ?? true;
                if ($date && $name && $isNat && !isset($holidaysList[$date])) {
                    $holidaysList[$date] = $name;
                }
            }
        }
    }

    // Insert ke database dengan ON DUPLICATE / INSERT IGNORE
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

    // Return total count libur nasional untuk tahun ini
    $check = mysqli_query($conn, "SELECT COUNT(*) AS c FROM holidays WHERE YEAR(tanggal) = $year AND type = 'national'");
    if ($check) {
        return (int)mysqli_fetch_assoc($check)['c'];
    }

    return $inserted;
}

