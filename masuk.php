<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Barang Masuk';
$page_sub    = 'Catat kedatangan barang (masuk sebagai stok belum rakit)';
$active_page = 'masuk';
$form_error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId      = (int)($_POST['item_id'] ?? 0);
    $jumlah      = (int)($_POST['jumlah'] ?? 0);
    $jumlahKoliRaw = trim($_POST['jumlah_koli'] ?? '');
    $isiPerKoliRaw = trim($_POST['isi_per_koli'] ?? '');
    $jumlahKoli  = $jumlahKoliRaw !== '' ? (int)$jumlahKoliRaw : null;
    $isiPerKoli  = $isiPerKoliRaw !== '' ? (int)$isiPerKoliRaw : null;
    $tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
    $sumber      = trim($_POST['sumber'] ?? '');
    $catatan     = trim($_POST['catatan'] ?? '');

    if ($itemId <= 0 || $jumlah <= 0) {
        $form_error = 'Pilih barang dan isi jumlah yang valid.';
    } else {
        $pdo->beginTransaction();
        try {
            // Barang baru datang masuk sebagai stok BELUM RAKIT.
            $stmt = $pdo->prepare('UPDATE items SET stok_belum_rakit = stok_belum_rakit + ? WHERE id = ?');
            $stmt->execute([$jumlah, $itemId]);

            $stmt = $pdo->prepare(
                'INSERT INTO transactions (type, item_id, jumlah, tanggal, pihak, catatan, jumlah_koli, isi_per_koli, user_id)
                 VALUES ("masuk", ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$itemId, $jumlah, $tanggal, $sumber, $catatan, $jumlahKoli, $isiPerKoli, $_SESSION['user_id']]);

            $pdo->commit();
            flash_set('Barang masuk berhasil dicatat sebagai stok belum rakit. Lanjutkan ke menu Rakit Barang untuk merakitnya.');
            header('Location: masuk.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $form_error = 'Gagal menyimpan data. Coba lagi.';
        }
    }
}

$items = $pdo->query('SELECT * FROM items ORDER BY nama ASC')->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Catat barang masuk</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= e($form_error) ?></div><?php endif; ?>
    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="big">—</div>Belum ada barang terdaftar.<div class="cta"><a href="barang.php">Tambahkan barang dulu &rarr;</a></div></div>
    <?php else: ?>
    <form method="post" id="form-masuk">
      <div class="form-grid">
        <div class="field span2">
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

        <div class="field"><label>Jumlah koli (opsional)</label><input type="number" id="m-koli" name="jumlah_koli" min="0" placeholder="cth. 4"></div>
        <div class="field"><label>Isi per koli (opsional)</label><input type="number" id="m-isi" name="isi_per_koli" min="0" placeholder="cth. 8 sepeda/koli"></div>

        <div class="field">
          <label>Jumlah masuk (unit, belum rakit)</label>
          <input type="number" id="m-jumlah" name="jumlah" min="1" required>
          <div class="combo-hint" id="m-koli-hint"></div>
        </div>
        <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Dari (pemasok/sumber)</label><input type="text" name="sumber" placeholder="cth. Supplier A"></div>
        <div class="field span2"><label>Catatan</label><textarea name="catatan"></textarea></div>
      </div>
      <div class="btn-row"><button type="submit" class="btn btn-masuk">Catat barang masuk</button></div>
    </form>
    <script>
      (function () {
        var koli = document.getElementById('m-koli');
        var isi = document.getElementById('m-isi');
        var jumlah = document.getElementById('m-jumlah');
        var hint = document.getElementById('m-koli-hint');
        function recalc() {
          var k = parseInt(koli.value, 10) || 0;
          var i = parseInt(isi.value, 10) || 0;
          if (k > 0 && i > 0) {
            jumlah.value = k * i;
            hint.textContent = k + ' koli × ' + i + ' pcs/koli = ' + (k * i) + ' unit';
          } else {
            hint.textContent = '';
          }
        }
        koli.addEventListener('input', recalc);
        isi.addEventListener('input', recalc);
      })();
    </script>
    <?php endif; ?>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
