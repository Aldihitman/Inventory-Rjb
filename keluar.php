<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Barang Keluar';
$page_sub    = 'Catat pengeluaran stok (dari stok yang sudah dirakit / siap kirim)';
$active_page = 'keluar';
$form_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId  = (int)($_POST['item_id'] ?? 0);
    $jumlah  = (int)($_POST['jumlah'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $tujuan  = trim($_POST['tujuan'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if ($itemId <= 0 || $jumlah <= 0) {
        $form_error = 'Pilih barang dan isi jumlah yang valid.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            $form_error = 'Barang tidak ditemukan.';
        } elseif ($jumlah > $item['stok']) {
            $form_error = 'Stok tidak cukup. Stok tersedia saat ini: ' . $item['stok'] . ' ' . $item['satuan'] . '.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE items SET stok = stok - ? WHERE id = ?');
                $stmt->execute([$jumlah, $itemId]);

                $stmt = $pdo->prepare(
                    'INSERT INTO transactions (type, item_id, jumlah, tanggal, pihak, catatan, user_id)
                     VALUES ("keluar", ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$itemId, $jumlah, $tanggal, $tujuan, $catatan, $_SESSION['user_id']]);

                $pdo->commit();
                flash_set('Barang keluar berhasil dicatat.');
                header('Location: keluar.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $form_error = 'Gagal menyimpan data. Coba lagi.';
            }
        }
    }
}

$items = $pdo->query('SELECT * FROM items ORDER BY nama ASC')->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Catat barang keluar</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= e($form_error) ?></div><?php endif; ?>
    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="big">—</div>Belum ada barang terdaftar.<div class="cta"><a href="barang.php">Tambahkan barang dulu &rarr;</a></div></div>
    <?php else: ?>
    <form method="post">
      <div class="form-grid">
        <div class="field">
          <label>Barang</label>
          <div class="combo" data-combo data-items='<?= e(json_encode(array_map(fn($i) => [
                'id' => $i['id'], 'kode' => $i['kode'], 'nama' => $i['nama'],
                'satuan' => $i['satuan'], 'stok' => $i['stok'], 'stok_belum_rakit' => $i['stok_belum_rakit'], 'stok_min' => $i['stok_min'],
              ], $items), JSON_UNESCAPED_UNICODE)) ?>'>
            <input type="text" class="combo-input" data-combo-input placeholder="Ketik kode atau nama barang..." autocomplete="off">
            <input type="hidden" name="item_id" data-combo-value>
            <div class="combo-list" data-combo-list></div>
          </div>
        </div>
        <div class="field"><label>Jumlah keluar (dari stok siap kirim)</label><input type="number" name="jumlah" min="1" required></div>
        <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Tujuan (customer/proyek)</label><input type="text" name="tujuan" placeholder="cth. Toko Makmur"></div>
        <div class="field span2"><label>Catatan</label><textarea name="catatan"></textarea></div>
      </div>
      <div class="btn-row"><button type="submit" class="btn btn-keluar">Catat barang keluar</button></div>
    </form>
    <?php endif; ?>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
