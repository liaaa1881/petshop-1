<?php
// =========================================================================
// FUNGSI UTILITY: Eksekusi query secara aman untuk mencegah Fatal Error
// =========================================================================
function ambil_nilai_single($conn, $sql, $default = 0) {
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        if (($errors = sqlsrv_errors()) != null) {
            foreach ($errors as $error) {
                echo "<!-- Gagal Query: " . htmlspecialchars($error['message']) . " | SQL: " . htmlspecialchars($sql) . " -->";
            }
        }
        return $default;
    }
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
    sqlsrv_free_stmt($stmt);
    return ($row && $row[0] !== null) ? $row[0] : $default;
}

// =========================================================================
// DEFINISI KOLOM BERDASARKAN HASIL DIAGNOSTIK
// =========================================================================
$kolom_pendapatan = 'Grand_Total';       // Kolom nominal Anda
$kolom_status     = 'Status_Pembayaran'; // Kolom status Anda

// =========================================================================
// PROSES HITUNG DATA (SINKRON DENGAN STATUS LUNAS SAJA)
// =========================================================================

// 1. DATA INVENTARIS BARANG
$total_kritis = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Barang WHERE Stok <= Stok_Minimum");
$total_produk = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Barang");
$total_qty_stok = ambil_nilai_single($conn, "SELECT SUM(Stok) FROM Barang");

// 2. DATA KATALOG LAYANAN
$total_layanan = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Layanan");

// 3. SINKRONISASI DATA TRANSAKSI PENJUALAN
// RTRIM & LTRIM digunakan untuk membersihkan spasi bawaan jika tipe data kolom berupa CHAR
$total_pendapatan = ambil_nilai_single($conn, "SELECT SUM($kolom_pendapatan) FROM Penjualan WHERE RTRIM(LTRIM($kolom_status)) = 'Lunas'");
$total_transaksi  = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Penjualan WHERE RTRIM(LTRIM($kolom_status)) = 'Lunas'");

// 4. DATA RESERVASI GROOMING
$total_booking = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Booking WHERE Status_Booking IN ('Pending', 'Diproses')");
$total_seluruh_booking = ambil_nilai_single($conn, "SELECT COUNT(*) FROM Booking");
?>

<!-- HEADER DASHBOARD -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="fw-bold text-dark">Dashboard Pelayanan 🐾</h2>
        <p class="text-muted mb-0">Pantau kinerja penjualan, stok gudang, layanan jasa, dan reservasi grooming secara real-time.</p>
    </div>
</div>

<div class="row g-4">
    <!-- PANEL UTAMA KASIR & STATISTIK -->
    <div class="col-lg-8">
        <!-- Panel Utama Pelayanan Kasir -->
        <div class="card p-4 shadow-sm border-0 mb-4 bg-white" style="border-radius: 16px;">
            <h5 class="fw-bold mb-3 text-dark d-flex align-items-center">
                <i class="fas fa-cash-register me-2 text-success"></i> Panel Utama Pelayanan Kasir
            </h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="../transaksi/penjualan_read.php" class="btn btn-success w-100 py-4 rounded-4 fw-bold shadow-sm text-uppercase d-flex flex-column align-items-center justify-content-center border-0" style="background-color: #2ec4b6;">
                        <i class="fas fa-shopping-cart mb-2 fa-2x"></i>
                        <span>Penjualan Baru</span>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="../transaksi/booking_read.php" class="btn btn-primary w-100 py-4 rounded-4 fw-bold shadow-sm text-uppercase d-flex flex-column align-items-center justify-content-center border-0" style="background-color: #4361ee;">
                        <i class="fas fa-calendar-check mb-2 fa-2x"></i>
                        <span>Antrean Grooming</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Ringkasan Transaksi & Layanan -->
        <h5 class="fw-bold mb-3 text-dark">Ringkasan Operasional & Finansial</h5>
        <div class="row g-3">
            <!-- Finansial: Total Pendapatan -->
            <div class="col-md-6">
                <div class="card p-4 shadow-sm border-0 d-flex flex-row align-items-center justify-content-between bg-white" style="border-radius: 16px;">
                    <div>
                        <span class="text-muted d-block small fw-bold">TOTAL PENDAPATAN</span>
                        <h3 class="fw-bold text-dark mt-1">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h3>
                    </div>
                    <div class="p-3 bg-light-success text-success rounded-4" style="background-color: #e8f5e9;">
                        <i class="fas fa-wallet fa-2x"></i>
                    </div>
                </div>
            </div>

            <!-- Finansial: Total Transaksi -->
            <div class="col-md-6">
                <div class="card p-4 shadow-sm border-0 d-flex flex-row align-items-center justify-content-between bg-white" style="border-radius: 16px;">
                    <div>
                        <span class="text-muted d-block small fw-bold">TOTAL TRANSAKSI</span>
                        <h3 class="fw-bold text-dark mt-1"><?= $total_transaksi; ?> <span class="fs-6 text-muted">Nota</span></h3>
                    </div>
                    <div class="p-3 bg-light-primary text-primary rounded-4" style="background-color: #e8eaf6;">
                        <i class="fas fa-file-invoice-dollar fa-2x"></i>
                    </div>
                </div>
            </div>

            <!-- Operasional: Total Produk -->
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0 bg-white" style="border-radius: 16px;">
                    <span class="text-muted d-block small fw-bold">TOTAL PRODUK</span>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <h4 class="fw-bold text-dark mb-0"><?= $total_produk; ?> <span class="fs-6 text-muted">Item</span></h4>
                        <span class="badge rounded-pill bg-light text-dark px-2 py-1"><?= number_format($total_qty_stok, 0, ',', '.'); ?> Qty</span>
                    </div>
                </div>
            </div>

            <!-- Operasional: Katalog Layanan -->
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0 bg-white" style="border-radius: 16px;">
                    <span class="text-muted d-block small fw-bold">LAYANAN JASA</span>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <h4 class="fw-bold text-dark mb-0"><?= $total_layanan; ?> <span class="fs-6 text-muted">Jenis</span></h4>
                        <span class="badge rounded-pill text-info bg-light-info px-2 py-1" style="background-color: #e0f7fa;">Aktif</span>
                    </div>
                </div>
            </div>

            <!-- Operasional: Total Reservasi Grooming -->
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0 bg-white" style="border-radius: 16px;">
                    <span class="text-muted d-block small fw-bold">TOTAL RESERVASI</span>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <h4 class="fw-bold text-dark mb-0"><?= $total_seluruh_booking; ?> <span class="fs-6 text-muted">Antrean</span></h4>
                        <span class="badge rounded-pill text-warning bg-light-warning px-2 py-1" style="background-color: #fff3e0;">Grooming</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- NOTIFIKASI & AGENDA HARI INI -->
    <div class="col-lg-4">
        <div class="card p-4 shadow-sm border-0 bg-white" style="border-radius: 16px; min-height: 100%;">
            <h5 class="fw-bold mb-3 text-dark">Informasi Penting</h5>
            <p class="text-muted small mb-4">Butuh tindakan atau pemantauan segera:</p>
            <div class="list-group list-group-flush bg-transparent">
                
                <!-- Notifikasi Stok Kritis -->
                <div class="list-group-item bg-transparent px-0 py-3 border-bottom d-flex align-items-start">
                    <div class="p-2 rounded-3 me-3 <?= ($total_kritis > 0) ? 'bg-light-danger text-danger' : 'bg-light text-muted'; ?>" style="background-color: <?= ($total_kritis > 0) ? '#ffebee' : '#f5f5f5'; ?>;">
                        <i class="fas fa-exclamation-triangle fs-4"></i>
                    </div>
                    <div>
                        <span class="d-block fw-bold small text-dark">Inventaris & Gudang</span>
                        <small class="text-muted d-block">Ada <strong><?= $total_kritis; ?></strong> produk di bawah batas stok minimum.</small>
                        <?php if ($total_kritis > 0): ?>
                            <a href="../Master/barang/barang_tampil.php" class="btn btn-sm btn-danger py-1 px-2 mt-2" style="font-size: 11px; border-radius: 8px;">Cek Stok Gudang</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifikasi Grooming Aktif -->
                <div class="list-group-item bg-transparent px-0 py-3 border-bottom d-flex align-items-start">
                    <div class="p-2 rounded-3 me-3 bg-light-warning text-warning" style="background-color: #fff3e0;">
                        <i class="fas fa-clock fs-4"></i>
                    </div>
                    <div>
                        <span class="d-block fw-bold small text-dark">Antrean Grooming Aktif</span>
                        <small class="text-muted d-block">Ada <strong><?= $total_booking; ?></strong> reservasi berstatus Pending/Diproses.</small>
                        <a href="../transaksi/booking_read.php" class="btn btn-sm btn-warning py-1 px-2 mt-2" style="font-size: 11px; border-radius: 8px; color: #fff;">Lihat Antrean</a>
                    </div>
                </div>

                <!-- Notifikasi Transaksi Masuk -->
                <div class="list-group-item bg-transparent px-0 py-3 border-bottom d-flex align-items-start">
                    <div class="p-2 rounded-3 me-3 bg-light-primary text-primary" style="background-color: #e8eaf6;">
                        <i class="fas fa-chart-line fs-4"></i>
                    </div>
                    <div>
                        <span class="d-block fw-bold small text-dark">Aktivitas Penjualan</span>
                        <small class="text-muted d-block">Total <strong><?= $total_transaksi; ?></strong> invoice tercatat di database.</small>
                        <a href="../transaksi/penjualan_read.php" class="btn btn-sm btn-primary py-1 px-2 mt-2" style="font-size: 11px; border-radius: 8px; background-color: #4361ee;">Riwayat Penjualan</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>