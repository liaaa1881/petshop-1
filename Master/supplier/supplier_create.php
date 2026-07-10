
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../dashboard/index.php");
    exit;
}

$error_message = "";
$success_message = "";

if (isset($_POST['simpan'])) {
    $nama            = $_POST['nama'];
    $telp            = $_POST['telp'];
    $email           = $_POST['email'];
    
    $alamat          = $_POST['alamat'];
    $kelurahan       = $_POST['kelurahan'];
    $kecamatan       = $_POST['kecamatan'];
    $kota_kabupaten  = $_POST['kota_kabupaten'];
    $provinsi        = $_POST['provinsi'];
    $kode_pos        = $_POST['kode_pos'];
    
    $nama_cp         = $_POST['nama_cp'];
    $jabatan_cp      = $_POST['jabatan_cp'];
    $no_telepon_cp   = $_POST['no_telepon_cp'];
    $email_cp        = $_POST['email_cp'];
    
    $nama_bank       = $_POST['nama_bank'];
    $no_rekening     = $_POST['no_rekening'];
    $atas_nama       = $_POST['atas_nama_rekening'];
    
    $user            = $_POST['user'];
    $pass            = $_POST['pass'];
    $pass_konf       = $_POST['pass_konfirm']; 
    
    // Data Audit
    $status     = 'Aktif';
    $created_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // --- VALIDASI SERVER-SIDE PHP (MUTLAK & AMAN) ---
    if (empty($_FILES['foto']['name'])) {
        $error_message = 'Foto atau Logo mitra supplier wajib diunggah!';
    } elseif ($pass !== $pass_konf) {
        $error_message = 'Kata Sandi dan Konfirmasi Kata Sandi tidak cocok!';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pass)) {
        $error_message = 'Kata Sandi minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka!';
    } elseif (!preg_match("/^[a-zA-Z0-9\s.,\-()]+$/", $nama)) {
        $error_message = 'Nama perusahaan/supplier tidak valid!';
    } elseif (strlen($nama) < 3 || strlen($nama) > 100) {
        $error_message = 'Nama perusahaan/supplier harus berada di kisaran 3 sampai 100 karakter!';
    } elseif (!preg_match("/^[a-zA-Z]+$/", $user)) {
        $error_message = 'Nama pengguna hanya boleh diisi oleh huruf alfabet tanpa angka atau spasi!';
    } elseif (strlen($user) < 5 || strlen($user) > 20) {
        $error_message = 'Nama pengguna harus berada di kisaran 5 sampai 20 karakter!';
    } elseif (!preg_match('/^(\+\d{1,3}\d{8,12}|0\d{8,13})$/', $telp)) {
        $error_message = 'Nomor telepon kantor tidak valid! Gunakan format internasional, contoh: +62812xxxxxxx atau 0812xxxxxxx.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format alamat surel kantor tidak valid!';
    } elseif (strlen($kode_pos) !== 5 || !ctype_digit($kode_pos)) {
        $error_message = 'Kode Pos tidak valid! Harus berupa angka dan tepat 5 digit.';
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $nama_cp)) {
        $error_message = 'Nama CP hanya boleh diisi oleh huruf alfabet!';
    } elseif (strlen($nama_cp) < 3 || strlen($nama_cp) > 50) {
        $error_message = 'Nama CP harus berada di kisaran 3 sampai 50 karakter!';
    } elseif (!preg_match('/^(\+\d{1,3}\d{8,12}|0\d{8,13})$/', $no_telepon_cp)) {
        $error_message = 'Nomor telepon CP tidak valid!';
    } elseif (!filter_var($email_cp, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format alamat surel CP tidak valid!';
    } elseif (!ctype_digit($no_rekening)) {
        $error_message = 'Nomor rekening bank harus berupa angka!';
    } else {
        // Cek format alamat kantor
        if (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat) || strlen($alamat) < 20) {
            $error_message = 'Alamat kantor tidak valid! Harus diawali dengan "Jl." atau "Jalan" dan minimal 20 karakter.';
        }

        if (empty($error_message)) {
            $foto_baru = null; 
            $upload_ok = true;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto_name = $_FILES['foto']['name'];
                $tmp_name  = $_FILES['foto']['tmp_name'];
                $ekstensi  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $ekstensi_diperbolehkan = array('jpg', 'jpeg', 'png');

                if (in_array($ekstensi, $ekstensi_diperbolehkan)) {
                    $foto_baru = "sup_" . time() . "." . $ekstensi;
                    $target_dir = "../../assets/uploads/supplier/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    move_uploaded_file($tmp_name, $target_dir . $foto_baru);
                } else {
                    $upload_ok = false;
                    $error_message = 'Format berkas foto tidak valid! Gunakan JPG, JPEG atau PNG.';
                }
            }

            if ($upload_ok && empty($error_message)) {
                $pass_hashed = password_hash($pass, PASSWORD_DEFAULT);

                // Eksekusi penambahan data menggunakan Stored Procedure via CALL syntax
                $sql = "{CALL sp_Supplier_Create(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
                
                $params = array(
                    $nama, 
                    $telp, 
                    $email, 
                    !empty($alamat) ? $alamat : null, 
                    !empty($kelurahan) ? $kelurahan : null, 
                    !empty($kecamatan) ? $kecamatan : null, 
                    !empty($kota_kabupaten) ? $kota_kabupaten : null, 
                    !empty($provinsi) ? $provinsi : null, 
                    !empty($kode_pos) ? $kode_pos : null,
                    !empty($nama_cp) ? $nama_cp : null, 
                    !empty($jabatan_cp) ? $jabatan_cp : null, 
                    !empty($no_telepon_cp) ? $no_telepon_cp : null, 
                    !empty($email_cp) ? $email_cp : null, 
                    !empty($nama_bank) ? $nama_bank : null, 
                    !empty($no_rekening) ? $no_rekening : null, 
                    !empty($atas_nama) ? $atas_nama : null,
                    !empty($user) ? $user : null, 
                    $pass_hashed, 
                    $foto_baru, 
                    $status, 
                    $created_by
                );
                
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    if ($errors !== null) {
                        $raw_error = $errors[0]['message'];
                        $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
                    } else {
                        $error_message = 'Terjadi kesalahan sistem saat menghubungi database.';
                    }
                } else {
                    $has_errors = false;
                    do {
                        $errors = sqlsrv_errors();
                        if ($errors !== null) {
                            $raw_error = $errors[0]['message'];
                            $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
                            $has_errors = true;
                            break;
                        }
                    } while (sqlsrv_next_result($stmt));

                    if (!$has_errors) {
                        $success_message = 'Mitra Supplier berhasil ditambahkan!';
                    }
                    sqlsrv_free_stmt($stmt);
                }
            }
        }
    }
}
?>

<!-- PUSTAKA UTAMA SWEETALERT, FONTAWESOME, JQUERY, DAN SELECT2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE CSS EKSLUSIF MODAL TAMBAH SUPPLIER -->
<style>
    :root { 
        --primary-gradient-supplier: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        --accent-color-supplier: #f59e0b; 
        --border-color-supplier: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahSupplier {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalTambahSupplier {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInSupplier {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahSupplier.show .modal-content-custom {
        animation: modalZoomInSupplier 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-supplier { 
        background: var(--primary-gradient-supplier); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
        border-top-left-radius: 1.5rem;
        border-top-right-radius: 1.5rem;
    }

    .header-bg-supplier i {
        animation: pulseSupplier 2.5s infinite;
    }

    @keyframes pulseSupplier {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahSupplier .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-supplier { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-supplier); 
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
        border: 1.5px solid var(--border-color-supplier); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-supplier);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        outline: none;
    }

    /* SELECT2 MODIFIKASI AGAR MATCH DENGAN BOOTSTRAP */
    .select2-container {
        z-index: 9999999 !important;
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-supplier) !important;
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
        border-color: var(--accent-color-supplier) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .select2-container--open.select2-container--default.select2-container--above .select2-selection--single {
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        border-bottom-left-radius: 0.75rem !important;
        border-bottom-right-radius: 0.75rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #94a3b8 transparent transparent transparent !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent var(--accent-color-supplier) transparent !important;
    }

    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-supplier) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
        z-index: 9999999 !important;
        overflow: hidden;
        animation: select2FadeIn 0.1s ease-out;
    }

    .select2-container--open .select2-dropdown--below {
        border-top: none !important;
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        margin-top: -1px !important;
    }

    .select2-container--open .select2-dropdown--above {
        border-bottom: none !important;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        margin-top: 1px !important;
    }

    @keyframes select2FadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
        border: 1.5px solid var(--border-color-supplier) !important;
        border-radius: 0.6rem !important;
        padding: 0.55rem 2.2rem 0.55rem 0.9rem !important;
        font-size: 0.875rem !important;
        outline: none !important;
        background: #ffffff !important;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent-color-supplier) !important;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
    }

    .select2-results__options {
        max-height: 260px !important;
        padding: 0.35rem;
    }

    .select2-results__options::-webkit-scrollbar {
        width: 7px;
    }
    .select2-results__options::-webkit-scrollbar-track {
        background: transparent;
    }
    .select2-results__options::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    .select2-results__options::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .select2-results__option {
        border-radius: 0.5rem !important;
        padding: 0.55rem 0.75rem !important;
        font-size: 0.875rem !important;
        color: #334155 !important;
        margin-bottom: 2px;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-supplier) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #fff7ed !important;
        color: var(--accent-color-supplier) !important;
        font-weight: 600;
    }

    .select2-results__message {
        color: #94a3b8 !important;
        font-size: 0.85rem !important;
        padding: 0.75rem !important;
        text-align: center;
    }

    .select2-container--default .select2-results__option .select2-results__option--disabled {
        display: none;
    }

    /* EYE BUTTON/APPEND STYLE */
    .input-group-custom {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        width: 100%;
    }

    .input-group-custom .form-control {
        flex: 1 1 auto;
        width: 1%;
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .btn-toggle-pass {
        border: 1.5px solid var(--border-color-supplier);
        border-left: none;
        background-color: #f8fafc;
        color: #64748b;
        border-top-right-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
        padding: 0.75rem 1.2rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-toggle-pass:hover {
        background-color: #cbd5e1;
        color: #0f172a;
    }

    .input-group-custom:focus-within .form-control {
        border-color: var(--accent-color-supplier);
    }

    .input-group-custom:focus-within .btn-toggle-pass {
        border-color: var(--accent-color-supplier);
        background-color: #ffffff;
    }

    .btn-simpan { 
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
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

    .btn-batal:hover {
        background-color: #f1f5f9;
        transform: translateY(-1px);
    }

    .icon-box { 
        width: 32px; 
        height: 32px; 
        background: #fff7ed; 
        color: var(--accent-color-supplier); 
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
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        display: none;
    }
</style>

<!-- MODAL CONTAINER (PENGHAPUSAN tabindex="-1" AGAR DROPDOWN SEARCH SELECT2 BISA DIKETIK SECARA NORMAL DAN AMAN) -->
<div class="modal fade" id="modalTambahSupplier" aria-labelledby="modalTambahSupplierLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-supplier">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-truck-moving fa-3x mb-3 text-warning"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Tambah Mitra Supplier</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Registrasi kemitraan dan akses portal supplier baru</p>
            </div>

            <form id="formTambahSupplier" action="" method="POST" enctype="multipart/form-data">
                <!-- INPUT HIDDEN UNTUK DETEKSI POS METHOD -->
                <input type="hidden" name="simpan" value="1">

                <div class="form-container">
                    
                    <!-- BAGIAN 1: DATA PERUSAHAAN -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-building"></i></div>
                        Informasi Profil Perusahaan / Supplier
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Perusahaan / Supplier<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_nama" name="nama" class="form-control" placeholder="Contoh: PT. Pakan Hewan Indonesia" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon Kantor (Maksimal 15 Digit)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_telp" name="telp" class="form-control" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Alamat Surel Resmi Perusahaan<span class="text-danger-marker">*</span></label>
                            <input type="email" id="sup_email" name="email" class="form-control" placeholder="supplier@surel.com" required>
                        </div>
                    </div>

                    <!-- BAGIAN 2: ALAMAT KANTOR (CASCADING DROPDOWNS VIA API INDONESIA) -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-map-marked-alt"></i></div>
                        Informasi Alamat Kantor
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Alamat Lengkap Kantor (Mulai Jl./Jalan, min 20 char)<span class="text-danger-marker">*</span></label>
                            <textarea id="sup_alamat" name="alamat" class="form-control" rows="2" placeholder="Jalan Industri No. 123..." required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provinsi<span class="text-danger-marker">*</span></label>
                            <select id="provinsi" name="provinsi" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Provinsi</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kota / Kabupaten<span class="text-danger-marker">*</span></label>
                            <select id="kota_kabupaten" name="kota_kabupaten" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kota/Kabupaten</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kecamatan<span class="text-danger-marker">*</span></label>
                            <select id="kecamatan" name="kecamatan" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kecamatan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kelurahan<span class="text-danger-marker">*</span></label>
                            <select id="kelurahan" name="kelurahan" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kelurahan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kode Pos<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_kodepos" name="kode_pos" class="form-control" maxlength="5" placeholder="5 Digit Angka" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                    </div>

                    <!-- BAGIAN 3: DETAIL KONTAK PIC (CP) -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-address-book"></i></div>
                        Detail Kontak Narahubung (CP)
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Narahubung (CP)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_nama_cp" name="nama_cp" class="form-control" placeholder="Masukkan Nama Lengkap CP" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan CP<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_jabatan_cp" name="jabatan_cp" class="form-control" placeholder="Contoh: Manajer Penjualan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon CP<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_telp_cp" name="no_telepon_cp" class="form-control" placeholder="Contoh: +62812xxxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat Surel CP<span class="text-danger-marker">*</span></label>
                            <input type="email" id="sup_email_cp" name="email_cp" class="form-control" placeholder="narahubung@surel.com" required>
                        </div>
                    </div>

                    <!-- BAGIAN 4: INFORMASI REKENING BANK -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-wallet"></i></div>
                        Informasi Transfer Rekening Bank
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nama Bank<span class="text-danger-marker">*</span></label>
                            <input type="text" name="nama_bank" class="form-control" placeholder="Contoh: BCA, Mandiri, BRI..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nomor Rekening<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_norek" name="no_rekening" class="form-control" placeholder="Masukkan Nomor Rekening" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Atas Nama Rekening<span class="text-danger-marker">*</span></label>
                            <input type="text" name="atas_nama_rekening" class="form-control" placeholder="Masukkan Atas Nama Rekening" required>
                        </div>
                    </div>

                    <!-- BAGIAN 5: AKSES LOGIN PORTAL -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-key"></i></div>
                        Akses Login Portal Supplier
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Nama Pengguna Portal (Min. 5 Huruf)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="sup_user" name="user" class="form-control" placeholder="Masukkan Nama Pengguna untuk login" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kata Sandi<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="sup_pass" name="pass" class="form-control" placeholder="Masukkan Kata Sandi" required>
                                <button class="btn btn-toggle-pass" type="button" data-target="sup_pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="sup_password_strength_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Kata Sandi<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="sup_pass_konfirm" name="pass_konfirm" class="form-control" placeholder="Ulangi Kata Sandi" required>
                                <button class="btn btn-toggle-pass" type="button" data-target="sup_pass_konfirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="sup_password_match_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                    </div>

                    <!-- BAGIAN 6: FOTO MITRA (WAJIB) -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-camera"></i></div>
                        Profil Foto Supplier / Logo Mitra
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="avatar-container" class="avatar-preview-circle">
                                    <span id="avatar-initials">?</span>
                                    <img id="avatar-image-preview" src="" alt="Pratinjau Logo">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Pilih Foto Supplier / Logo Mitra (Wajib)<span class="text-danger-marker">*</span></label>
                                    <input type="file" id="foto-input" name="foto" class="form-control" accept="image/*" required>
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG</strong>. Maksimal ukuran: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="simpan" class="btn btn-simpan">
                            <i class="fas fa-save me-2"></i>Simpan Supplier
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: VALIDASI KETAT, CASCADING WILAYAH INDONESIA, INTEGRASI SELECT2, DAN GENERATOR INISIAL -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Solusi agar input pencarian Select2 di dalam Bootstrap modal dapat difokuskan & diketik secara normal
    document.addEventListener('focusin', function(e) {
        if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
            e.stopImmediatePropagation();
        }
    }, true);

    // INISIALISASI SELECT2 DENGAN ATRIBUT DROPDOWNPARENT YANG TEPAT
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

    // Mencegah focus trap Bootstrap menghalangi input pencarian Select2
    $('#modalTambahSupplier').on('shown.bs.modal', function() {
        $(document).off('focusin.bs.modal');
    });

    // VISIBILITAS KATA SANDI TOGGLE
    const toggleButtons = document.querySelectorAll('.btn-toggle-pass');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                targetInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    const namaInput = document.getElementById('sup_nama');
    const telpInput = document.getElementById('sup_telp');
    const userInput = document.getElementById('sup_user');
    const kodePosInput = document.getElementById('sup_kodepos');
    const initialsSpan = document.getElementById('avatar-initials');
    const imagePreview = document.getElementById('avatar-image-preview');
    const fileInput = document.getElementById('foto-input');
    
    const passInput = document.getElementById('sup_pass');
    const confirmInput = document.getElementById('sup_pass_konfirm');
    const feedback = document.getElementById('sup_password_match_feedback');
    const form = document.getElementById('formTambahSupplier');
    const alamatKantor = document.getElementById('sup_alamat');

    // NIK / No Telepon / Kode Pos: Batasi hanya angka saat diketik
    if (telpInput) {
        telpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    }

    const telpCpInput = document.getElementById('sup_telp_cp');
    if (telpCpInput) {
        telpCpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    }

    const namaCpInput = document.getElementById('sup_nama_cp');
    if (namaCpInput) {
        namaCpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        });
    }

    // Nama Perusahaan: Memperbolehkan alfanumerik beserta simbol umum perusahaan
    if (namaInput) {
        namaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9\s.,\-()]/g, '');
            updateInitials();
        });
    }

    // Nama Pengguna: Hanya menerima alfabet (Tanpa angka, spasi, atau simbol)
    if (userInput) {
        userInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z]/g, '');
        });
    }

    // LOGIKA GENERATOR INISIAL NAMA SUPPLIER
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

    // LOGIKA PRATINJAU LOGO MITRA UPLOAD & VALIDASI FORMAT BERKAS
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const allowedExtensions = ['jpg', 'jpeg', 'png'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (!allowedExtensions.includes(fileExtension)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Salah',
                        text: 'Hanya format JPG, JPEG, dan PNG yang diperbolehkan!',
                        confirmButtonColor: '#3498db'
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
                        text: 'Batas maksimum ukuran berkas logo adalah 2 MB.',
                        confirmButtonColor: '#3498db'
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
                imagePreview.style.display = 'none';
                initialsSpan.style.display = 'block';
                updateInitials();
            }
        });
    }

    // VALIDASI KECOCOKAN KATA SANDI SECARA REAL-TIME
    function checkPasswordMatch() {
        const pVal = passInput.value;
        const cVal = confirmInput.value;

        if (cVal === "") {
            feedback.textContent = "";
            confirmInput.style.borderColor = "#cbd5e1";
            return true;
        }

        if (pVal === cVal) {
            feedback.textContent = "✓ Kata sandi cocok";
            feedback.style.color = "#2ecc71";
            confirmInput.style.borderColor = "#2ecc71";
            return true;
        } else {
            feedback.textContent = "✗ Kata sandi tidak cocok";
            feedback.style.color = "#ef4444";
            confirmInput.style.borderColor = "#ef4444";
            return false;
        }
    }

    if (passInput && confirmInput) {
        passInput.addEventListener('input', checkPasswordMatch);
        confirmInput.addEventListener('input', checkPasswordMatch);
    }

    // VALIDASI KEKUATAN PASSWORD REAL-TIME
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    const strengthFeedback = document.getElementById('sup_password_strength_feedback');

    function checkPasswordStrength() {
        const val = passInput.value;
        if (val.length === 0) {
            strengthFeedback.textContent = "";
            passInput.style.borderColor = "#cbd5e1";
            return;
        }
        if (passwordRegex.test(val)) {
            strengthFeedback.textContent = "✓ Kata sandi cukup kuat";
            strengthFeedback.style.color = "#2ecc71";
            passInput.style.borderColor = "#2ecc71";
        } else {
            strengthFeedback.textContent = "Min. 8 karakter, mengandung huruf besar, huruf kecil & angka";
            strengthFeedback.style.color = "#ef4444";
            passInput.style.borderColor = "#ef4444";
        }
    }

    if (passInput && strengthFeedback) {
        passInput.addEventListener('input', checkPasswordStrength);
    }

    // ================== LOGIKA INTEGRASI API WILAYAH INDONESIA (CASCADING) ==================
    // Load Daftar Provinsi
    fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
        .then(response => response.json())
        .then(provinces => {
            let options = '<option value="" disabled selected>Pilih Provinsi</option>';
            provinces.forEach(prov => {
                options += `<option value="${prov.name}" data-id="${prov.id}">${prov.name}</option>`;
            });
            $('#provinsi').html(options).trigger('change');
        });

    // Ketika Provinsi Berubah -> Load Kota/Kabupaten
    $('#provinsi').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const provId = selectedOption.attr('data-id');
        
        if (!provId) return;

        fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
            .then(response => response.json())
            .then(regencies => {
                let options = '<option value="" disabled selected>Pilih Kota/Kabupaten</option>';
                regencies.forEach(reg => {
                    options += `<option value="${reg.name}" data-id="${reg.id}">${reg.name}</option>`;
                });
                $('#kota_kabupaten').html(options).trigger('change');
            });
    });

    // Ketika Kota/Kabupaten Berubah -> Load Kecamatan
    $('#kota_kabupaten').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const regId = selectedOption.attr('data-id');
        
        if (!regId) return;

        fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regId}.json`)
            .then(response => response.json())
            .then(districts => {
                let options = '<option value="" disabled selected>Pilih Kecamatan</option>';
                districts.forEach(dist => {
                    options += `<option value="${dist.name}" data-id="${dist.id}">${dist.name}</option>`;
                });
                $('#kecamatan').html(options).trigger('change');
            });
    });

    // Ketika Kecamatan Berubah -> Load Kelurahan
    $('#kecamatan').on('change', function() {
        const selectedOption = $(this).find(':selected');
        const distId = selectedOption.attr('data-id');
        
        if (!distId) return;

        fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${distId}.json`)
            .then(response => response.json())
            .then(villages => {
                let options = '<option value="" disabled selected>Pilih Kelurahan</option>';
                villages.forEach(vil => {
                    options += `<option value="${vil.name}">${vil.name}</option>`;
                });
                $('#kelurahan').html(options).trigger('change');
            });
    });

    // ================== DETEKSI & VALIDASI FORMAT ALAMAT ==================
    function validasiFormatAlamat(alamatVal) {
        const val = alamatVal.trim();
        if (val.length < 20) return false;
        
        // Cek awalan Jl. / Jalan
        if (!/^(jl\.\s*|jalan\s+)/i.test(val)) return false;

        // Anti-Gibberish: Cegah keyboard-mashing (huruf mati berturut-turut >= 6)
        if (/[bcdfghjklmnpqrstvwxyz]{6,}/i.test(val)) return false;

        // Anti-Gibberish: Karakter berulang >= 4 kali
        if (/([a-zA-Z0-9])\1{3,}/.test(val)) return false;

        // Harus mengandung minimal 2 spasi sebagai indikasi kata nyata
        if ((val.split(" ").length - 1) < 2) return false;

        return true;
    }

    // SUBMIT FORM VALIDATION
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // 1. Validasi Foto Logo (Wajib)
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Logo Wajib Diunggah',
                    text: 'Silakan pilih foto profil atau logo supplier terlebih dahulu!',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 2. Validasi Nama Perusahaan
            if (namaInput.value.trim().length < 3 || namaInput.value.trim().length > 100) {
                Swal.fire({
                    icon: 'error',
                    title: 'Nama Supplier Tidak Valid',
                    text: 'Panjang nama perusahaan/supplier harus berkisar antara 3 hingga 100 karakter.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 3. Validasi Username Alfabet saja (Tanpa Angka/Spasi)
            if (!/^[a-zA-Z]+$/.test(userInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Username Tidak Valid',
                    text: 'Nama pengguna portal hanya boleh mengandung huruf alfabet (A-Z, a-z).',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 4. Validasi Batasan Karakter Username
            if (userInput.value.length < 5 || userInput.value.length > 20) {
                Swal.fire({
                    icon: 'error',
                    title: 'Panjang Username Tidak Valid',
                    text: 'Nama pengguna portal supplier harus berkisar antara 5 hingga 20 karakter.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 5. Validasi Kode Pos (Tepat 5 Digit)
            if (kodePosInput.value.length !== 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kode Pos Tidak Valid',
                    text: 'Kode Pos wajib diisi dengan tepat 5 digit angka!',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 6. Validasi Format Alamat Kantor
            if (!validasiFormatAlamat(alamatKantor.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Alamat Kantor Tidak Valid',
                    text: 'Alamat harus diawali dengan "Jl." atau "Jalan", minimal 20 karakter, serta tidak menggunakan teks acak.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 7. Validasi Nomor Telepon Kantor
            const telpVal = telpInput.value.trim();
            const telpRegex = /^(\+\d{1,3}\d{8,12}|0\d{8,13})$/;
            if (!telpRegex.test(telpVal)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Nomor Telepon Kantor Tidak Valid',
                    text: 'Gunakan format internasional (contoh: +62812xxxxxxx) atau format lokal (0812xxxxxxx).',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 8. Validasi Alamat Email Kantor
            const emailVal = document.getElementById('sup_email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailVal)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Surel Kantor Tidak Valid',
                    text: 'Format alamat email kantor tidak lengkap atau salah.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 9. Validasi Nama CP (Alfabet saja)
            if (!/^[a-zA-Z\s]+$/.test(namaCpInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Nama CP Tidak Valid',
                    text: 'Nama Narahubung (CP) hanya boleh mengandung huruf alfabet dan spasi.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 10. Validasi Nomor Telepon CP
            const telpCpVal = telpCpInput.value.trim();
            if (!telpRegex.test(telpCpVal)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Nomor Telepon CP Tidak Valid',
                    text: 'Gunakan format nomor telepon yang valid pada kolom Kontak CP.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 11. Validasi Alamat Email CP
            const emailCpVal = document.getElementById('sup_email_cp').value.trim();
            if (!emailRegex.test(emailCpVal)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Surel CP Tidak Valid',
                    text: 'Format alamat email CP tidak lengkap atau salah.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 12. Validasi Kekuatan Password
            if (!passwordRegex.test(passInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kata Sandi Terlalu Lemah',
                    text: 'Kata sandi minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // 13. Validasi Password Cocok
            if (!checkPasswordMatch()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kata Sandi Tidak Cocok',
                    text: 'Silakan pastikan isi kolom konfirmasi kata sandi sama dengan kolom kata sandi.',
                    confirmButtonColor: '#f59e0b'
                });
                return;
            }

            // Jika lolos seluruh validasi
            this.submit();
        });
    }
});
</script>

<!-- PEMROSESAN STATUS ALERT SWEETALERT2 DARI SERVER-SIDE PHP -->
<?php if (!empty($error_message) || !empty($success_message)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if (!empty($error_message)): ?>
            var modalTambah = new bootstrap.Modal(document.getElementById('modalTambahSupplier'));
            modalTambah.show();

            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: <?= json_encode($error_message); ?>,
                confirmButtonColor: '#3498db'
            });
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            var modalEl = document.getElementById('modalTambahSupplier');
            if (modalEl) {
                var modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }

            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: <?= json_encode($success_message); ?>,
                confirmButtonColor: '#203a43',
                timer: 2000,
                timerProgressBar: true,
                willClose: () => {
                    window.location.href = 'supplier_read.php';
                }
            }).then(() => {
                window.location.href = 'supplier_read.php';
            });
        <?php endif; ?>
    });
</script>
<?php endif; ?>
