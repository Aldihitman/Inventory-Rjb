<?php
require 'includes/auth.php';
require_login();
require_admin();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Kelola Pengguna';
$page_sub    = 'Akun yang bisa masuk ke sistem';
$active_page = 'users';
$form_error  = '';

// --- Tambah pengguna ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $nama     = trim($_POST['nama_lengkap'] ?? '');
    $role     = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

    if ($username === '' || $password === '' || $nama === '') {
        $form_error = 'Semua kolom wajib diisi.';
    } elseif (strlen($password) < 6) {
        $form_error = 'Password minimal 6 karakter.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetchColumn() > 0) {
            $form_error = 'Username sudah dipakai.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, nama_lengkap, role) VALUES (?,?,?,?)');
            $stmt->execute([$username, $hash, $nama, $role]);
            flash_set('Pengguna "' . $username . '" berhasil ditambahkan.');
            header('Location: users.php');
            exit;
        }
    }
}

// --- Hapus pengguna ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) {
        flash_set('Tidak bisa menghapus akun yang sedang digunakan.', 'err');
    } else {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash_set('Pengguna berhasil dihapus.');
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Tambah pengguna baru</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= e($form_error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="form" value="add">
      <div class="form-grid">
        <div class="field"><label>Nama lengkap</label><input type="text" name="nama_lengkap" required></div>
        <div class="field"><label>Username</label><input type="text" name="username" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" minlength="6" required></div>
        <div class="field">
          <label>Peran</label>
          <select name="role">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>
      <div class="btn-row"><button type="submit" class="btn">Tambah pengguna</button></div>
    </form>
  </div>
</section>

<section class="block">
  <div class="block-head"><h2>Daftar pengguna</h2></div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['nama_lengkap']) ?></td>
            <td class="mono"><?= e($u['username']) ?></td>
            <td><?= e($u['role']) ?></td>
            <td>
              <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
              <form method="post" onsubmit="return confirm('Hapus pengguna ini?');" style="display:inline;">
                <input type="hidden" name="form" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="icon-btn">Hapus</button>
              </form>
              <?php else: ?>
                <span class="hint">akun aktif</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
