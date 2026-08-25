CREATE DATABASE IF NOT EXISTS absensi_barcode;
USE absensi_barcode;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','guru') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS siswa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nis VARCHAR(50) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  kelas VARCHAR(50) NOT NULL,
  jurusan VARCHAR(100) NOT NULL,
  jenis_kelamin CHAR(1) NOT NULL,
  shift ENUM('pagi','siang') NOT NULL DEFAULT 'pagi',
  barcode_code VARCHAR(100) NOT NULL UNIQUE,
  foto VARCHAR(255) DEFAULT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS absensi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  siswa_id INT NOT NULL,
  tanggal DATE NOT NULL,
  status ENUM('hadir','terlambat','izin','sakit','alpa') NOT NULL,
  shift ENUM('pagi','siang') NOT NULL DEFAULT 'pagi',
  jam_scan TIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_siswa_tanggal (siswa_id, tanggal),
  CONSTRAINT fk_absensi_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  nama VARCHAR(255) NOT NULL,
  type ENUM('national','school') NOT NULL DEFAULT 'school',
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_holiday_date (tanggal, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('guru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guru');
