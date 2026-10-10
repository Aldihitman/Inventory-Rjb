<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Dasbor';
$page_sub    = 'Ringkasan stok gudang';
$active_page = 'dashboard';

$totalItems     = (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
$totalStok      = (int)$pdo->query('SELECT COALESCE(SUM(stok),0) FROM items')->fetchColumn();
$totalBelumRakit = (int)$pdo->query('SELECT COALESCE(SUM(stok_belum_rakit),0) FROM items')->fetchColumn();
$totalSJ        = (int)$pdo->query('SELECT COUNT(*) FROM surat_jalan')->fetchColumn();

$lowStock = $pdo->query(
    'SELECT * FROM items WHERE stok_min > 0 AND stok <= stok_min ORDER BY stok ASC'
)->fetchAll();

// Gabungkan aktivitas barang masuk/keluar DAN rakitan jadi satu daftar aktivitas terbaru.
$recent = $pdo->query(
    "SELECT tanggal, type, item_nama, jumlah, satuan, pihak, created_at FROM (
        SELECT t.tanggal, t.type AS type, i.nama AS item_nama, t.jumlah, i.satuan, t.pihak, t.created_at
        FROM transactions t LEFT JOIN items i ON i.id = t.item_id
        UNION ALL
        SELECT a.tanggal, 'rakit' AS type, i.nama AS item_nama, a.jumlah, i.satuan, a.perakit AS pihak, a.created_at
        FROM assemblies a LEFT JOIN items i ON i.id = a.item_id
    ) AS activity
    ORDER BY tanggal DESC, created_at DESC
    LIMIT 8"
)->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<div class="stat-grid">
  <div class="stat-card"><div class="label">Jenis barang</div><div class="value"><?= $totalItems ?></div></div>
  <div class="stat-card good"><div class="label">Stok siap kirim (sudah rakit)</div><div class="value"><?= $totalStok ?></div></div>
  <div class="stat-card"><div class="label">Stok belum rakit</div><div class="value"><?= $totalBelumRakit ?></div></div>
  <div class="stat-card warn"><div class="label">Stok siap kirim menipis</div><div class="value"><?= count($lowStock) ?></div></div>
  <div class="stat-card"><div class="label">Surat jalan terbit</div><div class="value"><?= $totalSJ ?></div></div>
</div>

<section class="block">
  <div class="block-head"><h2>Stok menipis</h2></div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Kode</th><th>Nama barang</th><th class="num">Stok</th><th class="num">Batas minimum</th></tr></thead>
      <tbody>
        <?php if (empty($lowStock)): ?>
          <tr class="empty-row"><td colspan="4">Tidak ada barang dengan stok menipis.</td></tr>
        <?php else: foreach ($lowStock as $i): ?>
          <tr>
            <td class="mono"><?= e($i['kode']) ?></td>
            <td><?= e($i['nama']) ?></td>
            <td class="num low-stock"><?= $i['stok'] ?></td>
            <td class="num"><?= $i['stok_min'] ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="block">
  <div class="block-head"><h2>Aktivitas terbaru</h2></div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Tanggal</th><th>Tipe</th><th>Barang</th><th class="num">Jumlah</th><th>Keterangan</th></tr></thead>
      <tbody>
        <?php if (empty($recent)): ?>
          <tr class="empty-row"><td colspan="5">Belum ada aktivitas. Mulai dengan menambah barang lalu catat barang masuk.</td></tr>
        <?php else: foreach ($recent as $t):
          $label = ['masuk' => 'Masuk', 'keluar' => 'Keluar', 'rakit' => 'Rakit'][$t['type']] ?? $t['type']; ?>
          <tr>
            <td><?= format_tanggal($t['tanggal']) ?></td>
            <td><span class="badge <?= e($t['type']) ?>"><?= e($label) ?></span></td>
            <td><?= $t['item_nama'] ? e($t['item_nama']) : '(dihapus)' ?></td>
            <td class="num"><?= $t['jumlah'] ?> <?= e($t['satuan']) ?></td>
            <td><?= e($t['pihak'] ?: '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
