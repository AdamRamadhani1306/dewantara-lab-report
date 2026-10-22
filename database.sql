-- =====================================================
-- DEWANTARA LAB REPORT - database.sql
-- Catatan InfinityFree: database dibuat lewat panel
-- (MySQL Databases), BUKAN lewat perintah CREATE DATABASE.
-- Setelah dibuat, pilih database di phpMyAdmin lalu Import
-- file ini. Baris CREATE DATABASE di bawah hanya untuk
-- penggunaan lokal (XAMPP/Laragon).
-- =====================================================

-- CREATE DATABASE IF NOT EXISTS dewantara_lab_report
--   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE dewantara_lab_report;

CREATE TABLE IF NOT EXISTS `laporan` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nama_alat` VARCHAR(150) NOT NULL,
  `jenis_kerusakan` TEXT NOT NULL,
  `nama_pelapor` VARCHAR(100) NOT NULL,
  `tanggal_laporan` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `foto_bukti` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('Menunggu','Diproses','Selesai') NOT NULL DEFAULT 'Menunggu',
  `keterangan` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nama_alat` (`nama_alat`),
  KEY `idx_tanggal` (`tanggal_laporan`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jika tabel `laporan` sudah terlanjur dibuat sebelumnya, jalankan perintah ini
-- di phpMyAdmin (tab SQL) agar foto_bukti boleh kosong:
-- ALTER TABLE `laporan` MODIFY `foto_bukti` VARCHAR(255) NULL DEFAULT NULL;

-- Data contoh (opsional, boleh dihapus)
INSERT INTO `laporan` (`nama_alat`, `jenis_kerusakan`, `nama_pelapor`, `foto_bukti`, `status`, `keterangan`) VALUES
('PC Lab 1 - Unit 05', 'Monitor tidak menyala meskipun kabel sudah terpasang', 'Contoh Mahasiswa', NULL, 'Menunggu', NULL);
