<?php
require __DIR__ . '/koneksi.php';

$errors = [];
$old = ['nama_alat' => '', 'jenis_kerusakan' => '', 'nama_pelapor' => '', 'keterangan' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $errors[] = 'Sesi tidak valid, silakan muat ulang halaman.';
    }

    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    if ($old['nama_alat'] === '' || mb_strlen($old['nama_alat']) > 150) {
        $errors[] = 'Nama alat wajib diisi (maks. 150 karakter).';
    }
    if ($old['jenis_kerusakan'] === '') {
        $errors[] = 'Jenis kerusakan wajib diisi.';
    }
    if ($old['nama_pelapor'] === '' || mb_strlen($old['nama_pelapor']) > 100) {
        $errors[] = 'Nama pelapor wajib diisi (maks. 100 karakter).';
    }

    // ---- Validasi foto ----
    $newName = null;
    $file = $_FILES['foto_bukti'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        // Foto bersifat opsional: tidak diunggah = tidak ada validasi
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'Ukuran foto melebihi batas.' : 'Gagal mengunggah foto (kode ' . (int)$file['error'] . ').';
    } else {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'Ukuran foto maksimal 2 MB.';
        } elseif (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) {
            $errors[] = 'Format foto harus JPG, JPEG, PNG, atau WEBP (dan benar-benar berupa gambar).';
        } elseif (@getimagesize($file['tmp_name']) === false) {
            $errors[] = 'File bukan gambar yang valid.';
        } else {
            $newName = 'lap_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        }
    }

    if (!$errors) {
        // Simpan foto hanya jika ada yang diunggah
        if ($newName) {
            if (!is_dir(UPLOAD_DIR)) {
                @mkdir(UPLOAD_DIR, 0755, true);
            }
            if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
                $errors[] = 'Gagal menyimpan foto. Pastikan folder uploads/ ada dan dapat ditulis.';
            }
        }

        if (!$errors) {
            $ket = $old['keterangan'] !== '' ? $old['keterangan'] : null;
            try {
                $stmt = $koneksi->prepare(
                    "INSERT INTO laporan (nama_alat, jenis_kerusakan, nama_pelapor, foto_bukti, keterangan) VALUES (?,?,?,?,?)"
                );
                $stmt->bind_param('sssss', $old['nama_alat'], $old['jenis_kerusakan'], $old['nama_pelapor'], $newName, $ket);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Laporan berhasil dikirim. Terima kasih, petugas lab akan segera menindaklanjuti.');
                header('Location: index.php');
                exit;
            } catch (mysqli_sql_exception $ex) {
                // Gagal simpan: hapus foto yang sudah terunggah agar tidak menumpuk
                if ($newName && is_file(UPLOAD_DIR . $newName)) {
                    @unlink(UPLOAD_DIR . $newName);
                }
                $errors[] = 'Laporan gagal disimpan karena gangguan pada server. Silakan coba lagi beberapa saat lagi.';
                if (defined('DEBUG') && DEBUG) {
                    $errors[] = 'Detail: ' . $ex->getMessage();
                }
            }
        }
    }
}
$flash = get_flash();
$tampilModal = !($errors || $flash); // modal sambutan tidak ditampilkan setelah kirim/validasi server
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#5B0710">
  <title>DELAPORT - Dewantara Lab Report</title>
  <link rel="icon" href="assets/favicon.ico?v=1" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png?v=1">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16.png?v=1">
  <link rel="apple-touch-icon" href="assets/apple-touch-icon.png?v=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/tema.css?v=<?= (int)@filemtime(__DIR__ . '/assets/tema.css') ?>" rel="stylesheet">
</head>
<body class="tema-bg">

<!-- POP UP SELAMAT DATANG -->
<div class="modal fade modal-welcome" id="welcomeModal" data-bs-backdrop="static" data-bs-keyboard="false"
     tabindex="-1" aria-labelledby="welcomeTitle" aria-describedby="welcomeDesc" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <img src="assets/logo-fti.png" alt="Logo ITEBIS dan FTI" class="logo-badge logo-modal"
             width="136" height="60" decoding="async" onerror="this.style.display='none'">
        <h2 class="judul" id="welcomeTitle">Selamat datang di DELAPORT</h2>
        <p class="sub">DEWANTARA LAB REPORT</p>
      </div>
      <div class="modal-body">
        <p class="desc" id="welcomeDesc">Website ini digunakan untuk melaporkan kerusakan fasilitas yang ada pada laboratorium komputer ITEBIS PGRI Dewantara. Isi form laporan dengan lengkap, lampirkan foto bukti bila ada, dan petugas lab akan menindaklanjutinya.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary btn-lanjut" id="btnLanjut" data-bs-dismiss="modal">LANJUT</button>
      </div>
    </div>
  </div>
</div>

<nav class="navbar navbar-dark nav-maroon" aria-label="Navigasi utama">
  <div class="container">
    <span class="navbar-brand brand">
      <img src="assets/logo-fti.png" alt="ITEBIS dan FTI" class="logo-badge logo-navbar"
           width="90" height="40" decoding="async" onerror="this.style.display='none'">
      <span class="brand-text">DELAPORT</span>
    </span>
    <a href="admin.php" class="btn btn-outline-light btn-admin"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Admin</span></a>
  </div>
</nav>

<header class="hero text-center">
  <div class="container">
    <h1>Lapor Kerusakan Fasilitas Laboratorium</h1>
    <p>ITEBIS PGRI Dewantara &mdash; isi form di bawah untuk melaporkan alat yang rusak.</p>
  </div>
</header>

<main class="container pb-5">
  <div class="form-wrap">
    <div class="card card-form">
      <div class="card-body p-3 p-sm-4">

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?> js-alert" role="<?= $flash['type'] === 'success' ? 'status' : 'alert' ?>" tabindex="-1">
            <i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= e($flash['msg']) ?>
          </div>
        <?php endif; ?>
        <?php if ($errors): ?>
          <div class="alert alert-danger js-alert" role="alert" tabindex="-1">
            <strong class="d-block mb-1">Laporan belum terkirim:</strong>
            <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <p class="form-note">Kolom bertanda <span class="req" aria-hidden="true">*</span><span class="visually-hidden">bintang</span> wajib diisi.</p>

        <form id="formLaporan" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

          <div class="mb-3">
            <label class="form-label" for="nama_pelapor">Nama Pelapor <span class="req" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" id="nama_pelapor" name="nama_pelapor" maxlength="100"
                   value="<?= e($old['nama_pelapor']) ?>" placeholder="Mahasiswa / Dosen / Petugas" autocomplete="name"
                   aria-required="true" aria-describedby="err-nama_pelapor" required>
            <div class="invalid-feedback" id="err-nama_pelapor"></div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nama_alat">Nama Alat / Fasilitas <span class="req" aria-hidden="true">*</span></label>
            <input type="text" class="form-control" id="nama_alat" name="nama_alat" maxlength="150"
                   value="<?= e($old['nama_alat']) ?>" placeholder="Contoh: PC Unit 10 / Proyektor "
                   aria-required="true" aria-describedby="err-nama_alat" required>
            <div class="invalid-feedback" id="err-nama_alat"></div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="jenis_kerusakan">Jenis Kerusakan <span class="req" aria-hidden="true">*</span></label>
            <textarea class="form-control" id="jenis_kerusakan" name="jenis_kerusakan" rows="4"
                      placeholder="Contoh: Monitor tidak menyala meskipun kabel sudah terpasang"
                      aria-required="true" aria-describedby="err-jenis_kerusakan" required><?= e($old['jenis_kerusakan']) ?></textarea>
            <div class="invalid-feedback" id="err-jenis_kerusakan"></div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="foto_bukti">Foto Bukti <span class="opt">(opsional)</span></label>
            <input type="file" class="form-control" id="foto_bukti" name="foto_bukti"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                   aria-describedby="help-foto err-foto_bukti">
            <div class="invalid-feedback" id="err-foto_bukti"></div>
            <div class="form-text" id="help-foto">Boleh dikosongkan. Jika diunggah: JPG, PNG, atau WEBP, maksimal 2 MB.</div>
            <div id="previewWrap" class="foto-preview d-none">
              <img id="preview" alt="Pratinjau foto bukti">
              <div class="meta">
                <div class="nama" id="fotoNama"></div>
                <div class="ukuran" id="fotoUkuran"></div>
              </div>
              <button type="button" id="hapusFoto" class="btn btn-outline-danger btn-hapus-foto"
                      title="Hapus foto" aria-label="Hapus foto yang dipilih"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label" for="keterangan">Keterangan Tambahan <span class="opt">(opsional)</span></label>
            <textarea class="form-control kecil" id="keterangan" name="keterangan" rows="3"
                      placeholder="Contoh: Kerusakan terjadi sejak praktikum minggu lalu"><?= e($old['keterangan']) ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-submit w-100" id="btnKirim"><i class="bi bi-send me-1" aria-hidden="true"></i>Kirim Laporan</button>
        </form>
      </div>
    </div>
    <p class="text-center foot-note small mt-4 mb-0">&copy; <?= date('Y') ?> ITEBIS PGRI Dewantara &mdash; DELAPORT</p>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  var tampilModal = <?= $tampilModal ? 'true' : 'false' ?>;

  /* ---------- Pop up selamat datang (sekali per sesi browser) ---------- */
  var modalEl = document.getElementById('welcomeModal');
  var sudah = false;
  try { sudah = sessionStorage.getItem('delaport_welcome') === '1'; } catch (e) {}
  if (tampilModal && !sudah && window.bootstrap) {
    new bootstrap.Modal(modalEl).show();
    document.getElementById('btnLanjut').addEventListener('click', function () {
      try { sessionStorage.setItem('delaport_welcome', '1'); } catch (e) {}
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
      // Perangkat dengan mouse: langsung fokus ke kolom pertama. Perangkat sentuh: tidak,
      // agar keyboard virtual tidak muncul tiba-tiba.
      if (window.matchMedia('(pointer: fine)').matches) {
        document.getElementById('nama_pelapor').focus();
      }
    });
  }

  /* ---------- Pesan dari server: arahkan perhatian ke sana ---------- */
  var alertEl = document.querySelector('.js-alert');
  if (alertEl) {
    alertEl.scrollIntoView({ block: 'center' });
    alertEl.focus({ preventScroll: true });
  }

  /* ---------- Validasi sisi klien (server tetap memvalidasi ulang) ---------- */
  var form = document.getElementById('formLaporan');
  var btnKirim = document.getElementById('btnKirim');
  var labelAwal = btnKirim.innerHTML;
  var inputFoto = document.getElementById('foto_bukti');
  var wrap = document.getElementById('previewWrap');
  var img = document.getElementById('preview');
  var EXT = ['jpg', 'jpeg', 'png', 'webp'];
  var MAX = 2 * 1024 * 1024;
  var fields = [
    ['nama_pelapor', 'Nama pelapor', 100],
    ['nama_alat', 'Nama alat / fasilitas', 150],
    ['jenis_kerusakan', 'Jenis kerusakan', 0]
  ];

  function setError(el, msg) {
    var fb = document.getElementById('err-' + el.id);
    if (msg) {
      el.classList.add('is-invalid');
      el.setAttribute('aria-invalid', 'true');
    } else {
      el.classList.remove('is-invalid');
      el.removeAttribute('aria-invalid');
    }
    if (fb) fb.textContent = msg || '';
    return !msg;
  }
  function cekTeks(el, label, max) {
    var v = el.value.trim();
    if (v === '') return setError(el, label + ' wajib diisi.');
    if (max && v.length > max) return setError(el, label + ' maksimal ' + max + ' karakter.');
    return setError(el, '');
  }
  function cekFoto() {
    var f = inputFoto.files[0];
    if (!f) return setError(inputFoto, '');
    var ext = f.name.split('.').pop().toLowerCase();
    if (EXT.indexOf(ext) === -1) return setError(inputFoto, 'Format foto harus JPG, JPEG, PNG, atau WEBP.');
    if (f.size > MAX) return setError(inputFoto, 'Ukuran foto maksimal 2 MB.');
    return setError(inputFoto, '');
  }

  fields.forEach(function (f) {
    var el = document.getElementById(f[0]);
    el.addEventListener('input', function () {
      if (el.classList.contains('is-invalid')) cekTeks(el, f[1], f[2]);
    });
    el.addEventListener('blur', function () {
      if (el.value !== '' || el.classList.contains('is-invalid')) cekTeks(el, f[1], f[2]);
    });
  });

  form.addEventListener('submit', function (ev) {
    var pertama = null;
    fields.forEach(function (f) {
      var el = document.getElementById(f[0]);
      if (!cekTeks(el, f[1], f[2]) && !pertama) pertama = el;
    });
    if (!cekFoto() && !pertama) pertama = inputFoto;
    if (pertama) {
      ev.preventDefault();
      pertama.focus();
      return;
    }
    // Lolos validasi: tampilkan status mengirim & cegah klik ganda
    btnKirim.disabled = true;
    btnKirim.setAttribute('aria-busy', 'true');
    btnKirim.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Mengirim...';
  });

  // Tombol Back browser (bfcache) tidak boleh meninggalkan tombol dalam keadaan "Mengirim..."
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      btnKirim.disabled = false;
      btnKirim.removeAttribute('aria-busy');
      btnKirim.innerHTML = labelAwal;
    }
  });

  /* ---------- Pratinjau foto + tombol X ---------- */
  function formatUkuran(b) {
    return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1) + ' MB';
  }
  function sembunyikanPratinjau() {
    if (img.src && img.src.indexOf('blob:') === 0) URL.revokeObjectURL(img.src);
    img.removeAttribute('src');
    wrap.classList.add('d-none');
  }
  function resetFoto() {
    sembunyikanPratinjau();
    inputFoto.value = '';
    setError(inputFoto, '');
  }

  inputFoto.addEventListener('change', function () {
    var f = inputFoto.files[0];
    if (!f) { resetFoto(); return; }
    sembunyikanPratinjau();
    if (!cekFoto()) return;               // format/ukuran salah: tampilkan pesan, tanpa pratinjau
    img.src = URL.createObjectURL(f);
    document.getElementById('fotoNama').textContent = f.name;
    document.getElementById('fotoUkuran').textContent = formatUkuran(f.size);
    wrap.classList.remove('d-none');
  });

  document.getElementById('hapusFoto').addEventListener('click', function () {
    resetFoto();
    inputFoto.focus();
  });
})();
</script>
</body>
</html>
