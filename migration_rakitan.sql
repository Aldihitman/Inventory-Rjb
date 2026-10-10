-- =========================================================
-- MIGRASI: Fitur Stok Belum Rakit / Sudah Rakit
-- Jalankan file ini di phpMyAdmin (tab "SQL") pada database
-- rjb_inventory yang SUDAH ADA — tidak akan menghapus data lama.
-- =========================================================
USE rjb_inventory;

-- 1. Tambah kolom stok belum rakit di tabel barang
ALTER TABLE items
  ADD COLUMN stok_belum_rakit INT NOT NULL DEFAULT 0 AFTER stok;

-- 2. Tambah kolom opsional info koli di tabel transaksi (barang masuk)
ALTER TABLE transactions
  ADD COLUMN jumlah_koli INT DEFAULT NULL AFTER catatan,
  ADD COLUMN isi_per_koli INT DEFAULT NULL AFTER jumlah_koli;

-- 3. Tabel baru untuk mencatat riwayat perakitan
CREATE TABLE IF NOT EXISTS assemblies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT DEFAULT NULL,
  jumlah INT NOT NULL,
  perakit VARCHAR(150) NOT NULL,
  tanggal DATE NOT NULL,
  catatan TEXT DEFAULT NULL,
  user_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
