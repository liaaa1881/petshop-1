<?php
session_start();
// Menggunakan huruf kecil (lowercase) untuk folder config
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman: Pastikan customer benar-benar sudah login
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php"); 
    exit();
}

$id_pelanggan = isset($_SESSION['id_pelanggan']) ? intval($_SESSION['id_pelanggan']) : 0;
$nama_customer = $_SESSION['nama'] ?? 'Pelanggan';

if ($id_pelanggan <= 0) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h3>Identitas Pelanggan Tidak Valid</h3><p>Silakan login kembali.</p></div>");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Belanja - Petshop Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Plus Jakarta Sans', sans-serif; }
        .navbar-custom { background-color: #1e272e !important; }
        .card-custom { border-radius: 12px; border: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="../dashboard/index.php">
            <i class="fas fa-paw text-info me-2 fa-lg"></i>
            <span>PETSHOP PRO</span>
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard/index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="keranjang.php"><i class="fas fa-shopping-cart me-1"></i> Keranjang</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="booking_saya.php"><i class="fas fa-calendar-check text-warning me-1"></i> Booking Saya</a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center text-white">
                <span class="me-3 small">Halo, <strong class="text-info"><?= htmlspecialchars($nama_customer); ?></strong></span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold text-dark mb-1"><i class="fas fa-history text-primary me-2"></i>Riwayat Pembelian Produk</h4>
            <p class="text-muted small mb-0">Daftar nota belanja barang kebutuhan anabul yang pernah Anda transaksikan.</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="../dashboard/index.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm card-custom p-0 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="py-3 ps-4" style="width: 5%;">No</th>
                            <th class="py-3">No. Nota / Transaksi</th>
                            <th class="py-3">Tanggal Beli</th>
                            <th class="py-3">Total Pembayaran</th>
                            <th class="py-3">Status Nota</th>
                            <th class="py-3 text-center" style="width: 15%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT ID_Nota, No_Nota, Tanggal_Penjualan, Grand_Total, Status_Pembayaran 
                                FROM Penjualan 
                                WHERE ID_Pelanggan = ? 
                                ORDER BY Tanggal_Penjualan DESC";
                        
                        $params = array($id_pelanggan);
                        $stmt = sqlsrv_query($conn, $sql, $params);

                        $no = 1;
                        $ada_data = false;

                        if ($stmt !== false) {
                            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                $ada_data = true;
                                
                                // Format tanggal SQL Server ke PHP
                                $tgl = $row['Tanggal_Penjualan'];
                                $format_tgl = ($tgl instanceof DateTime) ? $tgl->format('d M Y - H:i') : $row['Tanggal_Penjualan'];
                                
                                // Kondisi warna status pembayaran sesuai CHECK constraint di SQL
                                $status = $row['Status_Pembayaran'] ?? 'Belum Lunas';
                                if ($status == 'Lunas') {
                                    $badge_class = 'bg-success';
                                } else {
                                    $badge_class = 'bg-danger';
                                }
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-secondary"><?= $no++; ?></td>
                            <td>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['No_Nota'] ?? "#".$row['ID_Nota']); ?></span>
                            </td>
                            <td><i class="far fa-calendar-alt text-muted me-1"></i> <?= $format_tgl; ?> WIB</td>
                            <td>
                                <span class="fw-bold text-danger">Rp <?= number_format($row['Grand_Total'], 0, ',', '.'); ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badge_class; ?> rounded-pill px-3 py-2 small fw-bold">
                                    <?= htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="riwayat_detail.php?nota=<?= $row['ID_Nota']; ?>" class="btn btn-sm btn-info rounded-pill px-3 text-white fw-medium">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                        <?php 
                            }
                        }

                        if (!$ada_data) {
                            echo "<tr><td colspan='6' class='text-center py-5 text-muted'><i class='fas fa-receipt fa-3x mb-3 opacity-25 d-block'></i>Belum ada riwayat transaksi pembelian produk.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>