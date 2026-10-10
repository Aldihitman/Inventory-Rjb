<?php
$user = current_user();
$menu = [
    'dashboard'  => ['index.php', 'Dasbor'],
    'barang'     => ['barang.php', 'Data Barang'],
    'masuk'      => ['masuk.php', 'Barang Masuk'],
    'rakit'      => ['rakit.php', 'Rakit Barang'],
    'keluar'     => ['keluar.php', 'Barang Keluar'],
    'suratjalan' => ['surat_jalan.php', 'Surat Jalan'],
    'riwayat'    => ['riwayat.php', 'Riwayat'],
    'profil'     => ['profil.php', 'Profil Perusahaan'],
];
if (($user['role'] ?? '') === 'admin') {
    $menu['users'] = ['users.php', 'Kelola Pengguna'];
}
?>
<div class="sidebar">
  <div class="brandbox">
    <img class="stamp-mark" src="assets/img/logo.png" alt="Logo perusahaan">
    <div class="brand">Gudang RJB<small><?= e($company['nama']) ?></small></div>
  </div>
  <nav>
    <?php foreach ($menu as $key => [$url, $label]): ?>
      <a href="<?= e($url) ?>" class="<?= ($active_page ?? '') === $key ? 'active' : '' ?>">
        <span class="dot"></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-foot">
    Masuk sebagai <strong><?= e($user['nama']) ?></strong> (<?= e($user['role']) ?>)<br>
    <a href="logout.php" class="logout-link">Keluar</a>
  </div>
</div>
<main>
  <div class="topline">
    <div>
      <h1><?= e($page_title ?? '') ?></h1>
      <div class="sub"><?= e($page_sub ?? '') ?></div>
    </div>
    <div class="today">Hari ini<div class="mono"><?= date('d-m-Y') ?></div></div>
  </div>
