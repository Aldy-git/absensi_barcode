<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Only allow JSON responses
// On GET: return all holidays (ensure national holidays sync)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Sinkronisasi otomatis hari libur nasional untuk tahun yang sedang ditampilkan di kalender
    $startParam = $_GET['start'] ?? '';
    $endParam = $_GET['end'] ?? '';

    $startYear = $startParam ? (int)date('Y', strtotime($startParam)) : (int)date('Y');
    $endYear = $endParam ? (int)date('Y', strtotime($endParam)) : (int)date('Y');

    if ($startYear < 2000 || $startYear > 2100) $startYear = (int)date('Y');
    if ($endYear < 2000 || $endYear > 2100) $endYear = $startYear;
    if ($endYear < $startYear) $endYear = $startYear;

    // Sinkronkan setiap tahun dalam rentang kalender yang dilihat
    for ($y = $startYear; $y <= $endYear; $y++) {
        syncNationalHolidays($conn, $y);
    }
    // Pastikan tahun ini dan tahun depan juga terisi
    syncNationalHolidays($conn, (int)date('Y'));
    syncNationalHolidays($conn, (int)date('Y') + 1);

    $res = mysqli_query($conn, "SELECT id, tanggal AS start, nama AS title, type FROM holidays ORDER BY tanggal");
    $out = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            if (!isset($row['type']) || $row['type'] === 'custom') $row['type'] = 'school';
            $out[] = $row;
        }
    }
    echo json_encode($out);
    exit;
}

// POST: add, delete, or sync holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'sync_national') {
        $year = (int)($_POST['year'] ?? date('Y'));
        // Hapus libur nasional lama tahun ini jika diminta sinkron ulang paksa
        if (!empty($_POST['force'])) {
            mysqli_query($conn, "DELETE FROM holidays WHERE YEAR(tanggal) = $year AND type = 'national'");
        }
        $count = syncNationalHolidays($conn, $year);
        echo json_encode(['success' => true, 'year' => $year, 'count' => $count, 'message' => "Hari libur nasional tahun $year berhasil diperbarui ($count hari)."]);
        exit;
    }

    if ($action === 'add') {
        // only admin can add custom holidays
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Forbidden']); exit;
        }
        $tanggal = $_POST['tanggal'] ?? '';
        $nama = trim($_POST['nama'] ?? 'Libur');
        if ($tanggal) {
            $stmt = $conn->prepare("INSERT INTO holidays (tanggal, nama, type, created_by) VALUES (?, ?, 'school', ?)");
            $userId = $_SESSION['user_id'] ?? null;
            $stmt->bind_param('ssi', $tanggal, $nama, $userId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
                exit;
            }
        }
        http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid input']); exit;
    }
    if ($action === 'delete') {
        // only admin
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403); echo json_encode(['success' => false, 'error' => 'Forbidden']); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM holidays WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) { echo json_encode(['success' => true]); exit; }
        }
        http_response_code(400); echo json_encode(['success' => false, 'error' => 'Invalid id']); exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

?>
