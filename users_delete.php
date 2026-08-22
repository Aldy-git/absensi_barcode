<?php
session_start();
require 'config.php';

// Hanya admin yang dapat mengakses
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($id <= 0) {
    header('Location: users.php?error=not_found');
    exit;
}

// Mencegah admin menghapus akunnya sendiri
if ($id === (int)$_SESSION['user_id']) {
    header('Location: users.php?error=self_delete');
    exit;
}

// Ambil data user target
$stmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$targetUser = $stmt->get_result()->fetch_assoc();

if (!$targetUser) {
    header('Location: users.php?error=not_found');
    exit;
}

// Mencegah penghapusan akun yang memiliki role admin
if ($targetUser['role'] === 'admin') {
    header('Location: users.php?error=admin_protected');
    exit;
}

// Hapus user
$del = $conn->prepare("DELETE FROM users WHERE id = ?");
$del->bind_param('i', $id);
if ($del->execute() && $del->affected_rows > 0) {
    header('Location: users.php?msg=deleted');
} else {
    header('Location: users.php?error=failed');
}
exit;
