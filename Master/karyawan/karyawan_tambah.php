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
    $nik             = $_POST['nik'];
    $nama            = $_POST['nama'];
    $jenis_kelamin   = $_POST['jenis_kelamin'];
    $tempat_lahir    = $_POST['tempat_lahir'];
    $tanggal_birth   = $_POST['tanggal_lahir'];
    $tanggal_lahir   = !empty($tanggal_birth) ? $tanggal_birth : null;
    $agama           = $_POST['agama'];
    $status_nikah    = $_POST['status_pernikahan'];
    $goldar          = $_POST['goldar'];
    $telp            = $_POST['telepon'];
    $email           = $_POST['email'];
    $user            = $_POST['user'];
    $pass            = $_POST['pass'];
    $pass_konf       = $_POST['pass_konfirm']; 
    $role            = $_POST['role'];
    
    $alamat_ktp      = $_POST['alamat_ktp'];
    $alamat_domisili = $_POST['alamat_domisili'];
    $kelurahan       = $_POST['kelurahan'];
    $kecamatan       = $_POST['kecamatan'];
    $kota_kabupaten  = $_POST['kota_kabupaten'];
    $provinsi        = $_POST['provinsi'];
    $kode_pos        = $_POST['kode_pos'];
    
    $jab             = $_POST['jabatan'];
    $status_karyawan = $_POST['status_karyawan'];
    $status          = 'Aktif'; 
    
    $created_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin'; 

    // --- VALIDASI SERVER-SIDE PHP (MUTLAK & AMAN) ---
    if (empty($_FILES['foto']['name'])) {
        $error_message = 'Foto karyawan wajib diunggah!';
    } elseif ($pass !== $pass_konf) {
        $error_message = 'Password dan Konfirmasi Password tidak cocok!';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pass)) {
        $error_message = 'Kata Sandi minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka!';
    } elseif (strlen($nik) !== 16 || !ctype_digit($nik)) {
        $error_message = 'NIK tidak valid! Harus berupa angka dan tepat 16 karakter.';
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $nama)) {
        $error_message = 'Nama lengkap hanya boleh diisi oleh huruf!';
    } elseif (strlen($nama) < 3 || strlen($nama) > 50) {
        $error_message = 'Nama lengkap harus berada di kisaran 3 sampai 50 karakter!';
    } elseif (!preg_match("/^[a-zA-Z]+$/", $user)) {
        $error_message = 'Nama pengguna hanya boleh diisi oleh huruf alfabet tanpa angka atau spasi!';
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
    } elseif (!in_array($agama, ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu', 'Lainnya'])) {
        $error_message = 'Pilihan Agama tidak valid!';
    } elseif (!in_array($status_nikah, ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'])) {
        $error_message = 'Pilihan Status Pernikahan tidak valid!';
    } elseif (!in_array($goldar, ['A', 'B', 'AB', 'O', '-'])) {
        $error_message = 'Pilihan Golongan Darah tidak valid!';
    } elseif (!in_array($role, ['Admin', 'Staff'])) {
        $error_message = 'Pilihan Role Akses tidak valid!';
    } elseif (!in_array($status_karyawan, ['Tetap', 'Kontrak', 'Magang'])) {
        $error_message = 'Pilihan Status Kepegawaian tidak valid!';
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
                    $error_message = 'Usia karyawan tidak boleh di bawah 17 tahun!';
                }
            }
        }
        
        // Cek format alamat KTP & Domisili
        if (empty($error_message)) {
            if (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat_ktp) || strlen($alamat_ktp) < 20) {
                $error_message = 'Alamat KTP tidak valid! Harus diawali dengan "Jl." or "Jalan" and minimal 20 karakter.';
            } elseif (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat_domisili) || strlen($alamat_domisili) < 20) {
                $error_message = 'Alamat Domisili tidak valid! Harus diawali dengan "Jl." or "Jalan" and minimal 20 karakter.';
            }
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
                    $foto_baru = "staff_" . time() . "." . $ekstensi;
                    $target_dir = "../../assets/uploads/karyawan/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    move_uploaded_file($tmp_name, $target_dir . $foto_baru);
                } else {
                    $upload_ok = false;
                    $error_message = 'Format file foto tidak valid! Gunakan JPG, JPEG atau PNG.';
                }
            }

            // Jika upload berhasil dan tidak ada error validasi dasar, jalankan stored procedure
            if ($upload_ok && empty($error_message)) {
                $pass_hashed = password_hash($pass, PASSWORD_DEFAULT); 

                // PANGGIL STORED PROCEDURE (sp_Karyawan_Create)
                $sql = "{call sp_Karyawan_Create(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
                        
                $params = array(
                    $nik, 
                    $nama, 
                    $jenis_kelamin, 
                    $tempat_lahir, 
                    $tanggal_lahir, 
                    $agama, 
                    $status_nikah, 
                    $goldar, 
                    $telp, 
                    $email, 
                    $user, 
                    $pass_hashed, 
                    $role, 
                    $foto_baru, 
                    $alamat_ktp, 
                    $alamat_domisili, 
                    $kelurahan, 
                    $kecamatan, 
                    $kota_kabupaten, 
                    $provinsi, 
                    $kode_pos, 
                    $jab, 
                    $status_karyawan, 
                    $status, 
                    $created_by
                );
                
                $stmt = sqlsrv_query($conn, $sql, $params);

                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    if ($errors !== null) {
                        // Membersihkan prefix [Microsoft][ODBC...] dari pesan error
                        $raw_error = $errors[0]['message'];
                        $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
                    } else {
                        $error_message = 'Terjadi kesalahan sistem saat menghubungi database.';
                    }
                } else { 
                    // Mengonsumsi seluruh result set untuk menangkap dan menampilkan error SQL Server yang sesungguhnya (seperti RAISERROR atau THROW)
                    $has_errors = false;
                    do {
                        $errors = sqlsrv_errors();
                        if ($errors !== null) {
                            // Membersihkan prefix [Microsoft][ODBC...] dari pesan error di result set berikutnya
                            $raw_error = $errors[0]['message'];
                            $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
                            $has_errors = true;
                            break;
                        }
                    } while (sqlsrv_next_result($stmt));

                    if (!$has_errors) {
                        $success_message = 'Berhasil menyimpan data staff baru!';
                    }
                    sqlsrv_free_stmt($stmt);
                }
            }
        }
    }
}
?>

<!-- MEMASTIKAN PUSTAKA SWEETALERT, FONTAWESOME, JQUERY, DAN SELECT2 SIAP -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE CSS EXCLUSIVE MODAL TAMBAH -->
<style>
    :root { 
        --primary-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        --accent-color: #3498db; 
        --border-color: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahKaryawan {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalTambahKaryawan {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahKaryawan.show .modal-content-custom {
        animation: modalZoomIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg { 
        background: var(--primary-gradient); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
        border-top-left-radius: 1.5rem;
        border-top-right-radius: 1.5rem;
    }

    #modalTambahKaryawan .modal-dialog {
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
        color: var(--accent-color); 
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

    /* KONSISTENSI UKURAN DAN DESIGN FORM */
    .form-control, .form-select { 
        padding: 0.75rem 1rem; 
        border-radius: 0.75rem; 
        border: 1.5px solid var(--border-color); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        outline: none;
    }

    /* SELECT2 MODIFIKASI AGAR MATCH DENGAN BOOTSTRAP */
    .select2-container {
        z-index: 9999999 !important; /* Memastikan dropdown berada di paling atas modal */
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color) !important;
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

    /* KONSISTENSI BENTUK KETIKA DROPDOWN SELECT2 AKTIF */
    .select2-container--open.select2-container--default .select2-selection--single {
        border-color: var(--accent-color) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    /* Jika dropdown terbuka ke atas */
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
        border-color: transparent transparent var(--accent-color) transparent !important;
    }

    /* DROPDOWN CONTAINER */
    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
        z-index: 9999999 !important;
        overflow: hidden;
        animation: select2FadeIn 0.1s ease-out;
    }

    /* Pengaturan menempel presisi */
    .select2-container--open .select2-dropdown--below {
        border-top: none !important;
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        margin-top: -1px !important; /* Menutup celah jarak */
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

    /* Kotak pencarian di dalam dropdown */
    .select2-search--dropdown {
        padding: 0.6rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        position: relative; /* Ditambahkan agar penempatan ikon kaca pembesar presisi */
    }

    /* FITUR KACA PEMBESAR DI INPUT DROPDOWN SEARCH */
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
        border: 1.5px solid var(--border-color) !important;
        border-radius: 0.6rem !important;
        padding: 0.55rem 2.2rem 0.55rem 0.9rem !important; /* Diubah padding kanan dari 0.9rem ke 2.2rem agar teks tidak menabrak ikon */
        font-size: 0.875rem !important;
        outline: none !important;
        background: #ffffff !important;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent-color) !important;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
    }

    /* Daftar hasil pencarian */
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
        background-color: var(--accent-color) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #eaf4fc !important;
        color: var(--accent-color) !important;
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
        border: 1.5px solid var(--border-color);
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
        border-color: var(--accent-color);
    }

    .input-group-custom:focus-within .btn-toggle-pass {
        border-color: var(--accent-color);
        background-color: #ffffff;
    }

    .btn-simpan { 
        background: var(--primary-gradient); 
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
        color: var(--accent-color); 
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
        display: none;
    }
</style>

<!-- MODAL CONTAINER (PENGHAPUSAN tabindex="-1" AGAR DROPDOWN SEARCH SELECT2 BISA DIKETIK LUAR BIASA AMAN) -->
<div class="modal fade" id="modalTambahKaryawan" aria-labelledby="modalTambahKaryawanLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-user-plus fa-3x mb-3"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Tambah Staff Baru</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Input data karyawan.</p>
            </div>

            <form id="formTambahKaryawan" action="" method="POST" enctype="multipart/form-data">
                <!-- INPUT HIDDEN UNTUK DETEKSI POS METHOD SAAT JS CALL .submit() -->
                <input type="hidden" name="simpan" value="1">

                <div class="form-container">
                    
                    <!-- BAGIAN 1: IDENTITAS DIRI (SIMETRIS) -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-id-card"></i></div>
                        Identitas & Data Diri Staff
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nomor Induk Kependudukan (NIK)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="kar_nik" name="nik" class="form-control" maxlength="16" placeholder="Masukkan 16 Digit NIK" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap Sesuai KTP<span class="text-danger-marker">*</span></label>
                            <input type="text" id="kar_nama" name="nama" class="form-control" placeholder="Hanya huruf alfabet" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Jenis Kelamin<span class="text-danger-marker">*</span></label>
                            <select name="jenis_kelamin" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Jenis Kelamin</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tempat Lahir (Kabupaten/Kota)<span class="text-danger-marker">*</span></label>
                            <select id="tempat_lahir" name="tempat_lahir" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kabupaten/Kota</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir (Min. 17 Tahun)<span class="text-danger-marker">*</span></label>
                            <input type="date" id="tanggal_lahir" name="tanggal_lahir" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Agama<span class="text-danger-marker">*</span></label>
                            <select name="agama" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Agama</option>
                                <option value="Islam">Islam</option>
                                <option value="Kristen">Kristen</option>
                                <option value="Katolik">Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Buddha">Buddha</option>
                                <option value="Khonghucu">Khonghucu</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status Pernikahan<span class="text-danger-marker">*</span></label>
                            <select name="status_pernikahan" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Status</option>
                                <option value="Belum Kawin">Belum Kawin</option>
                                <option value="Kawin">Kawin</option>
                                <option value="Cerai Hidup">Cerai Hidup</option>
                                <option value="Cerai Mati">Cerai Mati</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Golongan Darah<span class="text-danger-marker">*</span></label>
                            <select name="goldar" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Golongan Darah</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="AB">AB</option>
                                <option value="O">O</option>
                                <option value="-">-</option>
                            </select>
                        </div>
                    </div>

                    <!-- BAGIAN 2: ALAMAT LENGKAP (CASCADING DROPDOWNS VIA API) -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-map-marked-alt"></i></div>
                        Informasi Alamat Lengkap
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Alamat Sesuai KTP (Mulai Jl./Jalan, min 20 char)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="alamat_ktp" name="alamat_ktp" class="form-control" placeholder="Contoh: Jl. Diponegoro No. 25" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat Domisili (Mulai Jl./Jalan, min 20 char)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="alamat_domisili" name="alamat_domisili" class="form-control" placeholder="Contoh: Jl. Gatot Subroto No. 12" required>
                        </div>
                        
                        <!-- CASCADING SELECTS -->
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
                            <input type="text" id="kar_kodepos" name="kode_pos" class="form-control" maxlength="5" placeholder="5 Digit Angka" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                    </div>

                    <!-- BAGIAN 3: KONTAK & KEPEGAWAIAN -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-briefcase"></i></div>
                        Hubungan Kerja & Kontak
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Alamat Surel Aktif<span class="text-danger-marker">*</span></label>
                            <input type="email" id="kar_email" name="email" class="form-control" placeholder="contoh@petshop.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon (Skala Internasional +62/+63)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="kar_telepon" name="telepon" class="form-control" placeholder="Contoh: +62812xxxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan Kerja<span class="text-danger-marker">*</span></label>
                            <select name="jabatan" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Jabatan Kerja</option>
                                <option value="Manager">Manager</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Staff Kasir">Staff Kasir</option>
                                <option value="Staff Grooming">Staff Grooming</option>
                                <option value="Veterinarian">Veterinarian (Dokter Hewan)</option>
                                <option value="Asisten Dokter Hewan">Asisten Dokter Hewan</option>
                                <option value="Admin Logistik">Admin Logistik</option>
                                <option value="Keeper">Keeper (Penjaga Hewan)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status Kepegawaian<span class="text-danger-marker">*</span></label>
                            <select name="status_karyawan" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Status Hubungan Kerja</option>
                                <option value="Tetap">Tetap</option>
                                <option value="Kontrak">Kontrak</option>
                                <option value="Magang">Magang</option>
                            </select>
                        </div>
                    </div>

                    <!-- BAGIAN 4: AKSES LOGIN & KEAMANAN -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-key"></i></div>
                        Akses Masuk & Keamanan
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Pengguna Aplikasi (Min. 5 Huruf)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="kar_user" name="user" class="form-control" placeholder="Masukkan Nama Pengguna" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role Akses<span class="text-danger-marker">*</span></label>
                            <select name="role" class="form-select select2-nosearch" required>
                                <option value="" disabled selected>Pilih Role Akses</option>
                                <option value="Admin">Admin</option>
                                <option value="Staff">Staff</option>
                            </select>
                        </div>
                        
                        <!-- PASSWORD WITH TOGGLE EYE -->
                        <div class="col-md-6">
                            <label class="form-label">Kata Sandi<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="kar_pass" name="pass" class="form-control" placeholder=" Masukkan Kata Sandi " required>
                                <button class="btn btn-toggle-pass" type="button" data-target="kar_pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="kar_password_strength_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                        
                        <!-- CONFIRM PASSWORD WITH TOGGLE EYE -->
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Kata Sandi<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="kar_pass_konfirm" name="pass_konfirm" class="form-control" placeholder="Ulangi Kata Sandi" required>
                                <button class="btn btn-toggle-pass" type="button" data-target="kar_pass_konfirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="kar_password_match_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                    </div>

                    <!-- BAGIAN 5: FOTO PROFIL -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-camera"></i></div>
                        Profil Foto Staff
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="avatar-container" class="avatar-preview-circle">
                                    <span id="avatar-initials">?</span>
                                    <img id="avatar-image-preview" src="" alt="Pratinjau Foto">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Pilih Foto Karyawan (Wajib)<span class="text-danger-marker">*</span></label>
                                    <input type="file" id="foto-input" name="foto" class="form-control" accept="image/*" required>
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG</strong>. Maksimal file: <strong>2 MB</strong>.</div>
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
                            <i class="fas fa-save me-2"></i>Simpan Data Staff
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: VALIDASI KETAT, CASCADING WILAYAH INDONESIA, DAN INTEGRASI SELECT2 -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // SOLUSI UTAMA: Menggunakan native capturing listener (true) agar input pencarian Select2 bisa diketik secara normal
    document.addEventListener('focusin', function(e) {
        if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
            e.stopImmediatePropagation();
        }
    }, true);

    // Membatasi pilihan tanggal lahir maksimal adalah hari ini (tidak bisa memilih tanggal di masa depan)
    const dateInput = document.getElementById('tanggal_lahir');
    if (dateInput) {
        const todayStr = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('max', todayStr);
    }

    // INISIALISASI SELECT2 DENGAN PORTING KE BODY DOKUMEN (Tetap sesuai CSS asli Anda)
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

    // Inisialisasi Select2 tanpa Search (Tetap sesuai CSS asli Anda)
    $('.select2-nosearch').select2({
        dropdownParent: $(document.body),
        width: '100%',
        minimumResultsForSearch: Infinity,
        language: {
            noResults: function () { return 'Data tidak ditemukan'; }
        }
    });

    // Mencegah focus trap Bootstrap menghalangi input pencarian Select2 (di luar modal wrapper)
    $('#modalTambahKaryawan').on('shown.bs.modal', function() {
        $(document).off('focusin.bs.modal');
    });

    // PASSWORD VISIBILITY TOGGLE
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

    const namaInput = document.getElementById('kar_nama');
    const userInput = document.getElementById('kar_user');
    const kodePosInput = document.getElementById('kar_kodepos');
    const initialsSpan = document.getElementById('avatar-initials');
    const imagePreview = document.getElementById('avatar-image-preview');
    const fileInput = document.getElementById('foto-input');
    
    const passInput = document.getElementById('kar_pass');
    const confirmInput = document.getElementById('kar_pass_konfirm');
    const feedback = document.getElementById('kar_password_match_feedback');
    const telpInput = document.getElementById('kar_telepon');
    const nikInput = document.getElementById('kar_nik');
    const form = document.getElementById('formTambahKaryawan');

    const alamatKtp = document.getElementById('alamat_ktp');
    const alamatDomisili = document.getElementById('alamat_domisili');

    // NIK & No Telepon: Batasi hanya angka saat diketik
    if (nikInput) {
        nikInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    if (telpInput) {
        telpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });
    }

    // Nama Lengkap: Hanya menerima huruf alfabet dan spasi
    if (namaInput) {
        namaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            updateInitials();
        });
    }

    // Nama Pengguna: Hanya menerima alfabet (Tanpa angka, spasi, atau simbol)
    if (userInput) {
        userInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z]/g, '');
        });
    }

    // LOGIKA GENERATOR INISIAL NAMA
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

    // VALIDASI USIA MINIMAL 17 TAHUN SECARA REAL-TIME
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            
            // Cek jika memilih tanggal melebihi hari ini
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
                    text: 'Karyawan harus berusia minimal 17 tahun!',
                    confirmButtonColor: '#3498db'
                });
                this.value = '';
            }
        });
    }

    // LOGIKA PRATINJAU FOTO & VALIDASI JENIS FILE
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
                        title: 'Ukuran File Terlalu Besar',
                        text: 'Maksimal batas ukuran file foto adalah 2 MB.',
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

    // VALIDASI PASSWORD MATCH REAL-TIME
    function checkPasswordMatch() {
        const pVal = passInput.value;
        const cVal = confirmInput.value;

        if (cVal === "") {
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

    if (passInput && confirmInput) {
        passInput.addEventListener('input', checkPasswordMatch);
        confirmInput.addEventListener('input', checkPasswordMatch);
    }

    // VALIDASI KEKUATAN PASSWORD REAL-TIME
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    const strengthFeedback = document.getElementById('kar_password_strength_feedback');

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

    // Ketika Kota/Kabupaten Berubah -> Load Kecamatan & Isi Tempat Lahir
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

    // Memuat seluruh daftar kota indonesia secara lengkap untuk Tempat Lahir
    fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json')
        .then(response => response.json())
        .then(provinces => {
            let selectTempatLahir = $('#tempat_lahir');
            selectTempatLahir.html('<option value="" disabled selected>Memuat kota...</option>');
            
            // Mengambil semua kabupaten/kota dari seluruh provinsi
            const fetchPromises = provinces.map(p => 
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${p.id}.json`).then(r => r.json())
            );

            Promise.all(fetchPromises).then(results => {
                let options = '<option value="" disabled selected>Pilih Kabupaten/Kota</option>';
                results.flat().forEach(city => {
                    options += `<option value="${city.name}">${city.name}</option>`;
                });
                selectTempatLahir.html(options).trigger('change');
            });
        });

    // ================== DETEKSI & VALIDASI FORMAT ALAMAT ==================
    function validasiFormatAlamat(alamat) {
        const val = alamat.trim();
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

    // ================== PENANGANAN SUBMIT DENGAN SWEETALERT ==================
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // 1. Validasi Foto (Wajib)
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Foto Wajib Diunggah',
                    text: 'Silakan pilih foto profil karyawan terlebih dahulu!',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 2. Validasi Panjang NIK
            if (nikInput.value.length !== 16) {
                Swal.fire({
                    icon: 'error',
                    title: 'NIK Tidak Valid',
                    text: 'Panjang NIK harus tepat 16 digit angka.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 3. Validasi Nama Alfabet
            if (!/^[a-zA-Z\s]+$/.test(namaInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Nama Tidak Valid',
                    text: 'Nama lengkap hanya boleh mengandung huruf alfabet dan spasi.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 3.1 Validasi Batasan Karakter Nama
            if (namaInput.value.trim().length < 3 || namaInput.value.trim().length > 50) {
                Swal.fire({
                    icon: 'error',
                    title: 'Panjang Nama Tidak Valid',
                    text: 'Nama lengkap harus berkisar antara 3 hingga 50 karakter.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 3.2 Validasi Username Alfabet saja (Tanpa Angka/Spasi)
            if (!/^[a-zA-Z]+$/.test(userInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Username Tidak Valid',
                    text: 'Nama pengguna hanya boleh mengandung huruf alfabet (A-Z, a-z).',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 3.3 Validasi Batasan Karakter Username
            if (userInput.value.length < 5 || userInput.value.length > 20) {
                Swal.fire({
                    icon: 'error',
                    title: 'Panjang Username Tidak Valid',
                    text: 'Nama pengguna aplikasi harus berkisar antara 5 hingga 20 karakter.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 3.4 Validasi Format Kode Pos (Tepat 5 Digit)
            if (kodePosInput.value.length !== 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kode Pos Tidak Valid',
                    text: 'Kode Pos wajib diisi dengan tepat 5 digit angka!',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 4. Validasi Format Alamat KTP
            if (!validasiFormatAlamat(alamatKtp.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Alamat KTP Tidak Valid',
                    text: 'Alamat harus diawali dengan "Jl." atau "Jalan", minimal 20 karakter, serta tidak menggunakan teks acak.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 5. Validasi Format Alamat Domisili
            if (!validasiFormatAlamat(alamatDomisili.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Alamat Domisili Tidak Valid',
                    text: 'Alamat domisili harus diawali dengan "Jl." atau "Jalan", minimal 20 karakter, serta tidak menggunakan teks acak.',
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
                    text: 'Gunakan format internasional (contoh: +62812xxxxxxx, +63912xxxxxxx) atau format lokal (0812xxxxxxx).',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 7. Validasi Format Alamat Email (Surel)
            const emailVal = document.getElementById('kar_email').value.trim();
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

            // 8. Validasi Kekuatan Password
            if (!passwordRegex.test(passInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Kata Sandi Terlalu Lemah',
                    text: 'Kata sandi minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // 9. Validasi Password Cocok
            if (!checkPasswordMatch()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Password Tidak Cocok',
                    text: 'Silakan pastikan isi kolom konfirmasi password sama dengan kolom password.',
                    confirmButtonColor: '#3498db'
                });
                return;
            }

            // Jika lulus seluruh lapisan validasi, kirim form ke server
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
            // Tampilkan kembali modal agar admin tidak perlu membuka ulang
            var modalTambah = new bootstrap.Modal(document.getElementById('modalTambahKaryawan'));
            modalTambah.show();

            Swal.fire({
                icon: 'error',
                title: 'Gagal Menyimpan',
                text: <?= json_encode($error_message); ?>,
                confirmButtonColor: '#3498db'
            });
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            var modalEl = document.getElementById('modalTambahKaryawan');
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
                    window.location.href = 'karyawan_tampil.php';
                }
            }).then(() => {
                window.location.href = 'karyawan_tampil.php';
            });
        <?php endif; ?>
    });
</script>
<?php endif; ?>