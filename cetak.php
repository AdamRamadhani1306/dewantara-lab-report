<?php
/**
 * cetak.php - Laporan kerusakan yang siap dicetak / diunduh sebagai PDF.
 * Hanya untuk admin. Menerima filter yang sama dengan dashboard:
 *   ?tahun=2026&bulan=10&q=...&status=...&tanggal=YYYY-MM-DD
 *   &aksi=cetak  -> langsung membuka dialog cetak
 *   &aksi=pdf    -> langsung mengunduh PDF
 */
require __DIR__ . '/koneksi.php';
require __DIR__ . '/helper_laporan.php';

if (!is_admin()) {
    header('Location: admin.php');
    exit;
}

$f        = filter_laporan($_GET);
$rows     = query_laporan($koneksi, $f);
$count    = hitung_status($rows);
$total    = count($rows);
$aksi     = in_array($_GET['aksi'] ?? '', ['cetak', 'pdf'], true) ? $_GET['aksi'] : '';
$periode  = periode_teks($f);
$tglCetak = tanggal_id(time()) . ', ' . date('H:i') . ' WIB';
$namaFile = nama_file_laporan($f);
$qs       = qs_filter($f);

// Kelompokkan per tahun-bulan (data sudah terurut terbaru -> terlama)
$grup = [];
$no = 0;
foreach ($rows as $r) {
    $ts  = strtotime($r['tanggal_laporan']);
    $key = date('Y', $ts) . '-' . (int)date('n', $ts);
    if (!isset($grup[$key])) {
        $grup[$key] = ['label' => NAMA_BULAN[(int)date('n', $ts)] . ' ' . date('Y', $ts), 'items' => []];
    }
    $no++;
    $grup[$key]['items'][] = [
        'no'        => $no,
        'tanggal'   => tanggal_id($ts, true) . ' ' . date('H:i', $ts),
        'alat'      => $r['nama_alat'],
        'kerusakan' => $r['jenis_kerusakan'],
        'pelapor'   => $r['nama_pelapor'],
        'status'    => $r['status'],
        'ket'       => (string)$r['keterangan'],
    ];
}
$grup = array_values($grup);

$dataJs = [
    'periode'  => $periode,
    'tglCetak' => $tglCetak,
    'namaFile' => $namaFile,
    'total'    => $total,
    'count'    => $count,
    'grup'     => $grup,
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Laporan Kerusakan Lab - <?= e($periode) ?></title>
  <link rel="icon" href="assets/favicon.ico?v=1" sizes="any">
  <style>
    @page { size: A4 landscape; margin: 12mm 12mm 14mm;
            @bottom-left { content: "DELAPORT - ITEBIS PGRI Dewantara"; font: 9px Arial, sans-serif; color: #666; }
            @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); font: 9px Arial, sans-serif; color: #666; } }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9e6e3; color: #252525; font: 12px/1.45 Arial, Helvetica, sans-serif; }
    .toolbar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;
               padding: .6rem 1rem; background: #5B0710; color: #fff; }
    .btn { display: inline-flex; align-items: center; min-height: 40px; padding: 0 1rem; border: 1px solid rgba(255,255,255,.7);
           border-radius: .4rem; background: transparent; color: #fff; font: 600 14px Arial, sans-serif; text-decoration: none; cursor: pointer; }
    .btn.utama { background: #fff; color: #5B0710; border-color: #fff; }
    .btn:disabled { opacity: .6; cursor: wait; }
    #info { margin-left: auto; font-size: 13px; }
    .kertas { max-width: 1120px; margin: 1rem auto; padding: 18px 22px 22px; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
    .kop { display: flex; align-items: center; gap: 16px; padding-bottom: 10px; border-bottom: 3px solid #80000B; }
    .kop img { height: 54px; width: auto; }
    .kop h1 { margin: 0; font-size: 18px; letter-spacing: .02em; color: #5B0710; }
    .kop p { margin: 2px 0 0; color: #5C5F63; }
    .meta { display: flex; flex-wrap: wrap; gap: 4px 28px; margin: 10px 0 12px; }
    .meta b { color: #5B0710; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    th { background: #5B0710; color: #fff; text-align: left; padding: 6px 8px; font-size: 11.5px; border: 1px solid #5B0710; }
    td { padding: 5px 8px; border: 1px solid #cfcac5; vertical-align: top; }
    tr { break-inside: avoid; page-break-inside: avoid; }
    td.no { text-align: center; width: 32px; }
    td.tgl { white-space: nowrap; width: 100px; }
    td.st { width: 74px; font-weight: 700; }
    tr.g td { background: #F1E4E5; color: #5B0710; font-weight: 700; }
    .st-Menunggu { color: #8a5a00; } .st-Diproses { color: #0a58a0; } .st-Selesai { color: #157347; }
    .kosong { text-align: center; color: #5C5F63; padding: 24px; }
    @media print {
      body { background: #fff; }
      .no-print { display: none !important; }
      .kertas { max-width: none; margin: 0; padding: 0; box-shadow: none; }
      th, tr.g td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @media (max-width: 700px) { .kertas { margin: 0; padding: 12px; overflow-x: auto; } .kop img { height: 40px; } .kop h1 { font-size: 15px; } }
  </style>
</head>
<body>

<div class="toolbar no-print">
  <a class="btn" href="admin.php<?= $qs !== '' ? '?' . e($qs) : '' ?>">&larr; Kembali</a>
  <button type="button" class="btn utama" id="btnCetak">Cetak</button>
  <button type="button" class="btn" id="btnPdf">Unduh PDF</button>
  <span id="info" role="status" aria-live="polite"></span>
</div>

<div class="kertas">
  <header class="kop">
    <img src="assets/logo-fti.png" alt="Logo ITEBIS dan FTI" onerror="this.style.display='none'">
    <div>
      <h1>LAPORAN KERUSAKAN FASILITAS LABORATORIUM</h1>
      <p>ITEBIS PGRI Dewantara &mdash; DELAPORT (Dewantara Lab Report)</p>
    </div>
  </header>

  <div class="meta">
    <div>Periode: <b><?= e($periode) ?></b></div>
    <div>Dicetak: <b><?= e($tglCetak) ?></b></div>
    <div>Total: <b><?= $total ?></b> laporan &nbsp;|&nbsp; Menunggu: <b><?= $count['Menunggu'] ?></b> &nbsp;|&nbsp; Diproses: <b><?= $count['Diproses'] ?></b> &nbsp;|&nbsp; Selesai: <b><?= $count['Selesai'] ?></b></div>
  </div>

  <table>
    <thead>
      <tr><th>No</th><th>Tanggal</th><th>Nama Alat</th><th>Jenis Kerusakan</th><th>Pelapor</th><th>Status</th><th>Keterangan</th></tr>
    </thead>
    <tbody>
    <?php if (!$grup): ?>
      <tr><td colspan="7" class="kosong">Tidak ada laporan pada periode ini.</td></tr>
    <?php endif; ?>
    <?php foreach ($grup as $g): ?>
      <tr class="g"><td colspan="7"><?= e($g['label']) ?> &middot; <?= count($g['items']) ?> laporan</td></tr>
      <?php foreach ($g['items'] as $it): ?>
      <tr>
        <td class="no"><?= (int)$it['no'] ?></td>
        <td class="tgl"><?= e($it['tanggal']) ?></td>
        <td><?= e($it['alat']) ?></td>
        <td><?= nl2br(e($it['kerusakan'])) ?></td>
        <td><?= e($it['pelapor']) ?></td>
        <td class="st st-<?= e($it['status']) ?>"><?= e($it['status']) ?></td>
        <td><?= nl2br(e($it['ket'])) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/jspdf@4.2.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@5.0.8/dist/jspdf.plugin.autotable.min.js"></script>
<script>
(function () {
  'use strict';
  var D = <?= json_encode($dataJs, $jsonFlags) ?>;
  var AKSI = <?= json_encode($aksi) ?>;
  var info = document.getElementById('info');
  var btnPdf = document.getElementById('btnPdf');

  // Font bawaan PDF hanya mendukung karakter Latin; karakter lain (mis. emoji) dibuang.
  function bersih(s) {
    return String(s == null ? '' : s)
      .replace(/[\u2013\u2014]/g, '-').replace(/[\u2018\u2019]/g, "'").replace(/[\u201C\u201D]/g, '"')
      .replace(/\u2026/g, '...').replace(/\u00A0/g, ' ')
      .replace(/[^\x09\x0A\x0D\x20-\x7E\xA1-\xFF]/g, '');
  }

  function muatLogo() {
    return new Promise(function (resolve, reject) {
      var im = new Image();
      im.onload = function () {
        try {
          var c = document.createElement('canvas');
          c.width = im.naturalWidth; c.height = im.naturalHeight;
          c.getContext('2d').drawImage(im, 0, 0);
          resolve({ data: c.toDataURL('image/png'), w: c.width, h: c.height });
        } catch (e) { reject(e); }
      };
      im.onerror = reject;
      im.src = 'assets/logo-fti.png';
    });
  }

  function tabel(doc, opts) {
    if (typeof doc.autoTable === 'function') return doc.autoTable(opts);
    if (typeof window.autoTable === 'function') return window.autoTable(doc, opts);
    throw new Error('autoTable tidak tersedia');
  }

  var WARNA_STATUS = { Menunggu: [138, 90, 0], Diproses: [10, 88, 160], Selesai: [21, 115, 71] };

  async function unduhPdf() {
    if (!window.jspdf || !window.jspdf.jsPDF) {
      info.textContent = 'Pustaka PDF gagal dimuat (periksa internet). Gunakan tombol Cetak lalu pilih "Simpan sebagai PDF".';
      return;
    }
    btnPdf.disabled = true;
    info.textContent = 'Menyiapkan PDF...';
    try {
      var doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
      var MX = 12, lebarHal = doc.internal.pageSize.getWidth(), tinggiHal = doc.internal.pageSize.getHeight();
      var teksX = MX;

      try {
        var logo = await muatLogo();
        var lh = 15, lw = lh * logo.w / logo.h;
        doc.addImage(logo.data, 'PNG', MX, 9, lw, lh);
        teksX = MX + lw + 6;
      } catch (e) { /* tanpa logo */ }

      doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.setTextColor(91, 7, 16);
      doc.text('LAPORAN KERUSAKAN FASILITAS LABORATORIUM', teksX, 15);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(9.5); doc.setTextColor(60, 60, 60);
      doc.text('ITEBIS PGRI Dewantara - DELAPORT (Dewantara Lab Report)', teksX, 20.5);
      doc.setDrawColor(128, 0, 11); doc.setLineWidth(0.7); doc.line(MX, 26, lebarHal - MX, 26);

      doc.setFontSize(9.5); doc.setTextColor(37, 37, 37);
      doc.text('Periode: ' + bersih(D.periode) + '    Dicetak: ' + bersih(D.tglCetak), MX, 32);
      doc.text('Total: ' + D.total + ' laporan  |  Menunggu: ' + D.count.Menunggu + '  |  Diproses: ' + D.count.Diproses + '  |  Selesai: ' + D.count.Selesai, MX, 37);

      var body = [];
      if (!D.grup.length) {
        body.push([{ content: 'Tidak ada laporan pada periode ini.', colSpan: 7, styles: { halign: 'center', textColor: [92, 95, 99] } }]);
      }
      D.grup.forEach(function (g) {
        body.push([{ content: bersih(g.label) + '  -  ' + g.items.length + ' laporan', colSpan: 7,
                     styles: { fillColor: [241, 228, 229], textColor: [91, 7, 16], fontStyle: 'bold' } }]);
        g.items.forEach(function (it) {
          body.push([String(it.no), bersih(it.tanggal), bersih(it.alat), bersih(it.kerusakan), bersih(it.pelapor), it.status, bersih(it.ket)]);
        });
      });

      tabel(doc, {
        startY: 41,
        head: [['No', 'Tanggal', 'Nama Alat', 'Jenis Kerusakan', 'Pelapor', 'Status', 'Keterangan']],
        body: body,
        theme: 'grid',
        margin: { left: MX, right: MX, bottom: 14 },
        styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 2, overflow: 'linebreak', lineColor: [207, 202, 197], lineWidth: 0.2, textColor: [37, 37, 37] },
        headStyles: { fillColor: [91, 7, 16], textColor: 255, fontStyle: 'bold' },
        columnStyles: { 0: { cellWidth: 10, halign: 'center' }, 1: { cellWidth: 32 }, 2: { cellWidth: 44 }, 4: { cellWidth: 34 }, 5: { cellWidth: 22, fontStyle: 'bold' }, 6: { cellWidth: 48 } },
        didParseCell: function (data) {
          if (data.section === 'body' && data.column.index === 5 && WARNA_STATUS[data.cell.raw]) {
            data.cell.styles.textColor = WARNA_STATUS[data.cell.raw];
          }
        }
      });

      var n = doc.getNumberOfPages();
      for (var i = 1; i <= n; i++) {
        doc.setPage(i); doc.setFontSize(8); doc.setFont('helvetica', 'normal'); doc.setTextColor(110, 110, 110);
        doc.text('DELAPORT - ITEBIS PGRI Dewantara', MX, tinggiHal - 6);
        doc.text('Halaman ' + i + ' dari ' + n, lebarHal - MX, tinggiHal - 6, { align: 'right' });
      }
      doc.save(D.namaFile + '.pdf');
      info.textContent = 'PDF diunduh: ' + D.namaFile + '.pdf';
    } catch (e) {
      info.textContent = 'Gagal membuat PDF. Gunakan tombol Cetak lalu pilih "Simpan sebagai PDF".';
      if (window.console) console.error(e);
    } finally {
      btnPdf.disabled = false;
    }
  }

  document.getElementById('btnCetak').addEventListener('click', function () { window.print(); });
  btnPdf.addEventListener('click', unduhPdf);

  window.addEventListener('load', function () {
    if (AKSI === 'cetak') { setTimeout(function () { window.print(); }, 300); }
    else if (AKSI === 'pdf') { unduhPdf(); }
  });
})();
</script>
</body>
</html>
