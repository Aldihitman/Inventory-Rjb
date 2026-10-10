<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$page_title  = 'Surat Jalan';
$page_sub    = 'Buat dan cetak surat jalan pengiriman';
$active_page = 'suratjalan';
$form_error  = '';

// Kolom stok yang dipotong sesuai kondisi barang yang dikirim.
$KOLOM_STOK  = ['rakit' => 'stok', 'belum_rakit' => 'stok_belum_rakit'];
$LABEL_STOK  = ['rakit' => 'sudah rakit', 'belum_rakit' => 'belum rakit (koli)'];

// --- Hapus surat jalan ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $pdo->beginTransaction();
    try {
        // Kembalikan stok sesuai kondisi barang yang dulu dipotong oleh surat jalan ini
        $stmt = $pdo->prepare('SELECT item_id, jumlah, kondisi FROM surat_jalan_items WHERE surat_jalan_id = ?');
        $stmt->execute([$id]);
        $sjItems = $stmt->fetchAll();

        foreach ($sjItems as $si) {
            if ($si['item_id']) { // hanya jika barangnya belum terhapus dari master data
                $kolom = $KOLOM_STOK[$si['kondisi']] ?? 'stok';
                $restoreStmt = $pdo->prepare("UPDATE items SET $kolom = $kolom + ? WHERE id = ?");
                $restoreStmt->execute([$si['jumlah'], $si['item_id']]);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM surat_jalan WHERE id = ?');
        $stmt->execute([$id]);

        $pdo->commit();
        flash_set('Surat jalan berhasil dihapus dan stok barang telah dikembalikan.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_set('Gagal menghapus surat jalan. Coba lagi.', 'err');
    }
    header('Location: surat_jalan.php');
    exit;
}

// --- Simpan surat jalan baru ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create') {
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $penerima  = trim($_POST['penerima'] ?? '');
    $alamat    = trim($_POST['alamat'] ?? '');
    $kendaraan = trim($_POST['kendaraan'] ?? '');
    $sopir     = trim($_POST['sopir'] ?? '');
    $catatan   = trim($_POST['catatan'] ?? '');
    $barangIds = $_POST['barang_item'] ?? [];
    $barangQty = $_POST['barang_qty'] ?? [];
    $barangKet = $_POST['barang_ket'] ?? [];
    $barangKon = $_POST['barang_kondisi'] ?? [];

    $rows = [];
    foreach ($barangIds as $idx => $itemId) {
        $itemId  = (int)$itemId;
        $qty     = (int)($barangQty[$idx] ?? 0);
        $kondisi = $barangKon[$idx] ?? 'rakit';
        if (!isset($KOLOM_STOK[$kondisi])) $kondisi = 'rakit';
        if ($itemId > 0 && $qty > 0) {
            $rows[] = ['item_id' => $itemId, 'qty' => $qty, 'kondisi' => $kondisi, 'ket' => trim($barangKet[$idx] ?? '')];
        }
    }

    // Jumlahkan kebutuhan per barang + kondisi, supaya baris ganda tetap tervalidasi benar
    $needed = []; // key: "itemId|kondisi"
    foreach ($rows as $r) {
        $key = $r['item_id'] . '|' . $r['kondisi'];
        $needed[$key] = ($needed[$key] ?? 0) + $r['qty'];
    }

    if (empty($rows)) {
        $form_error = 'Tambahkan minimal satu barang untuk dikirim.';
    } else {
        // Cek stok untuk setiap barang & kondisi SEBELUM menyimpan apa pun
        $stockErrors = [];
        $itemMap = [];
        foreach ($needed as $key => $qtyNeeded) {
            [$itemId, $kondisi] = explode('|', $key);
            $kolom = $KOLOM_STOK[$kondisi];
            $stmt = $pdo->prepare("SELECT nama, satuan, $kolom AS tersedia FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                $stockErrors[] = 'Barang tidak ditemukan (mungkin sudah dihapus).';
                continue;
            }
            $itemMap[$itemId] = $item;
            if ($qtyNeeded > (int)$item['tersedia']) {
                $stockErrors[] = $item['nama'] . ' (' . $LABEL_STOK[$kondisi] . '): diminta ' . $qtyNeeded . ' ' . $item['satuan'] .
                    ', stok ' . $LABEL_STOK[$kondisi] . ' tersedia hanya ' . (int)$item['tersedia'] . ' ' . $item['satuan'] . '.';
            }
        }

        if (!empty($stockErrors)) {
            $form_error = "Surat jalan tidak bisa disimpan karena stok tidak mencukupi:\n" . implode("\n", $stockErrors);
        } else {
            $tahun  = date('Y', strtotime($tanggal));
            $nomor  = next_sj_number($pdo, $tahun);

            $pdo->beginTransaction();
            try {
                // Kunci baris barang & validasi ulang stok di dalam transaksi (cegah race condition)
                $stockChanged = false;
                foreach ($needed as $key => $qtyNeeded) {
                    [$itemId, $kondisi] = explode('|', $key);
                    $kolom = $KOLOM_STOK[$kondisi];
                    $lockStmt = $pdo->prepare("SELECT $kolom FROM items WHERE id = ? FOR UPDATE");
                    $lockStmt->execute([$itemId]);
                    $current = $lockStmt->fetchColumn();
                    if ($current === false || $qtyNeeded > (int)$current) {
                        $stockChanged = true;
                        break;
                    }
                }

                if ($stockChanged) {
                    $pdo->rollBack();
                    $form_error = 'Stok barang berubah saat surat jalan disimpan (mungkin ada transaksi lain). Silakan periksa stok dan coba lagi.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO surat_jalan (nomor, tanggal, penerima, alamat, kendaraan, sopir, catatan, user_id)
                         VALUES (?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$nomor, $tanggal, $penerima, $alamat, $kendaraan, $sopir, $catatan, $_SESSION['user_id']]);
                    $sjId = (int)$pdo->lastInsertId();

                    $insStmt = $pdo->prepare(
                        'INSERT INTO surat_jalan_items (surat_jalan_id, item_id, nama_barang, jumlah, satuan, kondisi, keterangan)
                         VALUES (?,?,?,?,?,?,?)'
                    );

                    foreach ($rows as $r) {
                        $item  = $itemMap[$r['item_id']];
                        $kolom = $KOLOM_STOK[$r['kondisi']];
                        $insStmt->execute([$sjId, $r['item_id'], $item['nama'], $r['qty'], $item['satuan'], $r['kondisi'], $r['ket']]);
                        $updStmt = $pdo->prepare("UPDATE items SET $kolom = $kolom - ? WHERE id = ?");
                        $updStmt->execute([$r['qty'], $r['item_id']]);
                    }

                    $pdo->commit();
                    flash_set('Surat jalan ' . $nomor . ' berhasil dibuat dan stok telah dipotong.');
                    header('Location: surat_jalan.php');
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $form_error = 'Gagal menyimpan surat jalan. Coba lagi.';
            }
        }
    }
}

$items = $pdo->query('SELECT * FROM items ORDER BY nama ASC')->fetchAll();
$nextNumber = next_sj_number($pdo, date('Y'));

$suratJalanList = $pdo->query(
    "SELECT sj.*,
            (SELECT COUNT(*) FROM surat_jalan_items sji WHERE sji.surat_jalan_id = sj.id) AS jumlah_barang,
            (SELECT GROUP_CONCAT(sji.nama_barang SEPARATOR ' ') FROM surat_jalan_items sji WHERE sji.surat_jalan_id = sj.id) AS nama_barang_all
     FROM surat_jalan sj ORDER BY sj.tanggal DESC, sj.id DESC"
)->fetchAll();

require 'includes/header.php';
require 'includes/sidebar.php';
?>

<section class="block">
  <div class="block-head"><h2>Buat surat jalan baru</h2></div>
  <div class="block-body">
    <?php render_flash(); ?>
    <?php if ($form_error): ?><div class="msg show err"><?= nl2br(e($form_error)) ?></div><?php endif; ?>

    <?php if (empty($items)): ?>
      <div class="empty-state"><div class="big">—</div>Belum ada barang terdaftar.<div class="cta"><a href="barang.php">Tambahkan barang dulu &rarr;</a></div></div>
    <?php else: ?>
    <form method="post" id="form-sj">
      <input type="hidden" name="form" value="create">
      <div class="form-grid">
        <div class="field"><label>Nomor surat jalan</label><input type="text" value="<?= e($nextNumber) ?>" readonly class="mono"></div>
        <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label>Nama penerima / tujuan</label><input type="text" name="penerima" placeholder="cth. PT Maju Jaya" required></div>
        <div class="field"><label>Nomor kendaraan</label><input type="text" name="kendaraan" placeholder="cth. B 1234 XY"></div>
        <div class="field span2"><label>Alamat tujuan</label><input type="text" name="alamat" placeholder="Alamat lengkap tujuan pengiriman"></div>
        <div class="field"><label>Nama sopir</label><input type="text" name="sopir"></div>
        <div class="field"></div>
      </div>

      <div class="field span2" style="margin-top:16px;">
        <label>Daftar barang dikirim</label>
        <div class="sj-rows" id="sj-rows">
          <div class="sj-row head"><div>Barang (ketik untuk cari)</div><div>Kondisi</div><div>Jumlah</div><div>Satuan</div><div>Keterangan</div><div></div></div>
        </div>
        <button type="button" class="btn ghost small" id="sj-add-row">+ Tambah baris barang</button>
      </div>

      <div class="field span2" style="margin-top:14px;">
        <label>Catatan</label>
        <textarea name="catatan"></textarea>
      </div>

      <div class="btn-row"><button type="submit" class="btn">Simpan surat jalan</button></div>
    </form>
    <?php endif; ?>
  </div>
</section>

<section class="block">
  <div class="block-head">
    <h2>Surat jalan tersimpan</h2>
    <input type="search" class="search-input" placeholder="Cari nomor, penerima, tanggal, atau nama barang..."
           data-filter-rows="#sj-list .list-item" data-filter-empty="#sj-empty">
  </div>
  <div class="block-body" style="padding:0;" id="sj-list">
    <?php if (empty($suratJalanList)): ?>
      <div class="empty-state"><div class="big">—</div>Belum ada surat jalan.<div class="cta">Isi form di atas untuk membuat surat jalan pertama.</div></div>
    <?php else: foreach ($suratJalanList as $sj): ?>
      <div class="list-item">
        <div class="li-main">
          <div class="num"><?= e($sj['nomor']) ?></div>
          <div class="to"><?= format_tanggal($sj['tanggal']) ?> · kepada <?= e($sj['penerima'] ?: '-') ?> · <?= (int)$sj['jumlah_barang'] ?> jenis barang</div>
          <span class="search-extra" hidden><?= e($sj['tanggal'] . ' ' . $sj['nama_barang_all'] . ' ' . $sj['kendaraan'] . ' ' . $sj['sopir']) ?></span>
        </div>
        <div class="li-actions">
          <a href="cetak_surat_jalan.php?id=<?= (int)$sj['id'] ?>" target="_blank" class="btn ghost small">Cetak</a>
          <form method="post" onsubmit="return confirm('Hapus surat jalan ini? Stok barang akan dikembalikan.');" style="display:inline;">
            <input type="hidden" name="form" value="delete">
            <input type="hidden" name="id" value="<?= (int)$sj['id'] ?>">
            <button type="submit" class="icon-btn">Hapus</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
      <div class="empty-state" id="sj-empty" style="display:none;">Tidak ada surat jalan yang cocok dengan pencarian.</div>
    <?php endif; ?>
  </div>
</section>

<script>
  // Data barang untuk pengisian otomatis satuan & info stok pada baris surat jalan
  window.RJB_ITEMS = <?= json_encode(array_map(fn($i) => ['id' => $i['id'], 'kode' => $i['kode'], 'nama' => $i['nama'], 'satuan' => $i['satuan'], 'stok' => (int)$i['stok'], 'stok_belum_rakit' => (int)$i['stok_belum_rakit'], 'stok_min' => (int)$i['stok_min']], $items), JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require 'includes/footer.php'; ?>
