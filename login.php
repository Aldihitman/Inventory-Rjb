<?php
require 'includes/auth.php';
require 'config/database.php';
require 'includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['nama_lengkap']  = $user['nama_lengkap'];
            $_SESSION['role']          = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}

$company = get_company($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Gudang RJB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
  <div class="login-box">
    <div class="login-logo">
      <img src="assets/img/logo.png" alt="Logo perusahaan">
    </div>
    <h1>Gudang RJB</h1>
    <div class="sub" style="text-align:center;margin-bottom:22px;"><?= e($company['nama']) ?></div>

    <?php if ($error): ?>
      <div class="msg show err"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" autofocus required value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="field" style="margin-top:12px;">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn" style="width:100%;margin-top:20px;">Masuk</button>
    </form>
    <div class="hint" style="text-align:center;margin-top:16px;">
      Akun bawaan: <span class="mono">admin</span> / <span class="mono">admin123</span> — segera ganti setelah masuk.
    </div>
  </div>
</body>
</html>
