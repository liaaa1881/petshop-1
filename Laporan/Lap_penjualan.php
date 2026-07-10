<?php
session_start();
require_once '../config/koneksi.php';

// Proteksi Login
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Inisialisasi filter tanggal (Jika belum filter, set dari awal bulan s/d hari ini)
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// Inisialisasi Ringkasan Statistik Laporan
$total_pendapatan = 0;
$transaksi_sukses = 0;
$transaksi_batal = 0;

if ($conn) {
    // 1. Hitung Total Pendapatan dari Transaksi Barang yang Lunas
    $sql_pendapatan = "SELECT SUM(Total_Bayar) as total FROM Penjualan 
                       WHERE Status_Pembayaran = 'Lunas' 
                       AND CAST(Tanggal_Penjualan AS DATE) BETWEEN ? AND ?";
    $stmt1 = sqlsrv_query($conn, $sql_pendapatan, array($tgl_mulai, $tgl_selesai));
    if ($stmt1) {
        $res1 = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
        $total_pendapatan = $res1['total'] ?? 0;
    }

    // 2. Hitung Jumlah Transaksi Sukses (Lunas)
    $sql_sukses = "SELECT COUNT(*) as total FROM Penjualan 
                   WHERE Status_Pembayaran = 'Lunas' 
                   AND CAST(Tanggal_Penjualan AS DATE) BETWEEN ? AND ?";
    $stmt2 = sqlsrv_query($conn, $sql_sukses, array($tgl_mulai, $tgl_selesai));
    if ($stmt2) {
        $transaksi_sukses = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
    }

    // 3. Hitung Jumlah Transaksi Belum Lunas / Menggantung (Sebagai pengganti Batal di tabel Penjualan)
    $sql_batal = "SELECT COUNT(*) as total FROM Penjualan 
                  WHERE Status_Pembayaran = 'Belum Lunas' 
                  AND CAST(Tanggal_Penjualan AS DATE) BETWEEN ? AND ?";
    $stmt3 = sqlsrv_query($conn, $sql_batal, array($tgl_mulai, $tgl_selesai));
    if ($stmt3) {
        $transaksi_batal = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan Barang | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2=family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; color: #2d3436; }
        .stat-card { border: none; border-radius: 20px; padding: 25px; transition: 0.3s; color: white; }
        .stat-card.blue { background: linear-gradient(135deg, #4466f2, #6a85f4); }
        .stat-card.green { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .stat-card.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.05); border: none; padding: 30px; }
        .badge-status { font-size: 0.75rem; padding: 6px 14px; border-radius: 50px; font-weight: 600; text-transform: uppercase; }
        .table thead th { 
            background: #f8fafc; color: #64748b; font-size: 0.75rem; 
            text-transform: uppercase; letter-spacing: 1px; border: none; padding: 15px;
        }
        .table tbody td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .btn-filter { background: #4466f2; color: white; border-radius: 12px; padding: 10px 20px; border: none; font-weight: 600; transition: 0.3s; }
        .btn-filter:hover { background: #3452ce; }
        .btn-print { background: #6c757d; color: white; border-radius: 12px; padding: 10px 20px; border: none; font-weight: 600; transition: 0.3s; text-decoration: none; }
        .btn-print:hover { background: #5a6268; color: white; }
        .search-box { border-radius: 50px; padding-left: 20px; border: 1px solid #dfe6e9; background: #f9f9f9; }

        @media print {
            body { background: white; color: black; }
            nav, .btn-print, .btn-filter, form, .search-container, .no-print { display: none !important; }
            .container { max-width: 100% !important; width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .glass-card { box-shadow: none !important; padding: 0 !important; border: none !important; }
            .stat-card { color: black !important; background: none !important; border: 1px solid #ccc !important; padding: 15px !important; margin-bottom: 10px; }
            .stat-card i { display: none !important; }
            .stat-card h2, .stat-card p { color: black !important; }
            .table th { background-color: #f2f2f2 !important; color: black !important; border: 1px solid #000 !important; }
            .table td { border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php include '../layouts/navbar.php'; ?>
    </div>

    <div class="container py-5">
        
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h3 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Laporan Penjualan Barang</h3>
                <p class="text-muted small mb-0">Periode: <strong><?= date('d M Y', strtotime($tgl_mulai)) ?></strong> s/d <strong><?= date('d M Y', strtotime($tgl_selesai)) ?></strong></p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0 no-print">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print me-2"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="glass-card mb-4 no-print">
            <form method="GET" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Tanggal Mulai</label>
                        <input type="date" name="tgl_mulai" class="form-control px-3 py-2" style="border-radius:10px;" value="<?= $tgl_mulai ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Tanggal Selesai</label>
                        <input type="date" name="tgl_selesai" class="form-control px-3 py-2" style="border-radius:10px;" value="<?= $tgl_selesai ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn-filter w-100 py-2">
                            <i class="fas fa-filter me-2"></i> Filter Laporan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Total Omset Pendapatan</p>
                            <h2 class="fw-bold mb-0">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></h2>
                        </div>
                        <i class="fas fa-money-bill-wave fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Transaksi Sukses (Lunas)</p>
                            <h2 class="fw-bold mb-0"><?= $transaksi_sukses ?></h2>
                        </div>
                        <i class="fas fa-circle-check fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card red">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Belum Lunas / Pending</p>
                            <h2 class="fw-bold mb-0"><?= $transaksi_batal ?></h2>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="row align-items-center mb-4 search-container">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1">Rincian Nota Penjualan</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="input-group ms-auto" style="max-width: 300px;">
                        <span class="input-group-text bg-transparent border-end-0 search-box"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="lapSearch" class="form-control border-start-0 search-box" placeholder="Cari nota atau pelanggan...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="lapTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>No Nota</th>
                            <th>Pelanggan</th>
                            <th>Tanggal Penjualan</th>
                            <th>Metode Pembayaran</th>
                            <th>Kasir</th>
                            <th class="text-end">Total Bayar</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        // PERBAIKAN UTAMA: Query dialihkan ke tabel Penjualan dengan filter CAST DATE SQL Server
                        $sql_rincian = "SELECT P.*, PL.Nama_Pelanggan, K.Nama_Karyawan 
                                        FROM Penjualan P
                                        LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
                                        LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
                                        WHERE CAST(P.Tanggal_Penjualan AS DATE) BETWEEN ? AND ?
                                        ORDER BY P.Tanggal_Penjualan DESC";
                        
                        $query = sqlsrv_query($conn, $sql_rincian, array($tgl_mulai, $tgl_selesai));

                        if ($query) {
                            $has_data = false;
                            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                                $has_data = true;
                                $status = $row['Status_Pembayaran'];
                                $badge = ($status == 'Lunas') ? 'bg-success text-white' : 'bg-warning text-dark';
                                $tgl_transaksi = ($row['Tanggal_Penjualan'] instanceof DateTime) ? $row['Tanggal_Penjualan']->format('d M Y, H:i') : '-';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="fw-bold text-muted">#NOTA-<?= $row['ID_Nota'] ?></span></td>
                            <td><strong><?= htmlspecialchars($row['Nama_Pelanggan'] ?? 'Umum / Guest') ?></strong></td>
                            <td><small><?= $tgl_transaksi ?></small></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['Metode_Pembayaran'] ?: '-') ?></span></td>
                            <td><small><?= htmlspecialchars($row['Nama_Karyawan'] ?? 'Online System') ?></small></td>
                            <td class="text-end fw-bold">Rp <?= number_format($row['Total_Bayar'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge badge-status <?= $badge ?>"><?= $status ?></span>
                            </td>
                        </tr>
                        <?php 
                            }
                            if (!$has_data) {
                                echo "<tr><td colspan='8' class='text-center text-muted py-4'>Tidak ada data transaksi penjualan pada periode ini.</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-danger fw-bold py-4'>Gagal memuat database laporan.</td></tr>";
                        } 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('lapSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#lapTable tbody tr');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if(row.cells.length > 1) {
                    row.style.display = text.includes(filter) ? "" : "none";
                }
            });
        });
    </script>
</body>
</html>