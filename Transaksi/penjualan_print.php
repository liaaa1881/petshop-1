<?php
session_start();
include '../config/koneksi.php';

// Proteksi Akses
if (!isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("<h4 style='text-align:center; margin-top:50px; font-family:sans-serif;'>ID Nota Transaksi Tidak Valid atau Tidak Ditemukan.</h4>");
}

$id_nota = intval($_GET['id']);

if (!$conn) {
    die("Koneksi database gagal terhubung.");
}

// 1. Ambil Data Induk Transaksi Penjualan
$sql = "SELECT P.*, PL.Nama_Pelanggan, PL.No_Telepon AS Telp_Pelanggan, K.Nama_Karyawan AS Nama_Kasir, B.Kode_Booking
        FROM Penjualan P
        LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
        LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
        LEFT JOIN Booking B ON P.ID_Booking = B.ID_Booking
        WHERE P.ID_Nota = ?";
$query = sqlsrv_query($conn, $sql, array($id_nota));

if ($query === false) {
    die("Error pengambilan data penjualan: " . print_r(sqlsrv_errors(), true));
}

$data = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if (!$data) {
    die("<h4 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Data transaksi penjualan tidak ditemukan di server.</h4>");
}

// ==========================================
// VALIDASI: Cegah cetak jika status pembayaran "Belum Lunas"
// ==========================================
if (strtolower(trim($data['Status_Pembayaran'])) !== 'lunas') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cetak Dibatalkan - Belum Lunas</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background-color: #f4f6fa; font-family: sans-serif; }
            .card-warning {
                max-width: 500px;
                margin: 100px auto;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                border: none;
            }
        </style>
    </head>
    <body>
        <div class="card card-warning p-5 text-center bg-white">
            <div class="mb-4 text-danger">
                <i class="fas fa-exclamation-triangle fa-4x"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Transaksi Belum Lunas ⚠️</h4>
            <p class="text-muted small mb-4" style="line-height: 1.5;">
                Nota transaksi <strong class="text-dark"><?= htmlspecialchars($data['No_Nota']) ?></strong> belum diselesaikan.<br>
                Struk pembayaran fisik hanya dapat dicetak apabila status pembayaran telah diubah menjadi <strong class="text-success">Lunas</strong> oleh kasir pada sistem.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="penjualan_read.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" style="background-color: #3b82f6; border: none;">
                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Kasir
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Format Tanggal untuk PHP & SQL Server (DateTime object)
$tanggal_cetak = "-";
if ($data['Tanggal_Penjualan'] instanceof DateTime) {
    $tanggal_cetak = $data['Tanggal_Penjualan']->format('d/m/Y H:i');
}

// 2. Ambil Rincian Barang Belanjaan
$sql_items = "SELECT DP.*, B.Nama_Barang, B.Satuan 
              FROM Detail_Penjualan DP 
              JOIN Barang B ON DP.ID_Barang = B.ID_Barang 
              WHERE DP.ID_Nota = ?";
$query_items = sqlsrv_query($conn, $sql_items, array($id_nota));

if ($query_items === false) {
    die("Error pengambilan rincian barang: " . print_r(sqlsrv_errors(), true));
}

$items = [];
while ($row = sqlsrv_fetch_array($query_items, SQLSRV_FETCH_ASSOC)) {
    $items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk_<?= htmlspecialchars($data['No_Nota']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }

        .receipt-container {
            width: 290px; /* Standar Lebar Struk Kertas Thermal 80mm */
            margin: 15px auto;
            padding: 5px;
            box-sizing: border-box;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }

        .header {
            margin-bottom: 12px;
        }

        .header .store-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }

        .header .store-info {
            font-size: 11px;
            margin: 0 0 2px 0;
            line-height: 1.3;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }

        .metadata-table {
            width: 100%;
            font-size: 11px;
            margin-bottom: 5px;
        }

        .metadata-table td {
            padding: 1px 0;
            vertical-align: top;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .items-table th {
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            text-align: left;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .item-row-name {
            display: block;
            font-weight: bold;
            word-wrap: break-word;
            max-width: 280px;
        }

        .item-row-detail {
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            padding-left: 5px;
        }

        .totals-table {
            width: 100%;
            font-size: 11px;
            margin-top: 5px;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .totals-table tr.grand-total td {
            font-size: 13px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 5px 0;
        }

        .footer {
            margin-top: 20px;
            font-size: 11px;
            line-height: 1.4;
        }

        /* Aturan Khusus Saat Halaman Dicetak */
        @media print {
            body {
                background: #fff;
                margin: 0;
            }
            .receipt-container {
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }

        /* Navigasi Struk */
        .no-print-bar {
            background-color: #f1f5f9;
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #cbd5e1;
            font-family: sans-serif;
        }
        .btn-kembali {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-kembali:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>

    <!-- Bar Navigasi Tombol yang Tersembunyi saat proses cetak struk -->
    <div class="no-print-bar no-print">
        <a href="penjualan_read.php" class="btn-kembali"><i class="fas fa-arrow-left"></i> Kembali ke Kasir</a>
        <button onclick="window.print()" class="btn-kembali" style="background-color: #10b981; margin-left:10px;">Cetak Ulang</button>
    </div>

    <div class="receipt-container">
        
        <!-- Header Toko Petshop -->
        <div class="header text-center">
            <h1 class="store-name">PETSHOP PRO</h1>
            <p class="store-info">Ruko Green Garden Blok A-2, Jakarta</p>
            <p class="store-info">Telp: 021-88992211 | HP: 0812-3456-7890</p>
        </div>

        <div class="divider"></div>

        <!-- Meta Data Transaksi -->
        <table class="metadata-table">
            <tr>
                <td style="width: 40%;">No. Nota</td>
                <td>: <span class="bold"><?= htmlspecialchars($data['No_Nota']) ?></span></td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>: <?= htmlspecialchars($tanggal_cetak) ?></td>
            </tr>
            <tr>
                <td>Kasir</td>
                <td>: <?= htmlspecialchars($data['Nama_Kasir'] ?? 'Kasir') ?></td>
            </tr>
            <tr>
                <td>Pelanggan</td>
                <td>: <?= htmlspecialchars($data['Nama_Pelanggan'] ?? 'Pelanggan Umum (Non-Member)') ?></td>
            </tr>
            <?php if (!empty($data['Kode_Booking'])): ?>
            <tr>
                <td>Ref Booking</td>
                <td>: <span class="bold">#<?= htmlspecialchars($data['Kode_Booking']) ?></span></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="divider"></div>

        <!-- Daftar Belanjaan -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Nama Produk</th>
                    <th class="text-right" style="width: 40%;">Total (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="2" class="text-center" style="padding: 10px 0; font-style: italic;">
                            Pelunasan Booking Jasa Grooming
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td colspan="2">
                                <span class="item-row-name"><?= htmlspecialchars($item['Nama_Barang']) ?></span>
                                <div class="item-row-detail">
                                    <span>
                                        <?= $item['Jumlah'] ?>x @Rp<?= number_format($item['Harga_Satuan'], 0, ',', '.') ?>
                                        <?php if ($item['Diskon_Item'] > 0): ?>
                                            (Disc: -Rp<?= number_format($item['Diskon_Item'], 0, ',', '.') ?>)
                                        <?php endif; ?>
                                    </span>
                                    <span class="bold">
                                        Rp<?= number_format($item['Subtotal'], 0, ',', '.') ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Rincian Pembayaran (Kalkulasi) -->
        <table class="totals-table">
            <tr>
                <td style="width: 60%;" class="text-right">Subtotal Belanja:</td>
                <td style="width: 40%;" class="text-right">Rp<?= number_format($data['Subtotal_Penjualan'], 0, ',', '.') ?></td>
            </tr>
            <?php if ($data['Total_Diskon'] > 0): ?>
            <tr>
                <td class="text-right text-danger">Potongan Diskon:</td>
                <td class="text-right text-danger">-Rp<?= number_format($data['Total_Diskon'], 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($data['Pajak_PPN'] > 0): ?>
            <tr>
                <td class="text-right">Pajak PPN:</td>
                <td class="text-right">Rp<?= number_format($data['Pajak_PPN'], 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            
            <tr class="grand-total">
                <td class="text-right bold">GRAND TOTAL:</td>
                <td class="text-right bold">Rp<?= number_format($data['Grand_Total'], 0, ',', '.') ?></td>
            </tr>

            <tr>
                <td class="text-right" style="padding-top: 6px;">Metode Pembayaran:</td>
                <td class="text-right bold" style="padding-top: 6px; text-transform: uppercase;"><?= htmlspecialchars($data['Metode_Pembayaran'] ?? 'Cash') ?></td>
            </tr>
            <tr>
                <td class="text-right">Jumlah Bayar:</td>
                <td class="text-right">Rp<?= number_format($data['Jumlah_Bayar'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="text-right">Kembalian:</td>
                <td class="text-right">Rp<?= number_format($data['Kembalian'], 0, ',', '.') ?></td>
            </tr>
        </table>

        <?php if (!empty($data['Catatan_Penjualan'])): ?>
            <div class="divider"></div>
            <div class="text-left" style="font-size: 10.5px; word-wrap: break-word; line-height: 1.3;">
                <span class="bold">Catatan Kasir:</span><br>
                <?= nl2br(htmlspecialchars($data['Catatan_Penjualan'])) ?>
            </div>
        <?php endif; ?>

        <div class="divider"></div>

        <!-- Footer Struk -->
        <div class="footer text-center">
            <p class="bold" style="margin: 0 0 5px 0;">Terima Kasih Atas Kunjungan Anda</p>
            <p style="margin: 0 0 5px 0;">Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan.</p>
            <p class="bold" style="margin: 10px 0 0 0; font-size: 9px; letter-spacing: 0.5px;">POWERED BY PETSHOP PRO v1.0</p>
        </div>

    </div>

    <!-- Pemicu Cetak Otomatis via Javascript -->
    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            // Jeda 300ms agar rendering font selesai sempurna sebelum kotak cetak muncul
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>