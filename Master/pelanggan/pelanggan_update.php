

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

// Fungsi helper untuk inisial nama jika foto lama kosong
if (!function_exists('getInitialsPelangganEdit')) {
    function getInitialsPelangganEdit($name) {
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

$error_message = "";
$success_message = "";
$data = null;
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// 1. Ambil data lama berdasarkan ID menggunakan sp_Pelanggan_Read
if ($id) {
    $sql_ambil = "EXEC sp_Pelanggan_Read @ID_Pelanggan = ?";
    $params_ambil = array($id);
    $query_ambil = sqlsrv_query($conn, $sql_ambil, $params_ambil);
    
    if ($query_ambil === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $data = sqlsrv_fetch_array($query_ambil, SQLSRV_FETCH_ASSOC);
}

// 2. Proses Update Data
if (isset($_POST['update']) && $data) {
    $nama            = trim($_POST['nama']);
    $jenis_kelamin   = $_POST['jenis_kelamin'];
    $tempat_lahir    = $_POST['tempat_lahir'];
    $tanggal_birth   = $_POST['tanggal_lahir'];
    $tanggal_lahir   = !empty($tanggal_birth) ? $tanggal_birth : null;
    $pekerjaan       = trim($_POST['pekerjaan']);
    
    $alamat          = $_POST['alamat'];
    $kelurahan       = $_POST['kelurahan'];
    $kecamatan       = $_POST['kecamatan'];
    $kota_kabupaten  = $_POST['kota_kabupaten'];
    $provinsi        = $_POST['provinsi'];
    $kode_pos        = trim($_POST['kode_pos']);
    
    $telp            = trim($_POST['telepon']);
    $email           = trim($_POST['email']);
    $status_mb       = $_POST['status_member']; 
    $poin_member     = (int)$_POST['poin_member'];
    
    $user            = trim($_POST['user']);
    $modified_by     = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // --- VALIDASI SERVER-SIDE PHP ---
    $pass_val = $_POST['pass'] ?? '';
    $pass_konf = $_POST['pass_konfirm'] ?? '';

    if (!empty($pass_val) && $pass_val !== $pass_konf) {
        $error_message = 'Password dan Konfirmasi Password tidak cocok!';
    } elseif (!empty($pass_val) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pass_val)) {
        $error_message = 'Kata Sandi baru minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka!';
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $nama)) {
        $error_message = 'Nama lengkap hanya boleh diisi oleh huruf!';
    } elseif (strlen($nama) < 3 || strlen($nama) > 50) {
        $error_message = 'Nama lengkap harus berada di kisaran 3 sampai 50 karakter!';
    } elseif (!preg_match("/^[a-zA-Z0-9]+$/", $user)) { 
        $error_message = 'Nama pengguna hanya boleh diisi oleh huruf atau angka tanpa spasi!';
    } elseif (strlen($user) < 5 || strlen($user) > 20) {
        $error_message = 'Nama pengguna harus berada di kisaran 5 sampai 20 karakter!';
    } elseif (!preg_match('/^(\+\d{1,3}\d{8,12}|0\d{8,13})$/', $telp)) {
        $error_message = 'Nomor telepon tidak valid! Gunakan format internasional, contoh: +62812xxxxxxx atau 0812xxxxxxx.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format alamat surel tidak valid!';
    } elseif (strlen($kode_pos) !== 5 || !ctype_digit($kode_pos)) {
        $error_message = 'Kode Pos tidak valid! Harus berupa angka dan tepat 5 digit.';
    } elseif (!in_array($jenis_kelamin, ['Laki-laki', 'Perempuan'])) {
        $error_message = 'Pilihan Jenis Kelamin tidak valid!';
    } elseif (!in_array($status_mb, ['Member', 'Non Member'])) {
        $error_message = 'Pilihan Status Keanggotaan tidak valid!';
    } else {
        // Cek umur di sisi server
        if ($tanggal_lahir) {
            $birthDate = new DateTime($tanggal_lahir);
            $today = new DateTime();
            if ($birthDate > $today) {
                $error_message = 'Tanggal lahir tidak boleh di masa depan!';
            } else {
                $age = $today->diff($birthDate)->y;
                if ($age < 17) {
                    $error_message = 'Usia pelanggan tidak boleh di bawah 17 tahun!';
                }
            }
        }
        
        // Cek format alamat
        if (empty($error_message)) {
            if (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat) || strlen($alamat) < 20) {
                $error_message = 'Alamat tidak valid! Harus diawali dengan "Jl." atau "Jalan" dan minimal 20 karakter.';
            }
        }

        if (empty($error_message)) {
            // Logika Password (Jika kosong, pakai yang lama)
            if (!empty($pass_val)) {
                $pass = password_hash($pass_val, PASSWORD_DEFAULT);
            } else {
                $pass = $data['Password'];
            }

            // Logika Update Foto Pelanggan
            $foto_baru = $data['Foto_Pelanggan']; // Default pakai foto lama
            $upload_ok = true;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto_name = $_FILES['foto']['name'];
                $tmp_name  = $_FILES['foto']['tmp_name'];
                $ekstensi  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
                $ekstensi_diperbolehkan = array('jpg', 'jpeg', 'png');

                if (in_array($ekstensi, $ekstensi_diperbolehkan)) {
                    $foto_baru = "pel_" . time() . "." . $ekstensi;
                    $target_dir = "../../assets/uploads/pelanggan/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }

                    // Hapus foto lama dari folder jika ada
                    if (!empty($data['Foto_Pelanggan']) && file_exists($target_dir . $data['Foto_Pelanggan'])) {
                        unlink($target_dir . $data['Foto_Pelanggan']);
                    }
                    
                    move_uploaded_file($tmp_name, $target_dir . $foto_baru);
                } else {
                    $upload_ok = false;
                    $error_message = 'Format file foto tidak valid! Gunakan JPG, JPEG atau PNG.';
                }
            }

            // Jalankan update jika tidak ada error upload
            if ($upload_ok) {
                // Pemanggilan disimpan menggunakan Stored Procedure sp_Pelanggan_Update
                $sql_update = "EXEC sp_Pelanggan_Update 
                                @ID_Pelanggan = ?, 
                                @Nama_Pelanggan = ?, 
                                @Jenis_Kelamin = ?,
                                @Tempat_Lahir = ?,
                                @Tanggal_Lahir = ?,
                                @Pekerjaan = ?,
                                @No_Telepon = ?, 
                                @Email = ?, 
                                @Username = ?, 
                                @Password = ?,
                                @Status_Member = ?, 
                                @Poin_Member = ?,
                                @Foto_Pelanggan = ?, 
                                @Alamat = ?,
                                @Kelurahan = ?,
                                @Kecamatan = ?,
                                @Kota_Kabupaten = ?,
                                @Provinsi = ?,
                                @Kode_Pos = ?,
                                @Pel_status = ?,
                                @Pel_modified_by = ?";

                $params = array(
                    $id, 
                    $nama, 
                    $jenis_kelamin, 
                    !empty($tempat_lahir) ? $tempat_lahir : null, 
                    !empty($tanggal_lahir) ? $tanggal_lahir : null, 
                    !empty($pekerjaan) ? $pekerjaan : null,
                    $telp, 
                    $email, 
                    $user, 
                    $pass, 
                    $status_mb, 
                    $poin_member, 
                    $foto_baru,
                    $alamat, 
                    !empty($kelurahan) ? $kelurahan : null, 
                    !empty($kecamatan) ? $kecamatan : null, 
                    !empty($kota_kabupaten) ? $kota_kabupaten : null, 
                    !empty($provinsi) ? $provinsi : null, 
                    !empty($kode_pos) ? $kode_pos : null,
                    $data['Pel_status'] ?? 'Aktif',
                    $modified_by
                );
                
                $stmt = sqlsrv_query($conn, $sql_update, $params);

                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    if ($errors !== null) {
                        $raw_error = $errors[0]['message'];
                        $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
                    } else {
                        $error_message = 'Terjadi kesalahan sistem saat memperbarui data pelanggan.';
                    }
                } else {
                    $success_message = 'Berhasil memperbarui data pelanggan!';
                    
                    // Ambil data terbaru hasil update agar form langsung sinkron
                    $query_ambil = sqlsrv_query($conn, "EXEC sp_Pelanggan_Read @ID_Pelanggan = ?", array($id));
                    if ($query_ambil !== false) {
                        $data = sqlsrv_fetch_array($query_ambil, SQLSRV_FETCH_ASSOC);
                    }
                }
            }
        }
    }

    // --- INTERSEPSI RESPON UNTUK AJAX SUBMISSION ---
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    if (!empty($error_message)) {
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    } else {
        echo json_encode(['status' => 'success', 'message' => $success_message]);
    }
    exit;
}
?>

<!-- MEMASTIKAN PUSTAKA SWEETALERT, FONTAWESOME, JQUERY, DAN SELECT2 SIAP -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE KHUSUS MODAL EDIT PELANGGAN DENGAN ANIMASI SINKRON -->
<style>
    :root { 
        --primary-gradient-pelanggan: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        --accent-color-pelanggan: #3498db; 
        --border-color-pelanggan: #cbd5e1;
        --text-danger: #ef4444;
    }

    #modalEditPelanggan {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalEditPelanggan {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInPelanggan {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalEditPelanggan.show .modal-content-custom {
        animation: modalZoomInPelanggan 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-edit-pelanggan { 
        background: var(--primary-gradient-pelanggan); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
        border-top-left-radius: 1.5rem;
        border-top-right-radius: 1.5rem;
    }

    .header-bg-edit-pelanggan i {
        animation: pulsePelanggan 2.5s infinite;
    }

    @keyframes pulsePelanggan {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalEditPelanggan .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-pelanggan); 
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
        border: 1.5px solid var(--border-color-pelanggan); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-pelanggan);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        outline: none;
    }

    /* SELECT2 MODIFIKASI AGAR MATCH DENGAN BOOTSTRAP */
    .select2-container {
        z-index: 9999999 !important;
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-pelanggan) !important;
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
        border-color: var(--accent-color-pelanggan) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
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
        border-color: transparent transparent var(--accent-color-pelanggan) transparent !important;
    }

    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-pelanggan) !important;
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
        border: 1.5px solid var(--border-color-pelanggan) !important;
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
        border-color: var(--accent-color-pelanggan) !important;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
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
        background-color: var(--accent-color-pelanggan) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #eaf4fc !important;
        color: var(--accent-color-pelanggan) !important;
        font-weight: 600;
    }

    .select2-results__message {
        color: #94a3b8 !important;
        font-size: 0.85rem !important;
        padding: 0.75rem !important;
        text-align: center;
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
        border: 1.5px solid var(--border-color-pelanggan);
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
        border-color: var(--accent-color-pelanggan);
    }

    .input-group-custom:focus-within .btn-toggle-pass {
        border-color: var(--accent-color-pelanggan);
        background-color: #ffffff;
    }

    .btn-simpan { 
        background: var(--primary-gradient-pelanggan); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(32, 58, 67, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(32, 58, 67, 0.3);
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
        background: #f0f9ff; 
        color: var(--accent-color-pelanggan); 
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
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
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
    
    .avatar-indigo { background: linear-gradient(135deg, #e0e7ff, #c7d2fe) !important; color: #4f46e5 !important; }
    .avatar-gold { background: linear-gradient(135deg, #fffbeb, #fde68a) !important; color: #f59e0b !important; }
</style>

<!-- RENDER MODAL DENGAN PENCEGAHAN BACKDROP DISMISS -->
<?php if ($data): ?>
<div class="modal fade" id="modalEditPelanggan" aria-labelledby="modalEditPelangganLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-edit-pelanggan">
                <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-user-edit fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Edit Data Pelanggan</h2>
            </div>

            <form id="formEditPelanggan" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                
                <div class="form-container">
                    
                    <!-- BAGIAN 1: IDENTITAS DIRI -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-id-card"></i></div>
                        Identitas & Data Diri Pelanggan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap Pelanggan<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_kar_nama" name="nama" class="form-control" value="<?= htmlspecialchars($data['Nama_Pelanggan']) ?>" placeholder="Masukkan Nama Lengkap Pelanggan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin<span class="text-danger-marker">*</span></label>
                            <select name="jenis_kelamin" class="form-select select2-nosearch" required>
                                <option value="Laki-laki" <?= ($data['Jenis_Kelamin'] == 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($data['Jenis_Kelamin'] == 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tempat Lahir (Kabupaten/Kota)<span class="text-danger-marker">*</span></label>
                            <select id="edit_tempat_lahir" name="tempat_lahir" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Tempat_Lahir'] ?? '') ?>" selected><?= htmlspecialchars($data['Tempat_Lahir'] ?? '') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir (Min. 17 Tahun)<span class="text-danger-marker">*</span></label>
                            <?php 
                                $tgl_lahir_formatted = '';
                                if ($data['Tanggal_Lahir'] instanceof DateTime) {
                                    $tgl_lahir_formatted = $data['Tanggal_Lahir']->format('Y-m-d');
                                } else if (!empty($data['Tanggal_Lahir'])) {
                                    $tgl_lahir_formatted = date('Y-m-d', strtotime($data['Tanggal_Lahir']));
                                }
                            ?>
                            <input type="date" id="edit_tanggal_lahir" name="tanggal_lahir" class="form-control" value="<?= $tgl_lahir_formatted ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pekerjaan<span class="text-danger-marker">*</span></label>
                            <input type="text" name="pekerjaan" class="form-control" value="<?= htmlspecialchars($data['Pekerjaan'] ?? '') ?>" placeholder="Masukkan Pekerjaan" required>
                        </div>
                    </div>

                    <!-- BAGIAN 2: ALAMAT LENGKAP -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-map-marked-alt"></i></div>
                        Informasi Alamat Lengkap
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Alamat Lengkap Rumah (Mulai Jl./Jalan, min 20 char)<span class="text-danger-marker">*</span></label>
                            <textarea id="edit_alamat" name="alamat" class="form-control" rows="2" placeholder="Masukkan Alamat Lengkap" required><?= htmlspecialchars($data['Alamat'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- CASCADING SELECTS -->
                        <div class="col-md-6">
                            <label class="form-label">Provinsi<span class="text-danger-marker">*</span></label>
                            <select id="edit_provinsi" name="provinsi" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Provinsi'] ?? '') ?>" selected><?= htmlspecialchars($data['Provinsi'] ?? '') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kota / Kabupaten<span class="text-danger-marker">*</span></label>
                            <select id="edit_kota_kabupaten" name="kota_kabupaten" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kota_Kabupaten'] ?? '') ?>" selected><?= htmlspecialchars($data['Kota_Kabupaten'] ?? '') ?></option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Kecamatan<span class="text-danger-marker">*</span></label>
                            <select id="edit_kecamatan" name="kecamatan" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kecamatan'] ?? '') ?>" selected><?= htmlspecialchars($data['Kecamatan'] ?? '') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kelurahan<span class="text-danger-marker">*</span></label>
                            <select id="edit_kelurahan" name="kelurahan" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kelurahan'] ?? '') ?>" selected><?= htmlspecialchars($data['Kelurahan'] ?? '') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kode Pos (Harus Tepat 5 Digit)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_kar_kodepos" name="kode_pos" class="form-control" maxlength="5" value="<?= htmlspecialchars($data['Kode_Pos'] ?? '') ?>" placeholder="Masukkan Kode Pos" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                    </div>

                    <!-- BAGIAN 3: KONTAK & LOYALITAS -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-crown"></i></div>
                        Kontak & Loyalitas Pelanggan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Alamat Surel Aktif<span class="text-danger-marker">*</span></label>
                            <input type="email" id="edit_kar_email" name="email" class="form-control" value="<?= htmlspecialchars($data['Email'] ?? '') ?>" placeholder="contoh@mail.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon (Skala Internasional +62/+63)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_kar_telepon" name="telepon" class="form-control" value="<?= htmlspecialchars($data['No_Telepon']) ?>" placeholder="Masukkan Nomor Telepon" oninput="this.value = this.value.replace(/[^0-9+]/g, '');" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status Keanggotaan<span class="text-danger-marker">*</span></label>
                            <select id="edit_status_member" name="status_member" class="form-select select2-nosearch" required>
                                <option value="Member" <?= ($data['Status_Member'] == 'Member') ? 'selected' : '' ?>>Member</option>
                                <option value="Non Member" <?= ($data['Status_Member'] == 'Non Member') ? 'selected' : '' ?>>Non Member</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Poin Awal Member<span class="text-danger-marker">*</span></label>
                            <input type="number" name="poin_member" class="form-control" value="<?= htmlspecialchars($data['Poin_Member'] ?? 0) ?>" min="0">
                        </div>
                    </div>

                    <!-- BAGIAN 4: AKSES LOGIN & KEAMANAN -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-key"></i></div>
                        Akses Login & Keamanan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Nama Pengguna Pelanggan (Min. 5 Karakter)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_kar_user" name="user" class="form-control" value="<?= htmlspecialchars(trim($data['Username'] ?? '')) ?>" placeholder="Masukkan Nama Pengguna" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kata Sandi Baru (Kosongkan jika tidak diubah)<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="edit_kar_pass" name="pass" class="form-control" placeholder="••••••••">
                                <button class="btn btn-toggle-pass" type="button" data-target="edit_kar_pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="edit_kar_password_strength_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Kata Sandi Baru<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="edit_kar_pass_konfirm" name="pass_konfirm" class="form-control" placeholder="••••••••">
                                <button class="btn btn-toggle-pass" type="button" data-target="edit_kar_pass_konfirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="edit_kar_password_match_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                    </div>

                    <!-- BAGIAN 5: FOTO PROFIL -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-camera"></i></div>
                        Profil Foto Pelanggan
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="edit-avatar-container" class="avatar-preview-circle">
                                    <span id="edit-avatar-initials">?</span>
                                    <img id="edit-avatar-image-preview" src="" alt="Pratinjau Foto">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Ganti Foto Pelanggan</label>
                                    <input type="file" id="edit-foto-input" name="foto" class="form-control" accept="image/*">
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG</strong>. Maksimal file: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal" id="btnBatalEdit">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="update" class="btn btn-simpan">
                            <i class="fas fa-save me-2"></i>Update Data Pelanggan
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // SOLUSI UTAMA: Menggunakan native capturing listener (true) agar input pencarian Select2 bisa diketik secara normal
        document.addEventListener('focusin', function(e) {
            if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
                e.stopImmediatePropagation();
            }
        }, true);

        // Inisialisasi modal menggunakan instansi tunggal
        var modalEl = document.getElementById('modalEditPelanggan');
        var modalEdit = null;

        <?php if ($data && empty($success_message)): ?>
            if (modalEl) {
                modalEdit = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalEdit.show();
            }
        <?php endif; ?>

        // PENGALIHAN LANGSUNG SAAT KLIK TOMBOL BATAL ATAU TOMBOL SILANG (X) - TANPA MEMBAWA PARAMETER ID DI URL
        const btnBatal = document.getElementById('btnBatalEdit');
        const btnSilang = document.querySelector('#modalEditPelanggan .btn-close');

        if (btnBatal) {
            btnBatal.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'pelanggan_read.php';
            });
        }

        if (btnSilang) {
            btnSilang.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'pelanggan_read.php';
            });
        }

        // Antisipasi cadangan jika modal ditutup melalui mekanisme luar bawaan Bootstrap - Pembersihan ID Terjamin
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                window.location.href = 'pelanggan_read.php';
            });
        }

        // --- INISIALISASI SELECT2 DENGAN PORTING KE BODY DOKUMEN ---
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

        $('.select2-nosearch').select2({
            dropdownParent: $(document.body),
            width: '100%',
            minimumResultsForSearch: Infinity,
            language: {
                noResults: function () { return 'Data tidak ditemukan'; }
            }
        });

        // Mencegah focus trap Bootstrap menghalangi input pencarian Select2
        $('#modalEditPelanggan').on('shown.bs.modal', function() {
            $(document).off('focusin.bs.modal');
        });

        // --- SHOW/HIDE PASSWORD TOGGLE ---
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

        // --- AVATAR PREVIEW DENGAN INISIAL NAMA ---
        const namaInput = document.getElementById('edit_kar_nama');
        const initialsSpan = document.getElementById('edit-avatar-initials');
        const imagePreview = document.getElementById('edit-avatar-image-preview');
        const fileInput = document.getElementById('edit-foto-input');
        const statusMemberSelect = document.getElementById('edit_status_member');
        const avatarContainer = document.getElementById('edit-avatar-container');
        
        const dbPhoto = <?= json_encode(trim($data['Foto_Pelanggan'] ?? '')); ?>;

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

        function updateAvatarTheme() {
            if (statusMemberSelect.value === 'Member') {
                avatarContainer.className = 'avatar-preview-circle avatar-gold';
            } else {
                avatarContainer.className = 'avatar-preview-circle avatar-indigo';
            }
        }

        // Inisialisasi awal avatar & tema member
        updateAvatarTheme();
        statusMemberSelect.addEventListener('change', updateAvatarTheme);

        if (dbPhoto && dbPhoto !== '') {
            imagePreview.src = '../../assets/uploads/pelanggan/' + dbPhoto;
            imagePreview.style.display = 'block';
            initialsSpan.style.display = 'none';
        } else {
            imagePreview.style.display = 'none';
            initialsSpan.style.display = 'block';
            updateInitials();
        }

        if (namaInput) {
            namaInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
                updateInitials();
            });
        }

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
                        return;
                    }

                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Ukuran File Terlalu Besar',
                            text: 'Maksimal batas ukuran file foto adalah 2 MB.',
                            confirmButtonColor: '#3498db'
                        });
                        this.value = '';
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
                        imagePreview.src = '../../assets/uploads/pelanggan/' + dbPhoto;
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

        // --- VALIDASI PASSWORD MATCH & STRENGTH REAL-TIME ---
        const passInput = document.getElementById('edit_kar_pass');
        const confirmInput = document.getElementById('edit_kar_pass_konfirm');
        const feedback = document.getElementById('edit_kar_password_match_feedback');
        const strengthFeedback = document.getElementById('edit_kar_password_strength_feedback');
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        function checkPasswordMatch() {
            const pVal = passInput.value;
            const cVal = confirmInput.value;

            if (pVal === "" && cVal === "") {
                feedback.textContent = "";
                confirmInput.style.borderColor = "#cbd5e1";
                return true;
            }

            if (pVal === cVal) {
                feedback.textContent = "✓ Password cocok";
                feedback.style.color = "#2ecc71";
                confirmInput.style.borderColor = "#2ecc71";
                return true;
            } else {
                feedback.textContent = "✗ Password tidak cocok";
                feedback.style.color = "#ef4444";
                confirmInput.style.borderColor = "#ef4444";
                return false;
            }
        }

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

        if (passInput && confirmInput) {
            passInput.addEventListener('input', function() {
                checkPasswordStrength();
                checkPasswordMatch();
            });
            confirmInput.addEventListener('input', checkPasswordMatch);
        }

        // ================== LOGIKA INTEGRASI API WILAYAH INDONESIA (CASCADING) ==================
        // Load Daftar Provinsi
        fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
            .then(response => response.json())
            .then(provinces => {
                let options = '<option value="" disabled>Pilih Provinsi</option>';
                let savedProvinsi = <?= json_encode($data['Provinsi'] ?? '') ?>;
                let activeId = null;

                provinces.forEach(prov => {
                    let selected = (prov.name.toLowerCase() === savedProvinsi.toLowerCase()) ? 'selected' : '';
                    if (selected) activeId = prov.id;
                    options += `<option value="${prov.name}" data-id="${prov.id}" ${selected}>${prov.name}</option>`;
                });
                $('#edit_provinsi').html(options).trigger('change');

                if (activeId) {
                    loadRegencies(activeId);
                }
            });

        function loadRegencies(provId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                .then(response => response.json())
                .then(regencies => {
                    let options = '<option value="" disabled>Pilih Kota/Kabupaten</option>';
                    let savedKota = <?= json_encode($data['Kota_Kabupaten'] ?? '') ?>;
                    let activeId = null;

                    regencies.forEach(reg => {
                        let selected = (reg.name.toLowerCase() === savedKota.toLowerCase()) ? 'selected' : '';
                        if (selected) activeId = reg.id;
                        options += `<option value="${reg.name}" data-id="${reg.id}" ${selected}>${reg.name}</option>`;
                    });
                    $('#edit_kota_kabupaten').html(options).trigger('change');

                    if (activeId) {
                        loadDistricts(activeId);
                    }
                });
        }

        function loadDistricts(regId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regId}.json`)
                .then(response => response.json())
                .then(districts => {
                    let options = '<option value="" disabled>Pilih Kecamatan</option>';
                    let savedKecamatan = <?= json_encode($data['Kecamatan'] ?? '') ?>;
                    let activeId = null;

                    districts.forEach(dist => {
                        let selected = (dist.name.toLowerCase() === savedKecamatan.toLowerCase()) ? 'selected' : '';
                        if (selected) activeId = dist.id;
                        options += `<option value="${dist.name}" data-id="${dist.id}" ${selected}>${dist.name}</option>`;
                    });
                    $('#edit_kecamatan').html(options).trigger('change');

                    if (activeId) {
                        loadVillages(activeId);
                    }
                });
        }

        function loadVillages(distId) {
            fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${distId}.json`)
                .then(response => response.json())
                .then(villages => {
                    let options = '<option value="" disabled>Pilih Kelurahan</option>';
                    let savedKelurahan = <?= json_encode($data['Kelurahan'] ?? '') ?>;

                    villages.forEach(vil => {
                        let selected = (vil.name.toLowerCase() === savedKelurahan.toLowerCase()) ? 'selected' : '';
                        options += `<option value="${vil.name}" ${selected}>${vil.name}</option>`;
                    });
                    $('#edit_kelurahan').html(options).trigger('change');
                });
        }

        // Penanganan Perubahan Manual Pengguna
        $('#edit_provinsi').on('select2:select', function(e) {
            const provId = $(this).find(':selected').attr('data-id');
            if (provId) {
                loadRegencies(provId);
                $('#edit_kecamatan').html('<option value="" disabled selected>Pilih Kecamatan</option>').trigger('change');
                $('#edit_kelurahan').html('<option value="" disabled selected>Pilih Kelurahan</option>').trigger('change');
            }
        });

        $('#edit_kota_kabupaten').on('select2:select', function(e) {
            const regId = $(this).find(':selected').attr('data-id');
            if (regId) {
                loadDistricts(regId);
                $('#edit_kelurahan').html('<option value="" disabled selected>Pilih Kelurahan</option>').trigger('change');
            }
        });

        $('#edit_kecamatan').on('select2:select', function(e) {
            const distId = $(this).find(':selected').attr('data-id');
            if (distId) {
                loadVillages(distId);
            }
        });

        // Memuat Tempat Lahir secara komprehensif
        fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
            .then(response => response.json())
            .then(provinces => {
                let selectTempatLahir = $('#edit_tempat_lahir');
                
                const fetchPromises = provinces.map(p => 
                    fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${p.id}.json`).then(r => r.json())
                );

                Promise.all(fetchPromises).then(results => {
                    let options = '<option value="" disabled>Pilih Kabupaten/Kota</option>';
                    let savedTempatLahir = <?= json_encode($data['Tempat_Lahir'] ?? '') ?>;

                    results.flat().forEach(city => {
                        let selected = (city.name.toLowerCase() === savedTempatLahir.toLowerCase()) ? 'selected' : '';
                        options += `<option value="${city.name}" ${selected}>${city.name}</option>`;
                    });
                    selectTempatLahir.html(options).trigger('change');
                });
            });

        // ================== DETEKSI & VALIDASI FORMAT ALAMAT ==================
        function validasiFormatAlamat(alamat) {
            const val = alamat.trim();
            if (val.length < 20) return false;
            
            if (!/^(jl\.\s*|jalan\s+)/i.test(val)) return false;
            if (/[bcdfghjklmnpqrstvwxyz]{6,}/i.test(val)) return false;
            if (/([a-zA-Z0-9])\1{3,}/.test(val)) return false;
            if ((val.split(" ").length - 1) < 2) return false;

            return true;
        }

        // ================== DETEKSI & LIMIT INPUT KATA KUNCI ==================
        const userInput = document.getElementById('edit_kar_user');
        const kodePosInput = document.getElementById('edit_kar_kodepos');
        const telpInput = document.getElementById('edit_kar_telepon');
        const dateInput = document.getElementById('edit_tanggal_lahir');
        const form = document.getElementById('formEditPelanggan');
        const alamatInput = document.getElementById('edit_alamat');

        if (userInput) {
            userInput.addEventListener('input', function() {
                // Mengizinkan kombinasi huruf dan angka tanpa spasi
                this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
            });
        }

        if (dateInput) {
            dateInput.addEventListener('change', function() {
                const birthDate = new Date(this.value);
                const today = new Date();
                
                if (birthDate > today) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Tanggal Lahir Tidak Valid',
                        text: 'Tanggal lahir tidak boleh melebihi tanggal hari ini!',
                        confirmButtonColor: '#3498db'
                    });
                    this.value = '';
                    return;
                }

                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age < 17) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Usia Tidak Memenuhi Syarat',
                        text: 'Pelanggan harus berusia minimal 17 tahun!',
                        confirmButtonColor: '#3498db'
                    });
                    this.value = '';
                }
            });
        }

        // ================== PENANGANAN SUBMIT EDIT DENGAN SWEETALERT2 & AJAX ==================
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // 1. VALIDASI FOTO PROFIL: Wajib hanya jika foto di database kosong/belum pernah diunggah
                if (!dbPhoto && (!fileInput.files || fileInput.files.length === 0)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Foto Wajib Diunggah',
                        text: 'Pelanggan ini belum memiliki foto. Silakan unggah foto profil pelanggan terlebih dahulu!',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 2. Validasi Nama Alfabet
                if (!/^[a-zA-Z\s]+$/.test(namaInput.value)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nama Tidak Valid',
                        text: 'Nama lengkap hanya boleh mengandung huruf alfabet dan spasi.',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                if (namaInput.value.trim().length < 3 || namaInput.value.trim().length > 50) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Panjang Nama Tidak Valid',
                        text: 'Nama lengkap harus berkisar antara 3 hingga 50 karakter.',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 3. Username Kombinasi Huruf dan Angka tanpa Spasi
                const userVal = userInput.value.trim();
                if (!/^[a-zA-Z0-9]+$/.test(userVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Username Tidak Valid',
                        text: 'Nama pengguna hanya boleh mengandung huruf alfabet dan angka (A-Z, a-z, 0-9) tanpa spasi.',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                if (userVal.length < 5 || userVal.length > 20) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Panjang Username Tidak Valid',
                        text: 'Nama pengguna aplikasi harus berkisar antara 5 hingga 20 karakter.',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 4. Validasi Format Kode Pos (Tepat 5 Digit)
                if (kodePosInput.value.length !== 5) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kode Pos Tidak Valid',
                        text: 'Kode Pos wajib diisi dengan tepat 5 digit angka!',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 5. Validasi Format Alamat
                if (!validasiFormatAlamat(alamatInput.value)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Alamat Tidak Valid',
                        text: 'Alamat harus diawali dengan "Jl." atau "Jalan", minimal 20 karakter, serta tidak menggunakan teks acak.',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 6. Validasi Nomor Telepon Skala Internasional
                const telpVal = telpInput.value.trim();
                const telpRegex = /^(\+\d{1,3}\d{8,12}|0\d{8,13})$/;
                if (!telpRegex.test(telpVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nomor Telepon Tidak Valid',
                        text: 'Gunakan format internasional (contoh: +62812xxxxxxx) atau format lokal (0812xxxxxxx).',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 7. Validasi Format Alamat Email (Surel)
                const emailVal = document.getElementById('edit_kar_email').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Surel Tidak Valid',
                        text: 'Format alamat email tidak lengkap atau salah (Wajib memiliki @ dan domain).',
                        confirmButtonColor: '#3498db'
                    });
                    return;
                }

                // 8. Validasi Kekuatan Password (Hanya jika diisi/ganti baru)
                if (passInput.value !== "") {
                    if (!passwordRegex.test(passInput.value)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kata Sandi Terlalu Lemah',
                            text: 'Kata sandi baru minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka.',
                            confirmButtonColor: '#3498db'
                        });
                        return;
                    }

                    if (!checkPasswordMatch()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Password Tidak Cocok',
                            text: 'Silakan pastikan isi kolom konfirmasi password sama dengan kolom password baru.',
                            confirmButtonColor: '#3498db'
                        });
                        return;
                    }
                }

                // --- PENGIRIMAN DATA VIA AJAX DIRECT KE FILE EDIT INI ---
                var formData = new FormData(this);
                formData.append('update', '1');

                $.ajax({
                    url: '<?= basename(__FILE__) ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                confirmButtonColor: '#203a43',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                // Tutup modal secara manual
                                var modalEl = document.getElementById('modalEditPelanggan');
                                if (modalEl) {
                                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                                    if (modalInstance) {
                                        modalInstance.hide();
                                    }
                                }
                                
                                // Redirection bersih tanpa parameter ID
                                window.location.href = 'pelanggan_read.php';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan',
                                text: response.message,
                                confirmButtonColor: '#3498db'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan Sistem',
                            text: 'Gagal memproses pengiriman data ke server. Pastikan koneksi atau hak akses file sudah benar.',
                            confirmButtonColor: '#3498db'
                        });
                    }
                });
            });
        }
    });
</script>
<?php endif; ?>

<!-- PEMROSESAN STATUS ALERT SWEETALERT2 DARI SERVER-SIDE PHP (FALLBACK) -->
<?php if (!empty($error_message) || !empty($success_message)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if (!empty($error_message)): ?>
            var modalEditEl = document.getElementById('modalEditPelanggan');
            if (modalEditEl) {
                var modalEdit = bootstrap.Modal.getOrCreateInstance(modalEditEl);
                modalEdit.show();
            }

            Swal.fire({
                icon: 'error',
                title: 'Terjadi Kesalahan',
                text: <?= json_encode($error_message); ?>,
                confirmButtonColor: '#3498db'
            });
        <?php endif; ?>
    });
</script>
<?php endif; ?>
