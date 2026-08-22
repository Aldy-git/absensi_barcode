<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Only allow JSON responses
// On GET: return all holidays (ensure national holidays sync)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // sync national holidays for current year (ID)
    $year = date('Y');
    $existing = mysqli_query($conn, "SELECT COUNT(*) AS c FROM holidays WHERE YEAR(tanggal) = $year AND type = 'national'");
    $count = mysqli_fetch_assoc($existing)['c'];
    if ($count == 0) {
        // try to fetch from Nager.Date API and insert
        $api = "https://date.nager.at/api/v3/PublicHolidays/" . $year . "/ID";
        $resp = null;
        // try file_get_contents first
        if (ini_get('allow_url_fopen')) {
            $opts = ['http' => ['timeout' => 5]];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($api, false, $context);
        }
        // fallback to cURL if available
        if (!$resp && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = @curl_exec($ch);
            curl_close($ch);
        }
        if ($resp) {
            $data = json_decode($resp, true);
            if (is_array($data)) {
                $stmt = $conn->prepare("INSERT IGNORE INTO holidays (tanggal, nama, type) VALUES (?, ?, 'national')");
                foreach ($data as $d) {
                    $date = $d['date'] ?? null;
                    $localName = $d['localName'] ?? ($d['name'] ?? '');
                    if ($date) {
                        $stmt->bind_param('ss', $date, $localName);
                        @$stmt->execute();
                    }
                }
            }
        }
    }
        $res = mysqli_query($conn, "SELECT id, tanggal AS start, nama AS title, type FROM holidays ORDER BY tanggal");
        $out = [];
        while ($row = mysqli_fetch_assoc($res)) {
            if (!isset($row['type']) || $row['type'] === 'custom') $row['type'] = 'school';
            $out[] = $row;
        }
    echo json_encode($out);
    exit;
}

// POST: add or delete holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
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
