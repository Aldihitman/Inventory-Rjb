<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Data Barang';
$page_sub    = 'Kelola daftar barang dan stok';
$active_page = 'barang';
$form_error  = '';

// --- Tambah barang ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add') {
    $kode          = trim($_POST['kode'] ?? '');
    $nama          = trim($_POST['nama'] ?? '');
    $satuan        = trim($_POST['satuan'] ?? '');
    $stok          = (int)($_POST['stok'] ?? 0);
    $stokBelumRakit = (int)($_POST['stok_belum_rakit'] ?? 0);
    $stokMin       = (int)($_POST['stok_min'] ?? 0);

    if ($kode === '' || $nama === '' || $satuan === '') {
        $form_error = 'Kode, nama, dan satuan wajib diisi.';
    } else {
        $check = $pdo->prepare('SELECT COUNT(*) FROM items WHERE kode = ?');
        $check->execute([$kode]);
        if ($check->fetchColumn() > 0) {
            $form_error = 'Kode barang sudah dipakai. Gunakan kode lain.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO items (kode, nama, satuan, stok, stok_belum_rakit, stok_min) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$kode, $nama, $satuan, $stok, $stokBelumRakit, $stokMin]);
            flash_set('Barang "' . $nama . '" berhasil ditambahkan.');
            header('Location: barang.php');
            exit;
        }
    }
}

// --- Hapus barang ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
    $stmt->execute([$id]);
    flash_set('Barang berhasil dihapus.');
    header('Location: barang.php');
    exit;
}

$items = $pdo->query('SELECT * FROM items ORDER BY nama ASC')->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Tambah barang baru</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= e($form_error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="form" value="add">
      <div class="form-grid">
        <div class="field"><label>Kode barang</label><input type="text" name="kode" placeholder="cth. BRG-001" required></div>
        <div class="field"><label>Nama barang</label><input type="text" name="nama" placeholder="cth. Sepeda Gunung 26\"" required></div>
        <div class="field"><label>Satuan</label><input type="text" name="satuan" placeholder="cth. unit, pcs" required></div>
        <div class="field"><label>Stok awal — belum rakit</label><input type="number" name="stok_belum_rakit" min="0" value="0"></div>
        <div class="field"><label>Stok awal — sudah rakit (siap kirim)</label><input type="number" name="stok" min="0" value="0"></div>
        <div class="field"><label>Batas stok minimum siap kirim (peringatan)</label><input type="number" name="stok_min" min="0" value="0"></div>
      </div>
      <div class="btn-row"><button type="submit" class="btn">Simpan barang</button></div>
    </form>
  </div>
</section>

<section class="block">
  <div class="block-head">
    <h2>Daftar barang</h2>
    <input type="search" class="search-input" placeholder="Cari kode, nama, atau satuan barang..."
           data-filter-rows="#items-table tr[data-row]" data-filter-empty="#items-empty">
  </div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Kode</th><th>Nama barang</th><th>Satuan</th><th class="num">Belum rakit</th><th class="num">Siap kirim</th><th class="num">Min.</th><th></th></tr></thead>
      <tbody id="items-table">
        <?php if (empty($items)): ?>
          <tr class="empty-row"><td colspan="7">Belum ada barang. Tambahkan barang pertama di form atas.</td></tr>
        <?php else: foreach ($items as $i):
          $isLow = $i['stok_min'] > 0 && $i['stok'] <= $i['stok_min']; ?>
          <tr data-row>
            <td class="mono"><?= e($i['kode']) ?></td>
            <td><?= e($i['nama']) ?></td>
            <td><?= e($i['satuan']) ?></td>
            <td class="num"><?= $i['stok_belum_rakit'] ?></td>
            <td class="num <?= $isLow ? 'low-stock' : '' ?>"><?= $i['stok'] ?></td>
            <td class="num"><?= $i['stok_min'] ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Hapus barang ini? Riwayat transaksinya juga akan terhapus.');" style="display:inline;">
                <input type="hidden" name="form" value="delete">
                <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                <button type="submit" class="icon-btn">Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
          <tr class="empty-row" id="items-empty" style="display:none;"><td colspan="7">Tidak ada barang yang cocok dengan pencarian.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
