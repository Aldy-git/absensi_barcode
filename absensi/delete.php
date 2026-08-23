<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

// Hanya admin yang boleh menghapus data absensi
if ($_SESSION['role'] !== 'admin') {
  header('Location: riwayat.php?error=forbidden');
  exit;
}

// Hanya terima request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: riwayat.php?error=method_not_allowed');
  exit;
}

// Verifikasi CSRF token
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
  header('Location: riwayat.php?error=csrf_invalid');
  exit;
}

$id = $_POST['id'] ?? '';

if ($id === '' || !ctype_digit((string)$id)) {
  header('Location: riwayat.php?error=id_invalid');
  exit;
}

$stmt = $conn->prepare("DELETE FROM absensi WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  header('Location: riwayat.php?msg=deleted');
} else {
  header('Location: riwayat.php?error=not_found');
}
exit;
