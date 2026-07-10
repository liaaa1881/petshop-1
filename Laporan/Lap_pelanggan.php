<?php
session_start();

// Menggunakan __DIR__ agar PHP mencari folder config secara absolut & anti-error "Undefined variable $conn"
if (file_exists(__DIR__ . '/../config/koneksi.php')) {
    require_once __DIR__ . '/../config/koneksi.php';
} else {
    die("Gagal memuat koneksi. Pastikan letak folder '../config/koneksi.php' sudah benar.");
}

// Proteksi Login
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Inisialisasi Variabel Statistik Laporan
$total_pelanggan = 0;
$pelanggan_aktif = 0;
$layanan_favorit = "Belum Ada";

if (isset($conn) && $conn) {
    // 1. Hitung Total Pelanggan Terdaftar
    $sql_total = "SELECT COUNT(*) as total FROM Pelanggan";
    $stmt1 = sqlsrv_query($conn, $sql_total);
    if ($stmt1) {
        $res1 = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
        $total_pelanggan = $res1['total'] ?? 0;
    }

    // 2. Hitung Jumlah Pelanggan Unik yang Pernah Melakukan Transaksi (Tabel Penjualan)
    $sql_aktif = "SELECT COUNT(DISTINCT ID_Pelanggan) as total FROM Penjualan WHERE ID_Pelanggan IS NOT NULL";
    $stmt2 = sqlsrv_query($conn, $sql_aktif);
    if ($stmt2) {
        $res2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
        $pelanggan_aktif = $res2['total'] ?? 0;
    }

    // 3. Cari Layanan yang Paling Sering Dipesan oleh Pelanggan (Terfavorit)
    // Catatan: Jika relasi layanan dicatat via tabel detail, ini mengambil nama layanan terpopuler dari transaksi Penjualan
   // 3. Cari Layanan yang Paling Sering Dipesan oleh Pelanggan (Terfavorit)
    // 3. Cari Layanan yang Paling Sering Dipesan oleh Pelanggan (Terfavorit)
    $sql_fav = "SELECT TOP 1 L.Nama_Layanan, COUNT(P.ID_Nota) as jumlah 
                FROM Penjualan P
                JOIN Layanan L ON P.ID_Layanan = L.ID_Layanan
                GROUP BY L.Nama_Layanan
                ORDER BY jumlah DESC";
    $stmt3 = sqlsrv_query($conn, $sql_fav);
    if ($stmt3) {
        $res3 = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC);
        if ($res3) {
            $layanan_favorit = $res3['Nama_Layanan'] . " (" . $res3['jumlah'] . "x)";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Analisis Pelanggan | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; color: #2d3436; }
        .stat-card { border: none; border-radius: 20px; padding: 25px; transition: 0.3s; color: white; }
        .stat-card.purple { background: linear-gradient(135deg, #6c5ce7, #a29bfe); }
        .stat-card.cyan { background: linear-gradient(135deg, #00cec9, #81ecec); }
        .stat-card.orange { background: linear-gradient(135deg, #e17055, #fab1a0); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.05); border: none; padding: 30px; }
        .table thead th { background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 15px; }
        .table tbody td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .btn-print { background: #4466f2; color: white; border-radius: 12px; padding: 10px 20px; border: none; font-weight: 600; transition: 0.3s; text-decoration: none; }
        .btn-print:hover { background: #3452ce; color: white; }
        .search-box { border-radius: 50px; padding-left: 20px; border: 1px solid #dfe6e9; background: #f9f9f9; }

        @media print {
            body { background: white; color: black; }
            nav, .btn-print, .search-container, .no-print { display: none !important; }
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
        <?php 
        if (file_exists(__DIR__ . '/../layouts/navbar.php')) {
            include __DIR__ . '/../layouts/navbar.php'; 
        }
        ?>
    </div>

    <div class="container py-5">
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h3 class="fw-bold mb-1"><i class="fas fa-users text-primary me-2"></i>Laporan Rekapitulasi Pelanggan</h3>
                <p class="text-muted small mb-0">Dicetak pada: <strong><?= date('d M Y, H:i') ?></strong></p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0 no-print">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print me-2"></i> Cetak Dokumen
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card purple">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Total Pelanggan Terdaftar</p>
                            <h2 class="fw-bold mb-0"><?= $total_pelanggan ?> Orang</h2>
                        </div>
                        <i class="fas fa-address-book fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card cyan">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Pelanggan Aktif Transaksi</p>
                            <h2 class="fw-bold mb-0"><?= $pelanggan_aktif ?> Orang</h2>
                        </div>
                        <i class="fas fa-user-check fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Layanan Terfavorit</p>
                            <h4 class="fw-bold mb-0 mt-1"><?= htmlspecialchars($layanan_favorit) ?></h4>
                        </div>
                        <i class="fas fa-star fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="row align-items-center mb-4 search-container">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1">Daftar Keaktifan & Total Transaksi Pelanggan</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="input-group ms-auto" style="max-width: 300px;">
                        <span class="input-group-text bg-transparent border-end-0 search-box"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="pelSearch" class="form-control border-start-0 search-box" placeholder="Cari pelanggan...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="pelTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Pelanggan</th>
                            <th>Nama Pelanggan</th>
                            <th>No. Telepon / HP</th>
                            <th>Alamat</th>
                            <th class="text-center">Frekuensi Booking</th>
                            <th class="text-end">Total Kontribusi Dana</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        
                        if (isset($conn) && $conn) {
                            // Query disesuaikan dengan struktur tabel Penjualan dan kolom No_Telepon
                            // Query disesuaikan dengan asumsi nama kolom 'Nota'
                            // Query disesuaikan menggunakan ID_Nota
                            $sql_rincian = "SELECT 
                                                P.ID_Pelanggan, 
                                                P.Nama_Pelanggan, 
                                                P.No_Telepon, 
                                                P.Alamat,
                                                COALESCE(T.Frekuensi_Booking, 0) as Frekuensi_Booking,
                                                COALESCE(T.Total_Kontribusi, 0) as Total_Kontribusi
                                            FROM Pelanggan P
                                            LEFT JOIN (
                                                SELECT 
                                                    ID_Pelanggan,
                                                    COUNT(ID_Nota) as Frekuensi_Booking,
                                                    SUM(COALESCE(Total_Bayar, 0)) as Total_Kontribusi
                                                FROM Penjualan
                                                GROUP BY ID_Pelanggan
                                            ) T ON P.ID_Pelanggan = T.ID_Pelanggan
                                            ORDER BY Frekuensi_Booking DESC, P.Nama_Pelanggan ASC";
                            
                            $query = sqlsrv_query($conn, $sql_rincian);

                            if ($query) {
                                $has_data = false;
                                while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                                    $has_data = true;
                                    $frekuensi = $row['Frekuensi_Booking'];
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="fw-bold text-muted">#PLG-<?= htmlspecialchars($row['ID_Pelanggan']) ?></span></td>
                            <td><strong><?= htmlspecialchars($row['Nama_Pelanggan']) ?></strong></td>
                            <td><small><?= htmlspecialchars($row['No_Telepon'] ?: '-') ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($row['Alamat'] ?: '-') ?></small></td>
                            <td class="text-center">
                                <span class="badge <?= $frekuensi > 0 ? 'bg-light text-primary border border-primary-subtle' : 'bg-light text-muted' ?> px-3 py-2" style="font-size:0.85rem;">
                                    <?= $frekuensi ?> Kali
                                </span>
                            </td>
                            <td class="text-end fw-bold text-success">
                                Rp <?= number_format($row['Total_Kontribusi'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php 
                                }
                                if (!$has_data) {
                                    echo "<tr><td colspan='7' class='text-center text-muted py-4'>Tidak ditemukan data pelanggan terdaftar di database.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center text-danger fw-bold py-4'>Gagal mengeksekusi query rincian tabel.</td></tr>";
                                if (($errors = sqlsrv_errors()) != null) {
                                    echo "<tr><td colspan='7' class='text-start text-dark bg-light p-3' style='font-family:monospace; font-size:11px;'>";
                                    echo "<strong>Pesan Error Database:</strong><br>";
                                    foreach ($errors as $error) {
                                        echo "- " . htmlspecialchars($error['message']) . "<br>";
                                    }
                                    echo "</td></tr>";
                                }
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center text-danger fw-bold py-4'>Koneksi database terputus atau tidak ditemukan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fitur Filter Pencarian Real-time Data Pelanggan
        document.getElementById('pelSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#pelTable tbody tr');
            rows.forEach(row => {
                if(row.cells.length > 1) {
                    let text = row.innerText.toLowerCase();
                    row.style.display = text.includes(filter) ? "" : "none";
                }
            });
        });
    </script>
</body>
</html>