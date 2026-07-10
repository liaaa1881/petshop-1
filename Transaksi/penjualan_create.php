
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

if (isset($_POST['simpan_penjualan_ajax'])) {
    header('Content-Type: application/json');

    $id_pelanggan      = !empty($_POST['id_pelanggan']) ? intval($_POST['id_pelanggan']) : null;
    $id_karyawan       = intval($_POST['id_karyawan']);
    $id_booking        = !empty($_POST['id_booking']) ? intval($_POST['id_booking']) : null;
    
    $subtotal          = floatval($_POST['subtotal_penjualan']);
    $total_diskon      = floatval($_POST['total_diskon']); 
    $pajak_ppn         = floatval($_POST['pajak_ppn']);     
    $grand_total       = floatval($_POST['grand_total']);
    $jumlah_bayar      = floatval($_POST['jumlah_bayar']); 
    $kembalian         = floatval($_POST['kembalian']);    
    
    $metode            = $_POST['metode_pembayaran'];
    $status_bayar      = $_POST['status_pembayaran'];      
    
    // Validasi Catatan Transaksi (Wajib diisi & Minimal 20 Karakter)
    $catatan_penjualan = !empty($_POST['catatan_penjualan']) ? trim($_POST['catatan_penjualan']) : '';
    if (strlen($catatan_penjualan) < 20) {
        echo json_encode([
            'success' => false,
            'message' => 'Catatan transaksi wajib diisi dengan minimal 20 karakter.'
        ]);
        exit;
    }
    
    $pen_status        = 'Aktif';
    $created_by        = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Kasir';

    $bukti_nama = null;
    if (isset($_FILES['bukti_pembayaran']) && !empty($_FILES['bukti_pembayaran']['name'])) {
        $target_dir = "../assets/img/bukti_bayar/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $bukti_nama = "BUKTI_" . time() . "." . $ext;
        move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_dir . $bukti_nama);
    }

    $year_month = date('Ym'); 
    $sql_code = "SELECT COUNT(*) as total FROM Penjualan WHERE No_Nota LIKE ?";
    $query_code = sqlsrv_query($conn, $sql_code, array("INV-$year_month-%"));
    
    if ($query_code === false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal membuat nomor nota otomatis.', 
            'error_details' => formatErrors(sqlsrv_errors())
        ]);
        exit;
    }
    
    $row_code = sqlsrv_fetch_array($query_code, SQLSRV_FETCH_ASSOC);
    $next_num = str_pad(($row_code['total'] + 1), 4, '0', STR_PAD_LEFT);
    $no_nota = "INV-" . $year_month . "-" . $next_num;

    sqlsrv_begin_transaction($conn);

    $sql_insert = "INSERT INTO Penjualan (
                        No_Nota, ID_Pelanggan, ID_Karyawan, ID_Booking, Tanggal_Penjualan,
                        Subtotal_Penjualan, Total_Diskon, Pajak_PPN, Grand_Total, Jumlah_Bayar, Kembalian,
                        Metode_Pembayaran, Bukti_Pembayaran, Status_Pembayaran, Catatan_Penjualan,
                        Pen_status, Pen_created_by, Pen_created_date
                    ) VALUES (?, ?, ?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
                    
    $params_insert = array(
        $no_nota, $id_pelanggan, $id_karyawan, $id_booking,
        $subtotal, $total_diskon, $pajak_ppn, $grand_total, $jumlah_bayar, $kembalian,
        $metode, $bukti_nama, $status_bayar, $catatan_penjualan,
        $pen_status, $created_by
    );
    
    $stmt_insert = sqlsrv_query($conn, $sql_insert, $params_insert);

    if ($stmt_insert) {
        $sql_id = "SELECT IDENT_CURRENT('Penjualan') AS ID_Nota";
        $query_id = sqlsrv_query($conn, $sql_id);
        $row_id = sqlsrv_fetch_array($query_id, SQLSRV_FETCH_ASSOC);
        $id_nota_baru = $row_id['ID_Nota'];

        if (isset($_POST['items_barang']) && is_array($_POST['items_barang'])) {
            $items_barang = $_POST['items_barang'];
            $items_jumlah = $_POST['items_jumlah'];
            $items_harga  = $_POST['items_harga'];
            $items_diskon = $_POST['items_diskon'];
            $items_subtotal = $_POST['items_subtotal'];

            for ($i = 0; $i < count($items_barang); $i++) {
                if (empty($items_barang[$i])) continue;

                $id_barang     = intval($items_barang[$i]);
                $jumlah        = intval($items_jumlah[$i]);
                $harga_satuan  = floatval($items_harga[$i]);
                $diskon_item   = floatval($items_diskon[$i]);
                $subtotal_item = floatval($items_subtotal[$i]);

                // Pengecekan Sisa Stok Awal
                $sql_cek_stok = "SELECT Nama_Barang, Stok FROM Barang WHERE ID_Barang = ?";
                $query_cek_stok = sqlsrv_query($conn, $sql_cek_stok, array($id_barang));

                if ($query_cek_stok === false) {
                    sqlsrv_rollback($conn);
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Gagal memverifikasi stok barang belanjaan.', 
                        'error_details' => formatErrors(sqlsrv_errors())
                    ]);
                    exit;
                }

                $row_barang = sqlsrv_fetch_array($query_cek_stok, SQLSRV_FETCH_ASSOC);
                if ($row_barang) {
                    $nama_barang = $row_barang['Nama_Barang'];
                    $stok_tersedia = intval($row_barang['Stok']);

                    if ($jumlah > $stok_tersedia) {
                        sqlsrv_rollback($conn); 
                        echo json_encode([
                            'success' => false, 
                            'message' => "Stok untuk '" . $nama_barang . "' tidak mencukupi.", 
                            'error_details' => "Stok yang tersedia saat ini: " . $stok_tersedia . " unit.\nJumlah yang dibeli: " . $jumlah . " unit."
                        ]);
                        exit;
                    }
                }

                $sql_detail = "INSERT INTO Detail_Penjualan (
                                    ID_Nota, ID_Barang, Jumlah, Harga_Satuan, Diskon_Item, Subtotal,
                                    DetPen_status, DetPen_created_by, DetPen_created_date
                               ) VALUES (?, ?, ?, ?, ?, ?, 'Aktif', ?, GETDATE())";

                $params_detail = array($id_nota_baru, $id_barang, $jumlah, $harga_satuan, $diskon_item, $subtotal_item, $created_by);
                $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);

                if ($stmt_detail === false) {
                    $errors = sqlsrv_errors();
                    sqlsrv_rollback($conn);
                    
                    $custom_message = 'Gagal menyimpan detail produk belanjaan.';
                    if ($errors !== null) {
                        foreach ($errors as $error) {
                            if (strpos($error['message'], 'Stok barang tidak mencukupi') !== false) {
                                $custom_message = 'Transaksi dibatalkan karena stok barang tidak mencukupi pada database.';
                                break;
                            }
                        }
                    }
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => $custom_message, 
                        'error_details' => formatErrors($errors)
                    ]);
                    exit;
                }
            }
        }

        if (!empty($id_booking)) {
            $sql_update_booking = "UPDATE Booking SET Status_Booking = 'Selesai', Book_modified_by = ?, Book_modified_date = GETDATE() WHERE ID_Booking = ?";
            sqlsrv_query($conn, $sql_update_booking, array($created_by, $id_booking));
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Transaksi penjualan baru berhasil disimpan dengan nomor nota ' . $no_nota . '!']);
    } else {
        $errors = sqlsrv_errors();
        sqlsrv_rollback($conn);
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal menyimpan transaksi ke basis data.', 
            'error_details' => formatErrors($errors)
        ]);
    }
    exit;
}

$list_pelanggan = [];
$list_karyawan = [];
$list_booking = [];
$list_barang = [];

if ($conn) {
    $query_p = sqlsrv_query($conn, "SELECT ID_Pelanggan, Nama_Pelanggan, No_Telepon, Status_Member FROM Pelanggan WHERE Pel_status = 'Aktif' AND Pel_is_deleted = 0 ORDER BY Nama_Pelanggan ASC");
    if ($query_p !== false) {
        while ($row = sqlsrv_fetch_array($query_p, SQLSRV_FETCH_ASSOC)) {
            $list_pelanggan[] = $row;
        }
    }

    $query_k = sqlsrv_query($conn, "SELECT ID_Karyawan, Nama_Karyawan FROM Karyawan WHERE Kar_status = 'Aktif' AND Kar_is_deleted = 0 ORDER BY Nama_Karyawan ASC");
    if ($query_k !== false) {
        while ($row = sqlsrv_fetch_array($query_k, SQLSRV_FETCH_ASSOC)) {
            $list_karyawan[] = $row;
        }
    }

    // PENYESUAIAN QUERY: Mengambil semua antrean booking aktif ('Pending' & 'Diproses') yang sesuai dengan rancangan riwayat booking_read.php
    $sql_booking = "SELECT B.ID_Booking, B.Kode_Booking, B.Total_Tarif, B.ID_Pelanggan, PL.Nama_Pelanggan 
                    FROM Booking B 
                    INNER JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan
                    WHERE B.Status_Booking IN ('Pending', 'Diproses')
                    ORDER BY B.Kode_Booking DESC";

    $query_b = sqlsrv_query($conn, $sql_booking);
    if ($query_b !== false) {
        while ($row = sqlsrv_fetch_array($query_b, SQLSRV_FETCH_ASSOC)) {
            $list_booking[] = $row;
        }
        sqlsrv_free_stmt($query_b);
    }

    $query_brg = sqlsrv_query($conn, "SELECT ID_Barang, Nama_Barang, Harga_Jual FROM Barang WHERE Bar_status = 'Aktif' AND Bar_is_deleted = 0 ORDER BY Nama_Barang ASC");
    if ($query_brg !== false) {
        while ($row = sqlsrv_fetch_array($query_brg, SQLSRV_FETCH_ASSOC)) {
            $list_barang[] = $row;
        }
    }
}
?>

<!-- Dependencies: Select2 & Bootstrap 5 Select2 Theme -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    :root { 
        --primary-gradient-penjualan: linear-gradient(135deg, #059669 0%, #10b981 50%, #047857 100%);
        --accent-color-penjualan: #10b981; 
        --border-color-penjualan: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahPenjualan {
        z-index: 1055 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @keyframes modalZoomInPenjualan {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahPenjualan.show .modal-content-custom-p {
        animation: modalZoomInPenjualan 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom-p { 
        background: white; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        overflow: hidden; 
    }

    .header-bg-penjualan { 
        background: var(--primary-gradient-penjualan); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-penjualan i {
        animation: pulsePenjualan 2.5s infinite;
    }

    @keyframes pulsePenjualan {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahPenjualan .modal-dialog {
        max-width: 950px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container-p { 
        padding: 2.5rem 3rem; 
    }

    .section-title-penjualan { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-penjualan); 
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
        border: 1.5px solid var(--border-color-penjualan); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-penjualan);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        outline: none;
    }

    /* Penyesuaian Tampilan Dropdown Select2 agar Sesuai Tema */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1.5px solid var(--border-color-penjualan) !important;
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
        border-color: var(--accent-color-penjualan) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15) !important;
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
        border-color: var(--accent-color-penjualan) !important;
        box-shadow: none !important;
        outline: none !important;
    }

    .select2-results__option {
        padding: 8px 12px !important;
        font-size: 0.9rem !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-penjualan) !important;
        color: white !important;
    }

    .btn-simpan-p { 
        background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan-p:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal-p {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .icon-box-p { 
        width: 32px; 
        height: 32px; 
        background: #ecfdf5; 
        color: var(--accent-color-penjualan); 
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
        border: 1.5px solid var(--border-color-penjualan);
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

<div class="modal fade" id="modalTambahPenjualan" tabindex="-1" aria-labelledby="modalTambahPenjualanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom-p">
            
            <div class="header-bg-penjualan">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-cash-register fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Transaksi Penjualan Baru</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Registrasikan nota pembayaran baru ke sistem Petshop Pro</p>
            </div>

            <form id="formTambahPenjualan" enctype="multipart/form-data">
                <input type="hidden" name="simpan_penjualan_ajax" value="1">
                <input type="hidden" id="total_diskon_nominal" name="total_diskon" value="0">
                <input type="hidden" id="pajak_ppn_nominal" name="pajak_ppn" value="0">

                <div class="form-container-p">
                    
                    <div class="section-title-penjualan d-flex align-items-center">
                        <div class="icon-box-p"><i class="fas fa-users"></i></div>
                        Informasi Pelanggan, Kasir & Layanan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Pelanggan <span class="text-danger-marker">*</span></label>
                            <select name="id_pelanggan" id="id_pelanggan_select" class="form-select select2-searchable" required>
                                <option value="" data-tipe="Non Member">-- Pelanggan Umum</option>
                                <?php foreach($list_pelanggan as $p): ?>
                                    <option value="<?= $p['ID_Pelanggan'] ?>" data-tipe="<?= htmlspecialchars($p['Status_Member'] ?? 'Regular') ?>">
                                        <?= htmlspecialchars($p['Nama_Pelanggan']) ?> (<?= htmlspecialchars($p['Status_Member'] ?? 'Regular') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kasir yang Melayani <span class="text-danger-marker">*</span></label>
                            <select name="id_karyawan" id="id_karyawan_select" class="form-select select2-searchable" required>
                                <option value="">-- Pilih Kasir --</option>
                                <?php foreach($list_karyawan as $k): ?>
                                    <option value="<?= $k['ID_Karyawan'] ?>"><?= htmlspecialchars($k['Nama_Karyawan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Tautkan Antrean Booking Jasa <span class="text-danger-marker">*</span></label>
                            <select id="select_booking_jasa" name="id_booking" class="form-select select2-searchable">
                                <option value="" data-tarif="0" data-id-pelanggan="">-- Tidak Menautkan Booking Jasa --</option>
                                <?php foreach($list_booking as $b): ?>
                                    <option value="<?= htmlspecialchars($b['ID_Booking']) ?>" 
                                            data-tarif="<?= (float)$b['Total_Tarif'] ?>" 
                                            data-id-pelanggan="<?= htmlspecialchars($b['ID_Pelanggan']) ?>">
                                        #<?= htmlspecialchars($b['Kode_Booking']) ?> - <?= htmlspecialchars($b['Nama_Pelanggan']) ?> (Tarif: Rp <?= number_format($b['Total_Tarif'], 0, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-title-penjualan d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="icon-box-p"><i class="fas fa-box-open"></i></div>
                            Daftar Produk <span class="text-danger-marker">*</span>
                        </div>
                        <button type="button" id="btn_tambah_barang" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" style="background: var(--accent-color-penjualan); border: none;">
                            <i class="fas fa-plus me-1"></i> Tambah Baris Barang
                        </button>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle text-center small" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;" class="text-start">Nama Produk</th>
                                    <th style="width: 12%;">Jumlah</th>
                                    <th style="width: 18%;">Harga Satuan</th>
                                    <th style="width: 15%;">Diskon Per Item (Rp)</th>
                                    <th style="width: 15%;">Subtotal</th>
                                    <th style="width: 50px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tabel_barang_tbody">
                            </tbody>
                        </table>
                    </div>

                    <div class="section-title-penjualan d-flex align-items-center">
                        <div class="icon-box-p"><i class="fas fa-calculator"></i></div>
                        Metode Pembayaran
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Total Belanja (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="subtotal_penjualan" name="subtotal_penjualan" class="form-control text-dark fw-bold bg-white" placeholder="0" min="0" readonly required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Potongan Diskon (%) <span class="text-danger-marker">*</span></label>
                            <div class="booking-input-group">
                                <input type="number" id="diskon_percent_input" class="form-control text-danger fw-bold bg-light" placeholder="0" min="0" max="100" value="0" readonly required>
                                <span class="booking-input-group-addon">%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pajak PPN (%) <span class="text-danger-marker">*</span></label>
                            <div class="booking-input-group">
                                <input type="number" id="pajak_persen" class="form-control text-warning fw-bold" placeholder="0" min="0" max="100" value="0" required>
                                <span class="booking-input-group-addon">%</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Metode Pembayaran <span class="text-danger-marker">*</span></label>
                            <select id="metode_pembayaran" name="metode_pembayaran" class="form-select" required>
                                <option value="Cash">Cash (Tunai)</option>
                                <option value="Transfer">Transfer Bank</option>
                                <option value="Qris">QRIS Elektronik</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Status Pembayaran <span class="text-danger-marker">*</span></label>
                            <input type="hidden" name="status_pembayaran" value="Belum Lunas">
                            <select id="status_pembayaran_disabled" class="form-select fw-bold text-danger bg-light" disabled>
                                <option value="Belum Lunas" selected>Belum Lunas</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-success fw-bold">Total Akhir(Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="grand_total" name="grand_total" class="form-control fw-bold text-success bg-white" value="0" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Bayar(Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="jumlah_bayar" name="jumlah_bayar" class="form-control fw-bold text-primary" placeholder="0" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-primary fw-bold">Kembalian (Rp) <span class="text-danger-marker">*</span></label>
                            <input type="number" id="kembalian" name="kembalian" class="form-control fw-bold text-primary bg-white" value="0" readonly>
                        </div>

                        <div class="col-md-12" id="upload_bukti_container" style="display: none;">
                            <label class="form-label">Bukti Pembayaran (Unggah Gambar) <span class="text-danger-marker">*</span></label>
                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" class="form-control" accept="image/*">
                            <small class="text-muted d-block mt-1">Harap lampirkan foto/screenshot struk transfer atau QRIS sebagai bukti pembayaran valid.</small>
                        </div>
                    </div>

                    <div class="section-title-penjualan d-flex align-items-center">
                        <div class="icon-box-p"><i class="fas fa-comment-alt"></i></div>
                        Catatan Transaksi <span class="text-danger-marker">*</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <textarea name="catatan_penjualan" id="catatan_penjualan" class="form-control" rows="3" minlength="20" placeholder="Catatan tambahan kasir (Wajib diisi, minimal 20 karakter)..." required></textarea>
                            <small class="text-muted d-block mt-1" id="catatan_counter">Karakter saat ini: 0/20 (Minimal 20 Karakter)</small>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal-p" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" id="btn_simpan_penjualan" class="btn btn-simpan-p">
                            <i class="fas fa-save me-2"></i>Simpan Nota Transaksi
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

<script>
const availableBarangList = <?php echo json_encode($list_barang); ?>;
const availableBookingList = <?php echo json_encode($list_booking); ?>;

$(document).ready(function() {
    // Inisialisasi Select2 pada Modal Utama
    function initSelect2() {
        $('.select2-searchable').each(function() {
            $(this).select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalTambahPenjualan'),
                width: '100%'
            });
        });
    }

    // Re-inisialisasi Select2 saat modal ditampilkan
    $('#modalTambahPenjualan').on('shown.bs.modal', function() {
        initSelect2();
    });

    const selectPelanggan       = document.getElementById('id_pelanggan_select');
    const selectBooking         = document.getElementById('select_booking_jasa');
    const inputSubtotal         = document.getElementById('subtotal_penjualan');
    const inputDiskonPersen     = document.getElementById('diskon_percent_input');
    const inputPajakPersen      = document.getElementById('pajak_persen');
    const inputGrand            = document.getElementById('grand_total');
    const inputBayar            = document.getElementById('jumlah_bayar');
    const inputKembalian        = document.getElementById('kembalian');
    
    const hiddenDiskonNominal   = document.getElementById('total_diskon_nominal');
    const hiddenPajakNominal    = document.getElementById('pajak_ppn_nominal');
    
    const selectMetode          = document.getElementById('metode_pembayaran');
    const uploadContainer       = document.getElementById('upload_bukti_container');
    const fileBukti             = document.getElementById('bukti_pembayaran');
    
    const formPenjualan         = document.getElementById('formTambahPenjualan');
    const tbodyBarang           = document.getElementById('tabel_barang_tbody');
    const btnTambahBarang       = document.getElementById('btn_tambah_barang');
    const txtCatatan            = document.getElementById('catatan_penjualan');
    const catatanCounter        = document.getElementById('catatan_counter');

    // Live counter untuk karakter catatan transaksi
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

    // Logika Otomatis Menghapus Angka 0 saat Fokus/Mulai Mengetik
    function setNumericAutoClearBehavior(inputElement) {
        inputElement.addEventListener('focus', function() {
            if (this.value === '0') {
                this.value = '';
            }
        });

        inputElement.addEventListener('blur', function() {
            if (this.value === '' || isNaN(parseFloat(this.value))) {
                this.value = '0';
            }
            hitungKalkulasiKasir();
        });

        inputElement.addEventListener('input', function() {
            // Mencegah input nilai negatif
            if (parseFloat(this.value) < 0) {
                this.value = 0;
            }
            hitungKalkulasiKasir();
        });
    }

    setNumericAutoClearBehavior(inputPajakPersen);
    setNumericAutoClearBehavior(inputBayar);

    if (selectBooking) {
        $(selectBooking).on('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tarifJasa = selectedOption ? (parseFloat(selectedOption.getAttribute('data-tarif')) || 0) : 0;
            if (tarifJasa > 0) {
                tbodyBarang.innerHTML = '';
                inputSubtotal.value = tarifJasa;
                hitungKalkulasiKasir();
            }
        });
    }

    // Penerapan aturan diskon: Premium/Member mendapat diskon 10% dan tidak bisa diedit. Non-member mendapat 0% dan tidak bisa diedit.
    if (selectPelanggan) {
        $(selectPelanggan).on('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const tipeMember = selectedOption ? (selectedOption.getAttribute('data-tipe') || '') : '';

            if (tipeMember === 'Premium' || tipeMember === 'Member') {
                inputDiskonPersen.value = 10;
            } else {
                inputDiskonPersen.value = 0;
            }
            hitungKalkulasiKasir();

            // Filter Booking berdasarkan Pelanggan Terpilih (Rebuild options secara presisi untuk Select2)
            if (selectBooking) {
                const idPelangganTerpilih = this.value;
                
                // Kosongkan dropdown booking native
                $(selectBooking).empty();
                
                // Tambahkan opsi default
                const defaultOption = document.createElement('option');
                defaultOption.value = "";
                defaultOption.setAttribute('data-tarif', '0');
                defaultOption.setAttribute('data-id-pelanggan', '');
                defaultOption.textContent = "-- Tidak Menautkan Booking Jasa --";
                selectBooking.appendChild(defaultOption);

                // Masukkan booking yang hanya dimiliki oleh pelanggan terpilih
                availableBookingList.forEach(b => {
                    if (idPelangganTerpilih !== "" && String(b.ID_Pelanggan) === String(idPelangganTerpilih)) {
                        const option = document.createElement('option');
                        option.value = b.ID_Booking;
                        option.setAttribute('data-tarif', b.Total_Tarif);
                        option.setAttribute('data-id-pelanggan', b.ID_Pelanggan);
                        
                        const formattedTarif = parseFloat(b.Total_Tarif).toLocaleString('id-ID');
                        option.textContent = `#${b.Kode_Booking} - ${b.Nama_Pelanggan} (Tarif: Rp ${formattedTarif})`;
                        selectBooking.appendChild(option);
                    }
                });
                
                // Beritahu Select2 agar melakukan refresh pilihan data yang tampil
                $(selectBooking).trigger('change');
            }
        });

        // Trigger inisialisasi awal saat pertama dimuat
        setTimeout(() => {
            $(selectPelanggan).trigger('change');
        }, 100);
    }

    function hitungKalkulasiKasir() {
        const subtotal     = parseFloat(inputSubtotal.value) || 0;
        const diskonPersen = parseFloat(inputDiskonPersen.value) || 0;
        const pajakPercent  = parseFloat(inputPajakPersen.value) || 0;

        const nominalDiskon = Math.round(subtotal * (diskonPersen / 100));
        const dpp = Math.max(0, subtotal - nominalDiskon);
        const nominalPajak = Math.round(dpp * (pajakPercent / 100));
        const grandTotal = Math.max(0, dpp + nominalPajak);

        hiddenDiskonNominal.value = nominalDiskon;
        hiddenPajakNominal.value  = nominalPajak;
        inputGrand.value          = Math.round(grandTotal);

        const jumlahBayar = parseFloat(inputBayar.value) || 0;
        if (jumlahBayar >= grandTotal) {
            inputKembalian.value = Math.round(jumlahBayar - grandTotal);
        } else {
            inputKembalian.value = 0;
        }
    }

    inputSubtotal.addEventListener('input', hitungKalkulasiKasir);

    function hitungUlangAkumulasiBarang() {
        let totalSubtotalBarang = 0;
        const barisSubtotal = tbodyBarang.querySelectorAll('.input-subtotal-row');
        
        barisSubtotal.forEach(el => {
            totalSubtotalBarang += parseFloat(el.value) || 0;
        });

        inputSubtotal.value = totalSubtotalBarang;
        hitungKalkulasiKasir();
    }

    function hitungSubtotalBaris(row) {
        const selectBrg = row.querySelector('.select-barang-row');
        const inputJml  = row.querySelector('.input-jumlah-row');
        const inputHrg  = row.querySelector('.input-harga-row');
        const inputDsk  = row.querySelector('.input-diskon-row');
        const inputSub  = row.querySelector('.input-subtotal-row');

        const selectedOption = selectBrg.options[selectBrg.selectedIndex];
        const harga = parseFloat(selectedOption.getAttribute('data-harga')) || 0;
        const jumlah = parseInt(inputJml.value) || 1;
        const diskon = parseFloat(inputDsk.value) || 0;

        inputHrg.value = harga;
        
        const subtotalBaris = Math.max(0, (harga * jumlah) - diskon);
        inputSub.value = subtotalBaris;

        hitungUlangAkumulasiBarang();
    }

    if (btnTambahBarang) {
        btnTambahBarang.addEventListener('click', function() {
            if (selectBooking && selectBooking.value !== "") {
                Swal.fire({
                    icon: 'warning',
                    title: 'Transaksi Tidak Boleh Dicampur',
                    text: 'Selesaikan transaksi booking jasa terlebih dahulu atau kosongkan pilihan booking untuk membeli barang produk.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            const tr = document.createElement('tr');
            tr.className = 'baris-barang-belanjaan';

            let optionsHtml = '<option value="" data-harga="0">-- Pilih Produk --</option>';
            availableBarangList.forEach(b => {
                optionsHtml += `<option value="${b.ID_Barang}" data-harga="${b.Harga_Jual}">${b.Nama_Barang} (Rp ${parseFloat(b.Harga_Jual).toLocaleString('id-ID')})</option>`;
            });

            tr.innerHTML = `
                <td>
                    <select name="items_barang[]" class="form-select select-barang-row select-barang-searchable text-start" required>
                        ${optionsHtml}
                    </select>
                </td>
                <td>
                    <input type="number" name="items_jumlah[]" class="form-control text-center input-jumlah-row" value="1" min="1" required>
                </td>
                <td>
                    <input type="number" name="items_harga[]" class="form-control text-end input-harga-row bg-white" value="0" readonly>
                </td>
                <td>
                    <input type="number" name="items_diskon[]" class="form-control text-end input-diskon-row" value="0" min="0">
                </td>
                <td>
                    <input type="number" name="items_subtotal[]" class="form-control text-end input-subtotal-row bg-white" value="0" readonly>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger btn-hapus-row"><i class="fas fa-trash-alt"></i></button>
                </td>
            `;

            tbodyBarang.appendChild(tr);

            // Inisialisasi Select2 pada dropdown produk baru dalam tabel
            $(tr).find('.select-barang-searchable').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalTambahPenjualan'),
                width: '100%'
            });

            const selectBrg = tr.querySelector('.select-barang-row');
            const inputJml  = tr.querySelector('.input-jumlah-row');
            const inputDsk  = tr.querySelector('.input-diskon-row');
            const btnHapus  = tr.querySelector('.btn-hapus-row');

            $(selectBrg).on('change', () => hitungSubtotalBaris(tr));
            inputJml.addEventListener('input', () => hitungSubtotalBaris(tr));
            inputDsk.addEventListener('input', () => hitungSubtotalBaris(tr));
            
            btnHapus.addEventListener('click', function() {
                $(selectBrg).select2('destroy');
                tr.remove();
                hitungUlangAkumulasiBarang();
            });
        });
    }

    if (selectMetode) {
        selectMetode.addEventListener('change', function() {
            if (this.value === 'Transfer' || this.value === 'Qris') {
                uploadContainer.style.display = 'block';
                fileBukti.setAttribute('required', 'required');
            } else {
                uploadContainer.style.display = 'none';
                fileBukti.removeAttribute('required');
                fileBukti.value = '';
            }
        });
    }

    if (formPenjualan) {
        formPenjualan.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validasi Frontend: Panjang karakter catatan transaksi
            const isiCatatan = txtCatatan.value.trim();
            if (isiCatatan.length < 20) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Catatan Transaksi Kurang Detail',
                    text: 'Silakan isi catatan transaksi minimal sebanyak 20 karakter sebelum menyimpan.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            const subtotal = parseFloat(inputSubtotal.value) || 0;
            if (subtotal <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Subtotal Belanja Kosong',
                    text: 'Silakan tambahkan produk belanjaan atau tautkan booking jasa terlebih dahulu.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            const btnSimpan = document.getElementById('btn_simpan_penjualan');
            const originalText = btnSimpan.innerHTML;
            btnSimpan.disabled = true;
            btnSimpan.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';

            const formData = new FormData(formPenjualan);

            fetch('penjualan_create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modalEl = document.getElementById('modalTambahPenjualan');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    formPenjualan.reset();
                    tbodyBarang.innerHTML = '';
                    uploadContainer.style.display = 'none';
                    fileBukti.removeAttribute('required');
                    
                    // Reset dropdown pencarian
                    $('.select2-searchable').val(null).trigger('change');
                    hitungKalkulasiKasir();

                    Swal.fire({
                        icon: 'success',
                        title: 'Transaksi Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#059669',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        if (typeof window.performSearchAndFilter === "function") {
                            window.performSearchAndFilter();
                        } else {
                            window.location.href = 'penjualan_read.php';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menyimpan',
                        text: data.message,
                        html: data.error_details ? `<div class="text-start mt-3 p-3 bg-light border rounded font-monospace small" style="max-height:180px; overflow-y:auto; white-space:pre-wrap; font-size:0.8rem;"><strong>Detail Kesalahan Server:</strong><br>${data.error_details}</div>` : '',
                        confirmButtonColor: '#059669'
                    });
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Terputus',
                    text: 'Terjadi masalah jaringan saat mengirim data transaksi ke server.',
                    confirmButtonColor: '#059669'
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
