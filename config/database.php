<?php
/**
 * Konfigurasi koneksi database.
 * Sesuaikan 4 baris di bawah dengan pengaturan server kamu
 * (di XAMPP/Laragon biasanya user 'root' tanpa password).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'rjb_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal. Pastikan database "rjb_inventory" sudah dibuat dan pengaturan di config/database.php sudah benar.<br>Detail: ' . htmlspecialchars($e->getMessage()));
}
