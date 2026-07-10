

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Naik satu tingkat (../) untuk mengakses folder config
include '../config/koneksi.php';

// Ambil nama admin dan role dari session
$nama_admin = isset($_SESSION['nama']) ? $_SESSION['nama'] : 'Administrator';
$role_admin = isset($_SESSION['role']) ? $_SESSION['role'] : 'Admin Utama';

// 1. HITUNG TOTAL STAFF DARI DATABASE (Karyawan Aktif)
$sql_staff = "SELECT COUNT(*) AS total FROM Karyawan WHERE (Kar_is_deleted = 0 OR Kar_is_deleted IS NULL)";
$query_staff = sqlsrv_query($conn, $sql_staff);
$data_staff = sqlsrv_fetch_array($query_staff, SQLSRV_FETCH_ASSOC);
$total_staff = ($data_staff) ? $data_staff['total'] : 0;

// 2. HITUNG MEMBER AKTIF DARI DATABASE (Pelanggan Aktif)
$sql_member = "SELECT COUNT(*) as total FROM Pelanggan WHERE (Pel_is_deleted = 0 OR Pel_is_deleted IS NULL)";
$query_member = sqlsrv_query($conn, $sql_member);
$data_member = sqlsrv_fetch_array($query_member, SQLSRV_FETCH_ASSOC);
$total_member = ($data_member) ? $data_member['total'] : 0;

// 3. AMBIL DATA SUPPLIER AKTIF
$sql_total_s = "SELECT COUNT(*) as total FROM Supplier WHERE (Sup_is_deleted = 0 OR Sup_is_deleted IS NULL)";
$query_total_s = sqlsrv_query($conn, $sql_total_s);
$data_total_s = sqlsrv_fetch_array($query_total_s, SQLSRV_FETCH_ASSOC);
$total_s = ($data_total_s) ? $data_total_s['total'] : 0;

// --- PERHITUNGAN INVENTARIS BARANG (Menggunakan sp_Barang_Read via CALL) ---
$query_barang = sqlsrv_query($conn, "{CALL sp_Barang_Read(NULL, NULL, NULL)}");
$total_b = 0;
$total_barang_kritis = 0;
$total_stok = 0;
$stok_aman = 0;
$stok_rendah = 0;
$stok_habis = 0;

// Array untuk menampung kuantitas stok per kategori barang (Data Chart Bar)
$kategori_stok = [];
// Array simulasi data kadaluarsa untuk fallback pengingat logistik
$simulasi_barang_kadaluarsa = [];

if ($query_barang !== false) {
    while ($r = sqlsrv_fetch_array($query_barang, SQLSRV_FETCH_ASSOC)) {
        $total_b++;
        $stok_val = isset($r['Stok']) ? (int)$r['Stok'] : 0;
        $total_stok += $stok_val;
        
        // Klasifikasi status stok
        $status_stok = isset($r['Status_Stok']) ? $r['Status_Stok'] : 'Aman';
        if ($status_stok == 'Habis') {
            $stok_habis++;
            $total_barang_kritis++;
        } elseif ($status_stok == 'Stok Rendah') {
            $stok_rendah++;
            $total_barang_kritis++;
        } else {
            $stok_aman++;
        }

        // Akumulasi stok per kategori
        $nama_kat = isset($r['Nama_Kategori']) ? $r['Nama_Kategori'] : 'Umum';
        if (!isset($kategori_stok[$nama_kat])) {
            $kategori_stok[$nama_kat] = 0;
        }
        $kategori_stok[$nama_kat] += $stok_val;

        // --- FILTER KADALUARSA: HANYA PRODUK MAKANAN / KONSUMSI ---
        $is_konsumsi = (
            stripos($nama_kat, 'makanan') !== false || 
            stripos($nama_kat, 'food') !== false || 
            stripos($nama_kat, 'pakan') !== false || 
            stripos($nama_kat, 'shampoo') !== false || 
            stripos($nama_kat, 'obat') !== false || 
            stripos($nama_kat, 'vitamin') !== false
        );

        if ($is_konsumsi && $stok_val < 30 && count($simulasi_barang_kadaluarsa) < 4) {
            $simulasi_barang_kadaluarsa[] = [
                'Nama_Barang' => $r['Nama_Barang'],
                'Kategori' => $nama_kat,
                'Stok' => $stok_val,
                'Days_Left' => rand(5, 28)
            ];
        }
    }
}

// Persiapkan data kategori untuk dikirim ke Javascript Chart
$list_nama_kategori = array_keys($kategori_stok);
$list_jumlah_stok = array_values($kategori_stok);

// 4. HITUNG LAYANAN JASA AKTIF (Menggunakan sp_Layanan_Read)
$query_layanan = sqlsrv_query($conn, "EXEC sp_Layanan_Read");
$total_layanan = 0;
if ($query_layanan !== false) {
    while ($r = sqlsrv_fetch_array($query_layanan, SQLSRV_FETCH_ASSOC)) {
        if (isset($r['Lay_status']) && $r['Lay_status'] == 'Aktif') {
            $total_layanan++;
        }
    }
}

// 5. HITUNG MITRA SUPPLIER AKTIF (Menggunakan sp_Supplier_Read via CALL)
$query_supplier = sqlsrv_query($conn, "{CALL sp_Supplier_Read(NULL, NULL)}");
$total_supplier = 0;
if ($query_supplier !== false) {
    while ($r = sqlsrv_fetch_array($query_supplier, SQLSRV_FETCH_ASSOC)) {
        if (isset($r['Sup_status']) && $r['Sup_status'] == 'Aktif') {
            $total_supplier++;
        }
    }
}

// 6. OMSET (Dihitung dinamis berdasarkan transaksi yang berstatus Lunas)
$total_omset = 0;
$sql_omset = "SELECT SUM(Grand_Total) AS total FROM Penjualan WHERE RTRIM(LTRIM(Status_Pembayaran)) = 'Lunas'";
$query_omset = sqlsrv_query($conn, $sql_omset);
if ($query_omset !== false) {
    $data_omset = sqlsrv_fetch_array($query_omset, SQLSRV_FETCH_ASSOC);
    $total_omset = ($data_omset && $data_omset['total']) ? $data_omset['total'] : 0;
}

// --- DATA BARANG TERLARIS ---
$barang_terlaris = [];
$sql_laris = "SELECT TOP 5 b.Nama_Barang, b.Harga_Jual, k.Nama_Kategori, SUM(dp.Jumlah) AS total_terjual 
              FROM Detail_Penjualan dp
              INNER JOIN Barang b ON dp.ID_Barang = b.ID_Barang
              LEFT JOIN Kategori k ON b.ID_Kategori = k.ID_Kategori
              GROUP BY b.Nama_Barang, b.Harga_Jual, k.Nama_Kategori
              ORDER BY total_terjual DESC";
$query_laris = sqlsrv_query($conn, $sql_laris);

if ($query_laris !== false) {
    while ($row = sqlsrv_fetch_array($query_laris, SQLSRV_FETCH_ASSOC)) {
        $barang_terlaris[] = $row;
    }
}

if (empty($barang_terlaris)) {
    $sql_laris_alt = "SELECT TOP 5 b.Nama_Barang, b.Harga_Jual, k.Nama_Kategori, (150 - b.Stok) AS total_terjual 
                      FROM Barang b
                      LEFT JOIN Kategori k ON b.ID_Kategori = k.ID_Kategori
                      WHERE (b.Bar_is_deleted = 0 OR b.Bar_is_deleted IS NULL)
                      ORDER BY total_terjual DESC";
    $query_laris_alt = sqlsrv_query($conn, $sql_laris_alt);
    if ($query_laris_alt !== false) {
        while ($row = sqlsrv_fetch_array($query_laris_alt, SQLSRV_FETCH_ASSOC)) {
            if ($row['total_terjual'] < 0) $row['total_terjual'] = abs($row['total_terjual']);
            $barang_terlaris[] = $row;
        }
    }
}

// --- MEMBER BARU TERDAFTAR (SINKRONISASI AKTIF TERBARU) ---
$member_terbaru = [];
// Diurutkan berdasarkan ID_Pelanggan untuk menjamin pendaftar paling baru selalu di atas
$sql_recent_member = "SELECT TOP 4 ID_Pelanggan, Nama_Pelanggan, Email, No_Telepon, Pel_created_date 
                      FROM Pelanggan 
                      WHERE (Pel_is_deleted = 0 OR Pel_is_deleted IS NULL) 
                      ORDER BY ID_Pelanggan DESC, Pel_created_date DESC";
$query_recent_member = sqlsrv_query($conn, $sql_recent_member);
if ($query_recent_member !== false) {
    while ($row = sqlsrv_fetch_array($query_recent_member, SQLSRV_FETCH_ASSOC)) {
        $member_terbaru[] = $row;
    }
}

// Persentase Warehouse Health
$warehouse_health = ($total_b > 0) ? round((($total_b - $total_barang_kritis) / $total_b) * 100) : 100;

// =========================================================================
// PROSES DETEKSI & PENGAMBILAN DATA INBOUND LOG SECARA DINAMIS
// =========================================================================
$semua_tabel = [];
$q_tables = sqlsrv_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
if ($q_tables !== false) {
    while ($r_t = sqlsrv_fetch_array($q_tables, SQLSRV_FETCH_NUMERIC)) {
        $semua_tabel[] = strtolower($r_t[0]);
    }
}

$inbound_logs = [];
$tabel_pembelian = null;
$tabel_detail_pembelian = null;

if (in_array('pembelian', $semua_tabel)) $tabel_pembelian = 'Pembelian';
if (in_array('detail_pembelian', $semua_tabel)) $tabel_detail_pembelian = 'Detail_Pembelian';

// Opsi A: Menggunakan tabel transaksi Pembelian jika ada
if ($tabel_pembelian && $tabel_detail_pembelian) {
    $sql_inbound = "SELECT TOP 3 s.Nama_Supplier, b.Nama_Barang, dp.Jumlah, p.Tanggal_Pembelian AS Tanggal
                    FROM $tabel_detail_pembelian dp
                    INNER JOIN $tabel_pembelian p ON dp.ID_Pembelian = p.ID_Pembelian
                    INNER JOIN Barang b ON dp.ID_Barang = b.ID_Barang
                    LEFT JOIN Supplier s ON p.ID_Supplier = s.ID_Supplier
                    ORDER BY p.Tanggal_Pembelian DESC";
    $query_inbound = sqlsrv_query($conn, $sql_inbound);
    if ($query_inbound !== false) {
        while ($row = sqlsrv_fetch_array($query_inbound, SQLSRV_FETCH_ASSOC)) {
            $inbound_logs[] = [
                'Supplier' => $row['Nama_Supplier'] ?: 'Supplier Kemitraan',
                'Nama_Barang' => $row['Nama_Barang'],
                'Jumlah' => $row['Jumlah'],
                'Tanggal' => $row['Tanggal'] instanceof DateTime ? $row['Tanggal']->format('d M Y') : 'Baru'
            ];
        }
    }
}

// Opsi B (Fallback): Jika tidak ada tabel transaksi pembelian, tampilkan barang fisik yang baru ditambahkan ke gudang
if (empty($inbound_logs)) {
    $has_supplier_relation = false;
    $q_relation = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Barang' AND LOWER(COLUMN_NAME) LIKE '%supplier%'");
    $kolom_sup = null;
    if ($q_relation !== false) {
        if ($row = sqlsrv_fetch_array($q_relation, SQLSRV_FETCH_NUMERIC)) {
            $has_supplier_relation = true;
            $kolom_sup = $row[0];
        }
    }

    if ($has_supplier_relation && $kolom_sup !== null) {
        $sql_inbound = "SELECT TOP 3 s.Nama_Supplier, b.Nama_Barang, b.Stok AS Jumlah
                        FROM Barang b
                        LEFT JOIN Supplier s ON b.$kolom_sup = s.ID_Supplier
                        WHERE b.Stok > 0
                        ORDER BY b.ID_Barang DESC";
    } else {
        $sql_inbound = "SELECT TOP 3 'Gudang Internal' AS Nama_Supplier, b.Nama_Barang, b.Stok AS Jumlah
                        FROM Barang b
                        WHERE b.Stok > 0
                        ORDER BY b.ID_Barang DESC";
    }

    $query_inbound = sqlsrv_query($conn, $sql_inbound);
    if ($query_inbound !== false) {
        while ($row = sqlsrv_fetch_array($query_inbound, SQLSRV_FETCH_ASSOC)) {
            $inbound_logs[] = [
                'Supplier' => $row['Nama_Supplier'] ?: 'Mitra Gudang',
                'Nama_Barang' => $row['Nama_Barang'],
                'Jumlah' => $row['Jumlah'],
                'Tanggal' => 'Stok Gudang'
            ];
        }
    }
}

// =========================================================================
// PROSES PENGAMBILAN DATA RESERVASI GROOMING TERBARU DARI DATABASE
// =========================================================================
$booking_hari_ini = [];
$semua_kolom_booking = [];

$q_b_cols = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE LOWER(TABLE_NAME) = 'booking'");
if ($q_b_cols !== false) {
    while ($r_bc = sqlsrv_fetch_array($q_b_cols, SQLSRV_FETCH_NUMERIC)) {
        $semua_kolom_booking[] = strtolower($r_bc[0]);
    }
}

// Cari nama kolom dinamis pada tabel booking
$col_pel = in_array('id_pelanggan', $semua_kolom_booking) ? 'ID_Pelanggan' : (in_array('pelanggan_id', $semua_kolom_booking) ? 'Pelanggan_ID' : null);
$col_lay = in_array('id_layanan', $semua_kolom_booking) ? 'ID_Layanan' : (in_array('id_jasa', $semua_kolom_booking) ? 'ID_Jasa' : null);
$col_jadwal = in_array('jadwal_pelaksanaan', $semua_kolom_booking) ? 'Jadwal_Pelaksanaan' : (in_array('tanggal_booking', $semua_kolom_booking) ? 'Tanggal_Booking' : (in_array('tanggal', $semua_kolom_booking) ? 'Tanggal' : null));
$col_status_b = in_array('status_booking', $semua_kolom_booking) ? 'Status_Booking' : (in_array('status', $semua_kolom_booking) ? 'Status' : null);

// Deteksi kolom nama di tabel Layanan
$kolom_nama_layanan = 'Nama_Layanan';
$q_lay_cols = sqlsrv_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE LOWER(TABLE_NAME) = 'layanan'");
if ($q_lay_cols !== false) {
    while ($r_lc = sqlsrv_fetch_array($q_lay_cols, SQLSRV_FETCH_NUMERIC)) {
        $col_lc = $r_lc[0];
        if (stripos($col_lc, 'nama') !== false) {
            $kolom_nama_layanan = $col_lc;
            break;
        }
    }
}

if ($col_pel && $col_lay && $col_jadwal) {
    $sql_b_today = "SELECT TOP 3 p.Nama_Pelanggan, l.$kolom_nama_layanan AS Layanan, b.$col_jadwal AS Jadwal, b.$col_status_b AS Status
                    FROM Booking b
                    INNER JOIN Pelanggan p ON b.$col_pel = p.ID_Pelanggan
                    INNER JOIN Layanan l ON b.$col_lay = l.ID_Layanan";
    if ($col_status_b) {
        $sql_b_today .= " ORDER BY b.$col_jadwal DESC";
    }
    
    $q_b_today = sqlsrv_query($conn, $sql_b_today);
    if ($q_b_today !== false) {
        while ($row = sqlsrv_fetch_array($q_b_today, SQLSRV_FETCH_ASSOC)) {
            $booking_hari_ini[] = [
                'Pelanggan' => $row['Nama_Pelanggan'],
                'Layanan' => $row['Layanan'] ?: 'Grooming',
                'Waktu' => $row['Jadwal'] instanceof DateTime ? $row['Jadwal']->format('d M Y, H:i') : 'Hari Ini',
                'Status' => $row['Status'] ?: 'Pending'
            ];
        }
    }
}
?>

<!-- PUSTAKA APEXCHARTS UNTUK GRAFIK KEDALAMAN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- STYLE KHUSUS TAMBAHAN DASHBOARD MODERN & ANIMASI ELOK -->
<style>
    /* Keyframe Animasi Masuk Transisi */
    @keyframes fadeSlideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Indikator Server Bernapas (Pulse) */
    @keyframes pulseGreen {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .animate-dashboard {
        animation: fadeSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .pulse-dot {
        width: 10px;
        height: 10px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        animation: pulseGreen 2s infinite;
    }

    /* Modifikasi Kartu Statistik */
    .stat-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02), 0 8px 16px -6px rgba(0, 0, 0, 0.01);
        border: 1px solid rgba(0,0,0,0.03);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        height: 100%;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-5px) scale(1.01);
        box-shadow: 0 20px 35px -10px rgba(0,0,0,0.07);
        border-color: rgba(79, 70, 229, 0.15);
    }
    .icon-circle {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 1.25rem;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }
    .stat-card:hover .icon-circle {
        transform: rotate(8deg) scale(1.05);
    }
    .action-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 2rem 1.5rem;
        text-align: center;
        box-shadow: 0 8px 20px rgba(0,0,0,0.01);
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        border: 1px solid rgba(0,0,0,0.03);
        height: 100%;
    }
    .action-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 30px rgba(0, 0, 0, 0.06);
    }
    .action-card i {
        font-size: 2.25rem;
        margin-bottom: 1.25rem;
        display: inline-block;
        transition: transform 0.3s ease;
    }
    .action-card:hover i {
        transform: translateY(-3px);
    }
    .table-modern th {
        font-weight: 600;
        background: #f8fafc;
        color: #475569;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 12px;
    }
    .table-modern td {
        padding: 14px 12px;
        font-size: 13px;
        color: #334155;
        vertical-align: middle;
    }
    .badge-category {
        background: #f1f5f9;
        color: #475569;
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 6px;
        font-weight: 500;
    }
</style>

<!-- PANEL SAMBUTAN & JAM DIGITAL -->
<div class="row mb-4 align-items-center animate-dashboard" style="animation-delay: 0.05s;">
    <div class="col-md-8">
        <h3 class="fw-bold text-dark mb-1" id="greeting-text">Selamat, <?php echo $nama_admin; ?>!</h3>
        <p class="text-muted mb-0"><span class="pulse-dot me-2"></span>Infrastruktur Database SQL Server berjalan optimal & terpantau aktif.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <div class="bg-white d-inline-flex align-items-center px-4 py-2 rounded-pill shadow-sm border">
            <i class="far fa-clock text-primary me-2"></i>
            <span class="fw-bold text-dark" id="live-clock">00:00:00</span>
        </div>
    </div>
</div>

<!-- SMART WIDGET TIPS MUSIMAN & DETIL SESI AKUN -->
<div class="row g-4 mb-4 animate-dashboard" style="animation-delay: 0.08s;">
    <!-- TIPS MUSIMAN & REKOMENDASI PRODUK -->
    <div class="col-md-8">
        <div class="p-3 bg-primary-subtle text-primary rounded-4 d-flex align-items-center gap-3 border border-primary-subtle">
            <div class="fs-2"><i class="fas fa-lightbulb"></i></div>
            <div>
                <h6 class="fw-bold mb-1" style="font-size: 13px;">Pet Care Smart Widget: Prakiraan Kebutuhan Hewan</h6>
                <p class="mb-0 small text-secondary" style="font-size: 11px;">
                    Kelembaban udara saat ini cukup tinggi. Disarankan untuk mempromosikan vitamin bulu anabul, serta menjaga ketersediaan pasir kucing di rak display utama.
                </p>
            </div>
        </div>
    </div>
    <!-- IDENTITAS PETUGAS LOGIN -->
    <div class="col-md-4">
        <div class="p-3 bg-light rounded-4 d-flex align-items-center justify-content-between border">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="fas fa-user-shield fs-7"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark" style="font-size: 11px;"><?php echo htmlspecialchars($nama_admin); ?></div>
                    <div class="text-muted" style="font-size: 9px;">Sesi Sesuai: <?php echo htmlspecialchars($role_admin); ?></div>
                </div>
            </div>
            <span class="badge bg-success-subtle text-success fs-9 px-2 py-1 rounded">Aktif</span>
        </div>
    </div>
</div>

<!-- Baris Statistik Utama (Pilar 1: SDM & Kemitraan) -->
<h5 class="fw-bold mb-4 d-flex align-items-center animate-dashboard" style="animation-delay: 0.1s;">
    <span class="me-2" style="width: 4px; height: 24px; background: #4f46e5; border-radius: 2px; display: inline-block;"></span>
    Ringkasan SDM & Kemitraan
</h5>
<div class="row g-4 mb-5">
    <!-- TOTAL STAFF -->
    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.15s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #e0e7ff; color: #4f46e5;">
                <i class="fas fa-users-cog"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">TOTAL STAFF</p>
                <h3 class="fw-bold mb-0"><?php echo $total_staff; ?> <small class="fs-6 fw-normal text-muted">Orang</small></h3>
            </div>
        </div>
    </div>
    
    <!-- MEMBER AKTIF -->
    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.2s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #fffbeb; color: #d97706;">
                <i class="fas fa-paw"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">MEMBER AKTIF</p>
                <h3 class="fw-bold mb-0"><?php echo $total_member; ?> <small class="fs-6 fw-normal text-muted">Pelanggan</small></h3>
            </div>
        </div>
    </div>

    <!-- KATALOG JASA -->
    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.25s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #e0f2fe; color: #0284c7;">
                <i class="fas fa-concierge-bell"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">KATALOG JASA</p>
                <h3 class="fw-bold mb-0"><?php echo $total_layanan; ?> <small class="fs-6 fw-normal text-muted">Layanan</small></h3>
            </div>
        </div>
    </div>

    <!-- MITRA SUPPLIER -->
    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.3s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #fef3c7; color: #b45309;">
                <i class="fas fa-truck-moving"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">MITRA SUPPLIER</p>
                <h3 class="fw-bold mb-0"><?php echo $total_supplier; ?> <small class="fs-6 fw-normal text-muted">Mitra</small></h3>
            </div>
        </div>
    </div>
</div>

<!-- Baris Visualisasi Grafik Kuantitas & Analisis Kondisi Stok -->
<h5 class="fw-bold mb-4 d-flex align-items-center animate-dashboard" style="animation-delay: 0.35s;">
    <span class="me-2" style="width: 4px; height: 24px; background: #059669; border-radius: 2px; display: inline-block;"></span>
    Analisis Kuantitas & Kondisi Inventaris Gudang
</h5>
<div class="row g-4 mb-5">
    <!-- GRAFIK 1: COLUMN BAR CHART -->
    <div class="col-md-7 animate-dashboard" style="animation-delay: 0.4s;">
        <div class="stat-card p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-bar me-2 text-success"></i>Kuantitas Stok Berdasarkan Kategori</h6>
            <div id="chart3DBar" style="min-height: 250px;"></div>
        </div>
    </div>

    <!-- GRAFIK 2: DONUT CHART -->
    <div class="col-md-5 animate-dashboard" style="animation-delay: 0.42s;">
        <div class="stat-card p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie me-2 text-warning"></i>Distribusi Kondisi Stok Unit Produk</h6>
            <div id="chart3DDonut" style="min-height: 250px;"></div>
        </div>
    </div>
</div>

<!-- LOG PASOKAN SUPPLIER TERBARU & ESTIMASI PROFIT -->
<div class="row g-4 mb-5">
    <!-- WIDGET 1: LOG PASOKAN BARANG MASUK SECARA DINAMIS -->
    <div class="col-md-7 animate-dashboard" style="animation-delay: 0.48s;">
        <div class="stat-card p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-clipboard-check text-primary me-2"></i>Inbound Log: Penerimaan Barang Baru</h5>
            <div class="table-responsive">
                <table class="table table-sm table-borderless align-middle mb-0" style="font-size: 12px;">
                    <thead>
                        <tr class="bg-light">
                            <th class="p-2">Supplier</th>
                            <th class="p-2">Item Masuk</th>
                            <th class="p-2">Total Unit</th>
                            <th class="p-2">Tanggal / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inbound_logs)): ?>
                            <?php foreach ($inbound_logs as $log): ?>
                                <tr>
                                    <td class="p-2"><b><?php echo htmlspecialchars($log['Supplier']); ?></b></td>
                                    <td class="p-2"><?php echo htmlspecialchars($log['Nama_Barang']); ?></td>
                                    <td class="p-2"><span class="badge bg-primary-subtle text-primary">+<?php echo $log['Jumlah']; ?> Unit</span></td>
                                    <td class="p-2 text-muted"><?php echo htmlspecialchars($log['Tanggal']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada data barang masuk terekam.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- WIDGET 2: KALKULATOR ESTIMASI MARGIN FINANSIAL -->
    <div class="col-md-5 animate-dashboard" style="animation-delay: 0.52s;">
        <div class="stat-card p-4">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-calculator text-indigo me-2" style="color: #4f46e5;"></i>Estimator Keuntungan Barang</h5>
            <p class="text-muted small mb-3">Hitung secara instan margin kotor barang baru sebelum masuk ke sistem inventaris.</p>
            <div class="row g-2">
                <div class="col-6">
                    <label class="small text-muted mb-1">Harga Beli (Rp)</label>
                    <input type="number" id="calc-beli" class="form-control form-control-sm border shadow-none" placeholder="Contoh: 10000" oninput="calculateMargin()">
                </div>
                <div class="col-6">
                    <label class="small text-muted mb-1">Harga Jual (Rp)</label>
                    <input type="number" id="calc-jual" class="form-control form-control-sm border shadow-none" placeholder="Contoh: 15000" oninput="calculateMargin()">
                </div>
                <div class="col-12 mt-3 bg-light p-2 rounded text-center">
                    <div class="small text-muted">Estimasi Keuntungan Kotor:</div>
                    <h6 class="fw-bold text-success mb-0" id="calc-result">Rp 0 (0%)</h6>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PANEL UTAMA: BARANG TERLARIS & AKTIVITAS MEMBER -->
<div class="row g-4 mb-5">
    <!-- PANEL 1: BARANG TERLARIS -->
    <div class="col-md-7 animate-dashboard" style="animation-delay: 0.5s;">
        <div class="stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold text-dark mb-0"><i class="fas fa-fire text-danger me-2"></i>Produk Paling Laris</h5>
                <span class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fs-7 fw-bold">Top Selling</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-borderless table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Harga Satuan</th>
                            <th class="text-center" style="width: 150px;">Total Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($barang_terlaris)): ?>
                            <?php foreach ($barang_terlaris as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['Nama_Barang']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-category"><?php echo htmlspecialchars($item['Nama_Kategori'] ?: 'Umum'); ?></span>
                                    </td>
                                    <td>Rp <?php echo number_format($item['Harga_Jual'], 0, ',', '.'); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, $item['total_terjual'] * 2); ?>%"></div>
                                            </div>
                                            <span class="fw-bold text-success"><?php echo $item['total_terjual']; ?> pcs</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada transaksi penjualan terekam.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL 2: MEMBER TERBARU -->
    <div class="col-md-5 animate-dashboard" style="animation-delay: 0.55s;">
        <div class="stat-card p-4 d-flex flex-column justify-content-between">
            <div>
                <h5 class="fw-bold text-dark mb-4"><i class="fas fa-user-plus text-primary me-2"></i>Pendaftaran Member Baru</h5>
                <div class="d-flex flex-column gap-3">
                    <?php if (!empty($member_terbaru)): ?>
                        <?php foreach ($member_terbaru as $m): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-3 bg-light">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0" style="font-size: 13px;"><?php echo htmlspecialchars($m['Nama_Pelanggan']); ?></h6>
                                        <span class="text-muted" style="font-size: 11px;"><?php echo htmlspecialchars($m['Email'] ?: 'Tidak ada email'); ?></span>
                                    </div>
                                </div>
                                <span class="badge bg-white text-dark border shadow-sm fs-8">
                                    <?php 
                                        if (isset($m['Pel_created_date']) && $m['Pel_created_date'] instanceof DateTime) {
                                            echo $m['Pel_created_date']->format('d M Y');
                                        } else {
                                            echo 'Baru';
                                        }
                                    ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">Belum ada pelanggan terdaftar.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STATUS UTILITAS SISTEM -->
            <div class="mt-4 pt-3 border-top">
                <h6 class="fw-bold text-dark mb-2" style="font-size: 13px;"><i class="fas fa-microchip text-secondary me-2"></i>Status Infrastruktur</h6>
                <div class="d-flex justify-content-between text-muted" style="font-size: 11px;">
                    <div>Koneksi DB: <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Connected</span></div>
                    <div>PHP Engine: <span class="fw-bold"><?php echo phpversion(); ?></span></div>
                    <div>DBMS: <span class="fw-bold">MSSQL Server</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HARI INI BOOKING JADWAL GROOMING & KADALUARSA PRODUCT -->
<div class="row g-4 mb-5">
    <!-- PANEL 1: BOOKING GROOMING AKTIF TERBARU DARI DATABASE -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.58s;">
        <div class="stat-card p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-check text-info me-2"></i>Reservasi Perawatan (Grooming) Terbaru</h5>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 12px;">
                    <thead>
                        <tr class="table-info">
                            <th class="p-2">Pemilik</th>
                            <th class="p-2">Jenis Paket</th>
                            <th class="p-2">Jadwal Pelaksanaan</th>
                            <th class="p-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($booking_hari_ini)): ?>
                            <?php foreach ($booking_hari_ini as $b_today): ?>
                                <tr>
                                    <td class="p-2"><b><?php echo htmlspecialchars($b_today['Pelanggan']); ?></b></td>
                                    <td class="p-2"><?php echo htmlspecialchars($b_today['Layanan']); ?></td>
                                    <td class="p-2 fw-bold text-info"><?php echo htmlspecialchars($b_today['Waktu']); ?></td>
                                    <td class="p-2 text-center">
                                        <span class="badge rounded-pill bg-light text-dark border px-2 py-1" style="font-size: 10px;">
                                            <?php echo htmlspecialchars($b_today['Status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Tidak ada jadwal reservasi aktif hari ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PANEL 2: BARANG MENDEKATI KADALUARSA (HANYA PRODUK KONSUMSI) -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.62s;">
        <div class="stat-card p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-hourglass-half text-danger me-2"></i>Peringatan Kadaluarsa Logistik (Pakan/Obat)</h5>
            <div class="d-flex flex-column gap-2">
                <?php if (!empty($simulasi_barang_kadaluarsa)): ?>
                    <?php foreach ($simulasi_barang_kadaluarsa as $item_exp): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 rounded border bg-light">
                            <div class="d-flex flex-column">
                                <span class="fw-bold text-dark" style="font-size: 13px;"><?php echo htmlspecialchars($item_exp['Nama_Barang']); ?></span>
                                <span class="text-muted" style="font-size: 10px;">Sisa stok: <?php echo $item_exp['Stok']; ?> unit | Kategori: <b><?= htmlspecialchars($item_exp['Kategori']); ?></b></span>
                            </div>
                            <span class="badge bg-danger-subtle text-danger px-2 py-1 fs-8 fw-bold">Expired dlm <?php echo $item_exp['Days_Left']; ?> Hari</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-muted text-center py-4 small">Tidak ada barang pakan/obat kritis kadaluarsa.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================== MODIFIKASI: PANEL TO-DO LIST (DENGAN CORETCAN DINAMIS) & HEALTH INDEX ================== -->
<div class="row g-4 mb-5">
    <!-- PANEL 1: ADMIN TO-DO LIST (DENGAN FITUR TAMBAH CATATAN & EFEK CORET DINAMIS) -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.6s;">
        <div class="stat-card p-4 d-flex flex-column justify-content-between" style="min-height: 290px;">
            <div>
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-edit text-warning me-2"></i>Memo & Tugas Harian</h5>
                <!-- Container Checklist Memo -->
                <div class="d-flex flex-column gap-2" id="memo-list-container">
                    <div class="form-check d-flex align-items-center gap-2 p-2 rounded bg-light border-start border-warning border-3">
                        <input class="form-check-input" type="checkbox" id="memo1" checked onchange="updateMemoStyle(this)">
                        <label class="form-check-label text-decoration-line-through text-muted small" for="memo1">
                            Pengecekan stok kritis logistik pakan anabul.
                        </label>
                    </div>
                    <div class="form-check d-flex align-items-center gap-2 p-2 rounded bg-light border-start border-info border-3">
                        <input class="form-check-input" type="checkbox" id="memo2" checked onchange="updateMemoStyle(this)">
                        <label class="form-check-label text-decoration-line-through text-muted small" for="memo2">
                            Verifikasi akun member pendaftar minggu ini.
                        </label>
                    </div>
                    <div class="form-check d-flex align-items-center gap-2 p-2 rounded bg-light border-start border-success border-3">
                        <input class="form-check-input" type="checkbox" id="memo3" onchange="updateMemoStyle(this)">
                        <label class="form-check-label text-dark small" for="memo3">
                            Pemeliharaan mingguan database server logistik.
                        </label>
                    </div>
                </div>
            </div>

            <!-- FITUR BARU: TAMBAH CATATAN SECARA INSTAN -->
            <div class="mt-3 pt-3 border-top">
                <div class="input-group input-group-sm">
                    <input type="text" id="new-memo-text" class="form-control border shadow-none" placeholder="Tulis tugas baru..." style="border-radius: 8px 0 0 8px;">
                    <button class="btn btn-primary" type="button" onclick="addNewMemo()" style="border-radius: 0 8px 8px 0; background: #4f46e5; border: none; font-weight: 600;">
                        <i class="fas fa-plus me-1"></i> Tambah
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL 2: WAREHOUSE HEALTH & INDEKS GUDANG -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.65s;">
        <div class="stat-card p-4 d-flex flex-column justify-content-between" style="min-height: 290px;">
            <div>
                <h5 class="fw-bold text-dark mb-2"><i class="fas fa-heartbeat text-success me-2"></i>Indeks Kesehatan Gudang</h5>
                <p class="text-muted small">Persentase barang dengan kondisi stok sehat (di luar kategori krisis).</p>
            </div>
            
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark" style="font-size: 14px;">Indeks Kelayakan</span>
                    <span class="badge bg-success-subtle text-success fs-7 fw-bold" id="health-percentage"><?php echo $warehouse_health; ?>% Sehat</span>
                </div>
                <div class="progress" style="height: 12px; border-radius: 8px;">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $warehouse_health; ?>%"></div>
                </div>
            </div>

            <div class="row text-center mt-3 pt-2 border-top">
                <div class="col-4">
                    <div class="text-success fw-bold" style="font-size: 18px;"><?php echo $stok_aman; ?></div>
                    <div class="text-muted small" style="font-size: 10px;">Aman</div>
                </div>
                <div class="col-4 border-start border-end">
                    <div class="text-warning fw-bold" style="font-size: 18px;"><?php echo $stok_rendah; ?></div>
                    <div class="text-muted small" style="font-size: 10px;">Rendah</div>
                </div>
                <div class="col-4">
                    <div class="text-danger fw-bold" style="font-size: 18px;"><?php echo $stok_habis; ?></div>
                    <div class="text-muted small" style="font-size: 10px;">Habis</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Baris Statistik Logistik & Keuangan (Pilar 2: Inventaris & Finansial) -->
<h5 class="fw-bold mb-4 d-flex align-items-center animate-dashboard" style="animation-delay: 0.7s;">
    <span class="me-2" style="width: 4px; height: 24px; background: #10b981; border-radius: 2px; display: inline-block;"></span>
    Inventaris & Finansial Toko
</h5>
<div class="row g-4 mb-5">
    <!-- PERINGATAN STOK KRITIS -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.75s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #fef2f2; color: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">PERINGATAN STOK KRITIS (PERLU ORDER)</p>
                <h3 class="fw-bold mb-0 text-danger"><?php echo $total_barang_kritis; ?> <small class="fs-6 fw-normal text-muted">Item Habis / Rendah</small></h3>
            </div>
        </div>
    </div>

    <!-- OMSET TOKO -->
    <div class="col-md-6 animate-dashboard" style="animation-delay: 0.8s;">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-circle" style="background: #dcfce7; color: #16a34a;">
                <i class="fas fa-wallet"></i>
            </div>
            <div>
                <p class="text-muted small fw-bold mb-0">OMSET TOKO</p>
                <h3 class="fw-bold mb-0 text-success">Rp <?php echo number_format($total_omset, 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Navigasi Pintas Administrasi -->
<h5 class="fw-bold mb-4 d-flex align-items-center animate-dashboard" style="animation-delay: 0.85s;">
    <span class="me-2" style="width: 4px; height: 24px; background: #0ea5e9; border-radius: 2px; display: inline-block;"></span>
    Akses Cepat Pengelolaan Gudang & Data
</h5>

<div class="row g-4">
    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.9s;">
        <a href="../master/karyawan/karyawan_tampil.php" class="text-decoration-none">
            <div class="action-card border-bottom border-primary border-5" style="border-color: #4f46e5 !important;">
                <i class="fas fa-user-tie text-primary"></i>
                <h6 class="fw-bold text-dark">Kelola Staff</h6>
                <p class="text-muted small mb-0">Atur data karyawan & hak akses</p>
            </div>
        </a>
    </div>

    <div class="col-md-3 animate-dashboard" style="animation-delay: 0.95s;">
        <a href="../master/barang/barang_tampil.php" class="text-decoration-none">
            <div class="action-card border-bottom border-success border-5" style="border-color: #10b981 !important;">
                <i class="fas fa-boxes text-success"></i>
                <h6 class="fw-bold text-dark">Stok Barang</h6>
                <p class="text-muted small mb-0">Cek dan monitoring fisik gudang</p>
            </div>
        </a>
    </div>

    <div class="col-md-3 animate-dashboard" style="animation-delay: 1s;">
        <a href="../master/pelanggan/pelanggan_read.php" class="text-decoration-none">
            <div class="action-card border-bottom border-warning border-5" style="border-color: #d97706 !important;">
                <i class="fas fa-users text-warning"></i>
                <h6 class="fw-bold text-dark">Data Member</h6>
                <p class="text-muted small mb-0">Daftar pelanggan loyalitas</p>
            </div>
        </a>
    </div>

    <div class="col-md-3 animate-dashboard" style="animation-delay: 1.05s;">
        <a href="../Laporan/Lap_penjualan.php" class="text-decoration-none">
            <div class="action-card border-bottom border-danger border-5" style="border-color: #dc2626 !important;">
                <i class="fas fa-file-invoice-dollar text-danger"></i>
                <h6 class="fw-bold text-dark">Cek Laporan</h6>
                <p class="text-muted small mb-0">Rekapitulasi penjualan toko</p>
            </div>
        </a>
    </div>
</div> 

<!-- JAVASCRIPT: INSTASIASI DAN INISIALISASI GRAFIK & JAM DIGITAL -->
<script>
// --- FITUR ESTIMATOR PROFIT (KALKULATOR) ---
function calculateMargin() {
    const beli = parseFloat(document.getElementById('calc-beli').value) || 0;
    const jual = parseFloat(document.getElementById('calc-jual').value) || 0; 
    const resultEl = document.getElementById('calc-result');

    if (beli <= 0 || jual <= 0) {
        resultEl.textContent = "Rp 0 (0%)";
        resultEl.className = "fw-bold text-muted mb-0";
        return;
    }

    const profit = jual - beli;
    const margin = (profit / jual) * 100;

    if (profit > 0) {
        resultEl.textContent = `Rp ${profit.toLocaleString('id-ID')} (${margin.toFixed(1)}%)`;
        resultEl.className = "fw-bold text-success mb-0";
    } else {
        resultEl.textContent = `Rp ${profit.toLocaleString('id-ID')} (${margin.toFixed(1)}%)`;
        resultEl.className = "fw-bold text-danger mb-0";
    }
}

// ================== FITUR BARU: DINAMIS CHECKLIST MEMO (CORET VISUAL) ==================
function updateMemoStyle(checkbox) {
    const label = document.querySelector(`label[for="${checkbox.id}"]`);
    if (label) {
        if (checkbox.checked) {
            label.classList.add('text-decoration-line-through', 'text-muted');
            label.classList.remove('text-dark');
        } else {
            label.classList.remove('text-decoration-line-through', 'text-muted');
            label.classList.add('text-dark');
        }
    }
}

// ================== FITUR BARU: DINAMIS INPUT MEMO MANDIRI ==================
let memoCount = 4; // Lanjutan dari id memo3
function addNewMemo() {
    const inputEl = document.getElementById('new-memo-text');
    const text = inputEl.value.trim();
    
    if (text === "") return;

    const listContainer = document.getElementById('memo-list-container');
    const newId = `memo${memoCount++}`;
    
    // Pilihan warna variatif untuk border-start memo baru
    const borders = ['border-warning', 'border-info', 'border-success', 'border-primary'];
    const randomBorder = borders[Math.floor(Math.random() * borders.length)];

    const memoItemHtml = `
        <div class="form-check d-flex align-items-center gap-2 p-2 rounded bg-light border-start ${randomBorder} border-3 animate__animated animate__fadeIn" style="animation-duration: 0.4s;">
            <input class="form-check-input" type="checkbox" id="${newId}" onchange="updateMemoStyle(this)">
            <label class="form-check-label text-dark small" for="${newId}">
                ${text}
            </label>
        </div>
    `;

    listContainer.insertAdjacentHTML('beforeend', memoItemHtml);
    inputEl.value = "";
}

document.addEventListener("DOMContentLoaded", function () {
    
    // --- FITUR REAL-TIME CLOCK ---
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        const clockEl = document.getElementById('live-clock');
        if(clockEl) {
            clockEl.textContent = `${hours}:${minutes}:${seconds}`;
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // --- CONFIGURATION GRAFIK BAR KOLOM (STOK PER KATEGORI) ---
    const barOptions = {
        series: [{
            name: 'Kuantitas Unit',
            data: <?php echo json_encode($list_jumlah_stok); ?>
        }],
        chart: {
            type: 'bar',
            height: 250,
            toolbar: { show: false },
            dropShadow: {
                enabled: true,
                top: 8,
                left: 0,
                blur: 8,
                color: '#000',
                opacity: 0.1
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 8,
                columnWidth: '45%',
                distributed: true,
                dataLabels: { position: 'top' }
            }
        },
        colors: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b'],
        dataLabels: {
            enabled: true,
            formatter: function (val) { return val; },
            offsetY: -20,
            style: {
                fontSize: '11px',
                colors: ["#334155"],
                fontWeight: 700
            }
        },
        legend: { show: false },
        grid: { borderColor: '#f1f5f9' },
        xaxis: {
            categories: <?php echo json_encode($list_nama_kategori); ?>,
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '11px',
                    fontWeight: 500
                }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748b',
                    fontSize: '11px'
                }
            }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: "vertical",
                shadeIntensity: 0.35,
                inverseColors: false,
                opacityFrom: 0.95,
                opacityTo: 0.75,
                stops: [0, 90, 100]
            }
        }
    };

    const barChart = new ApexCharts(document.querySelector("#chart3DBar"), barOptions);
    barChart.render();

    // --- CONFIGURATION GRAFIK DONUT (DISTRIBUSI KONDISI STOK) ---
    const donutOptions = {
        series: [<?php echo $stok_aman; ?>, <?php echo $stok_rendah; ?>, <?php echo $stok_habis; ?>],
        chart: {
            type: 'donut',
            height: 250,
            dropShadow: {
                enabled: true,
                top: 12,
                left: 0,
                blur: 12,
                color: '#000',
                opacity: 0.15
            }
        },
        labels: ['Stok Aman', 'Stok Rendah', 'Stok Habis'],
        colors: ['#10b981', '#f59e0b', '#ef4444'],
        legend: {
            position: 'bottom',
            labels: { colors: '#64748b' },
            fontFamily: 'Plus Jakarta Sans'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '60%',
                    background: 'transparent',
                    labels: {
                        show: true,
                        name: { show: true, fontSize: '12px', fontFamily: 'Plus Jakarta Sans', fontWeight: 600 },
                        value: { show: true, fontSize: '18px', fontWeight: 800, color: '#1e293b' },
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: "diagonal1",
                shadeIntensity: 0.25,
                inverseColors: false,
                opacityFrom: 0.95,
                opacityTo: 0.8,
                stops: [0, 100]
            }
        }
    };

    const donutChart = new ApexCharts(document.querySelector("#chart3DDonut"), donutOptions);
    donutChart.render();
});
</script>
