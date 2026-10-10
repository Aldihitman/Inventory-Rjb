-- =========================================================
-- MIGRASI: Surat Jalan bisa mengirim barang BELUM RAKIT (masih dalam koli)
-- Jalankan di phpMyAdmin (tab "SQL") pada database rjb_inventory yang SUDAH ADA.
-- Data lama aman: surat jalan lama otomatis dianggap "sudah rakit".
-- =========================================================
USE rjb_inventory;

ALTER TABLE surat_jalan_items
  ADD COLUMN kondisi ENUM('rakit','belum_rakit') NOT NULL DEFAULT 'rakit' AFTER satuan;
