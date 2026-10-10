<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Riwayat';
$page_sub    = 'Semua transaksi masuk, keluar, dan rakitan';
$active_page = 'riwayat';

$transactions = $pdo->query(
    "SELECT tanggal, type, item_nama, jumlah, satuan, pihak, catatan, jumlah_koli, isi_per_koli, created_at FROM (
        SELECT t.tanggal, t.type AS type, i.nama AS item_nama, t.jumlah, i.satuan, t.pihak, t.catatan,
               t.jumlah_koli, t.isi_per_koli, t.created_at
        FROM transactions t LEFT JOIN items i ON i.id = t.item_id
        UNION ALL
        SELECT a.tanggal, 'rakit' AS type, i.nama AS item_nama, a.jumlah, i.satuan, a.perakit AS pihak, a.catatan,
               NULL AS jumlah_koli, NULL AS isi_per_koli, a.created_at
        FROM assemblies a LEFT JOIN items i ON i.id = a.item_id
    ) AS activity
    ORDER BY tanggal DESC, created_at DESC"
)->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Riwayat semua transaksi</h2></div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Tanggal</th><th>Tipe</th><th>Barang</th><th class="num">Jumlah</th><th>Pihak terkait / perakit</th><th>Catatan</th></tr></thead>
      <tbody>
        <?php if (empty($transactions)): ?>
          <tr class="empty-row"><td colspan="6">Belum ada riwayat transaksi.</td></tr>
        <?php else: foreach ($transactions as $t):
          $label = ['masuk' => 'Masuk', 'keluar' => 'Keluar', 'rakit' => 'Rakit'][$t['type']] ?? $t['type'];
          $catatan = $t['catatan'];
          if ($t['type'] === 'masuk' && $t['jumlah_koli'] && $t['isi_per_koli']) {
              $catatan = trim($t['jumlah_koli'] . ' koli × ' . $t['isi_per_koli'] . ' pcs/koli. ' . $catatan);
          } ?>
          <tr>
            <td><?= format_tanggal($t['tanggal']) ?></td>
            <td><span class="badge <?= e($t['type']) ?>"><?= e($label) ?></span></td>
            <td><?= $t['item_nama'] ? e($t['item_nama']) : '(dihapus)' ?></td>
            <td class="num"><?= $t['jumlah'] ?> <?= e($t['satuan']) ?></td>
            <td><?= e($t['pihak'] ?: '-') ?></td>
            <td><?= e($catatan ?: '-') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
