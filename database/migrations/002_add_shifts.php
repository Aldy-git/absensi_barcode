<?php
require_once __DIR__ . '/../../config/config.php';

// Check and add shift column to siswa
$res = mysqli_query($conn, "SHOW COLUMNS FROM siswa LIKE 'shift'");
if (mysqli_num_rows($res) == 0) {
    mysqli_query($conn, "ALTER TABLE siswa ADD COLUMN shift ENUM('pagi','siang') NOT NULL DEFAULT 'pagi' AFTER jenis_kelamin");
    echo "Added column 'shift' to table 'siswa'.\n";
} else {
    echo "Column 'shift' already exists in table 'siswa'.\n";
}

// Check and add shift column to absensi
$res2 = mysqli_query($conn, "SHOW COLUMNS FROM absensi LIKE 'shift'");
if (mysqli_num_rows($res2) == 0) {
    mysqli_query($conn, "ALTER TABLE absensi ADD COLUMN shift ENUM('pagi','siang') NOT NULL DEFAULT 'pagi' AFTER status");
    echo "Added column 'shift' to table 'absensi'.\n";
} else {
    echo "Column 'shift' already exists in table 'absensi'.\n";
}

echo "Migration finished.\n";
