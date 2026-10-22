<?php
require __DIR__ . '/koneksi.php';
require __DIR__ . '/helper_laporan.php';

// ---------- LOGOUT ----------
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---------- LOGIN ----------
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'login') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if (csrf_valid($_POST['csrf'] ?? '') && hash_equals(ADMIN_USER, $u) && hash_equals(ADMIN_PASS, $p)) {
        session_regenerate_id(true);
        $_SESSION['admin_login'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Username atau password salah.';
}

if (!is_admin()):
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login Admin - Dewantara Lab Report</title>
  <link rel="icon" href="assets/favicon.ico?v=1" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=1">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=1">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png?v=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/tema.css?v=<?= (int)@filemtime(__DIR__ . '/assets/tema.css') ?>" rel="stylesheet">
</head>
<body style="background:linear-gradient(135deg,#3b0a12,#800000);min-height:100vh" class="d-flex align-items-center">
  <div class="container" style="max-width:400px">
    <div class="card border-0 shadow-lg rounded-4">
      <div class="card-body p-4">
        <h1 class="h5 fw-bold text-center mb-1">Login Petugas Lab</h1>
        <p class="text-center text-muted small mb-4">Dewantara Lab Report</p>
        <?php if ($loginError): ?><div class="alert alert-danger py-2"><?= e($loginError) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="aksi" value="login">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required autofocus></div>
          <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
          <button class="btn btn-primary w-100">Masuk</button>
          <a href="index.php" class="btn btn-link w-100 mt-2">&larr; Kembali ke form laporan</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
<?php
    exit;
endif;

// ---------- DATA ----------
$f      = filter_laporan($_GET);
$rows   = query_laporan($koneksi, $f);
$rekap  = rekap_bulanan($koneksi);
$tahunTersedia = array_values(array_unique(array_column($rekap, 'tahun')));
$count  = hitung_status($rows);
$total  = count($rows);

// Jumlah laporan per tahun & per bulan pada hasil saat ini (untuk judul kelompok)
$perTahun = []; $perBulan = [];
foreach ($rows as $r) {
    $ts = strtotime($r['tanggal_laporan']);
    $y = (int)date('Y', $ts);
    $ym = $y . '-' . (int)date('n', $ts);
    $perTahun[$y] = ($perTahun[$y] ?? 0) + 1;
    $perBulan[$ym] = ($perBulan[$ym] ?? 0) + 1;
}
$badge = ['Menunggu' => 'warning text-dark', 'Diproses' => 'info text-dark', 'Selesai' => 'success'];
$adaFilter = qs_filter($f) !== '';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Admin - Dewantara Lab Report</title>
  <link rel="icon" href="assets/favicon.ico?v=1" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=1">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=1">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png?v=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/tema.css?v=<?= (int)@filemtime(__DIR__ . '/assets/tema.css') ?>" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body { background:#f4f6fb; }
    .thumb { width:56px; height:56px; object-fit:cover; border-radius:.5rem; cursor:pointer; }
    .stat { border:0; border-radius:1rem; box-shadow:0 4px 14px rgba(15,23,42,.06); }
    .chip { display:inline-flex; align-items:center; gap:.4rem; flex:none; padding:.4rem .75rem; border:1px solid #D8D4CF; border-radius:2rem;
            background:#fff; color:#252525; text-decoration:none; font-size:.875rem; white-space:nowrap; }
    .chip:hover { border-color:#80000B; color:#80000B; }
    .chip.aktif { background:#80000B; border-color:#80000B; color:#fff; }
    .chip .n { background:#F1E4E5; color:#5B0710; border-radius:1rem; padding:0 .45rem; font-size:.75rem; font-weight:600; }
    .chip.aktif .n { background:#fff; }
    .baris-tahun td { background:#5B0710; color:#fff; font-weight:700; letter-spacing:.02em; }
    .baris-bulan td { background:#F1E4E5; color:#5B0710; font-weight:600; }
    .baris-bulan a { color:#5B0710; font-weight:500; font-size:.8rem; text-decoration:none; margin-left:.75rem; }
    .baris-bulan a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<nav class="navbar navbar-dark" style="background:#5B0710;border-bottom:3px solid #80000B">
  <div class="container-fluid px-3 px-md-4">
    <span class="navbar-brand fw-semibold"><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</span>
    <div>
      <a href="index.php" class="btn btn-sm btn-outline-light me-1"><i class="bi bi-plus-lg"></i> Form</a>
      <a href="?logout=1" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-right"></i> Keluar</a>
    </div>
  </div>
</nav>

<main class="container-fluid px-3 px-md-4 py-4">
  <?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show"><?= e($flash['msg']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <!-- Ringkasan sesuai filter yang sedang aktif -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat"><div class="card-body py-3 text-center">
      <div class="fs-3 fw-bold"><?= $total ?></div><span class="badge bg-dark">Total<?= $adaFilter ? ' (sesuai filter)' : '' ?></span>
    </div></div></div>
    <?php foreach ($count as $s => $n): ?>
      <div class="col-6 col-md-3"><div class="card stat"><div class="card-body py-3 text-center">
        <div class="fs-3 fw-bold"><?= $n ?></div><span class="badge bg-<?= $badge[$s] ?>"><?= e($s) ?></span>
      </div></div></div>
    <?php endforeach; ?>
  </div>

  <!-- Rekap per bulan -->
  <div class="card stat mb-3"><div class="card-body py-3">
    <div class="small text-muted fw-semibold mb-2"><i class="bi bi-calendar3 me-1"></i>Rekap laporan per bulan</div>
    <?php if (!$rekap): ?>
      <div class="text-muted small">Belum ada laporan masuk.</div>
    <?php else: ?>
      <div class="d-flex gap-2 overflow-auto pb-1">
        <a class="chip <?= !$f['tahun'] && !$f['bulan'] ? 'aktif' : '' ?>" href="admin.php">Semua <span class="n"><?= array_sum(array_column($rekap, 'jumlah')) ?></span></a>
        <?php foreach ($rekap as $rk): ?>
          <a class="chip <?= ($f['tahun'] === $rk['tahun'] && $f['bulan'] === $rk['bulan']) ? 'aktif' : '' ?>"
             href="admin.php?tahun=<?= $rk['tahun'] ?>&amp;bulan=<?= $rk['bulan'] ?>"><?= e(substr(NAMA_BULAN[$rk['bulan']], 0, 3)) ?> <?= $rk['tahun'] ?> <span class="n"><?= $rk['jumlah'] ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div></div>

  <!-- Filter + cetak -->
  <div class="card stat mb-4"><div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1" for="f-q">Nama alat</label>
        <input type="search" id="f-q" name="q" class="form-control" placeholder="Cari nama alat..." value="<?= e($f['q']) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="f-tahun">Tahun</label>
        <select id="f-tahun" name="tahun" class="form-select">
          <option value="">Semua tahun</option>
          <?php foreach ($tahunTersedia as $th): ?><option value="<?= $th ?>" <?= $f['tahun'] === $th ? 'selected' : '' ?>><?= $th ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="f-bulan">Bulan</label>
        <select id="f-bulan" name="bulan" class="form-select">
          <option value="">Semua bulan</option>
          <?php foreach (NAMA_BULAN as $no => $nm): ?><option value="<?= $no ?>" <?= $f['bulan'] === $no ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1" for="f-tanggal">Tanggal</label>
        <input type="date" id="f-tanggal" name="tanggal" class="form-control" value="<?= e($f['tanggal']) ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="f-status">Status</label>
        <select id="f-status" name="status" class="form-select">
          <option value="">Semua status</option>
          <?php foreach (DAFTAR_STATUS as $s): ?><option value="<?= e($s) ?>" <?= $f['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 d-flex flex-wrap gap-2 justify-content-between pt-2">
        <div class="d-flex gap-2">
          <button class="btn btn-primary"><i class="bi bi-search"></i> Cari</button>
          <a href="admin.php" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= e(url_cetak($f, 'cetak')) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Cetak</a>
          <a href="<?= e(url_cetak($f, 'pdf')) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> Unduh PDF</a>
        </div>
      </div>
    </form>
  </div></div>

  <div class="card stat"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>#</th><th>Tanggal</th><th>Alat</th><th>Kerusakan</th><th>Pelapor</th><th>Foto</th><th>Status</th><th style="min-width:190px">Ubah Status</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-5"><?= $adaFilter ? 'Tidak ada laporan untuk filter ini.' : 'Belum ada laporan masuk.' ?></td></tr>
      <?php endif; ?>
      <?php
      $prevY = null; $prevYM = null;
      foreach ($rows as $r):
          $ts = strtotime($r['tanggal_laporan']);
          $y = (int)date('Y', $ts); $m = (int)date('n', $ts); $ym = $y . '-' . $m;
          if ($y !== $prevY): ?>
        <tr class="baris-tahun"><td colspan="8"><i class="bi bi-calendar3 me-1"></i>Tahun <?= $y ?> <span class="fw-normal opacity-75">&middot; <?= $perTahun[$y] ?> laporan</span></td></tr>
      <?php endif;
          if ($ym !== $prevYM): ?>
        <tr class="baris-bulan"><td colspan="8">
          <?= e(NAMA_BULAN[$m]) ?> <?= $y ?> <span class="fw-normal">&middot; <?= $perBulan[$ym] ?> laporan</span>
          <a href="<?= e(url_cetak(['tahun' => $y, 'bulan' => $m], 'cetak')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Cetak bulan ini</a>
          <a href="<?= e(url_cetak(['tahun' => $y, 'bulan' => $m], 'pdf')) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </td></tr>
      <?php endif;
          $prevY = $y; $prevYM = $ym; ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td class="text-nowrap">
            <div class="fw-semibold"><?= e(tanggal_id($ts, true)) ?></div>
            <div class="small text-muted"><?= e(date('H:i', $ts)) ?> WIB</div>
          </td>
          <td class="fw-semibold"><?= e($r['nama_alat']) ?></td>
          <td style="min-width:220px">
            <?= nl2br(e($r['jenis_kerusakan'])) ?>
            <?php if ($r['keterangan']): ?><div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> <?= e($r['keterangan']) ?></div><?php endif; ?>
          </td>
          <td><?= e($r['nama_pelapor']) ?></td>
          <td>
            <?php if (!empty($r['foto_bukti'])): ?>
            <img src="uploads/<?= e($r['foto_bukti']) ?>" class="thumb" alt="Bukti" loading="lazy"
                 data-bs-toggle="modal" data-bs-target="#fotoModal" data-src="uploads/<?= e($r['foto_bukti']) ?>"
                 onerror="this.style.visibility='hidden'">
            <?php else: ?>
            <span class="text-muted small">Tanpa foto</span>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-<?= $badge[$r['status']] ?>"><?= e($r['status']) ?></span></td>
          <td>
            <form method="post" action="update_status.php" class="d-flex gap-1">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="kembali" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
              <select name="status" class="form-select form-select-sm">
                <?php foreach (array_keys($badge) as $s): ?><option <?= $r['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-primary" title="Simpan"><i class="bi bi-check-lg"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
</main>

<div class="modal fade" id="fotoModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-body p-2 text-center"><img id="fotoBesar" src="" class="img-fluid rounded" alt="Foto bukti"></div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('fotoModal').addEventListener('show.bs.modal', function (ev) {
    document.getElementById('fotoBesar').src = ev.relatedTarget.dataset.src;
  });
</script>
</body>
</html>
