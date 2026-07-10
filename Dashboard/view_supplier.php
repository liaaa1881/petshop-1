<div class="row g-4">
    <!-- Status Pengiriman -->
    <div class="col-md-6">
        <div class="card stat-card p-4 shadow-sm border-0 h-100">
            <h5 class="fw-bold text-dark mb-4"><i class="fas fa-truck-moving me-2 text-primary"></i>Status Distribusi Pasok</h5>
            <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-4">
                <div class="icon-circle bg-primary text-white me-3">
                    <i class="fas fa-building"></i>
                </div>
                <div>
                    <p class="text-muted small mb-0">Mitra Perusahaan Supplier:</p>
                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($_SESSION['nama']); ?></h5>
                </div>
            </div>
            <p class="text-muted small mb-0">Anda terhubung sebagai mitra penyuplai resmi. Manfaatkan menu di samping kanan untuk mencatatkan nota stok masuk baru atau memantau sisa barang Anda yang tersedia di dalam gudang.</p>
        </div>
    </div>

    <!-- Akses Pengiriman / Stok -->
    <div class="col-md-6">
        <div class="row g-4">
            <div class="col-12">
                <a href="../transaksi/stok_masuk_read.php" class="text-decoration-none">
                    <div class="card stat-card p-4 border-start border-primary border-5 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-start">
                                <h5 class="fw-bold text-dark mb-1">Daftarkan Stok Masuk</h5>
                                <p class="text-muted small mb-0">Laporkan pengiriman pasokan barang baru</p>
                            </div>
                            <i class="fas fa-file-import fa-3x text-primary opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12">
                <a href="../master/barang/barang_tampil.php" class="text-decoration-none">
                    <div class="card stat-card p-4 border-start border-info border-5 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-start">
                                <h5 class="fw-bold text-dark mb-1">Pantau Stok Gudang</h5>
                                <p class="text-muted small mb-0">Cek kuantitas sisa pasokan Anda di rak</p>
                            </div>
                            <i class="fas fa-chart-pie fa-3x text-info opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>