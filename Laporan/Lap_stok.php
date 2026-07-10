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

// Inisialisasi Variabel Statistik Stok
$total_item = 0;
$stok_menipis = 0;
$stok_habis = 0;

// SINKRONISASI NAMA TABEL: Silakan ganti 'Barang' di bawah ini jika nama tabel Anda di database berbeda (misal: 'Produk')
$nama_tabel_produk = "Barang"; 

if (isset($conn) && $conn) {
    // 1. Hitung Total Jenis Item/Produk Terdaftar
    $sql_total = "SELECT COUNT(*) as total FROM $nama_tabel_produk";
    $stmt1 = sqlsrv_query($conn, $sql_total);
    if ($stmt1) {
        $res1 = sqlsrv_fetch_array($stmt1, SQLSRV_FETCH_ASSOC);
        $total_item = $res1['total'] ?? 0;
    }

    // 2. Hitung Jumlah Item dengan Stok Menipis (Stok antara 1 sampai 5)
    $sql_menipis = "SELECT COUNT(*) as total FROM $nama_tabel_produk WHERE Stok > 0 AND Stok <= 5";
    $stmt2 = sqlsrv_query($conn, $sql_menipis);
    if ($stmt2) {
        $res2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
        $stok_menipis = $res2['total'] ?? 0;
    }

    // 3. Hitung Jumlah Item dengan Stok Habis (Stok = 0)
    $sql_habis = "SELECT COUNT(*) as total FROM $nama_tabel_produk WHERE Stok <= 0";
    $stmt3 = sqlsrv_query($conn, $sql_habis);
    if ($stmt3) {
        $res3 = sqlsrv_fetch_array($stmt3, SQLSRV_FETCH_ASSOC);
        $stok_habis = $res3['total'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Inventaris Stok | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; color: #2d3436; }
        .stat-card { border: none; border-radius: 20px; padding: 25px; transition: 0.3s; color: white; }
        .stat-card.indigo { background: linear-gradient(135deg, #4e73df, #224abe); }
        .stat-card.warning { background: linear-gradient(135deg, #f6c23e, #f1b319); color: #333; }
        .stat-card.danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .glass-card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.05); border: none; padding: 30px; }
        .badge-stock { font-size: 0.75rem; padding: 6px 14px; border-radius: 50px; font-weight: 600; }
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
            .badge-stock { border: 1px solid #000 !important; color: black !important; background: transparent !important; }
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
                <h3 class="fw-bold mb-1"><i class="fas fa-boxes-stacked text-primary me-2"></i>Laporan Keadaan Stok Barang</h3>
                <p class="text-muted small mb-0">Waktu Peninjauan: <strong><?= date('d M Y, H:i') ?></strong></p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0 no-print">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print me-2"></i> Cetak Laporan Stok
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card indigo">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Total Produk Terdaftar</p>
                            <h2 class="fw-bold mb-0"><?= $total_item ?> Item</h2>
                        </div>
                        <i class="fas fa-cubes fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Stok Menipis (≤ 5)</p>
                            <h2 class="fw-bold mb-0"><?= $stok_menipis ?> Item</h2>
                        </div>
                        <i class="fas fa-triangle-exclamation fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-1 opacity-75">Stok Habis (Kosong)</p>
                            <h2 class="fw-bold mb-0"><?= $stok_habis ?> Item</h2>
                        </div>
                        <i class="fas fa-circle-exclamation fa-3x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="row align-items-center mb-4 search-container">
                <div class="col-md-6">
                    <h5 class="fw-bold mb-1">Daftar Inventaris & Nilai Aset Produk</h5>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="input-group ms-auto" style="max-width: 300px;">
                        <span class="input-group-text bg-transparent border-end-0 search-box"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="stokSearch" class="form-control border-start-0 search-box" placeholder="Cari nama barang...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="stokTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Produk / Barang</th>
                            <th>Nama Produk</th>
                            <th>Kategori / Deskripsi</th>
                            <th class="text-end">Harga Jual</th>
                            <th class="text-center">Jumlah Stok</th>
                            <th class="text-center">Status Gudang</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        
                        if (isset($conn) && $conn) {
                            $sql_produk = "SELECT * FROM $nama_tabel_produk ORDER BY Stok ASC, Nama_Barang ASC";
                            $query = sqlsrv_query($conn, $sql_produk);

                            if ($query) {
                                $has_data = false;
                                while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
                                    $has_data = true;
                                    $stok = $row['Stok'] ?? 0;
                                    
                                    // SINKRONISASI KOLOM: Menghindari error undifined column ID
                                    $id_tampil = $row['ID_Barang'] ?? $row['ID_Produk'] ?? '0';
                                    $nama_tampil = $row['Nama_Barang'] ?? $row['Nama_Produk'] ?? '-';
                                    $harga_tampil = $row['Harga_Jual'] ?? $row['Harga'] ?? 0;
                                    
                                    if ($stok <= 0) {
                                        $badge_class = 'bg-danger text-white';
                                        $status_text = 'Habis';
                                    } elseif ($stok <= 5) {
                                        $badge_class = 'bg-warning text-dark';
                                        $status_text = 'Menipis';
                                    } else {
                                        $badge_class = 'bg-success text-white';
                                        $status_text = 'Aman';
                                    }
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="fw-bold text-muted">#BRG-<?= htmlspecialchars($id_tampil) ?></span></td>
                            <td><strong><?= htmlspecialchars($nama_tampil) ?></strong></td>
                            <td><small class="text-muted"><?= htmlspecialchars($row['Deskripsi'] ?? $row['Kategori'] ?? '-') ?></small></td>
                            <td class="text-end fw-bold">
                                Rp <?= number_format($harga_tampil, 0, ',', '.') ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold <?= $stok <= 5 ? 'text-danger' : 'text-dark' ?>">
                                    <?= $stok ?> Pcs
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-stock <?= $badge_class ?>"><?= $status_text ?></span>
                            </td>
                        </tr>
                        <?php 
                                }
                                if (!$has_data) {
                                    echo "<tr><td colspan='7' class='text-center text-muted py-4'>Belum ada data barang terdaftar di database.</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' class='text-center text-danger fw-bold py-4'>Gagal mengeksekusi data tabel.</td></tr>";
                                if (($errors = sqlsrv_errors()) != null) {
                                    echo "<tr><td colspan='7' class='text-start text-dark bg-light p-3' style='font-family:monospace; font-size:11px;'>";
                                    echo "<strong>Pesan Sistem SQL Server:</strong><br>";
                                    foreach ($errors as $error) {
                                        echo "- " . htmlspecialchars($error['message']) . "<br>";
                                    }
                                    echo "<br>💡 <em>Tip: Jika muncul pesan 'Invalid object name', ganti isi variabel <code>\$nama_tabel_produk</code> di baris ke-21 file ini dengan nama tabel Anda yang benar.</em>";
                                    echo "</td></tr>";
                                }
                            }
                        } else {
                            echo "<tr><td colspan='7' class='text-center text-danger fw-bold py-4'>Koneksi database tidak aktif.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('stokSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#stokTable tbody tr');
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