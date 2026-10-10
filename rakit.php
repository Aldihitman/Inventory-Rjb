<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Rakit Barang';
$page_sub    = 'Ubah stok belum rakit menjadi stok sudah rakit (siap kirim)';
$active_page = 'rakit';
$form_error  = '';

// --- Simpan data perakitan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create') {
    $itemId  = (int)($_POST['item_id'] ?? 0);
    $jumlah  = (int)($_POST['jumlah'] ?? 0);
    $perakit = trim($_POST['perakit'] ?? '');
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $catatan = trim($_POST['catatan'] ?? '');

    if ($itemId <= 0 || $jumlah <= 0) {
        $form_error = 'Pilih barang dan isi jumlah yang valid.';
    } elseif ($perakit === '') {
        $form_error = 'Nama orang yang merakit wajib diisi.';
    } else {
        $pdo->beginTransaction();
        try {
            // Kunci baris barang & validasi ulang stok belum rakit di dalam transaksi
            $lockStmt = $pdo->prepare('SELECT nama, satuan, stok_belum_rakit FROM items WHERE id = ? FOR UPDATE');
            $lockStmt->execute([$itemId]);
            $item = $lockStmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                $form_error = 'Barang tidak ditemukan.';
            } elseif ($jumlah > (int)$item['stok_belum_rakit']) {
                $pdo->rollBack();
                $form_error = 'Jumlah melebihi stok belum rakit. Stok belum rakit "' . $item['nama'] . '" saat ini hanya ' .
                    (int)$item['stok_belum_rakit'] . ' ' . $item['satuan'] . '. Rakitan tidak disimpan.';
            } else {
                $upd = $pdo->prepare('UPDATE items SET stok_belum_rakit = stok_belum_rakit - ?, stok = stok + ? WHERE id = ?');
                $upd->execute([$jumlah, $jumlah, $itemId]);

                $ins = $pdo->prepare(
                    'INSERT INTO assemblies (item_id, jumlah, perakit, tanggal, catatan, user_id)
                     VALUES (?,?,?,?,?,?)'
                );
                $ins->execute([$itemId, $jumlah, $perakit, $tanggal, $catatan, $_SESSION['user_id']]);

                $pdo->commit();
                flash_set($jumlah . ' ' . $item['satuan'] . ' "' . $item['nama'] . '" berhasil dirakit oleh ' . $perakit . ' dan ditambahkan ke stok siap kirim.');
                header('Location: rakit.php');
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $form_error = 'Gagal menyimpan data rakitan. Coba lagi.';
        }
    }
}

// --- Hapus riwayat rakitan (otomatis kembalikan stok) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM assemblies WHERE id = ?');
        $stmt->execute([$id]);
        $a = $stmt->fetch();

        if (!$a) {
            $pdo->rollBack();
            flash_set('Data rakitan tidak ditemukan.', 'err');
        } elseif (!$a['item_id']) {
            $pdo->rollBack();
            flash_set('Barang terkait sudah dihapus, tidak bisa membatalkan rakitan ini secara otomatis.', 'err');
        } else {
            $lockStmt = $pdo->prepare('SELECT stok FROM items WHERE id = ? FOR UPDATE');
            $lockStmt->execute([$a['item_id']]);
            $current = $lockStmt->fetchColumn();

            if ($current === false || (int)$current < (int)$a['jumlah']) {
                $pdo->rollBack();
                flash_set('Tidak bisa membatalkan: sebagian stok hasil rakitan ini sepertinya sudah terpakai/terkirim.', 'err');
            } else {
                $upd = $pdo->prepare('UPDATE items SET stok = stok - ?, stok_belum_rakit = stok_belum_rakit + ? WHERE id = ?');
                $upd->execute([$a['jumlah'], $a['jumlah'], $a['item_id']]);

                $del = $pdo->prepare('DELETE FROM assemblies WHERE id = ?');
                $del->execute([$id]);

                $pdo->commit();
                flash_set('Data rakitan dihapus dan stok dikembalikan ke belum rakit.');
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_set('Gagal menghapus data rakitan. Coba lagi.', 'err');
    }
    header('Location: rakit.php');
    exit;
}

$items = $pdo->query('SELECT * FROM items ORDER BY nama ASC')->fetchAll();

$riwayat = $pdo->query(
    'SELECT a.*, i.nama AS item_nama, i.satuan
     FROM assemblies a
     LEFT JOIN items i ON i.id = a.item_id
     ORDER BY a.tanggal DESC, a.id DESC
     LIMIT 50'
)->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Rakit barang</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= nl2br(e($form_error)) ?></div><?php endif; ?>

    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="big">—</div>Belum ada barang terdaftar.<div class="cta"><a href="barang.php">Tambahkan barang dulu &rarr;</a></div></div>
    <?php else: ?>
    <form method="post" id="form-rakit">
      <input type="hidden" name="form" value="create">
      <div class="form-grid">
        <div class="field span2">
          <label>Barang</label>
          <div class="combo" data-combo data-items='<?= e(json_encode(array_map(fn($i) => [
                'id' => $i['id'], 'kode' => $i['kode'], 'nama' => $i['nama'],
                'satuan' => $i['satuan'], 'stok' => $i['stok'], 'stok_belum_rakit' => $i['stok_belum_rakit'],
              ], $items), JSON_UNESCAPED_UNICODE)) ?>' id="rakit-combo">
            <input type="text" class="combo-input" data-combo-input placeholder="Ketik kode atau nama barang..." autocomplete="off">
            <input type="hidden" name="item_id" data-combo-value id="rakit-item-id">
            <div class="combo-list" data-combo-list></div>
          </div>
          <div class="combo-hint" id="rakit-stok-hint"></div>
        </div>
        <div class="field"><label>Jumlah dirakit</label><input type="number" id="rakit-jumlah" name="jumlah" min="1" required></div>
        <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Dirakit oleh</label><input type="text" name="perakit" placeholder="cth. Budi (teknisi)" required></div>
        <div class="field"></div>
        <div class="field span2"><label>Catatan</label><textarea name="catatan" placeholder="cth. kondisi barang, kelengkapan, dsb."></textarea></div>
      </div>
      <div class="btn-row"><button type="submit" class="btn">Simpan rakitan</button></div>
    </form>
    <script>
      (function () {
        var combo = document.getElementById('rakit-combo');
        var hiddenId = document.getElementById('rakit-item-id');
        var jumlah = document.getElementById('rakit-jumlah');
        var hint = document.getElementById('rakit-stok-hint');
        var items = {};
        try {
          JSON.parse(combo.getAttribute('data-items')).forEach(function (i) { items[i.id] = i; });
        } catch (e) {}

        function updateHint() {
          var item = items[hiddenId.value];
          if (!item) { hint.textContent = ''; return; }
          hint.textContent = 'Stok belum rakit tersedia: ' + item.stok_belum_rakit + ' ' + item.satuan;
          hint.style.color = (parseInt(jumlah.value, 10) || 0) > item.stok_belum_rakit ? 'var(--keluar)' : 'var(--muted)';
        }
        combo.addEventListener('click', function () { setTimeout(updateHint, 0); });
        combo.querySelector('[data-combo-input]').addEventListener('input', function () { setTimeout(updateHint, 0); });
        jumlah.addEventListener('input', updateHint);

        document.getElementById('form-rakit').addEventListener('submit', function (e) {
          var item = items[hiddenId.value];
          var qty = parseInt(jumlah.value, 10) || 0;
          if (item && qty > item.stok_belum_rakit) {
            e.preventDefault();
            alert('Jumlah melebihi stok belum rakit.\nStok belum rakit "' + item.nama + '" saat ini hanya ' + item.stok_belum_rakit + ' ' + item.satuan + '.');
          }
        });
      })();
    </script>
    <?php endif; ?>
  </div>
</section>

<section class="block">
  <div class="block-head"><h2>Riwayat rakitan</h2></div>
  <div class="block-body" style="padding:0;">
    <table>
      <thead><tr><th>Tanggal</th><th>Barang</th><th class="num">Jumlah</th><th>Dirakit oleh</th><th>Catatan</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($riwayat)): ?>
          <tr class="empty-row"><td colspan="6">Belum ada riwayat perakitan.</td></tr>
        <?php else: foreach ($riwayat as $r): ?>
          <tr>
            <td><?= format_tanggal($r['tanggal']) ?></td>
            <td><?= $r['item_nama'] ? e($r['item_nama']) : '(barang dihapus)' ?></td>
            <td class="num"><?= (int)$r['jumlah'] ?> <?= e($r['satuan']) ?></td>
            <td><?= e($r['perakit']) ?></td>
            <td><?= e($r['catatan'] ?: '-') ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Batalkan rakitan ini? Stok akan dikembalikan ke belum rakit.');" style="display:inline;">
                <input type="hidden" name="form" value="delete">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="icon-btn">Batalkan</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php require 'includes/footer.php'; ?>
