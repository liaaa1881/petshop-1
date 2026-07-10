<?php
session_start();
require_once '../config/koneksi.php';

// Proteksi Login: Pastikan user sudah login
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Validasi Parameter ID dari URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h3>ID Transaksi Tidak Valid</h3><p>Kembali ke <a href='stok_masuk_read.php'>Halaman Riwayat</a></p></div>");
}

if (!$conn) {
    die("Koneksi database gagal terhubung.");
}

// 1. Ambil Data Header Stok_Masuk
$sql_header = "SELECT SM.*, S.Nama_Supplier, S.No_Telepon AS Telp_Supplier, S.Alamat AS Alamat_Supplier,
                      K.Nama_Karyawan AS Nama_Penerima
               FROM Stok_Masuk SM
               LEFT JOIN Supplier S ON SM.ID_Supplier = S.ID_Supplier
               LEFT JOIN Karyawan K ON SM.ID_Karyawan = K.ID_Karyawan
               WHERE SM.ID_Stok = ?";
$query_header = sqlsrv_query($conn, $sql_header, array($id));

if ($query_header === false || !sqlsrv_has_rows($query_header)) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h3>Data Faktur Tidak Ditemukan</h3><p>Kembali ke <a href='stok_masuk_read.php'>Halaman Riwayat</a></p></div>");
}

$header = sqlsrv_fetch_array($query_header, SQLSRV_FETCH_ASSOC);

// Mencegah cetak jika status masih Pending (Sesuai Aturan Validasi Keamanan)
if ($header['Status'] === 'Pending') {
    die("<script>
            alert('Peringatan: Dokumen pengadaan tidak diizinkan dicetak karena status faktur masih PENDING!');
            window.close();
         </script>");
}

// 2. Ambil Rincian Item Barang Pengadaan
$sql_items = "SELECT DSM.*, B.Nama_Barang 
              FROM Detail_Stok_Masuk DSM 
              JOIN Barang B ON DSM.ID_Barang = B.ID_Barang 
              WHERE DSM.ID_Stok = ?";
$query_items = sqlsrv_query($conn, $sql_items, array($id));
$items = [];

if ($query_items !== false) {
    while ($row = sqlsrv_fetch_array($query_items, SQLSRV_FETCH_ASSOC)) {
        $items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktur Pengadaan_<?= htmlspecialchars($header['No_Faktur']) ?> | Petshop Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        body {
            background-color: #fff;
            color: #1e293b;
            margin: 0;
            padding: 0;
            font-size: 11pt;
            line-height: 1.4;
        }
        .print-container {
            width: 210mm; /* Lebar standar A4 */
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm 15mm;
            background: #ffffff;
            position: relative;
        }

        /* HEADER DOKUMEN */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .company-info h1 {
            margin: 0;
            font-size: 22pt;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: -0.5px;
        }
        .company-info p {
            margin: 4px 0 0 0;
            font-size: 9.5pt;
            color: #475569;
        }
        .doc-title {
            text-align: right;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 18pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }
        .doc-title span {
            font-size: 11pt;
            font-weight: 600;
            color: #2563eb;
            display: block;
            margin-top: 5px;
        }

        /* METADATA INFORMASI */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-card {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #e2e8f0;
        }
        .info-card h4 {
            margin: 0 0 10px 0;
            font-size: 10.5pt;
            color: #2563eb;
            font-weight: 700;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 0;
            font-size: 9.5pt;
            vertical-align: top;
        }
        .info-table td.label {
            color: #64748b;
            width: 35%;
        }
        .info-table td.value {
            font-weight: 600;
            color: #1e293b;
        }

        /* TABEL DAFTAR BARANG */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table-items th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            font-size: 9pt;
            text-transform: uppercase;
            padding: 10px 12px;
            text-align: center;
            border: 1px solid #0f172a;
        }
        .table-items th.align-left { text-align: left; }
        .table-items th.align-right { text-align: right; }
        .table-items td {
            padding: 10px 12px;
            font-size: 9.5pt;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }
        .table-items tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        /* RINGKASAN FINANSIAL */
        .summary-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }
        .summary-box {
            width: 50%;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 12px;
            font-size: 10pt;
        }
        .summary-table td.label {
            text-align: right;
            color: #64748b;
            font-weight: 500;
        }
        .summary-table td.value {
            text-align: right;
            font-weight: 700;
            color: #1e293b;
            width: 45%;
        }
        .summary-table tr.grand-total td {
            border-top: 2px solid #cbd5e1;
            padding-top: 10px;
        }
        .summary-table tr.grand-total td.value {
            font-size: 13pt;
            color: #2563eb;
        }

        /* AREA TANDA TANGAN */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 40%;
            text-align: center;
        }
        .signature-box p {
            margin: 0 0 60px 0;
            font-size: 9.5pt;
            color: #64748b;
            font-weight: 600;
        }
        .signature-box .signature-line {
            border-top: 1.5px solid #0f172a;
            width: 80%;
            margin: 0 auto;
            font-weight: 700;
            color: #1e293b;
            font-size: 10pt;
            padding-top: 5px;
        }

        /* LAYOUT SPESIFIK UNTUK PRINTER */
        @media print {
            body {
                background: none;
                color: #000;
            }
            .print-container {
                width: 100%;
                margin: 0;
                padding: 0;
                min-height: auto;
            }
            .info-card {
                background-color: #fff !important;
                border: 1px solid #cbd5e1;
            }
            .table-items th {
                background-color: #f1f5f9 !important;
                color: #000 !important;
                border: 1px solid #94a3b8 !important;
            }
            .table-items td {
                border: 1px solid #cbd5e1 !important;
            }
            .table-items tr:nth-child(even) {
                background-color: transparent !important;
            }
            .summary-table tr.grand-total td.value {
                color: #000 !important;
            }
            @page {
                size: A4;
                margin: 15mm;
            }
        }
    </style>
</head>
<body>

    <div class="print-container">
        
        <!-- HEADER SURAT JALAN / FAKTUR -->
        <div class="doc-header">
            <div class="company-info">
                <h1>PETSHOP PRO</h1>
                <p>Komp. Pergudangan Logistik Blok B No. 42</p>
                <p>Telp: (021) 8847-1102 | Email: logistics@petshoppro.com</p>
            </div>
            <div class="doc-title">
                <h2>Bukti Terima Barang</h2>
                <span>No. Faktur: <?= htmlspecialchars($header['No_Faktur']) ?></span>
            </div>
        </div>

        <!-- DETAIL INFORMASI LOGISTIK -->
        <div class="info-grid">
            <div class="info-card">
                <h4>Informasi Pengiriman & Penerima</h4>
                <table class="info-table">
                    <tr>
                        <td class="label">Mitra Supplier</td>
                        <td class="value">: <?= htmlspecialchars($header['Nama_Supplier']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Kontak Supplier</td>
                        <td class="value">: <?= htmlspecialchars($header['Telp_Supplier'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Alamat Kirim</td>
                        <td class="value">: <?= htmlspecialchars($header['Alamat_Supplier'] ?: 'Alamat Tidak Dicantumkan') ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="info-card">
                <h4>Informasi Registrasi Logistik</h4>
                <table class="info-table">
                    <tr>
                        <td class="label">Tanggal Registrasi</td>
                        <td class="value">: <?= $header['Tanggal_Masuk'] ? $header['Tanggal_Masuk']->format('d M Y, H:i') : '-' ?> WIB</td>
                    </tr>
                    <tr>
                        <td class="label">Tanggal Diterima</td>
                        <td class="value">: <?= $header['Tanggal_Diterima'] ? $header['Tanggal_Diterima']->format('d M Y, H:i') : '-' ?> WIB</td>
                    </tr>
                    <tr>
                        <td class="label">Penerima Gudang</td>
                        <td class="value">: <?= htmlspecialchars($header['Nama_Penerima'] ?: '-') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TABEL RINCIAN ITEM LOGISTIK MASUK -->
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th class="align-left" style="width: 35%;">Nama Produk / Barang Belanja</th>
                    <th style="width: 10%;">Jumlah</th>
                    <th class="align-right" style="width: 15%;">Harga Beli</th>
                    <th style="width: 15%;">No. Batch</th>
                    <th style="width: 15%;">Kadaluarsa</th>
                    <th class="align-right" style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach($items as $item): 
                    $tgl_exp = ($item['Tanggal_Kadaluarsa'] instanceof DateTime) ? $item['Tanggal_Kadaluarsa']->format('d M Y') : '-';
                ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><strong style="color:#0f172a;"><?= htmlspecialchars($item['Nama_Barang']) ?></strong></td>
                    <td style="text-align: center; font-weight:700;"><?= number_format($item['Jumlah_Masuk'], 0, ',', '.') ?></td>
                    <td style="text-align: right;">Rp <?= number_format($item['Harga_Beli'], 0, ',', '.') ?></td>
                    <td style="text-align: center; font-family: monospace; font-size: 8.5pt; color: #475569;"><?= htmlspecialchars($item['No_Batch'] ?: '-') ?></td>
                    <td style="text-align: center; color: #475569;"><?= $tgl_exp ?></td>
                    <td style="text-align: right; font-weight: 600;">Rp <?= number_format($item['Subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- RINGKASAN BIAYA LOGISTIK -->
        <div class="summary-container">
            <div class="summary-box">
                <table class="summary-table">
                    <tr>
                        <td class="label">Subtotal Pengadaan</td>
                        <td class="value">Rp <?= number_format($header['Subtotal_Stok'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="label">PPN Masukan (Nominal)</td>
                        <td class="value">Rp <?= number_format($header['Pajak_Stok'], 0, ',', '.') ?></td>
                    </tr>
                    <tr class="grand-total">
                        <td class="label" style="font-weight: 700; color: #0f172a;">Total Tagihan Supplier</td>
                        <td class="value">Rp <?= number_format($header['Total_Harga'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- VALIDASI FISIK / CATATAN -->
        <div style="background-color: #f8fafc; border-radius:12px; padding: 12px 15px; border:1px solid #e2e8f0; margin-bottom: 40px; font-size: 9pt; page-break-inside: avoid;">
            <strong style="color: #0f172a; display: block; margin-bottom: 4px;"><i class="fas fa-comment-alt" style="margin-right: 5px; color:#2563eb;"></i>Catatan Kondisi Penerimaan Logistik:</strong>
            <span style="color: #475569; font-style: italic;"><?= htmlspecialchars($header['Catatan_Masuk'] ?: 'Seluruh barang diterima dalam keadaan fisik tersegel dan lolos verifikasi QC gudang.') ?></span>
        </div>

        <!-- KOTAK TANDA TANGAN -->
        <div class="signature-section">
            <div class="signature-box">
                <p>Diserahkan Oleh,<br>Mitra Supplier</p>
                <div class="signature-line">( <?= htmlspecialchars($header['Nama_Supplier']) ?> )</div>
            </div>
            <div class="signature-box">
                <p>Diterima Oleh,<br>Petugas Penerima Gudang</p>
                <div class="signature-line">( <?= htmlspecialchars($header['Nama_Penerima'] ?: '-') ?> )</div>
            </div>
        </div>

    </div>

    <!-- OTOMATIS BUKA PRINT PREVIEW JIKA DI-LOAD -->
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>