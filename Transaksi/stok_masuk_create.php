<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/koneksi.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
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

if (isset($_POST['simpan_stok_ajax'])) {
    header('Content-Type: application/json');

    $id_supplier       = intval($_POST['id_supplier']);
    $id_karyawan       = intval($_POST['id_karyawan']);
    $tanggal_diterima  = !empty($_POST['tanggal_diterima']) ? $_POST['tanggal_diterima'] : null;
    
    $subtotal          = floatval($_POST['subtotal_stok']);
    $pajak_stok        = floatval($_POST['pajak_stok']); 
    $total_harga       = floatval($_POST['total_harga']);
    
    // Validasi Catatan Penerimaan (Wajib diisi & Minimal 20 Karakter)
    $catatan_masuk     = !empty($_POST['catatan_masuk']) ? trim($_POST['catatan_masuk']) : '';
    if (strlen($catatan_masuk) < 20) {
        echo json_encode([
            'success' => false,
            'message' => 'Catatan penerimaan / kondisi logistik wajib diisi dengan minimal 20 karakter.'
        ]);
        exit;
    }
    
    $sm_status         = 'Aktif';
    $created_by        = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Petugas Gudang';

    // Formatisasi Tanggal untuk SQL Server
    if (!empty($tanggal_diterima)) {
        $tanggal_diterima = str_replace('T', ' ', $tanggal_diterima);
        if (strlen($tanggal_diterima) == 16) {
            $tanggal_diterima .= ':00';
        }
    } else {
        $tanggal_diterima = date('Y-m-d H:i:s');
    }

    // 1. Validasi Sisi Server (Menolak tanggal & waktu sebelum waktu saat ini)
    if (!empty($tanggal_diterima)) {
        $waktu_pilihan  = strtotime($tanggal_diterima);
        $waktu_sekarang = time();
        
        // Toleransi waktu 5 menit (300 detik) untuk perbedaan clock server/client
        if ($waktu_pilihan < ($waktu_sekarang - 300)) {
            echo json_encode([
                'success' => false,
                'message' => 'Tanggal penerimaan barang tidak boleh sebelum tanggal atau waktu saat ini!'
            ]);
            exit;
        }
    }

    // Pembuatan nomor faktur otomatis
    $received_date_only = substr($tanggal_diterima, 0, 10);
    $tahun_diterima = substr($received_date_only, 0, 4);
    $prefix_faktur = "FKT-" . $tahun_diterima . "-";
    
    $sql_code = "SELECT COUNT(*) as total FROM Stok_Masuk WHERE No_Faktur LIKE ?";
    $query_code = sqlsrv_query($conn, $sql_code, array($prefix_faktur . "%"));
    
    if ($query_code === false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal membuat nomor faktur otomatis.', 
            'error_details' => formatErrors(sqlsrv_errors())
        ]);
        exit;
    }
    
    $row_code = sqlsrv_fetch_array($query_code, SQLSRV_FETCH_ASSOC);
    $next_num = str_pad(($row_code['total'] + 1), 4, '0', STR_PAD_LEFT);
    $no_faktur = $prefix_faktur . $next_num;

    sqlsrv_begin_transaction($conn);

    // STATUS DEFAULT ADALAH 'Pending' (Stok fisik bertambah otomatis melalui trigger trg_DetailStokMasuk_TambahStok saat detail ditambahkan)
    $sql_insert = "INSERT INTO Stok_Masuk (
                        No_Faktur, ID_Supplier, ID_Karyawan, Tanggal_Masuk, Tanggal_Diterima,
                        Subtotal_Stok, Pajak_Stok, Total_Harga, Status, Catatan_Masuk,
                        SM_status, SM_created_by, SM_created_date
                    ) VALUES (?, ?, ?, GETDATE(), ?, ?, ?, ?, 'Pending', ?, ?, ?, GETDATE())";
                    
    $params_insert = array(
        $no_faktur, $id_supplier, $id_karyawan, $tanggal_diterima,
        $subtotal, $pajak_stok, $total_harga, $catatan_masuk,
        $sm_status, $created_by
    );
    
    $stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

    if ($stmt_insert) {
        $sql_id = "SELECT IDENT_CURRENT('Stok_Masuk') AS ID_Stok";
        $query_id = sqlsrv_query($conn, $sql_id);
        $row_id = sqlsrv_fetch_array($query_id, SQLSRV_FETCH_ASSOC);
        $id_stok_baru = $row_id['ID_Stok'];

        if (isset($_POST['items_barang']) && is_array($_POST['items_barang'])) {
            $items_barang   = $_POST['items_barang'];
            $items_jumlah   = $_POST['items_jumlah'];
            $items_harga    = $_POST['items_harga'];
            $items_batch    = $_POST['items_batch'] ?? [];
            $items_expired  = $_POST['items_expired'] ?? [];
            $items_subtotal = $_POST['items_subtotal'];

            for ($i = 0; $i < count($items_barang); $i++) {
                if (empty($items_barang[$i])) continue;

                $id_barang     = intval($items_barang[$i]);
                $jumlah        = intval($items_jumlah[$i]);
                $harga_beli    = floatval($items_harga[$i]);
                $no_batch      = !empty($items_batch[$i]) ? trim($items_batch[$i]) : null;
                $tgl_expired   = !empty($items_expired[$i]) ? $items_expired[$i] : null;
                $subtotal_item = floatval($items_subtotal[$i]);

                // Batasan angka di backend (Sesuai validasi JS)
                if ($jumlah < 1 || $jumlah > 1000 || $harga_beli > 999999999) {
                    sqlsrv_rollback($conn);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Nilai input berada di luar batas aturan.',
                        'error_details' => 'Kuantitas per item wajib bernilai antara 1 s.d 1.000 unit.'
                    ]);
                    exit;
                }

                // Cek data barang menggunakan UDF fn_StokBarang
                $sql_cek_barang = "SELECT Nama_Barang FROM dbo.fn_StokBarang(?)";
                $query_cek_barang = sqlsrv_query($conn, $sql_cek_barang, array($id_barang));

                if ($query_cek_barang === false) {
                    sqlsrv_rollback($conn);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Gagal memverifikasi status barang.', 
                        'error_details' => formatErrors(sqlsrv_errors())
                    ]);
                    exit;
                }

                if (!empty($tgl_expired)) {
                    $today_date = date('Y-m-d');
                    if ($tgl_expired < $today_date) {
                        sqlsrv_rollback($conn);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Tanggal Kedaluwarsa Tidak Valid',
                            'error_details' => 'Tanggal kedaluwarsa tidak boleh kurang dari hari ini.'
                        ]);
                        exit;
                    }
                } else {
                    $tgl_expired = null;
                }

                // Simpan rincian ke tabel Detail_Stok_Masuk
                $sql_detail = "INSERT INTO Detail_Stok_Masuk (
                                    ID_Stok, ID_Barang, Jumlah_Masuk, Harga_Beli, Subtotal,
                                    No_Batch, Tanggal_Kadaluarsa,
                                    DetSM_status, DetSM_created_by, DetSM_created_date
                               ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif', ?, GETDATE())";

                $params_detail = array(
                    $id_stok_baru, $id_barang, $jumlah, $harga_beli, $subtotal_item, 
                    $no_batch, $tgl_expired, $created_by
                );
                
                $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);

                if ($stmt_detail === false) {
                    sqlsrv_rollback($conn);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Gagal menyimpan rincian barang masuk.', 
                        'error_details' => formatErrors(sqlsrv_errors())
                    ]);
                    exit;
                }
            }
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Registrasi pengadaan logistik disimpan dengan status Pending (Faktur ' . $no_faktur . ')!']);
    } else {
        sqlsrv_rollback($conn);
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal menyimpan data pengadaan stok ke basis data.', 
            'error_details' => formatErrors(sqlsrv_errors())
        ]);
    }
    exit;
}

$list_supplier = [];
$list_karyawan = [];
$list_barang = [];

if ($conn) {
    $query_s = sqlsrv_query($conn, "SELECT ID_Supplier, Nama_Supplier FROM Supplier WHERE Sup_status = 'Aktif' AND Sup_is_deleted = 0 ORDER BY Nama_Supplier ASC");
    if ($query_s !== false) {
        while ($row = sqlsrv_fetch_array($query_s, SQLSRV_FETCH_ASSOC)) { $list_supplier[] = $row; }
    }

    $query_k = sqlsrv_query($conn, "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan WHERE Kar_status = 'Aktif' AND Kar_is_deleted = 0 ORDER BY Nama_Karyawan ASC");
    if ($query_k !== false) {
        while ($row = sqlsrv_fetch_array($query_k, SQLSRV_FETCH_ASSOC)) { $list_karyawan[] = $row; }
    }

    // SINKRONISASI: Ambil list barang aktif menggunakan UDF fn_StokBarang agar informasinya akurat & terstruktur
    $query_brg = sqlsrv_query($conn, "SELECT ID_Barang, Nama_Barang, Stok FROM dbo.fn_StokBarang(NULL) ORDER BY Nama_Barang ASC");
    if ($query_brg !== false) {
        while ($row = sqlsrv_fetch_array($query_brg, SQLSRV_FETCH_ASSOC)) { $list_barang[] = $row; }
    }
}
?>

<!-- Dependencies: Select2 & Bootstrap 5 Select2 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    :root { 
        --primary-gradient-stok: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        --accent-color-stok: #3b82f6; 
        --border-color-stok: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahStok {
        z-index: 1055 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @keyframes modalZoomInStok {
        from { opacity: 0; transform: scale(0.95) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    #modalTambahStok.show .modal-content-custom-s {
        animation: modalZoomInStok 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom-s { 
        background: white; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        overflow: hidden; 
    }

    .header-bg-stok { 
        background: var(--primary-gradient-stok); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-stok i {
        animation: pulseStok 2.5s infinite;
    }

    @keyframes pulseStok {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahStok .modal-dialog {
        max-width: 950px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container-s { padding: 2.5rem 3rem; }

    .section-title-stok { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-stok); 
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
        border: 1.5px solid var(--border-color-stok); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-stok);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        outline: none;
    }

    /* FIX CSS: PAKSA SEMBUNYIKAN SELECT ASLI BRWOSER JIKA SELECT2 SUDAH AKTIF */
    select.select2-hidden-accessible {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        position: absolute !important;
        left: -9999px !important;
    }

    /* Penyesuaian Tampilan Dropdown Select2 agar Sesuai Tema */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1.5px solid var(--border-color-stok) !important;
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
        border-color: var(--accent-color-stok) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15) !important;
    }

    /* Styling Kustom Hasil Pencarian Dropdown Select2 */
    .select2-dropdown {
        border: 1px solid #cbd5e1 !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        overflow: hidden;
        z-index: 1065 !important; /* Di atas modalTambahStok (1055) */
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
        border-color: var(--accent-color-stok) !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .select2-results__option {
        padding: 8px 12px !important;
        font-size: 0.9rem !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-stok) !important;
        color: white !important;
    }

    .btn-simpan-s { 
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan-s:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal-s {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .icon-box-s { 
        width: 32px; 
        height: 32px; 
        background: #eff6ff; 
        color: var(--accent-color-stok); 
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
        border: 1.5px solid var(--border-color-stok);
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
        box-sizing: border-box;
    }
</style>

<div class="modal fade" id="modalTambahStok" tabindex="-1" aria-labelledby="modalTambahStokLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 950px;">
        <div class="modal-content modal-content-custom-s">
            
            <div class="header-bg-stok">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-truck-loading fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Pengadaan Stok Masuk Baru</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Registrasikan nota logistik barang masuk dari supplier</p>
            </div>

            <form id="formTambahStok">
                <input type="hidden" name="simpan_stok_ajax" value="1">
                <input type="hidden" id="pajak_stok_nominal" name="pajak_stok" value="0">

                <div class="form-container-s">
                    
                    <div class="section-title-stok d-flex align-items-center">
                        <div class="icon-box-s"><i class="fas fa-file-invoice"></i></div>
                        Informasi Supplier & Penerima
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Mitra Supplier <span class="text-danger-marker">*</span></label>
                            <select name="id_supplier" class="form-select select2-searchable" required>
                                <option value="">-- Pilih Supplier --</option>
                                <?php foreach($list_supplier as $s): ?>
                                    <option value="<?= $s['ID_Supplier'] ?>"><?= htmlspecialchars($s['Nama_Supplier']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Petugas Penerima <span class="text-danger-marker">*</span></label>
                            <select name="id_karyawan" class="form-select select2-searchable" required>
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach($list_karyawan as $k): ?>
                                    <option value="<?= $k['ID_Karyawan'] ?>"><?= htmlspecialchars($k['Nama_Karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Diterima Fisik <span class="text-danger-marker">*</span></label>
                            <input type="datetime-local" id="tanggal_diterima" name="tanggal_diterima" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                    </div>

                    <div class="section-title-stok d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-s"><i class="fas fa-boxes"></i></div>
                            Daftar Barang Masuk <span class="text-danger-marker">*</span>
                        </div>
                        <button type="button" id="btn_tambah_barang_stok" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" style="background: var(--accent-color-stok); border: none;">
                            <i class="fas fa-plus me-1"></i> Tambah Baris Barang
                        </button>
                    </div>
                    <div class="table-responsive mb-4" style="overflow-x: auto;">
                        <table class="table table-bordered align-middle text-center small" style="width: 100%; min-width: 850px;">
                            <thead class="table-light">
                                <tr>
                                    <!-- RETRIBUSI LEBAR KOLOM: Harga Beli & Subtotal Diperbesar agar nominal angka muat sempurna -->
                                    <th style="width: 22%;" class="text-start">Nama Produk</th>
                                    <th style="width: 10%;">Kuantitas</th>
                                    <th style="width: 20%;">Harga Beli (Rp)</th>
                                    <th style="width: 13%;">No Batch (Opt)</th>
                                    <th style="width: 15%;">Kedaluwarsa (Opt)</th>
                                    <th style="width: 17%;">Subtotal</th>
                                    <th style="width: 3%; min-width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tabel_barang_tbody">
                            </tbody>
                        </table>
                    </div>

                    <div class="section-title-stok d-flex align-items-center">
                        <div class="icon-box-s"><i class="fas fa-calculator"></i></div>
                        Ringkasan Finansial Pengadaan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Subtotal Faktur (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="subtotal_stok" name="subtotal_stok" class="form-control text-dark fw-bold bg-white" placeholder="0" min="0" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PPN Masukan (%) <span class="text-danger-marker">*</span></label>
                            <div class="booking-input-group">
                                <!-- INTERAKTIF ONFOCUS & ONBLUR: Menghilangkan angka 0 default saat diklik, kembali ke 0 jika kosong -->
                                <input type="number" id="pajak_persen" class="form-control text-warning fw-bold" placeholder="0" min="0" max="100" value="0" onfocus="if(this.value === '0') this.value = '';" onblur="if(this.value === '') this.value = '0';" required>
                                <span class="booking-input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-success fw-bold">Total Harga Akhir (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="total_harga" name="total_harga" class="form-control fw-bold text-success bg-white" value="0" readonly required>
                        </div>
                    </div>

                    <div class="section-title-stok d-flex align-items-center">
                        <div class="icon-box-s"><i class="fas fa-comment-alt"></i></div>
                        Catatan Penerimaan / Kondisi Logistik <span class="text-danger-marker">*</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <textarea name="catatan_masuk" id="catatan_masuk" class="form-control" rows="3" minlength="20" placeholder="Catatan kondisi pengiriman logistik (Wajib diisi, misalnya: barang dalam kondisi utuh, segel aman, minimal 20 karakter)..." required></textarea>
                            <small class="text-muted d-block mt-1" id="catatan_counter">Karakter saat ini: 0/20 (Minimal 20 Karakter)</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal-s" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" id="btn_simpan_stok" class="btn btn-simpan-s">
                            <i class="fas fa-save me-2"></i>Simpan Pengadaan
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts Dependencies: Memuat Pustaka Utama Secara Berurutan (Guna menjamin tersedianya Select2 & jQuery di modal) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 Dependencies: Menjamin tersedianya notifikasi modern berbasis SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 1. FUNGSI INISIALISASI UNTUK SELECT2 ATAS (Mitra Supplier & Petugas Gudang)
function initSelect2Stok() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        jQuery('#modalTambahStok .select2-searchable').each(function() {
            var $select = jQuery(this);
            
            // JIKA sudah memiliki class select2-hidden-accessible atau data Select2, jangan ditumpuk!
            if ($select.hasClass('select2-hidden-accessible') || $select.data('select2')) {
                return; 
            }
            
            // Lakukan pembersihan kontainer rusak di sebelahnya terlebih dahulu
            $select.next('.select2-container').remove();
            
            // Bangun mesin pencarian baru
            $select.select2({
                theme: 'bootstrap-5',
                dropdownParent: jQuery('#modalTambahStok'),
                width: '100%'
            });
        });
    }
}

// Jalankan sekali saat dokumen modal siap
jQuery(document).ready(function() {
    initSelect2Stok();
});

// Jalankan secara andal saat transisi modal Bootstrap selesai terbuka
jQuery(document).on('shown.bs.modal show.bs.modal', '#modalTambahStok', function() {
    initSelect2Stok();
});

const availableBarangList = <?php echo json_encode($list_barang); ?>;

document.addEventListener("DOMContentLoaded", function() {
    const inputSubtotal       = document.getElementById('subtotal_stok');
    const inputPajakPersen    = document.getElementById('pajak_persen');
    const hiddenPajakNominal  = document.getElementById('pajak_stok_nominal');
    const inputTotalHarga     = document.getElementById('total_harga');
    const inputTanggalDiterima = document.getElementById('tanggal_diterima');
    
    const formStok            = document.getElementById('formTambahStok');
    const tbodyBarang         = document.getElementById('tabel_barang_tbody');
    const btnTambahBarang     = document.getElementById('btn_tambah_barang_stok');
    const txtCatatan          = document.getElementById('catatan_masuk');
    const catatanCounter      = document.getElementById('catatan_counter');

    // Live counter untuk karakter catatan masuk
    if (txtCatatan && catatanCounter) {
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

    // Ambil format tanggal hari ini untuk lock kalender
    const getTodayDateString = () => {
        const sekarang = new Date();
        const tahun = sekarang.getFullYear();
        const bulan = String(sekarang.getMonth() + 1).padStart(2, '0');
        const tanggal = String(sekarang.getDate()).padStart(2, '0');
        return `${tahun}-${bulan}-${tanggal}`;
    };

    // 2. SISTEM LOCK TANGGAL DITERIMA FISIK & AUTO-RESET (Memblokir pilihan sebelum hari ini)
    if (inputTanggalDiterima) {
        const batasiMinimalTanggal = () => {
            const sekarang = new Date();
            const tahun = sekarang.getFullYear();
            const bulan = String(sekarang.getMonth() + 1).padStart(2, '0');
            const tanggal = String(sekarang.getDate()).padStart(2, '0');
            const jam = String(sekarang.getHours()).padStart(2, '0');
            const menit = String(sekarang.getMinutes()).padStart(2, '0');
            
            const formatMinimal = `${tahun}-${bulan}-${tanggal}T${jam}:${menit}`;
            inputTanggalDiterima.setAttribute('min', formatMinimal);
        };
        
        batasiMinimalTanggal();
        setInterval(batasiMinimalTanggal, 30000); // Perbarui min value secara real-time

        // Validasi interaktif: jika diketik manual ke masa lalu, langsung di-reset ke waktu sekarang
        inputTanggalDiterima.addEventListener('change', function() {
            const tanggalPilihan = new Date(this.value);
            const waktuSekarang = new Date();
            waktuSekarang.setSeconds(0, 0);

            if (tanggalPilihan < waktuSekarang) {
                Swal.fire({
                    icon: 'error',
                    title: 'Tanggal Tidak Valid',
                    text: 'Tanggal penerimaan barang tidak boleh sebelum hari atau waktu saat ini!',
                    confirmButtonColor: '#3b82f6'
                });
                
                const sekarang = new Date();
                const tahun = sekarang.getFullYear();
                const bulan = String(sekarang.getMonth() + 1).padStart(2, '0');
                const tanggal = String(sekarang.getDate()).padStart(2, '0');
                const jam = String(sekarang.getHours()).padStart(2, '0');
                const menit = String(sekarang.getMinutes()).padStart(2, '0');
                this.value = `${tahun}-${bulan}-${tanggal}T${jam}:${menit}`;
            }
        });
    }

    // FUNGSI LIVE CALCULATION
    function hitungKalkulasiStok() {
        const subtotal = parseFloat(inputSubtotal.value) || 0;
        
        let pajakPercent = parseFloat(inputPajakPersen.value) || 0;
        if (pajakPercent > 100) {
            pajakPercent = 100;
            inputPajakPersen.value = 100;
        }

        const nominalPajak = Math.round(subtotal * (pajakPercent / 100));
        const total = subtotal + nominalPajak;

        hiddenPajakNominal.value = nominalPajak;
        inputTotalHarga.value = Math.round(total);
    }

    if (inputSubtotal) inputSubtotal.addEventListener('input', hitungKalkulasiStok);
    if (inputPajakPersen) inputPajakPersen.addEventListener('input', hitungKalkulasiStok);

    function hitungUlangAkumulasiBarang() {
        let totalSubtotalBarang = 0;
        const barisSubtotal = tbodyBarang.querySelectorAll('.input-subtotal-row');
        
        barisSubtotal.forEach(el => {
            totalSubtotalBarang += parseFloat(el.value) || 0;
        });

        inputSubtotal.value = totalSubtotalBarang;
        hitungKalkulasiStok();
    }

    function hitungSubtotalBaris(row) {
        const inputJml  = row.querySelector('.input-jumlah-row');
        const inputHrg  = row.querySelector('.input-harga-row');
        const inputSub  = row.querySelector('.input-subtotal-row');

        const jumlah = parseInt(inputJml.value) || 0;
        const harga  = parseFloat(inputHrg.value) || 0;

        const subtotalBaris = Math.max(0, jumlah * harga);
        inputSub.value = subtotalBaris;

        hitungUlangAkumulasiBarang();
    }

    // Generator Rekomendasi No Batch otomatis secara dinamis
    function generateNoBatchPlaceholder() {
        const dateVal = inputTanggalDiterima.value ? inputTanggalDiterima.value.substring(0, 10).replace(/-/g, '') : '';
        const randomNum = Math.floor(100 + Math.random() * 900); // 3 digit random
        return dateVal ? `BCH-${dateVal}-${randomNum}` : `BCH-GEN-01`;
    }

    if (btnTambahBarang) {
        btnTambahBarang.addEventListener('click', function() {
            const tr = document.createElement('tr');
            tr.className = 'baris-barang-pengadaan';

            let optionsHtml = '<option value="" data-stok="0">-- Pilih Produk --</option>';
            availableBarangList.forEach(b => {
                optionsHtml += `<option value="${b.ID_Barang}" data-stok="${b.Stok}">${b.Nama_Barang} (Stok: ${b.Stok})</option>`;
            });

            const defaultBatch = generateNoBatchPlaceholder();
            const todayStr = getTodayDateString(); // Lock tanggal untuk min expired-row

            tr.innerHTML = `
                <td>
                    <select name="items_barang[]" class="form-select select-barang-row text-start select2-searchable" required>
                        ${optionsHtml}
                    </select>
                </td>
                <td>
                    <input type="number" name="items_jumlah[]" class="form-control text-center input-jumlah-row" value="1" min="1" max="1000" oninput="if(this.value.length > 4) this.value = this.value.slice(0, 4); if(this.value > 1000) this.value = 1000;" required>
                </td>
                <td>
                    <!-- INTERAKTIF ONFOCUS & ONBLUR: Angka 0 otomatis bersih saat diklik, kembali ke 0 jika kosong -->
                    <input type="number" name="items_harga[]" class="form-control text-end input-harga-row" value="0" min="0" max="999999999" oninput="if(this.value.length > 9) this.value = this.value.slice(0, 9);" onfocus="if(this.value === '0') this.value = '';" onblur="if(this.value === '') this.value = '0';" required>
                </td>
                <td>
                    <input type="text" name="items_batch[]" class="form-control text-center input-batch-row" value="${defaultBatch}" placeholder="No Batch">
                </td>
                <td>
                    <!-- FIX: TANGGAL KEDALUWARSA DI-LOCK AGAR HARI LALU TIDAK BISA DIPENCET SAMA SEKALI -->
                    <input type="date" name="items_expired[]" class="form-control text-center input-expired-row" min="${todayStr}">
                </td>
                <td>
                    <input type="number" name="items_subtotal[]" class="form-control text-end input-subtotal-row bg-white" value="0" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger btn-hapus-row"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;

            tbodyBarang.appendChild(tr);

            const selectBrg   = tr.querySelector('.select-barang-row');
            const inputJml    = tr.querySelector('.input-jumlah-row');
            const inputHrg    = tr.querySelector('.input-harga-row');
            const inputExp    = tr.querySelector('.input-expired-row');
            const btnHapus    = tr.querySelector('.btn-hapus-row');

            // 3. INISIALISASI INSTAN SELECT2 AMAN PADA BARIS BARU (MENGGUNAKAN JQUERY LANGSUNG TANPA KONFLIK SYMBOL $)
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                jQuery(selectBrg).select2({
                    theme: 'bootstrap-5',
                    dropdownParent: jQuery('#modalTambahStok'),
                    width: '100%'
                });
            }

            $(selectBrg).on('change', () => hitungSubtotalBaris(tr));
            inputJml.addEventListener('input', () => hitungSubtotalBaris(tr));
            inputHrg.addEventListener('input', () => hitungSubtotalBaris(tr));
            
            // Validasi interaktif input kedaluwarsa jika user mengetik manual tanggal lampau
            inputExp.addEventListener('change', function() {
                if (this.value && this.value < todayStr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tanggal Kedaluwarsa Tidak Valid',
                        text: 'Tanggal kedaluwarsa tidak boleh kurang dari hari ini!',
                        confirmButtonColor: '#3b82f6'
                    });
                    this.value = '';
                }
            });

            btnHapus.addEventListener('click', function() {
                if (typeof jQuery !== 'undefined') {
                    jQuery(selectBrg).select2('destroy');
                }
                tr.remove();
                hitungUlangAkumulasiBarang();
            });
        });
    }

    if (formStok) {
        formStok.addEventListener('submit', function(e) {
            e.preventDefault();

            const tanggalPilihan = new Date(inputTanggalDiterima.value);
            const waktuSekarang = new Date();
            waktuSekarang.setSeconds(0, 0);

            if (tanggalPilihan < waktuSekarang) {
                Swal.fire({
                    icon: 'error',
                    title: 'Tanggal Tidak Valid',
                    text: 'Tanggal penerimaan barang tidak boleh sebelum tanggal atau waktu saat ini!',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const isiCatatan = txtCatatan.value.trim();
            if (isiCatatan.length < 20) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Catatan Kurang Detail',
                    text: 'Silakan isi catatan penerimaan / kondisi logistik minimal sebanyak 20 karakter sebelum menyimpan.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            let isBarisBarangValid = true;
            let warningTitle = 'Validasi Barang Gagal';
            let warningMsg = '';

            const barisBarang = tbodyBarang.querySelectorAll('.baris-barang-pengadaan');
            if (barisBarang.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Daftar Barang Kosong',
                    text: 'Silakan tambahkan minimal 1 baris barang masuk terlebih dahulu.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const todayStr = getTodayDateString();

            for (let row of barisBarang) {
                const selectBrg = row.querySelector('.select-barang-row');
                if (selectBrg.value === "") {
                    isBarisBarangValid = false;
                    warningTitle = 'Produk Belum Dipilih';
                    warningMsg = 'Harap pilih produk pada setiap baris pengadaan yang Anda tambahkan.';
                    break;
                }

                const selectedOption = selectBrg.options[selectBrg.selectedIndex];
                const selectNama = selectedOption.text.split('(Stok:')[0].trim();
                
                const inputJml    = parseInt(row.querySelector('.input-jumlah-row').value) || 0;
                const inputHrg    = parseFloat(row.querySelector('.input-harga-row').value) || 0;
                const inputExpired = row.querySelector('.input-expired-row').value;

                if (inputJml < 1) {
                    isBarisBarangValid = false;
                    warningTitle = 'Kuantitas Tidak Valid';
                    warningMsg = `Kuantitas barang "${selectNama}" tidak boleh kurang dari 1 unit. Harap masukkan kuantitas minimal 1.`;
                    break;
                }

                if (inputJml > 1000) {
                    isBarisBarangValid = false;
                    warningTitle = 'Kuantitas Melebihi Batas';
                    warningMsg = `Kuantitas masuk untuk "${selectNama}" tidak boleh melebihi batas maksimal 1.000 unit per transaksi.`;
                    break;
                }

                if (inputHrg > 999999999) {
                    isBarisBarangValid = false;
                    warningTitle = 'Harga Tidak Valid';
                    warningMsg = `Harga beli per item "${selectNama}" tidak boleh melebihi Rp 999.999.999.`;
                    break;
                }

                if (inputExpired && inputExpired < todayStr) {
                    isBarisBarangValid = false;
                    warningTitle = 'Tanggal Kedaluwarsa Lampau';
                    warningMsg = `Tanggal kedaluwarsa produk "${selectNama}" tidak boleh berupa tanggal lampau sebelum hari ini.`;
                    break;
                }
            }

            if (!isBarisBarangValid) {
                Swal.fire({
                    icon: 'error',
                    title: warningTitle,
                    text: warningMsg,
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const subtotal = parseFloat(inputSubtotal.value) || 0;
            if (subtotal <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Subtotal Pengadaan Kosong',
                    text: 'Silakan isi harga barang secara valid untuk menghitung tagihan pengadaan.',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const btnSimpan = document.getElementById('btn_simpan_stok');
            const originalText = btnSimpan.innerHTML;
            btnSimpan.disabled = true;
            btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';

            const formData = new FormData(formStok);

            fetch('stok_masuk_create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    const modalEl = document.getElementById('modalTambahStok');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    formStok.reset();
                    tbodyBarang.innerHTML = '';
                    hitungKalkulasiStok();

                    // Reset dropdown pencarian Select2
                    if (typeof jQuery !== 'undefined') {
                        jQuery('.select2-searchable').val(null).trigger('change');
                    }
                    if (catatanCounter) {
                        catatanCounter.textContent = "Karakter saat ini: 0/20 (Minimal 20 Karakter)";
                        catatanCounter.className = "text-muted d-block mt-1 small";
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Pengadaan Berhasil Disimpan!',
                        text: data.message,
                        confirmButtonColor: '#3b82f6',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        if (typeof window.performSearchAndFilter === "function") {
                            window.performSearchAndFilter();
                        } else {
                            window.location.href = 'stok_masuk_read.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
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
                    text: 'Terjadi kegagalan sistem di server.',
                    html: `<div class="text-start mt-3 p-3 bg-light border rounded font-monospace small" style="max-height:180px; overflow-y:auto; white-space:pre-wrap; font-size:0.8rem;"><strong>Detail Error:</strong><br>${error.message}</div>`,
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
