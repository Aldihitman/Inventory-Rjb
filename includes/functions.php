<?php
/** Escape output HTML agar aman dari XSS. */
function e($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Format tanggal Y-m-d menjadi "13 Sep 2026". */
function format_tanggal(?string $tgl): string
{
    if (!$tgl) return '-';
    $bulan = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun',
              '07'=>'Jul','08'=>'Agu','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
    $p = explode('-', $tgl);
    if (count($p) !== 3) return $tgl;
    return $p[2] . ' ' . ($bulan[$p[1]] ?? $p[1]) . ' ' . $p[0];
}

/** Simpan pesan notifikasi ke session (tampil sekali lalu hilang). */
function flash_set(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

/** Ambil & hapus pesan notifikasi dari session. */
function flash_get(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Tampilkan kotak notifikasi jika ada flash message. */
function render_flash(): void
{
    $f = flash_get();
    if ($f) {
        $cls = $f['type'] === 'err' ? 'err' : 'ok';
        echo '<div class="msg show ' . $cls . '">' . e($f['msg']) . '</div>';
    }
}

/** Buat nomor surat jalan otomatis: SJ/RJB/{tahun}/{urut}. */
function next_sj_number(PDO $pdo, string $tahun): string
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_jalan WHERE nomor LIKE ?");
    $stmt->execute(["SJ/RJB/$tahun/%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('SJ/RJB/%s/%03d', $tahun, $count);
}

/** Ambil profil perusahaan (untuk kop surat). */
function get_company(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM company_profile WHERE id = 1");
    $row = $stmt->fetch();
    return $row ?: ['nama' => 'PT Rejeki Jaya Baik', 'alamat' => '', 'telp' => '', 'logo_path' => 'assets/img/logo.png'];
}
