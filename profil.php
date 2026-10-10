<?php
require 'includes/auth.php';
require_login();
require_admin();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Profil Perusahaan';
$page_sub    = 'Data untuk kop surat jalan';
$active_page = 'profil';
$form_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telp   = trim($_POST['telp'] ?? '');

    if ($nama === '') {
        $form_error = 'Nama perusahaan wajib diisi.';
    } else {
        $logoPath = null;

        // Upload logo baru (opsional)
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'webp'];
            if (!in_array($ext, $allowed)) {
                $form_error = 'Format logo harus PNG, JPG, atau WEBP.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $form_error = 'Ukuran logo maksimal 2MB.';
            } else {
                $target = 'assets/img/logo.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/' . $target)) {
                    $logoPath = $target;
                } else {
                    $form_error = 'Gagal mengunggah logo.';
                }
            }
        }

        if ($form_error === '') {
            if ($logoPath) {
                $stmt = $pdo->prepare('UPDATE company_profile SET nama=?, alamat=?, telp=?, logo_path=? WHERE id=1');
                $stmt->execute([$nama, $alamat, $telp, $logoPath]);
            } else {
                $stmt = $pdo->prepare('UPDATE company_profile SET nama=?, alamat=?, telp=? WHERE id=1');
                $stmt->execute([$nama, $alamat, $telp]);
            }
            flash_set('Profil perusahaan tersimpan.');
            header('Location: profil.php');
            exit;
        }
    }
}

$company = get_company($pdo);

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Profil perusahaan</h2></div>
  <div class="block-body">
    <div class="hint" style="margin-bottom:14px;">Informasi ini akan muncul di kop surat jalan yang dicetak.</div>
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= e($form_error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-grid wide">
        <div class="field"><label>Nama perusahaan</label><input type="text" name="nama" value="<?= e($company['nama']) ?>" required></div>
        <div class="field"><label>Alamat</label><input type="text" name="alamat" value="<?= e($company['alamat']) ?>"></div>
        <div class="field"><label>Telepon</label><input type="text" name="telp" value="<?= e($company['telp']) ?>"></div>
        <div class="field">
          <label>Logo (opsional, PNG/JPG/WEBP maks 2MB)</label>
          <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= e($company['logo_path'] ?: 'assets/img/logo.png') ?>" alt="Logo saat ini" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid var(--line);">
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
          </div>
        </div>
      </div>
      <div class="btn-row"><button type="submit" class="btn">Simpan profil</button></div>
    </form>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
