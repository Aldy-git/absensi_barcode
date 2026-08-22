-- Migration: create holidays table
CREATE TABLE IF NOT EXISTS holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  nama VARCHAR(255) NOT NULL,
  type ENUM('national','school') NOT NULL DEFAULT 'school',
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_holiday_date (tanggal, type)
);
