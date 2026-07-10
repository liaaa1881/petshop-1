

<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/koneksi.php';

// Proteksi Akses
if (!isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi Anda telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal terhubung.'
    ]);
    exit;
}

// Fungsi pembantu cetak error SQL Server
if (!function_exists('formatErrors')) {
    function formatErrors($errors) {
        if ($errors === null) return '';
        $output = "";
        foreach ($errors as $error) {
            $output .= "SQLSTATE: ".$error['SQLSTATE']."\nCode: ".$error['code']."\nMessage: ".$error['message']."\n\n";
        }
        return trim($output);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id_pelanggan    = $_POST['id_pelanggan'] ?? '';
    $id_layanan      = $_POST['id_layanan'] ?? '';
    $id_karyawan     = $_POST['id_karyawan'] ?? '';
    
    // Format jadwal datetime-local agar kompatibel penuh dengan SQL Server
    $jadwal_booking  = !empty($_POST['jadwal_booking']) ? str_replace('T', ' ', $_POST['jadwal_booking']) . ':00' : '';
    
    // Konversi angka agar aman bagi tipe data DECIMAL SQL Server
    $harga_layanan   = isset($_POST['harga_layanan']) ? floatval($_POST['harga_layanan']) : 0.0;
    $diskon_percent  = !empty($_POST['diskon_booking']) ? intval($_POST['diskon_booking']) : 0;
    
    // Validasi Catatan Transaksi / Kondisi Hewan (Wajib diisi & Minimal 20 Karakter)
    $catatan_booking = !empty($_POST['catatan_booking']) ? trim($_POST['catatan_booking']) : '';
    if (strlen($catatan_booking) < 20) {
        echo json_encode([
            'success' => false,
            'message' => 'Catatan kondisi hewan wajib diisi dengan minimal 20 karakter.'
        ]);
        exit;
    }

    // Kalkulasi tarif bersih
    $diskon_booking  = round($harga_layanan * ($diskon_percent / 100));
    $total_tarif     = max(0.0, $harga_layanan - $diskon_booking);
    
    $status_booking  = 'Pending';
    $book_status     = 'Aktif';
    $created_by      = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Kasir';

    // 1. Validasi Sisi Server
    if (!empty($jadwal_booking)) {
        $waktu_pilihan  = strtotime($jadwal_booking);
        $waktu_sekarang = time();
        
        // Toleransi waktu 5 menit (300 detik) untuk perbedaan clock server/client
        if ($waktu_pilihan < ($waktu_sekarang - 300)) {
            echo json_encode([
                'success' => false,
                'message' => 'Rencana jadwal reservasi tidak boleh sebelum tanggal atau waktu saat ini!'
            ]);
            exit;
        }
    }
    
    if (empty($id_pelanggan) || empty($id_layanan) || empty($id_karyawan) || empty($jadwal_booking)) {
        echo json_encode([
            'success' => false,
            'message' => 'Semua kolom bertanda bintang (*) wajib diisi!'
        ]);
        exit;
    }

    if ($diskon_percent < 0 || $diskon_percent > 100) {
        echo json_encode([
            'success' => false,
            'message' => 'Persentase diskon tidak valid! Harus berada di antara rentang 0% hingga 100%.'
        ]);
        exit;
    }

    // Mulai Transaksi SQL Server
    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memulai transaksi database.',
            'error_details' => formatErrors(sqlsrv_errors())
        ]);
        exit;
    }

    try {
        // 2. Validasi Jadwal Bentrok Pelanggan (Sisi PHP)
        $sql_check_pelanggan = "SELECT COUNT(*) AS pelanggan_clash 
                                FROM Booking 
                                WHERE ID_Pelanggan = ? 
                                  AND Jadwal_Booking = ? 
                                  AND Status_Booking IN ('Pending', 'Diproses')";
                        
        $params_check = array((int)$id_pelanggan, $jadwal_booking);
        $check_stmt = sqlsrv_query($conn, $sql_check_pelanggan, $params_check);
        
        if ($check_stmt === false) {
            throw new Exception("Gagal memeriksa ketersediaan jadwal pelanggan.");
        }
        
        $row_check = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);

        if ($row_check['pelanggan_clash'] > 0) {
            sqlsrv_rollback($conn);
            echo json_encode([
                'success' => false,
                'message' => 'Pelanggan yang bersangkutan sudah memiliki reservasi aktif di waktu tersebut!'
            ]);
            exit;
        }

        // 3. Generator Kode Booking Otomatis (BK-YYYYMM-XXXX)
        $year_month = date('Ym'); 
        $sql_code = "SELECT COUNT(*) as total FROM Booking WHERE Kode_Booking LIKE ?";
        $query_code = sqlsrv_query($conn, $sql_code, array("BK-$year_month-%"));
        
        if ($query_code === false) {
            throw new Exception("Gagal menghasilkan nomor urut kode booking.");
        }
        
        $row_code = sqlsrv_fetch_array($query_code, SQLSRV_FETCH_ASSOC);
        $next_num = str_pad(($row_code['total'] + 1), 4, '0', STR_PAD_LEFT);
        $kode_booking = "BK-" . $year_month . "-" . $next_num;

        // 4. Proses Insert ke Database
        $sql_insert = "INSERT INTO Booking (
                            Kode_Booking, ID_Pelanggan, ID_Layanan, ID_Karyawan, Tanggal_Booking, Jadwal_Booking, 
                            Harga_Layanan, Diskon_Booking, Total_Tarif, Catatan_Booking, Status_Booking,
                            Book_status, Book_created_by, Book_created_date
                        ) VALUES (?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
                        
        $params_insert = array(
            $kode_booking, 
            (int)$id_pelanggan, 
            (int)$id_layanan, 
            (int)$id_karyawan, 
            $jadwal_booking,
            (float)$harga_layanan, 
            (float)$diskon_booking, 
            (float)$total_tarif, 
            $catatan_booking, 
            $status_booking,
            $book_status, 
            $created_by
        );
        
        $stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

        if ($stmt_insert === false) {
            $errors = sqlsrv_errors();
            $custom_error_message = "Gagal menyimpan data reservasi booking baru ke database.";
            
            if ($errors !== null) {
                foreach ($errors as $error) {
                    if (strpos($error['message'], 'Jadwal karyawan bentrok') !== false) {
                        $custom_error_message = "Petugas terapis sudah memiliki jadwal treatment aktif di rentang waktu tersebut (toleransi selisih minimal 2 jam).";
                        break;
                    }
                }
            }
            throw new Exception($custom_error_message);
        }

        sqlsrv_commit($conn);
        echo json_encode([
            'success' => true,
            'message' => "Reservasi Booking baru sukses terdaftar dengan Kode: $kode_booking"
        ]);
        exit;

    } catch (Exception $e) {
        @sqlsrv_rollback($conn);
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error_details' => formatErrors(sqlsrv_errors())
        ]);
        exit;
    }
}

$list_pelanggan = [];
$list_layanan = [];
$list_karyawan = [];

if ($conn) {
    // 1. Ambil Pelanggan yang aktif & tidak dihapus saja
    $query_p = sqlsrv_query($conn, "SELECT ID_Pelanggan, Nama_Pelanggan, No_Telepon, Status_Member 
                                    FROM Pelanggan 
                                    WHERE Pel_is_deleted = 0 AND Pel_status = 'Aktif' 
                                    ORDER BY Nama_Pelanggan ASC");
    if ($query_p !== false) {
        while ($row = sqlsrv_fetch_array($query_p, SQLSRV_FETCH_ASSOC)) {
            $list_pelanggan[] = $row;
        }
    }

    // 2. Ambil Layanan Jasa Grooming aktif
    $query_l = sqlsrv_query($conn, "SELECT ID_Layanan, Nama_Layanan, Harga_Layanan 
                                    FROM Layanan 
                                    WHERE Lay_is_deleted = 0 AND Lay_status = 'Aktif' 
                                    ORDER BY Nama_Layanan ASC");
    if ($query_l !== false) {
        while ($row = sqlsrv_fetch_array($query_l, SQLSRV_FETCH_ASSOC)) {
            $list_layanan[] = $row;
        }
    }

    // 3. Ambil Karyawan/Petugas yang aktif
    $query_k = sqlsrv_query($conn, "SELECT ID_Karyawan, Nama_Karyawan 
                                    FROM Karyawan 
                                    WHERE Kar_status = 'Aktif' AND Kar_is_deleted = 0 
                                    ORDER BY Nama_Karyawan ASC");
    if ($query_k !== false) {
        while ($row = sqlsrv_fetch_array($query_k, SQLSRV_FETCH_ASSOC)) {
            $list_karyawan[] = $row;
        }
    }
}
?>

<!-- Dependencies: Select2 & Bootstrap 5 Select2 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- STYLE KHUSUS MODAL TAMBAH BOOKING DENGAN ANIMASI PREMIUM -->
<style>
    :root { 
        --primary-gradient-booking: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #1d4ed8 100%);
        --accent-color-booking: #3b82f6; 
        --border-color-booking: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahBooking {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalTambahBooking {
            padding-left: 260px !important; 
        }
    }

    @keyframes modalZoomInBooking {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahBooking.show .modal-content-custom {
        animation: modalZoomInBooking 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: white; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        overflow: hidden; 
    }

    .header-bg-booking { 
        background: var(--primary-gradient-booking); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-booking i {
        animation: pulseBooking 2.5s infinite;
    }

    @keyframes pulseBooking {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahBooking .modal-dialog {
        max-width: 800px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-booking { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-booking); 
        border-bottom: 2px solid #e2e8f0; 
        padding-bottom: 0.6rem; 
        margin-bottom: 1.5rem; 
        letter-spacing: 1.5px; 
    }

    .form-label { 
        font-weight: 650; 
        color: #334155; 
        font-size: 0.85rem; 
        margin-bottom: 0.4rem; 
    }

    .text-danger-marker {
        color: var(--text-danger);
        font-weight: bold;
        margin-left: 2px;
    }

    .form-control, .form-select { 
        padding: 0.75rem 1rem; 
        border-radius: 0.75rem; 
        border: 1.5px solid var(--border-color-booking); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-booking);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        outline: none;
    }

    /* Penyesuaian Tampilan Dropdown Select2 agar Sesuai Tema */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1.5px solid var(--border-color-booking) !important;
        background-color: #f8fafc !important;
        border-radius: 0.75rem !important;
        min-height: 46px !important;
        display: flex;
        align-items: center;
        transition: all 0.25s ease-in-out;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #0f172a !important;
        font-size: 0.9rem !important;
        padding-left: 1rem !important;
    }
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: var(--accent-color-booking) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15) !important;
    }

    /* Styling Kustom Hasil Pencarian Dropdown Select2 */
    .select2-dropdown {
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        overflow: hidden;
        z-index: 9999 !important;
    }

    /* Ikon Kaca Pembesar Google-style di Input Pencarian Select2 */
    .select2-search--dropdown {
        padding: 8px 12px !important;
        position: relative;
    }
    .select2-search--dropdown .select2-search__field {
        padding: 8px 12px 8px 35px !important; 
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 0.5rem !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: 10px center !important;
        background-size: 16px 16px !important;
        font-size: 0.85rem !important;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent-color-booking) !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .select2-results__option {
        padding: 8px 12px !important;
        font-size: 0.9rem !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-booking) !important;
        color: white !important;
    }

    .btn-simpan { 
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .icon-box { 
        width: 32px; 
        height: 32px; 
        background: #eff6ff; 
        color: var(--accent-color-booking); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 8px; 
        margin-right: 12px; 
        font-size: 0.9rem;
    }

    .booking-input-group {
        display: flex;
        align-items: center;
    }
    
    .booking-input-group .form-control {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right: none;
    }
    
    .booking-input-group-addon {
        background-color: #f1f5f9;
        border: 1.5px solid var(--border-color-booking);
        border-left: none;
        border-top-right-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        font-weight: 700;
        color: #64748b;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>

<!-- MODAL CONTAINER TAMBAH BOOKING -->
<div class="modal fade" id="modalTambahBooking" tabindex="-1" aria-labelledby="modalTambahBookingLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-booking">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-calendar-check fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Buat Jadwal Reservasi</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Registrasikan jadwal treatment hewan baru ke sistem</p>
            </div>

            <form id="formTambahBooking" novalidate>
                <div class="form-container">
                    
                    <!-- BAGIAN 1: PELANGGAN & JADWAL -->
                    <div class="section-title-booking d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-user-clock"></i></div>
                        Informasi Pelanggan & Jadwal
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Pilih Pelanggan <span class="text-danger-marker">*</span></label>
                            <select id="select_pelanggan" name="id_pelanggan" class="form-select select2-searchable" required>
                                <option value="" data-status-member="Non Member">-- Pilih Nama Pelanggan --</option>
                                <?php foreach($list_pelanggan as $p): ?>
                                    <option value="<?= $p['ID_Pelanggan'] ?>" data-status-member="<?= htmlspecialchars($p['Status_Member']) ?>">
                                        <?= htmlspecialchars($p['Nama_Pelanggan']) ?> (<?= htmlspecialchars($p['No_Telepon'] ?: '-') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rencana Jadwal Treatment <span class="text-danger-marker">*</span></label>
                            <input type="datetime-local" name="jadwal_booking" class="form-control" required>
                        </div>
                    </div>

                    <!-- BAGIAN 2: JASA LAYANAN & PETUGAS -->
                    <div class="section-title-booking d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-cut"></i></div>
                        Pilihan Treatment & Petugas Grooming
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Pilih Layanan Jasa Grooming <span class="text-danger-marker">*</span></label>
                            <select id="select_layanan" name="id_layanan" class="form-select select2-searchable" required>
                                <option value="" data-harga="0">-- Pilih Jenis Layanan --</option>
                                <?php foreach($list_layanan as $l): ?>
                                    <option value="<?= $l['ID_Layanan'] ?>" data-harga="<?= $l['Harga_Layanan'] ?>"><?= htmlspecialchars($l['Nama_Layanan']) ?> (Rp <?= number_format($l['Harga_Layanan'], 0, ',', '.') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ditugaskan Kepada Petugas / Terapis <span class="text-danger-marker">*</span></label>
                            <select name="id_karyawan" id="id_karyawan_select" class="form-select select2-searchable" required>
                                <option value="">-- Pilih Petugas Terapis --</option>
                                <?php foreach($list_karyawan as $k): ?>
                                    <option value="<?= $k['ID_Karyawan'] ?>"><?= htmlspecialchars($k['Nama_Karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- BAGIAN 3: RINCIAN BIAYA (LIVE CALCULATION & AUTO DISCOUNT) -->
                    <div class="section-title-booking d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-receipt"></i></div>
                        Rincian Biaya Treatment Jasa
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Harga Asli Jasa Layanan (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="harga_layanan" name="harga_layanan" class="form-control bg-white" value="0" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Potongan Diskon Khusus (%) <span class="text-danger-marker">*</span></label>
                            <div class="booking-input-group">
                                <input type="number" id="diskon_booking" name="diskon_booking" class="form-control text-danger fw-bold bg-light" placeholder="0" min="0" max="100" value="0" readonly required>
                                <span class="booking-input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Tagihan Bersih (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="total_tarif" name="total_tarif" class="form-control fw-bold text-success fs-5 bg-white" value="0" readonly>
                        </div>
                    </div>

                    <!-- BAGIAN 4: CATATAN KONDISI HEWAN -->
                    <div class="section-title-booking d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-info-circle"></i></div>
                        Catatan Keluhan / Kondisi Hewan <span class="text-danger-marker">*</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <textarea name="catatan_booking" id="catatan_booking" class="form-control" rows="3" minlength="20" placeholder="Contoh: Takut air dingin, alergi shampoo wangi mawar, kulit sensitif berjamur (Wajib diisi, minimal 20 karakter)..." required></textarea>
                            <small class="text-muted d-block mt-1" id="catatan_counter">Karakter saat ini: 0/20 (Minimal 20 Karakter)</small>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" id="btn_simpan_booking" class="btn btn-simpan">
                            <i class="fas fa-save me-2"></i>Jadwalkan Reservasi
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts Dependencies: jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- JAVASCRIPT: PROSES LIVE CALCULATION, SINKRONISASI DISKON MEMBER, DAN SUBMIT FETCH AJAX -->
<script>
$(document).ready(function() {
    // Inisialisasi Select2 pada Modal Booking
    function initSelect2Booking() {
        $('.select2-searchable').each(function() {
            $(this).select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalTambahBooking'),
                width: '100%'
            });
        });
    }

    // Re-inisialisasi Select2 saat modal ditampilkan
    $('#modalTambahBooking').on('shown.bs.modal', function() {
        initSelect2Booking();
    });

    const selectPelanggan = document.getElementById('select_pelanggan');
    const selectLayanan   = document.getElementById('select_layanan');
    const inputHarga      = document.getElementById('harga_layanan');
    const inputDiskon     = document.getElementById('diskon_booking');
    const inputTotal      = document.getElementById('total_tarif');
    const formBooking     = document.getElementById('formTambahBooking');
    const inputJadwal     = document.querySelector('input[name="jadwal_booking"]');
    const txtCatatan      = document.getElementById('catatan_booking');
    const catatanCounter  = document.getElementById('catatan_counter');

    // Live counter untuk karakter catatan booking
    if (txtCatatan) {
        txtCatatan.addEventListener('input', function() {
            const length = this.value.trim().length;
            catatanCounter.textContent = `Karakter saat ini: ${length}/20 (Minimal 20 Karakter)`;
            if (length >= 20) {
                catatanCounter.className = "text-success d-block mt-1 small";
            } else {
                catatanCounter.className = "text-muted d-block mt-1 small";
            }
        });
    }

    // BATASI KALENDER BROWSER: Kunci tanggal & waktu di bawah waktu sekarang
    if (inputJadwal) {
        const batasiMinimalTanggal = () => {
            const sekarang = new Date();
            const tahun = sekarang.getFullYear();
            const bulan = String(sekarang.getMonth() + 1).padStart(2, '0');
            const tanggal = String(sekarang.getDate()).padStart(2, '0');
            const jam = String(sekarang.getHours()).padStart(2, '0');
            const menit = String(sekarang.getMinutes()).padStart(2, '0');
            
            const formatMinimal = `${tahun}-${bulan}-${tanggal}T${jam}:${menit}`;
            inputJadwal.setAttribute('min', formatMinimal);
        };
        
        batasiMinimalTanggal();
        setInterval(batasiMinimalTanggal, 60000);
    }

    // FUNGSI LIVE CALCULATION
    function hitungTarifLayanan() {
        const selectedOption = selectLayanan.options[selectLayanan.selectedIndex];
        const hargaAsli = parseInt(selectedOption ? selectedOption.getAttribute('data-harga') : 0) || 0;
        
        let diskonPersen = parseInt(inputDiskon.value) || 0;

        if (diskonPersen > 100) {
            diskonPersen = 100;
            inputDiskon.value = 100;
        }

        const diskonRupiah = Math.round(hargaAsli * (diskonPersen / 100));
        const totalTarif = Math.max(0, hargaAsli - diskonRupiah);

        inputHarga.value = hargaAsli;
        inputTotal.value = totalTarif;
    }

    // DETEKSI STATUS MEMBER: Diskon otomatis 10% jika status adalah 'Member' / 'Premium', selain itu 0%
    if (selectPelanggan) {
        $(selectPelanggan).on('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const statusMember = selectedOption ? (selectedOption.getAttribute('data-status-member') || 'Non Member') : 'Non Member';

            if (statusMember === 'Member' || statusMember === 'Premium') {
                inputDiskon.value = 10;
            } else {
                inputDiskon.value = 0;
            }
            hitungTarifLayanan();
        });
    }

    if (selectLayanan) {
        $(selectLayanan).on('change', hitungTarifLayanan);
    }

    // SUBMIT AJAX MENGGUNAKAN FETCH
    if (formBooking) {
        formBooking.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validasi Karakter Catatan Keluhan
            const isiCatatan = txtCatatan.value.trim();
            if (isiCatatan.length < 20) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Catatan Kurang Detail',
                    text: 'Silakan isi catatan kondisi hewan minimal sebanyak 20 karakter sebelum menyimpan.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const harga = parseInt(inputHarga.value) || 0;
            const diskonPersen = parseInt(inputDiskon.value) || 0;
            const jadwalValue = inputJadwal ? inputJadwal.value : '';

            // VALIDASI 1: Cek apakah tanggal kosong
            if (!jadwalValue) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jadwal Kosong',
                    text: 'Silakan tentukan rencana jadwal treatment terlebih dahulu.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // VALIDASI 2: Cek apakah tanggal yang dipilih berada di masa lampau
            const tanggalPilihan = new Date(jadwalValue);
            const waktuSekarang = new Date();
            waktuSekarang.setSeconds(0, 0);

            if (tanggalPilihan < waktuSekarang) {
                Swal.fire({
                    icon: 'error',
                    title: 'Jadwal Tidak Valid',
                    text: 'Rencana jadwal reservasi tidak boleh sebelum hari atau waktu saat ini!',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            if (harga === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Layanan Belum Dipilih',
                    text: 'Silakan pilih jenis jasa layanan terlebih dahulu.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            if (diskonPersen < 0 || diskonPersen > 100) {
                Swal.fire({
                    icon: 'error',
                    title: 'Diskon Tidak Valid',
                    text: 'Persentase diskon harus berada di rentang 0% hingga 100%.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const btnSimpan = document.getElementById('btn_simpan_booking');
            const originalText = btnSimpan.innerHTML;
            btnSimpan.disabled = true;
            btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';

            const formData = new FormData(formBooking);

            // AJAX FETCH POST ke booking_create.php
            fetch('booking_create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modalEl = document.getElementById('modalTambahBooking');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    formBooking.reset();
                    inputHarga.value = 0;
                    inputTotal.value = 0;
                    
                    // Reset dropdown pencarian Select2
                    $('.select2-searchable').val(null).trigger('change');
                    if (catatanCounter) {
                        catatanCounter.textContent = "Karakter saat ini: 0/20 (Minimal 20 Karakter)";
                        catatanCounter.className = "text-muted d-block mt-1 small";
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Reservasi Sukses!',
                        text: data.message,
                        confirmButtonColor: '#1e3a8a',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'booking_read.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menjadwalkan',
                        text: data.message,
                        html: data.error_details ? `<div class="text-start mt-3 p-3 bg-light border rounded font-monospace small" style="max-height:180px; overflow-y:auto; white-space:pre-wrap; font-size:0.8rem;"><strong>Detail Kesalahan Server:</strong><br>${data.error_details}</div>` : '',
                        confirmButtonColor: '#3b82f6'
                    });
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Terputus',
                    text: 'Terjadi kegagalan jaringan saat menghubungi server.',
                    confirmButtonColor: '#3b82f6'
                });
            })
            .finally(() => {
                btnSimpan.disabled = false;
                btnSimpan.innerHTML = originalText;
            });
        });
    }
});
</script>
