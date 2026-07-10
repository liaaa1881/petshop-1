<?php
session_start();
// Menggunakan huruf kecil (lowercase) untuk folder config
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman
if (!isset($_SESSION['id_pelanggan'])) {
    echo "<script>
            window.location.href = '../auth/login.php';
          </script>";
    exit();
}

// 2. Ambil parameter ID_Nota dari URL
if (!isset($_GET['nota']) || empty($_GET['nota'])) {
    echo "<script>
            window.location.href = 'riwayat_belanja.php';
          </script>";
    exit();
}

$id_nota = intval($_GET['nota']);
$id_pelanggan = intval($_SESSION['id_pelanggan']);

if (!$conn) {
    die("<pre>Koneksi database gagal terhubung.</pre>");
}

// 3. Query ambil data induk Penjualan (Memastikan kepemilikan nota milik pelanggan yang bersangkutan)
$sql_nota = "SELECT P.*, PL.Nama_Pelanggan, PL.No_Telepon, PL.Alamat 
             FROM Penjualan P
             INNER JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
             WHERE P.ID_Nota = ? AND P.ID_Pelanggan = ?";
$params_nota = array($id_nota, $id_pelanggan);
$stmt_nota = sqlsrv_query($conn, $sql_nota, $params_nota);

if ($stmt_nota === false || !($data_nota = sqlsrv_fetch_array($stmt_nota, SQLSRV_FETCH_ASSOC))) {
    echo "<script>
            window.location.href = 'riwayat_belanja.php';
          </script>";
    exit();
}

// Format Tanggal Transaksi
$tgl = $data_nota['Tanggal_Penjualan'];
$format_tgl = ($tgl instanceof DateTime) ? $tgl->format('d F Y - H:i') : $data_nota['Tanggal_Penjualan'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Nota #<?= htmlspecialchars($data_nota['No_Nota'] ?? $id_nota); ?> - Petshop Pro</title>
    
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
    </div>
</nav>

<div class="container my-5">
    <div class="row mb-4">
        <div class="col-6">
            <h4 class="fw-bold text-dark"><i class="fas fa-file-invoice-dollar text-info me-2"></i>Detail Invoice Belanja</h4>
        </div>
        <div class="col-6 text-end">
            <a href="riwayat_belanja.php" class="btn btn-outline-secondary rounded-pill btn-sm px-4">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Riwayat
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm card-custom p-4 bg-white mb-4">
                <h6 class="fw-bold text-secondary text-uppercase small mb-3">Informasi Nota</h6>
                <div class="mb-3">
                    <small class="text-muted d-block">Nomor Nota:</small>
                    <strong class="text-dark fs-6 text-wrap text-break"><?= htmlspecialchars($data_nota['No_Nota']); ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Tanggal Transaksi:</small>
                    <span class="fw-semibold text-dark"><i class="far fa-calendar-alt me-1"></i> <?= $format_tgl; ?> WIB</span>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Metode Pembayaran:</small>
                    <span class="badge bg-secondary px-3 py-2 fw-medium"><?= htmlspecialchars($data_nota['Metode_Pembayaran'] ?? 'Cash'); ?></span>
                </div>
                <div>
                    <small class="text-muted d-block">Status Pembayaran:</small>
                    <?php
                    $status = $data_nota['Status_Pembayaran'];
                    $badge = ($status == 'Lunas') ? 'bg-success' : (($status == 'DP') ? 'bg-warning text-dark' : 'bg-danger');
                    ?>
                    <span class="badge <?= $badge; ?> px-3 py-2 fw-bold rounded-pill"><?= htmlspecialchars($status); ?></span>
                </div>
            </div>

            <div class="card border-0 shadow-sm card-custom p-4 bg-white">
                <h6 class="fw-bold text-secondary text-uppercase small mb-3">Tujuan Pengiriman / Pelanggan</h6>
                <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($data_nota['Nama_Pelanggan']); ?></div>
                <div class="text-muted small mb-2"><i class="fas fa-phone me-1 small"></i> <?= htmlspecialchars($data_nota['No_Telepon']); ?></div>
                <div class="text-dark small bg-light p-2 rounded border"><?= htmlspecialchars($data_nota['Alamat'] ?? 'Alamat tidak diisi.'); ?></div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm card-custom overflow-hidden bg-white">
                <div class="card-header bg-dark text-white py-3 px-4">
                    <h6 class="m-0 fw-bold"><i class="fas fa-box me-2"></i>Item yang Dibeli</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-4">Nama Produk / Barang</th>
                                <th class="py-3 text-end">Harga Satuan</th>
                                <th class="py-3 text-center">Jumlah</th>
                                <th class="py-3 text-end pe-4">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query mengambil rincian detail barang terintegrasi dengan UDF fn_StokBarang
                            $sql_detail = "SELECT DP.*, B.Nama_Barang, B.Satuan, B.Nama_Kategori 
                                           FROM Detail_Penjualan DP
                                           INNER JOIN dbo.fn_StokBarang(NULL) B ON DP.ID_Barang = B.ID_Barang
                                           WHERE DP.ID_Nota = ?";
                            $params_detail = array($id_nota);
                            $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);

                            if ($stmt_detail !== false) {
                                while ($row_d = sqlsrv_fetch_array($stmt_detail, SQLSRV_FETCH_ASSOC)) {
                            ?>
                            <tr>
                                <td class="py-3 ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row_d['Nama_Barang']); ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($row_d['Nama_Kategori'] ?? 'Kategori Umum'); ?></small>
                                </td>
                                <td class="text-end">Rp <?= number_format($row_d['Harga_Satuan'], 0, ',', '.') ?></td>
                                <td class="text-center"><?= $row_d['Jumlah']; ?> <?= htmlspecialchars($row_d['Satuan'] ?? 'Pcs'); ?></td>
                                <td class="text-end fw-bold text-dark pe-4">Rp <?= number_format($row_d['Subtotal'], 0, ',', '.') ?></td>
                            </tr>
                            <?php
                                }
                            }
                            ?>
                        </tbody>
                        <tfoot class="table-light border-top-0">
                            <tr>
                                <td colspan="3" class="text-end fw-bold py-3">Total yang Harus Dibayar:</td>
                                <td class="text-end fw-bold text-danger fs-5 py-3 pe-4">Rp <?= number_format($data_nota['Grand_Total'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>