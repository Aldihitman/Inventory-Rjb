<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Redirect ke login.php jika belum login. Panggil di awal setiap halaman terproteksi. */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/** Hanya izinkan role admin. Panggil setelah require_login(). */
function require_admin(): void
{
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;">Akses ditolak. Halaman ini hanya untuk admin.<br><a href="index.php">&larr; Kembali ke dasbor</a></div>');
    }
}

/** Ambil data pengguna yang sedang login. */
function current_user(): array
{
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'nama'     => $_SESSION['nama_lengkap'] ?? null,
        'role'     => $_SESSION['role'] ?? null,
    ];
}
