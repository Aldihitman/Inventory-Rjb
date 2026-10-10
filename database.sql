-- =========================================================
-- Database: rjb_inventory
-- Sistem Inventory & Surat Jalan - PT Rejeki Jaya Baik
-- Import file ini lewat phpMyAdmin atau perintah:
--   mysql -u root -p < database.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS rjb_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rjb_inventory;

-- ---------------------------------------------------------
-- Tabel pengguna (login)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel data barang
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(50) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(30) NOT NULL,
  stok INT NOT NULL DEFAULT 0,             -- stok SUDAH DIRAKIT / siap kirim
  stok_belum_rakit INT NOT NULL DEFAULT 0, -- stok BELUM DIRAKIT (baru datang, masih dalam koli)
  stok_min INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel transaksi (barang masuk & keluar)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('masuk','keluar') NOT NULL,
  item_id INT NOT NULL,
  jumlah INT NOT NULL,
  tanggal DATE NOT NULL,
  pihak VARCHAR(150) DEFAULT NULL,
  catatan TEXT DEFAULT NULL,
  jumlah_koli INT DEFAULT NULL,   -- opsional: jumlah koli saat barang masuk
  isi_per_koli INT DEFAULT NULL,  -- opsional: isi per koli saat barang masuk
  user_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel rakitan (perakitan sepeda: belum rakit -> sudah rakit)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS assemblies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT DEFAULT NULL,
  jumlah INT NOT NULL,
  perakit VARCHAR(150) NOT NULL,   -- nama orang/teknisi yang merakit
  tanggal DATE NOT NULL,
  catatan TEXT DEFAULT NULL,
  user_id INT DEFAULT NULL,        -- staf yang menginput data ini
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel surat jalan
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS surat_jalan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nomor VARCHAR(50) NOT NULL UNIQUE,
  tanggal DATE NOT NULL,
  penerima VARCHAR(150) DEFAULT NULL,
  alamat TEXT DEFAULT NULL,
  kendaraan VARCHAR(50) DEFAULT NULL,
  sopir VARCHAR(100) DEFAULT NULL,
  catatan TEXT DEFAULT NULL,
  user_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel item di dalam surat jalan (satu SJ bisa banyak barang)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS surat_jalan_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  surat_jalan_id INT NOT NULL,
  item_id INT DEFAULT NULL,
  nama_barang VARCHAR(150) NOT NULL,
  jumlah INT NOT NULL,
  satuan VARCHAR(30) DEFAULT NULL,
  kondisi ENUM('rakit','belum_rakit') NOT NULL DEFAULT 'rakit', -- stok mana yang dipotong
  keterangan VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (surat_jalan_id) REFERENCES surat_jalan(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tabel profil perusahaan (untuk kop surat jalan)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS company_profile (
  id INT PRIMARY KEY DEFAULT 1,
  nama VARCHAR(150) NOT NULL DEFAULT 'PT Rejeki Jaya Baik',
  alamat TEXT DEFAULT NULL,
  telp VARCHAR(50) DEFAULT NULL,
  logo_path VARCHAR(255) DEFAULT 'assets/img/logo.png'
) ENGINE=InnoDB;

INSERT INTO company_profile (id, nama, alamat, telp, logo_path)
VALUES (1, 'PT Rejeki Jaya Baik', '', '', 'assets/img/logo.png')
ON DUPLICATE KEY UPDATE id = id;

-- ---------------------------------------------------------
-- Akun admin bawaan
-- Username : admin
-- Password : admin123
-- SEGERA GANTI PASSWORD INI setelah login pertama kali!
-- ---------------------------------------------------------
INSERT INTO users (username, password, nama_lengkap, role)
VALUES ('admin', '$2b$12$rC7HeW9vwJxRqcwaQOuBCO3KLRW0Mg.N0Nc21HenRDQj92YANuJGa', 'Administrator', 'admin')
ON DUPLICATE KEY UPDATE username = username;
