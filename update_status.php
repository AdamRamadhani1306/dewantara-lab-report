<?php
require __DIR__ . '/koneksi.php';

// Hanya admin yang login & hanya metode POST
if (!is_admin()) {
    header('Location: admin.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

// Kembali ke halaman admin dengan filter pencarian yang sama
$kembali = 'admin.php';
$qs = preg_replace('/[^A-Za-z0-9=&%_\-\.\+]/', '', $_POST['kembali'] ?? '');
if ($qs !== '') {
    $kembali .= '?' . $qs;
}

if (!csrf_valid($_POST['csrf'] ?? '')) {
    set_flash('danger', 'Sesi tidak valid. Silakan coba lagi.');
    header('Location: ' . $kembali);
    exit;
}

$id     = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

if (!$id || !in_array($status, ['Menunggu', 'Diproses', 'Selesai'], true)) {
    set_flash('danger', 'Data tidak valid.');
    header('Location: ' . $kembali);
    exit;
}

$stmt = $koneksi->prepare('UPDATE laporan SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);
$stmt->execute();
$stmt->close();

set_flash('success', "Status laporan #$id diubah menjadi \"$status\".");
header('Location: ' . $kembali);
exit;
