<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM siswa WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
}

header('Location: index.php');
exit;
