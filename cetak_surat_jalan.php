<?php
require 'includes/auth.php';
require_login();
require 'config/database.php';
require 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM surat_jalan WHERE id = ?');
$stmt->execute([$id]);
$sj = $stmt->fetch();

if (!$sj) {
    die('Surat jalan tidak ditemukan. <a href="surat_jalan.php">Kembali</a>');
}

$stmt = $pdo->prepare('SELECT * FROM surat_jalan_items WHERE surat_jalan_id = ?');
$stmt->execute([$id]);
$barang = $stmt->fetchAll();

$company = get_company($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Jalan <?= e($sj['nomor']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="print-body">

  <div class="print-toolbar no-print">
    <a href="surat_jalan.php" class="btn ghost small">&larr; Kembali</a>
    <button onclick="window.print()" class="btn small">Cetak / Simpan PDF</button>
  </div>

  <div class="sj-doc">
    <div class="doc-head">
      <div style="display:flex;gap:12px;align-items:flex-start;">
        <img src="<?= e($company['logo_path'] ?: 'assets/img/logo.png') ?>" alt="Logo" style="width:46px;height:46px;border-radius:50%;flex-shrink:0;">
        <div>
          <div class="co-name"><?= e($company['nama']) ?></div>
          <div class="co-addr"><?= e($company['alamat'] ?? '') ?><?= $company['telp'] ? '<br>Telp: ' . e($company['telp']) : '' ?></div>
        </div>
      </div>
      <div class="doc-title">
        <h2>SURAT JALAN</h2>
        <div class="num"><?= e($sj['nomor']) ?></div>
      </div>
    </div>

    <div class="sj-info">
      <div class="row"><div class="k">Tanggal</div><div>: <?= format_tanggal($sj['tanggal']) ?></div></div>
      <div class="row"><div class="k">No. kendaraan</div><div>: <?= e($sj['kendaraan'] ?: '-') ?></div></div>
      <div class="row"><div class="k">Kepada</div><div>: <?= e($sj['penerima'] ?: '-') ?></div></div>
      <div class="row"><div class="k">Sopir</div><div>: <?= e($sj['sopir'] ?: '-') ?></div></div>
      <div class="row"><div class="k">Alamat</div><div>: <?= e($sj['alamat'] ?: '-') ?></div></div>
    </div>

    <table>
      <thead>
        <tr><th style="width:30px;">No</th><th>Nama barang</th><th style="width:70px;">Jumlah</th><th style="width:70px;">Satuan</th><th style="width:110px;">Kondisi</th><th>Keterangan</th></tr>
      </thead>
      <tbody>
        <?php foreach ($barang as $idx => $b): ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td><?= e($b['nama_barang']) ?></td>
            <td class="num"><?= $b['jumlah'] ?></td>
            <td><?= e($b['satuan']) ?></td>
            <td><?= ($b['kondisi'] ?? 'rakit') === 'belum_rakit' ? 'Belum rakit (koli)' : 'Sudah rakit' ?></td>
            <td><?= e($b['keterangan'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="catatan"><?= $sj['catatan'] ? 'Catatan: ' . e($sj['catatan']) : '' ?></div>

    <div class="sj-sign">
      <div class="box">Pengirim</div>
      <div class="box">Sopir</div>
      <div class="box">Penerima</div>
    </div>
  </div>

</body>
</html>
