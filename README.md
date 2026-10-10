# Gudang RJB — Inventory & Surat Jalan (PHP + MySQL)

Aplikasi inventory (barang masuk/keluar) dengan cetak Surat Jalan untuk
**PT Rejeki Jaya Baik**, dilengkapi sistem login.

## Yang dibutuhkan

- PHP 7.4 ke atas (disarankan PHP 8.x)
- MySQL / MariaDB
- Server lokal seperti **XAMPP**, **Laragon**, atau **MAMP** — atau hosting PHP biasa

## Cara instalasi (di komputer sendiri, pakai XAMPP)

1. **Install XAMPP** dari https://www.apachefriends.org (jika belum ada), lalu jalankan
   modul **Apache** dan **MySQL** dari Control Panel-nya.
2. Salin folder `rjb-inventory` ini ke dalam folder `htdocs` XAMPP, contoh:
   `C:\xampp\htdocs\rjb-inventory` (Windows) atau `/Applications/XAMPP/htdocs/rjb-inventory` (Mac).
3. Buka **phpMyAdmin** di browser: `http://localhost/phpmyadmin`.
4. Klik tab **Import**, pilih file `database.sql` dari folder ini, lalu klik **Go / Kirim**.
   Ini akan otomatis membuat database `rjb_inventory` beserta seluruh tabelnya, dan
   satu akun admin bawaan.
5. Buka file `config/database.php`, sesuaikan bila perlu (default XAMPP biasanya sudah cocok):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'rjb_inventory');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```
6. Buka browser ke `http://localhost/rjb-inventory/login.php`.
7. Masuk dengan akun bawaan:
   - **Username:** `admin`
   - **Password:** `admin123`
8. **Segera** buka menu *Kelola Pengguna* untuk menambah akun staf, dan pertimbangkan
   mengganti password admin (lewat phpMyAdmin, kolom `password` tabel `users`, isi dengan
   hasil `password_hash()` PHP — atau minta bantuan untuk fitur ganti password bila perlu).

## Cara pakai di hosting online

Sama seperti di atas: upload semua file ke folder public_html (lewat cPanel File Manager
atau FTP), buat database MySQL lewat panel hosting, import `database.sql` lewat phpMyAdmin
yang disediakan hosting, lalu sesuaikan `config/database.php` dengan data yang diberikan
oleh hosting (host, nama database, user, password biasanya beda dari `root`/kosong).

## Struktur folder

```
rjb-inventory/
├── config/database.php       ← koneksi database (edit di sini)
├── includes/                 ← bagian layout & fungsi bersama (auth, header, sidebar, footer)
├── assets/css/style.css      ← semua tampilan
├── assets/js/script.js       ← baris barang dinamis di form Surat Jalan
├── assets/img/logo.png       ← logo perusahaan (bisa diganti lewat menu Profil Perusahaan)
├── database.sql              ← struktur tabel + akun admin bawaan (import ini untuk install baru)
├── migration_rakitan.sql     ← upgrade database lama: fitur rakit
├── migration_kondisi_sj.sql  ← upgrade database lama: surat jalan barang belum rakit
├── login.php / logout.php    ← sistem login
├── index.php                 ← dasbor
├── barang.php                ← data barang (tambah/hapus, lihat stok belum & sudah rakit)
├── masuk.php                 ← catat barang masuk (stok belum rakit bertambah)
├── rakit.php                 ← rakit barang: belum rakit → sudah rakit, dicatat oleh siapa
├── keluar.php                ← catat barang keluar (dari stok sudah rakit, cek stok cukup)
├── surat_jalan.php           ← buat & lihat daftar surat jalan
├── cetak_surat_jalan.php     ← tampilan cetak/PDF surat jalan
├── profil.php                ← nama, alamat, telepon & logo perusahaan (khusus admin)
└── users.php                 ← kelola akun login (khusus admin)
```

## Fitur Rakit Barang (khusus inventory sepeda)

Alur kerjanya sekarang mengikuti cara kerja toko sepeda: barang datang masih dalam
koli/box (belum rakit), baru setelah dirakit jadi stok yang siap dikirim/jual.

1. **Barang Masuk** → jumlah yang dicatat masuk sebagai **stok belum rakit**.
   Ada kalkulator opsional: isi "Jumlah koli" + "Isi per koli" (misalnya 1 koli
   isi 4 atau 8 sepeda), nanti "Jumlah masuk" otomatis terisi hasil perkaliannya.
   Bisa juga diisi manual langsung di "Jumlah masuk" kalau tidak lewat koli.
2. **Rakit Barang** (menu baru) → pilih barang, isi jumlah yang dirakit, nama
   orang/teknisi yang merakit, tanggal, dan catatan. Setelah disimpan:
   - Stok **belum rakit** barang itu berkurang.
   - Stok **sudah rakit (siap kirim)** bertambah sejumlah yang sama.
   - Kalau jumlah yang diisi melebihi stok belum rakit yang tersedia, sistem
     **menolak menyimpan** (bukan cuma kasih peringatan) dan jelas menyebutkan
     sisa stok belum rakit yang ada.
   - Ada riwayat rakitan di bawah form, dengan tombol "Batalkan" untuk
     mengembalikan stok ke belum rakit (hanya bisa kalau stok hasil rakitan itu
     belum terpakai di Barang Keluar / Surat Jalan).
3. **Barang Keluar** dan **Surat Jalan** tetap memotong dari stok **sudah rakit**
   saja — karena yang bisa dikirim ke pelanggan tentu yang sudah jadi sepeda utuh.

### Kalau database kamu SUDAH ada datanya (upgrade dari versi sebelumnya)

Jangan import ulang `database.sql` karena akan menghapus data lama. Cukup jalankan
file `migration_rakitan.sql`:

1. Buka phpMyAdmin → pilih database `rjb_inventory`.
2. Klik tab **SQL**.
3. Buka file `migration_rakitan.sql` dengan text editor, salin semua isinya,
   tempel ke kotak SQL di phpMyAdmin, lalu klik **Go / Kirim**.
4. Timpa file PHP/CSS/JS lama kamu dengan semua file dari paket ini.

Kalau kamu baru install dari nol, cukup import `database.sql` seperti biasa
(fitur ini sudah termasuk di dalamnya).

### Surat Jalan untuk barang yang masih dalam koli

Di form Surat Jalan, tiap baris barang punya pilihan **Kondisi**:
- **Sudah rakit** → memotong stok *siap kirim*.
- **Belum rakit (koli)** → memotong stok *belum rakit*, jadi barang yang masih
  dalam koli bisa langsung dikirim tanpa dirakit dulu.

Stok dicek sesuai kondisi yang dipilih (kalau kurang, surat jalan ditolak), dan saat
surat jalan dihapus stok dikembalikan ke kolom yang sama. Kondisi juga tercetak di
surat jalan. Barang yang sama boleh dipakai di beberapa baris dengan kondisi berbeda.

**Upgrade dari versi sebelumnya:** jalankan juga `migration_kondisi_sj.sql`
(phpMyAdmin → database `rjb_inventory` → tab SQL → tempel isinya → Go).

### Pencarian
- Daftar Barang: kotak cari di atas tabel (kode / nama / satuan).
- Surat Jalan: pilih barang lewat kotak cari di tiap baris, plus kotak cari di daftar
  surat jalan tersimpan (nomor, penerima, tanggal, nama barang, kendaraan, sopir).

## Peran pengguna

- **admin** — akses semua menu, termasuk Kelola Pengguna dan Profil Perusahaan.
- **staff** — akses Dasbor, Data Barang, Barang Masuk/Keluar, Surat Jalan, Riwayat.

## Keamanan yang sudah diterapkan

- Password disimpan ter-hash (bcrypt), tidak pernah dalam teks biasa.
- Semua query database pakai *prepared statements* (aman dari SQL injection).
- Semua output ke halaman di-escape (aman dari XSS).
- Sesi login wajib untuk semua halaman kecuali `login.php`.

## Catatan

- Nomor surat jalan otomatis mengikuti format `SJ/RJB/{tahun}/{urut}` dan akan reset
  urutannya setiap pergantian tahun.
- Barang keluar tidak bisa melebihi stok yang tersedia — sistem akan menolak dan
  menampilkan pesan jika jumlah yang diminta lebih besar dari stok saat ini.
