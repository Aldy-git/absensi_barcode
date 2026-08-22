<?php
// Run this script once to create the holidays table in the configured database.
require 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  nama VARCHAR(255) NOT NULL,
  type ENUM('national','school') NOT NULL DEFAULT 'school',
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_holiday_date (tanggal, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if (mysqli_query($conn, $sql)) {
    echo "OK: tabel 'holidays' sudah ada atau berhasil dibuat.";
} else {
    echo "ERROR: Gagal membuat tabel: " . mysqli_error($conn);
}

?>
