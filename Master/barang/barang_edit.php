

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// 1. Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../dashboard/index.php");
    exit;
}

// Fungsi helper untuk inisial nama barang jika foto lama kosong
if (!function_exists('getInitialsBarangEdit')) {
    function getInitialsBarangEdit($name) {
        $words = explode(" ", trim($name));
        $initials = "";
        if (isset($words[0])) {
            $initials .= substr($words[0], 0, 1);
        }
        if (count($words) > 1 && isset($words[count($words) - 1])) {
            $initials .= substr($words[count($words) - 1], 0, 1);
        }
        return strtoupper($initials);
    }
}

$id = $_GET['id'] ?? $_POST['id'] ?? null;
$data = null;

// 2. Ambil data lama berdasarkan ID
if ($id) {
    $sql_ambil = "SELECT * FROM Barang WHERE ID_Barang = ? AND Bar_is_deleted = 0";
    $params_ambil = array($id);
    $query_ambil = sqlsrv_query($conn, $sql_ambil, $params_ambil);
    
    if ($query_ambil === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $data = sqlsrv_fetch_array($query_ambil, SQLSRV_FETCH_ASSOC);
}

// 3. Proses Update Data (Menggunakan POST AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && $data) {
    // Bersihkan buffer output sebelum mengirimkan JSON agar spasi/error PHP tidak merusak format JSON
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    $kode_barang = $_POST['Kode_Barang'] ?? '';
    $id_kat      = $_POST['ID_Kategori'] ?? '';
    $nama        = $_POST['Nama_Barang'] ?? '';
    $harga_beli  = $_POST['Harga_Beli'] ?? 0;
    $harga_jual  = $_POST['Harga_Jual'] ?? 0;
    $stok        = $_POST['Stok'] ?? 0;
    $stok_min    = $_POST['Stok_Minimum'] ?? 0;
    $satuan      = $_POST['Satuan'] ?? '';
    $deskripsi   = $_POST['Deskripsi'] ?? '';
    $modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    $error_message = "";
    $upload_ok = true;

    // Cek duplikasi Nama Barang & Kode Barang (SKU) kecuali milik sendiri
    $sql_cek = "SELECT 
                    (SELECT COUNT(*) FROM Barang WHERE Nama_Barang = ? AND ID_Barang != ? AND Bar_is_deleted = 0) AS nama_exist,
                    (SELECT COUNT(*) FROM Barang WHERE Kode_Barang = ? AND ID_Barang != ? AND Bar_is_deleted = 0) AS kode_exist";
                    
    $query_cek = sqlsrv_query($conn, $sql_cek, array($nama, $id, $kode_barang, $id));
    
    if ($query_cek === false) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memvalidasi duplikasi data ke database.']);
        exit;
    }
    
    $row_check = sqlsrv_fetch_array($query_cek, SQLSRV_FETCH_ASSOC);

    if ($row_check['nama_exist'] > 0) {
        $error_message = 'Nama produk sudah terdaftar! Gunakan nama produk lain.';
        $upload_ok = false;
    } elseif ($row_check['kode_exist'] > 0) {
        $error_message = 'Kode SKU / Barcode produk sudah digunakan barang lain! Gunakan kode lain.';
        $upload_ok = false;
    }
    // VALIDASI: Batasi harga dan stok agar tidak negatif
    elseif ($harga_beli < 0 || $harga_jual < 0 || $stok < 0 || $stok_min < 0) {
        $error_message = 'Harga Beli, Harga Jual, Stok, dan Stok Minimum tidak boleh bernilai negatif!';
        $upload_ok = false;
    }
    // VALIDASI: Rugi (Harga beli > Harga jual)
    elseif ($harga_beli > $harga_jual) {
        $error_message = 'Rugi! Harga Beli (Modal) tidak boleh lebih besar dari Harga Jual.';
        $upload_ok = false;
    }

    $params = array($id_kat, $kode_barang, $nama, $harga_beli, $harga_jual, $stok, $stok_min, $deskripsi, $satuan, $modified_by);
    $foto_query = "";

    if ($upload_ok) {
        $has_new_file = isset($_FILES['Foto_Barang']) && $_FILES['Foto_Barang']['error'] == 0;
        $has_old_file = !empty($data['Foto_Barang']);

        // Logika Pengunggahan & Validasi Foto Barang Baru
        if ($has_new_file) {
            $target_dir = "../../uploads/barang/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_name = $_FILES['Foto_Barang']['name'];
            $file_size = $_FILES['Foto_Barang']['size'];
            $file_tmp  = $_FILES['Foto_Barang']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = array("jpg", "jpeg", "png", "webp");

            if (!in_array($file_ext, $allowed_extensions)) {
                $upload_ok = false;
                $error_message = "Format berkas foto tidak didukung! Gunakan format JPG, JPEG, PNG, atau WEBP.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $upload_ok = false;
                $error_message = "Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.";
            } else {
                $foto_name = "barang_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $target_file = $target_dir . $foto_name;
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    // Hapus file lama jika ada
                    if ($has_old_file && file_exists($target_dir . $data['Foto_Barang'])) {
                        unlink($target_dir . $data['Foto_Barang']);
                    }
                    $foto_query = ", Foto_Barang = ?";
                    $params[] = $foto_name;
                } else {
                    $upload_ok = false;
                    $error_message = "Gagal mengunggah foto produk baru ke server.";
                }
            }
        } else {
            // Jika tidak ada foto baru diunggah dan foto lama kosong, wajib unggah foto
            if (!$has_old_file) {
                $upload_ok = false;
                $error_message = "Foto produk wajib diunggah karena produk ini belum memiliki foto!";
            }
        }
    }

    if ($upload_ok) {
        $params[] = $id;

        // UPDATE Query
        $sql_up = "UPDATE Barang SET 
                    ID_Kategori = ?, 
                    Kode_Barang = ?,
                    Nama_Barang = ?, 
                    Harga_Beli = ?,
                    Harga_Jual = ?, 
                    Stok = ?, 
                    Stok_Minimum = ?, 
                    Deskripsi = ?, 
                    Satuan = ?, 
                    Bar_modified_by = ?, 
                    Bar_modified_date = GETDATE()
                    $foto_query
                   WHERE ID_Barang = ?";

        $stmt = sqlsrv_query($conn, $sql_up, $params);

        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Informasi produk berhasil diperbarui!']);
        } else {
            $errors = sqlsrv_errors();
            $db_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err['message']);
                    $db_err .= trim($clean_msg) . " ";
                }
            } else {
                $db_err = 'Terjadi kesalahan sistem saat memperbarui data.';
            }
            echo json_encode(['status' => 'error', 'message' => $db_err]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    }
    exit;
}
?>

<!-- MEMASTIKAN PUSTAKA SWEETALERT, JQUERY, DAN SELECT2 SIAP -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE KHUSUS MODAL EDIT BARANG -->
<style>
    :root { 
        --primary-gradient-barang: linear-gradient(135deg, #059669 0%, #10b981 100%);
        --accent-color-barang: #10b981; 
        --dark-emerald: #059669;
        --border-color-barang: #cbd5e1;
        --text-danger: #ef4444;
    }

    #modalEditBarang {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalEditBarang {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInBarang {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalEditBarang.show .modal-content-custom {
        animation: modalZoomInBarang 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-edit-barang { 
        background: var(--primary-gradient-barang); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-edit-barang i {
        animation: pulseBarang 2.5s infinite;
    }

    @keyframes pulseBarang {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalEditBarang .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-barang { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--dark-emerald); 
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
        border: 1.5px solid var(--border-color-barang); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-barang);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        outline: none;
    }

    .select2-container {
        z-index: 9999999 !important;
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-barang) !important;
        border-radius: 0.75rem !important;
        background-color: #f8fafc !important;
        display: flex;
        align-items: center;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #0f172a !important;
        font-size: 0.9rem !important;
        padding-left: 15px !important;
    }

    .select2-container--open.select2-container--default .select2-selection--single {
        border-color: var(--accent-color-barang) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-barang) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
        z-index: 9999999 !important;
        overflow: hidden;
    }

    .select2-search--dropdown {
        padding: 0.6rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
    }

    .select2-search--dropdown::after {
        content: "\f002"; 
        font-family: "Font Awesome 5 Free", "Font Awesome 6 Free", sans-serif;
        font-weight: 900;
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 0.85rem;
    }

    .select2-search--dropdown .select2-search__field {
        border: 1.5px solid var(--border-color-barang) !important;
        border-radius: 0.6rem !important;
        padding: 0.55rem 2.2rem 0.55rem 0.9rem !important;
        font-size: 0.875rem !important;
        outline: none !important;
        background: #ffffff !important;
        color: #0f172a;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent-color-barang) !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .select2-results__options {
        max-height: 200px !important;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-barang) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #ecfdf5 !important;
        color: var(--dark-emerald) !important;
        font-weight: 600;
    }

    .input-group-custom {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .input-group-custom .input-group-text-custom {
        background-color: #cbd5e1;
        border: 1.5px solid var(--border-color-barang);
        border-right: none;
        color: #334155;
        font-weight: 700;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-top-left-radius: 0.75rem;
        border-bottom-left-radius: 0.75rem;
        transition: all 0.25s ease;
    }

    .input-group-custom .form-control {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        flex: 1 1 auto;
        width: 1%;
    }

    .input-group-custom:focus-within .input-group-text-custom {
        border-color: var(--accent-color-barang);
        background-color: #e2e8f0;
    }

    .btn-simpan-barang { 
        background: var(--primary-gradient-barang); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan-barang:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal-barang {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .btn-batal-barang:hover {
        background-color: #f1f5f9;
        transform: translateY(-1px);
    }

    .icon-box-barang { 
        width: 32px; 
        height: 32px; 
        background: #ecfdf5; 
        color: var(--dark-emerald); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 8px; 
        margin-right: 12px; 
        font-size: 0.9rem;
    }

    .avatar-wrapper {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: 1rem;
        border: 2px dashed #cbd5e1;
    }

    .avatar-preview-circle {
        width: 85px;
        height: 85px;
        border-radius: 50%;
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        font-size: 2.25rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        border: 3px solid #ffffff;
        outline: 3px solid #e2e8f0;
        overflow: hidden;
        flex-shrink: 0;
    }

    .avatar-preview-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .audit-tag { 
        font-size: 0.7rem; 
        color: #94a3b8; 
        font-style: italic; 
    }
</style>

<!-- RENDER MODAL HANYA JIKA DATA BERHASIL DIAMBIL -->
<?php if ($data): ?>
<div class="modal fade" id="modalEditBarang" tabindex="-1" aria-labelledby="modalEditBarangLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-edit-barang">
                <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-edit fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Edit Data Produk</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">ID Barang: #BRG-<?= $data['ID_Barang'] ?></p>
            </div>

            <form id="formEditBarang" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                
                <div class="form-container">
                    
                    <!-- BAGIAN 1: IDENTITAS PRODUK -->
                    <div class="section-title-barang d-flex align-items-center">
                        <div class="icon-box-barang"><i class="fas fa-tag"></i></div>
                        Identitas Fisik Produk
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">SKU / Kode Barang<span class="text-danger-marker">*</span></label>
                            <input type="text" name="Kode_Barang" class="form-control" value="<?= htmlspecialchars($data['Kode_Barang']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap Barang<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_brg_nama" name="Nama_Barang" class="form-control" value="<?= htmlspecialchars($data['Nama_Barang']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategori Produk<span class="text-danger-marker">*</span></label>
                            <select name="ID_Kategori" class="form-select select2-enable" required>
                                <?php
                                $sql_kat = "SELECT * FROM Kategori WHERE Tipe_Kategori = 'Barang' ORDER BY Nama_Kategori ASC";
                                $query_kat = sqlsrv_query($conn, $sql_kat);
                                while($kat = sqlsrv_fetch_array($query_kat, SQLSRV_FETCH_ASSOC)) {
                                    $selected = ($kat['ID_Kategori'] == $data['ID_Kategori']) ? 'selected' : '';
                                    echo "<option value='".$kat['ID_Kategori']."' $selected>".$kat['Nama_Kategori']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satuan Ukuran<span class="text-danger-marker">*</span></label>
                            <select name="Satuan" class="form-select select2-enable" required>
                                <option value="" disabled>Pilih Satuan...</option>
                                <?php
                                $satuan_list = ["Buah", "Pak", "Bungkus", "Karung", "Pcs", "Botol", "Sachet", "Kg", "Gram", "Liter"];
                                foreach ($satuan_list as $sat) {
                                    $selected = (strtolower($data['Satuan']) == strtolower($sat)) ? 'selected' : '';
                                    echo "<option value='$sat' $selected>$sat</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- BAGIAN 2: REGULASI HARGA & STOK -->
                    <div class="section-title-barang d-flex align-items-center">
                        <div class="icon-box-barang"><i class="fas fa-coins"></i></div>
                        Finansial & Regulasi Stok
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Harga Beli Awal<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-group-text-custom">Rp</span>
                                <input type="number" id="edit_brg_harga_beli" name="Harga_Beli" class="form-control" value="<?= (int)$data['Harga_Beli'] ?>" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga Jual Produk<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-group-text-custom">Rp</span>
                                <input type="number" id="edit_brg_harga_jual" name="Harga_Jual" class="form-control" value="<?= (int)$data['Harga_Jual'] ?>" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Stok Saat Ini<span class="text-danger-marker">*</span></label>
                            <input type="number" name="Stok" class="form-control" value="<?= $data['Stok'] ?>" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stok Minimum (Batas Minimum)<span class="text-danger-marker">*</span></label>
                            <input type="number" name="Stok_Minimum" class="form-control" value="<?= $data['Stok_Minimum'] ?>" min="0" required>
                        </div>
                    </div>

                    <!-- BAGIAN 3: VISUAL & RINCIAN DESKRIPSI -->
                    <div class="section-title-barang d-flex align-items-center">
                        <div class="icon-box-barang"><i class="fas fa-image"></i></div>
                        Visual & Rincian Deskripsi
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Deskripsi / Spesifikasi Lengkap<span class="text-danger-marker">*</span></label>
                            <textarea name="Deskripsi" class="form-control" rows="3" required><?= htmlspecialchars($data['Deskripsi']) ?></textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="edit-avatar-container" class="avatar-preview-circle">
                                    <span id="edit-avatar-initials">?</span>
                                    <img id="edit-avatar-image-preview" src="" alt="Pratinjau Foto">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Ganti Foto Barang<?= empty($data['Foto_Barang']) ? '<span class="text-danger-marker">*</span>' : '' ?></label>
                                    <input type="file" id="edit-foto-input" name="Foto_Barang" class="form-control" accept="image/*" <?= empty($data['Foto_Barang']) ? 'required' : '' ?>>
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG, WEBP</strong>. Maksimal ukuran berkas: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal-barang" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <div class="text-end">
                            <button type="submit" name="update" class="btn btn-simpan-barang">
                                <i class="fas fa-save me-2"></i>Update Data Produk
                            </button>
                            <div class="audit-tag mt-2">
                                Pembaruan terakhir oleh: <?= htmlspecialchars($data['Bar_modified_by'] ?: $data['Bar_created_by'] ?: 'Sistem') ?>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Mencegah focus trap Bootstrap menghalangi pencarian Select2
        document.addEventListener('focusin', function(e) {
            if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
                e.stopImmediatePropagation();
            }
        }, true);

        // INISIALISASI SELECT2 (TEMA EMERALD)
        $('.select2-enable').select2({
            dropdownParent: $(document.body),
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                searching: function () { return 'Mencari...'; },
                noResults: function () { return 'Data tidak ditemukan'; },
                errorLoading: function () { return 'Gagal memuat data'; }
            }
        });

        $('#modalEditBarang').on('shown.bs.modal', function() {
            $(document).off('focusin.bs.modal');
        });

        // FUNGSI PEMBERSIH URL DARI PARAMETER ?id= SAAT MODAL DITUTUP
        function hapusIdDariUrl() {
            const url = new URL(window.location);
            url.searchParams.delete('id');
            window.history.pushState({}, '', url);
        }

        var modalEl = document.getElementById('modalEditBarang');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                hapusIdDariUrl();
                window.location.href = 'barang_tampil.php';
            });
        }

        // --- AVATAR PREVIEW DENGAN INISIAL NAMA ---
        const namaInput = document.getElementById('edit_brg_nama');
        const initialsSpan = document.getElementById('edit-avatar-initials');
        const imagePreview = document.getElementById('edit-avatar-image-preview');
        const fileInput = document.getElementById('edit-foto-input');
        const form = document.getElementById('formEditBarang');
        
        const dbPhoto = <?= json_encode($data['Foto_Barang'] ?? ''); ?>;

        function updateInitials() {
            if (imagePreview.style.display === 'block') return;

            const nama = namaInput.value.trim();
            if (nama === "") {
                initialsSpan.textContent = "?";
                return;
            }

            const parts = nama.split(/\s+/);
            let initials = parts[0].charAt(0);
            if (parts.length > 1) {
                initials += parts[parts.length - 1].charAt(0);
            }
            initialsSpan.textContent = initials.toUpperCase();
        }

        // Inisialisasi awal avatar
        if (dbPhoto && dbPhoto !== '') {
            imagePreview.src = '../../uploads/barang/' + dbPhoto;
            imagePreview.style.display = 'block';
            initialsSpan.style.display = 'none';
        } else {
            imagePreview.style.display = 'none';
            initialsSpan.style.display = 'block';
            updateInitials();
        }

        if (namaInput) {
            namaInput.addEventListener('input', updateInitials);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    const fileExtension = file.name.split('.').pop().toLowerCase();

                    if (!allowedExtensions.includes(fileExtension)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Format File Salah',
                            text: 'Hanya format JPG, JPEG, PNG, dan WEBP yang diperbolehkan!',
                            confirmButtonColor: '#10b981'
                        });
                        this.value = '';
                        imagePreview.style.display = 'none';
                        initialsSpan.style.display = 'block';
                        updateInitials();
                        return;
                    }

                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Ukuran Berkas Terlalu Besar',
                            text: 'Batas maksimum ukuran berkas foto adalah 2 MB.',
                            confirmButtonColor: '#10b981'
                        });
                        this.value = '';
                        imagePreview.style.display = 'none';
                        initialsSpan.style.display = 'block';
                        updateInitials();
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                        initialsSpan.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                } else {
                    if (dbPhoto && dbPhoto !== '') {
                        imagePreview.src = '../../uploads/barang/' + dbPhoto;
                        imagePreview.style.display = 'block';
                        initialsSpan.style.display = 'none';
                    } else {
                        imagePreview.style.display = 'none';
                        initialsSpan.style.display = 'block';
                        updateInitials();
                    }
                }
            });
        }

        // SUBMIT AJAX LANGSUNG KE BARANG_EDIT.PHP DENGAN DIAGNOSA DETAILED ERROR
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const hargaBeli = parseFloat(document.getElementById('edit_brg_harga_beli').value) || 0;
                const hargaJual = parseFloat(document.getElementById('edit_brg_harga_jual').value) || 0;
                const stok = parseInt(form.elements['Stok'].value) || 0;
                const stokMin = parseInt(form.elements['Stok_Minimum'].value) || 0;
                const hasOldPhoto = dbPhoto && dbPhoto !== '';

                // 1. Validasi Angka Negatif
                if (hargaBeli < 0 || hargaJual < 0 || stok < 0 || stokMin < 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Input Tidak Valid',
                        text: 'Harga Beli, Harga Jual, Stok, dan Stok Minimum tidak boleh bernilai negatif.',
                        confirmButtonColor: '#10b981'
                    });
                    return;
                }

                // 2. Validasi Rugi
                if (hargaBeli > hargaJual) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Rugi! Input Tidak Valid',
                        text: 'Harga Beli (Modal) tidak boleh lebih besar dari Harga Jual! Silakan sesuaikan kembali harga jual produk.',
                        confirmButtonColor: '#10b981'
                    });
                    return;
                }

                // 3. Validasi Keberadaan Foto
                if (!hasOldPhoto && (!fileInput.files || fileInput.files.length === 0)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Foto Wajib Diunggah',
                        text: 'Silakan pilih foto produk terlebih dahulu sebelum memperbarui data.',
                        confirmButtonColor: '#10b981'
                    });
                    return;
                }

                const formData = new FormData(form);
                formData.append('update', '1');

                Swal.fire({
                    title: 'Sedang Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // TARGET FETCH KE 'barang_edit.php' (TIDAK MENGGUNAKAN window.location.href)
                fetch('barang_edit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        const resData = JSON.parse(text);
                        if (resData.status === 'success') {
                            var modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            hapusIdDariUrl();

                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: resData.message,
                                confirmButtonColor: '#059669',
                                timer: 2000,
                                timerProgressBar: true,
                                willClose: () => {
                                    window.location.href = 'barang_tampil.php';
                                }
                            }).then(() => {
                                window.location.href = 'barang_tampil.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Menyimpan',
                                text: resData.message,
                                confirmButtonColor: '#10b981'
                            });
                        }
                    } catch (e) {
                        // DIAGNOSTIK ERROR: Jika JSON gagal diparse, tampilkan pesan error mentah dari server ke SweetAlert
                        console.error("Kesalahan Parsing JSON. Respon mentah server:", text);
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan Sistem',
                            html: '<div class="text-start">' +
                                  '<p>Terjadi kesalahan parsing data dari server. Respon mentah:</p>' +
                                  '<pre style="background: #f1f5f9; padding: 10px; border-radius: 5px; font-size: 0.75rem; max-height: 200px; overflow-y: auto; text-wrap: wrap;">' + 
                                  text.replace(/</g, "&lt;").replace(/>/g, "&gt;") + 
                                  '</pre></div>',
                            confirmButtonColor: '#10b981'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Jaringan',
                        text: 'Terjadi kesalahan sistem atau database saat menghubungi server.',
                        confirmButtonColor: '#10b981'
                    });
                });
            });
        }

        var modalEdit = new bootstrap.Modal(modalEl);
        modalEdit.show();
    });
</script>
<?php endif; ?>
