<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/koneksi.php';

// Proteksi Akses
if (!isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$conn) {
    die("Koneksi database gagal terhubung.");
}

// Query mengambil data reservasi booking secara spesifik
$sql = "SELECT B.*, PL.Nama_Pelanggan, PL.No_Telepon, L.Nama_Layanan, K.Nama_Karyawan 
        FROM Booking B
        LEFT JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan
        LEFT JOIN Layanan L ON B.ID_Layanan = L.ID_Layanan
        LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
        WHERE B.ID_Booking = ?";

$query = sqlsrv_query($conn, $sql, array($id));

if ($query === false || !sqlsrv_has_rows($query)) {
    die("Data transaksi reservasi tidak ditemukan.");
}

$d = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

// Format Tanggal & Waktu SQL Server (DateTime Object)
$tanggal_booking = ($d['Tanggal_Booking'] instanceof DateTime) ? $d['Tanggal_Booking']->format('d M Y, H:i') : '-';
$jadwal_booking  = ($d['Jadwal_Booking'] instanceof DateTime) ? $d['Jadwal_Booking']->format('d M Y, H:i') : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk_Booking_<?= htmlspecialchars($d['Kode_Booking']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .header h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            text-transform: uppercase;
        }
        .header p { margin: 2px 0; font-size: 11px; }
        .meta-info, .item-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .meta-info td { padding: 2px 0; vertical-align: top; }
        .item-table th, .item-table td { padding: 4px 0; }
        .total-section {
            width: 100%;
            margin-top: 5px;
            font-size: 11px;
        }
        .total-section td { padding: 2px 0; }
        .footer {
            margin-top: 20px;
            font-size: 10px;
        }
        @media print {
            body { width: 100%; margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">

    <div class="header text-center">
        <h3>PETSHOP PRO</h3>
        <p>Jl. Raya Peliharaan Cantik No. 80</p>
        <p>Telp: 0812-3456-7890</p>
    </div>

    <div class="divider"></div>

    <table class="meta-info">
        <tr>
            <td style="width: 40%;">No. Booking</td>
            <td>: <?= htmlspecialchars($d['Kode_Booking']) ?></td>
        </tr>
        <tr>
            <td>Tgl Pesan</td>
            <td>: <?= $tanggal_booking ?></td>
        </tr>
        <tr>
            <td>Pelanggan</td>
            <td>: <?= htmlspecialchars($d['Nama_Pelanggan']) ?></td>
        </tr>
        <tr>
            <td>No. HP</td>
            <td>: <?= htmlspecialchars($d['No_Telepon'] ?: '-') ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-info">
        <tr>
            <td style="width: 40%;">Jadwal Grooming</td>
            <td>: <?= $jadwal_booking ?></td>
        </tr>
        <tr>
            <td>Petugas/Terapis</td>
            <td>: <?= htmlspecialchars($d['Nama_Karyawan'] ?: '-') ?></td>
        </tr>
        <tr>
            <td>Status Antrean</td>
            <td>: <strong><?= htmlspecialchars($d['Status_Booking']) ?></strong></td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="item-table">
        <thead>
            <tr>
                <th style="text-align: left; width: 60%;">Nama Layanan Jasa</th>
                <th style="text-align: right; width: 40%;">Harga</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($d['Nama_Layanan']) ?></td>
                <td class="text-right">Rp <?= number_format($d['Harga_Layanan'], 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="total-section">
        <tr>
            <td style="width: 60%;" class="text-right">Subtotal:</td>
            <td class="text-right">Rp <?= number_format($d['Harga_Layanan'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td class="text-right">Diskon Booking:</td>
            <td class="text-right" style="color: red;">-Rp <?= number_format($d['Diskon_Booking'], 0, ',', '.') ?></td>
        </tr>
        <tr style="font-weight: bold;">
            <td class="text-right">Total Tarif:</td>
            <td class="text-right">Rp <?= number_format($d['Total_Tarif'], 0, ',', '.') ?></td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="footer text-center">
        <p>Terima kasih atas kepercayaan Anda!</p>
        <p>Layanan Grooming Profesional Petshop Pro</p>
    </div>

</body>
</html>