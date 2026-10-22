<?php
/**
 * helper_laporan.php - fungsi bersama untuk admin.php dan cetak.php
 * (filter tahun/bulan, query laporan, format tanggal Indonesia).
 * Tidak berisi kredensial; aman ditimpa saat update.
 */

const NAMA_BULAN = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
const DAFTAR_STATUS = ['Menunggu', 'Diproses', 'Selesai'];

/** Ambil & validasi filter dari $_GET. */
function filter_laporan($src) {
    $f = ['q' => trim((string)($src['q'] ?? '')), 'tahun' => 0, 'bulan' => 0, 'tanggal' => '', 'status' => ''];

    $t = filter_var($src['tahun'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
    if ($t) { $f['tahun'] = $t; }

    $b = filter_var($src['bulan'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
    if ($b) { $f['bulan'] = $b; }

    $tg = trim((string)($src['tanggal'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tg)) { $f['tanggal'] = $tg; }

    $s = trim((string)($src['status'] ?? ''));
    if (in_array($s, DAFTAR_STATUS, true)) { $f['status'] = $s; }

    return $f;
}

/** Ambil laporan sesuai filter, terbaru di atas. */
function query_laporan($db, $f) {
    $where = []; $params = []; $types = '';
    if ($f['q'] !== '') {
        $where[] = 'nama_alat LIKE ?';
        $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
        $types .= 's';
    }
    if ($f['tahun']) {
        $where[] = 'YEAR(tanggal_laporan) = ?';
        $params[] = $f['tahun']; $types .= 'i';
    }
    if ($f['bulan']) {
        $where[] = 'MONTH(tanggal_laporan) = ?';
        $params[] = $f['bulan']; $types .= 'i';
    }
    if ($f['tanggal'] !== '') {
        $where[] = 'DATE(tanggal_laporan) = ?';
        $params[] = $f['tanggal']; $types .= 's';
    }
    if ($f['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $f['status']; $types .= 's';
    }
    $sql = 'SELECT id, nama_alat, jenis_kerusakan, nama_pelapor, tanggal_laporan, foto_bukti, status, keterangan FROM laporan'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY tanggal_laporan DESC, id DESC';
    $stmt = $db->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Rekap jumlah laporan per tahun-bulan (terbaru dulu). */
function rekap_bulanan($db) {
    $out = [];
    $res = $db->query('SELECT YEAR(tanggal_laporan) AS y, MONTH(tanggal_laporan) AS m, COUNT(*) AS c
                       FROM laporan GROUP BY y, m ORDER BY y DESC, m DESC');
    while ($r = $res->fetch_assoc()) {
        $out[] = ['tahun' => (int)$r['y'], 'bulan' => (int)$r['m'], 'jumlah' => (int)$r['c']];
    }
    return $out;
}

/** "21 Oktober 2026" */
function tanggal_id($ts, $singkat = false) {
    $b = NAMA_BULAN[(int)date('n', $ts)];
    if ($singkat) { $b = substr($b, 0, 3); }
    return date('j', $ts) . ' ' . $b . ' ' . date('Y', $ts);
}

/** Teks periode untuk judul laporan. */
function periode_teks($f) {
    if ($f['tanggal'] !== '') { return 'Tanggal ' . tanggal_id(strtotime($f['tanggal'])); }
    if ($f['tahun'] && $f['bulan']) { return NAMA_BULAN[$f['bulan']] . ' ' . $f['tahun']; }
    if ($f['tahun']) { return 'Tahun ' . $f['tahun']; }
    if ($f['bulan']) { return 'Bulan ' . NAMA_BULAN[$f['bulan']] . ' (semua tahun)'; }
    return 'Semua periode';
}

/** Nama dasar file unduhan, mis. laporan-kerusakan-lab-2026-10 */
function nama_file_laporan($f) {
    $n = 'laporan-kerusakan-lab';
    if ($f['tanggal'] !== '') { return $n . '-' . $f['tanggal']; }
    if ($f['tahun']) { $n .= '-' . $f['tahun']; }
    if ($f['bulan']) { $n .= '-' . ($f['tahun'] ? '' : 'bulan-') . sprintf('%02d', $f['bulan']); }
    if (!$f['tahun'] && !$f['bulan']) { $n .= '-semua'; }
    return $n;
}

/** Query string filter yang aktif (tanpa nilai kosong). */
function qs_filter($f) {
    return http_build_query(array_filter($f, function ($v) { return $v !== '' && $v !== 0; }));
}

/** URL ke cetak.php dengan filter yang sama. $aksi: 'cetak' | 'pdf' | '' (pratinjau). */
function url_cetak($f, $aksi = '') {
    $qs = qs_filter($f);
    if ($aksi !== '') { $qs .= ($qs !== '' ? '&' : '') . 'aksi=' . $aksi; }
    return 'cetak.php' . ($qs !== '' ? '?' . $qs : '');
}

/** Hitung jumlah per status. */
function hitung_status($rows) {
    $c = ['Menunggu' => 0, 'Diproses' => 0, 'Selesai' => 0];
    foreach ($rows as $r) { if (isset($c[$r['status']])) { $c[$r['status']]++; } }
    return $c;
}
