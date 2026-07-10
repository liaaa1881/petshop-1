<?php
session_start();
// Menggunakan huruf kecil (lowercase) untuk folder config
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php"); 
    exit();
}

$id_pelanggan = $_SESSION['id_pelanggan'] ?? 0;
$nama_customer = $_SESSION['nama'] ?? 'Pelanggan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Petshop Pro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #1e272e !important; }
        .card-produk { border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead { background-color: #f1f2f6; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="../dashboard/index.php">
            <i class="fas fa-paw text-info me-2 fa-lg"></i>
            <span>PETSHOP PRO</span>
        </a>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../dashboard/index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center text-white">
                <span class="me-3 small">Halo, <strong class="text-info"><?= htmlspecialchars($nama_customer); ?></strong></span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold text-dark mb-1"><i class="fas fa-shopping-basket text-success me-2"></i>Keranjang Belanja</h4>
            <p class="text-muted small mb-0">Kelola barang belanjaan untuk anabul kesayangan Anda.</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="../dashboard/index.php" class="btn btn-light border btn-sm">
                <i class="fas fa-chevron-left me-1"></i> Belanja Lagi
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-produk p-3">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="py-3">Produk</th>
                                <th class="py-3">Harga</th>
                                <th class="py-3 text-center">Jumlah</th>
                                <th class="py-3">Subtotal</th>
                                <th class="py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Mengambil data draf belanja yang belum lunas (Diintegrasikan dengan UDF fn_StokBarang)
                            $sql = "SELECT DP.ID_Detail, DP.ID_Nota, DP.ID_Barang, DP.Jumlah, DP.Harga_Satuan, DP.Subtotal, B.Nama_Barang, B.Satuan
                                    FROM Detail_Penjualan DP
                                    INNER JOIN Penjualan P ON DP.ID_Nota = P.ID_Nota
                                    INNER JOIN dbo.fn_StokBarang(NULL) B ON DP.ID_Barang = B.ID_Barang
                                    WHERE P.ID_Pelanggan = ? AND P.Status_Pembayaran = 'Belum Lunas'";
                            
                            $params = array($id_pelanggan);
                            $stmt = sqlsrv_query($conn, $sql, $params);

                            $total_belanja = 0;
                            $ada_item = false;
                            $id_nota_aktif = null;

                            if ($stmt !== false) {
                                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                                    $ada_item = true;
                                    $id_nota_aktif = $row['ID_Nota'];
                                    $subtotal = $row['Subtotal'];
                                    $total_belanja += $subtotal;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['Nama_Barang']); ?></div>
                                    <small class="text-muted">#<?= $row['ID_Barang']; ?></small>
                                </td>
                                <td>Rp <?= number_format($row['Harga_Satuan'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= $row['Jumlah']; ?> <?= $row['Satuan']; ?></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <a href="keranjang_hapus.php?id_detail=<?= $row['ID_Detail']; ?>" class="text-danger" onclick="return confirm('Hapus item ini dari keranjang?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                }
                            }

                            if (!$ada_item) {
                                echo "<tr><td colspan='5' class='text-center py-5 text-muted'>Keranjang belanja Anda masih kosong.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-produk p-4">
                <h5 class="fw-bold mb-3">Ringkasan</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>Total Harga</span>
                    <span>Rp <?= number_format($total_belanja, 0, ',', '.') ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-4">
                    <span class="fw-bold">Total Bayar</span>
                    <h4 class="fw-bold text-danger">Rp <?= number_format($total_belanja, 0, ',', '.') ?></h4>
                </div>

                <?php if ($total_belanja > 0): ?>
                    <a href="checkout_proses.php?nota=<?= $id_nota_aktif; ?>" class="btn btn-warning w-100 fw-bold rounded-pill" onclick="return confirm('Apakah Anda yakin ingin memproses pesanan ini?')">
                        Checkout Sekarang
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>