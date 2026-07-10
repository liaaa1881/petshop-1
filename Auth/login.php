<?php
session_start();
require_once '../Config/koneksi.php'; 

// Definisikan proses login Google
$google_login_url = "auth_google.php";

$error = "";
$success = "";

// --- KONFIGURASI LINK FACEBOOK LOGIN ---
$fb_app_id = '1373784418006017';
$fb_redirect_uri = 'http://localhost:3000/Auth/facebook-callback.php';
$fb_state = bin2hex(random_bytes(16));
$_SESSION['fb_oauth_state'] = $fb_state;

// Perbaikan nama variabel di bawah ini agar sesuai dengan deklarasi di atas
$facebook_login_url = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
    'client_id'     => $fb_app_id,
    'redirect_uri'  => $fb_redirect_uri,
    'state'         => $fb_state,
    'scope'         => 'email',
]);

// --- LOGIKA REGISTER ---
$show_register_first = false; 
if (isset($_POST['register'])) {
    $show_register_first = true;
    $nama             = $_POST['nama'];
    $username         = $_POST['reg_username'];
    $password         = $_POST['reg_password'];
    $password_confirm = $_POST['reg_password_confirm']; 
    $telp             = $_POST['telp'];
    $email            = $_POST['email'];
    $jenis_kelamin    = $_POST['jenis_kelamin']; 
    $alamat           = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';

    // Validasi kecocokan password di sisi server (PHP) sebagai cadangan
    if ($password !== $password_confirm) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (strcasecmp(trim($nama), trim($username)) === 0) {
        $error = "Nama Lengkap tidak boleh sama dengan Nama Pengguna!";
    } elseif (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat)) {
        $error = "Alamat harus diawali dengan 'Jl.' atau 'Jalan'!";
    } elseif (strlen($alamat) < 20) {
        $error = "Alamat harus minimal 20 karakter!";
    } elseif (preg_match('/[bcdfghjklmnpqrstvwxyz]{6,}/i', $alamat) || substr_count($alamat, ' ') < 2) {
        $error = "Alamat terdeteksi tidak valid atau menggunakan teks acak!";
    } else {
        $sql_check = "SELECT Username FROM Pelanggan WHERE Username = ?";
        $params_check = array($username);
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);

        if (sqlsrv_has_rows($stmt_check)) {
            $error = "Nama Pengguna sudah digunakan!";
        } else {
            $sql_ins = "INSERT INTO Pelanggan (Nama_Pelanggan, No_Telepon, Email, Username, Password, Status_Member, Pel_status, Pel_created_date, Pel_is_deleted) 
                        VALUES (?, ?, ?, ?, ?, 'Non Member', 'Aktif', GETDATE(), 0)";
            $params_ins = array($nama, $telp, $email, $username, $password);
            $stmt_ins = sqlsrv_query($conn, $sql_ins, $params_ins);

            if ($stmt_ins) {
                $success = "Akun berhasil dibuat! Silakan Login.";
                $show_register_first = false; 
                // Bersihkan input setelah pendaftaran berhasil
                $_POST = array();
            } else {
                $error = "Gagal mendaftar!";
            }
        }
    }
}

// --- LOGIKA LOGIN MANUAL (MENDUKUNG 4 ROLE: Admin, Staff, Pelanggan, Supplier) ---
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. CEK AKUN KARYAWAN (Admin & Staff) - Menggunakan Stored Procedure (sp_Karyawan_Login)
    $sql_kar = "{call sp_Karyawan_Login(?, ?)}";
    $params_kar = array($username, $password);
    $stmt_kar = sqlsrv_query($conn, $sql_kar, $params_kar);

    if ($stmt_kar === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $user_kar = sqlsrv_fetch_array($stmt_kar, SQLSRV_FETCH_ASSOC);

    if ($user_kar) {
        $_SESSION['user_id'] = $user_kar['ID_Karyawan'];
        $_SESSION['nama'] = $user_kar['Nama_Karyawan'];
        $_SESSION['role'] = $user_kar['Role']; 
        header("Location: ../Dashboard/index.php");
        exit();
    } else {
        // 2. CEK AKUN PELANGGAN - Menggunakan Stored Procedure (sp_Pelanggan_Login)
        $sql_pel = "{call sp_Pelanggan_Login(?, ?)}";
        $params_pel = array($username, $password);
        $stmt_pel = sqlsrv_query($conn, $sql_pel, $params_pel);

        if ($stmt_pel === false) {
            die(print_r(sqlsrv_errors(), true));
        }

        $user_pel = sqlsrv_fetch_array($stmt_pel, SQLSRV_FETCH_ASSOC);

        if ($user_pel) {
            $_SESSION['user_id'] = $user_pel['ID_Pelanggan'];
            $_SESSION['nama'] = $user_pel['Nama_Pelanggan'];
            $_SESSION['role'] = 'Pelanggan'; 
            header("Location: ../Dashboard/index.php");
            exit();
        } else {
            // 3. CEK AKUN SUPPLIER (Kueri manual karena tidak ada Stored Procedure khusus Supplier)
            $sql_sup = "SELECT * FROM Supplier WHERE Username = ? AND Password = ? AND Sup_is_deleted = 0";
            $params_sup = array($username, $password);
            $stmt_sup = sqlsrv_query($conn, $sql_sup, $params_sup);
            
            if ($stmt_sup === false) {
                $sql_sup = "SELECT * FROM Supplier WHERE Username = ? AND Password = ?";
                $stmt_sup = sqlsrv_query($conn, $sql_sup, $params_sup);
            }
            
            $user_sup = sqlsrv_fetch_array($stmt_sup, SQLSRV_FETCH_ASSOC);

            if ($user_sup) {
                $_SESSION['user_id'] = $user_sup['ID_Supplier'];
                $_SESSION['nama'] = $user_sup['Nama_Supplier'];
                $_SESSION['role'] = 'Supplier';
                header("Location: ../Dashboard/index.php");
                exit();
            } else {
                $error = "Nama Pengguna atau Kata Sandi salah!";
            }
        }
    }
}

// Ambil nilai jenis kelamin yang diinput sebelumnya untuk mempertahankan state custom dropdown
$selected_jk = isset($_POST['jenis_kelamin']) ? $_POST['jenis_kelamin'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Petshop Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <!-- SCRIPT GOOGLE & SWEETALERT2 -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
        body {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?auto=format&fit=crop&w=1350&q=80');
            background-size: cover; background-position: center;
            height: 100vh; display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }

        /* PERBAIKAN TATA LETAK SAAT SWEETALERT MUNCUL (mencegah pergeseran body & html) */
        html.swal2-shown, body.swal2-shown {
            height: 100vh !important;
            overflow: hidden !important;
        }

        /* Kontainer Utama dengan Transisi Dimensi Dinamis */
        .container {
            background: white; 
            display: flex; 
            border-radius: 25px; 
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); 
            animation: containerEntry 0.8s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
            transition: width 0.5s cubic-bezier(0.25, 1, 0.5, 1), 
                        height 0.5s cubic-bezier(0.25, 1, 0.5, 1), 
                        max-height 0.5s cubic-bezier(0.25, 1, 0.5, 1);
        }

        /* Ukuran Mode Login */
        .container.login-mode {
            width: 780px;
            height: 530px;
        }

        /* Ukuran Mode Register */
        .container.register-mode {
            width: 900px;
            height: 85vh;
            max-height: 750px;
        }

        @keyframes containerEntry {
            from { opacity: 0; transform: scale(0.9) translateY(40px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        /* Animasi Getar saat terjadi Error */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-6px); }
            20%, 40%, 60%0%, 80% { transform: translateX(6px); }
        }

        .shake-error {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shakeInput {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }

        .shake-field {
            animation: shakeInput 0.4s ease-in-out;
        }

        .shake-field input {
            border-color: #dc2626 !important;
            background: #fef2f2 !important;
            box-shadow: 0 0 8px rgba(220, 38, 38, 0.2) !important;
        }

        .shake-field i {
            color: #dc2626 !important;
        }

        /* Status validasi merah tanpa animasi getar (untuk validasi real-time) */
        .field-invalid input {
            border-color: #dc2626 !important;
            background: #fef2f2 !important;
        }

        .field-invalid > i.field-icon {
            color: #dc2626 !important;
        }

        .field-valid input {
            border-color: #16a34a !important;
        }

        .field-hint {
            font-size: 11px;
            color: #94a3b8;
            margin: -10px 0 12px 5px;
            display: block;
        }

        .field-hint.hint-error {
            color: #dc2626;
        }

        .sidebar {
            background: #2c3e50; color: white; width: 40%; padding: 40px;
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
            position: relative;
            transition: background 0.5s ease;
        }
        
        .sidebar i.fa-paw { 
            font-size: 80px; color: #3498db; margin-bottom: 20px; 
            animation: pawFloat 3s ease-in-out infinite;
        }

        @keyframes pawFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-12px) rotate(8deg); }
        }

        .sidebar h2 { font-size: 28px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 2px; }

        /* Area Form */
        .form-area {
            width: 60%; 
            padding: 40px 45px; 
            position: relative;
            display: flex; 
            flex-direction: column; 
            justify-content: flex-start;
            background: #fff;
            overflow-y: auto;
            max-height: 100%;
        }

        .form-area::-webkit-scrollbar {
            width: 6px;
        }
        .form-area::-webkit-scrollbar-track {
            background: transparent;
        }
        .form-area::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .form-area::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Transisi Lembut & Efek Scaling Saat Berpindah Form */
        .form-content { 
            display: none; 
            opacity: 0;
            transform: scale(0.96) translateY(10px);
            transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.25, 1, 0.5, 1); 
        }
        
        .form-content.active { 
            display: block; 
            opacity: 1;
            transform: scale(1) translateY(0);
        }

        h3 { font-size: 32px; margin-bottom: 5px; color: #333; font-weight: 700; }
        .subtitle { color: #888; font-size: 14px; margin-bottom: 30px; }

        /* Label khusus untuk field Tanggal Lahir */
        .date-label {
            font-size: 11px;
            color: #94a3b8;
            margin: -5px 0 6px 5px;
            display: block;
            letter-spacing: 0.3px;
        }

        /* INPUT FIELD */
        .input-group { 
            position: relative; 
            margin-bottom: 15px; 
        }
        
        /* Menggunakan kelas khusus "field-icon" agar tidak memengaruhi ikon mata di kanan */
        .input-group > i.field-icon { 
            position: absolute; 
            left: 15px; 
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            pointer-events: none;
            z-index: 5;
        }
        
        .input-group input {
            width: 100%; 
            padding: 12px 12px 12px 45px; 
            border-radius: 10px;
            border: 1px solid #e1e8ed; 
            background: #f9f9f9; 
            outline: none; 
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-size: 14px;
            color: #333;
        }

        .input-group input:focus { 
            border-color: #000000;
            background: white; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .input-group:focus-within > i.field-icon {
            color: #000000;
            transform: translateY(-50%) scale(1.1);
        }

        /* Tombol Toggle Lihat/Sembunyikan Kata Sandi (Ikon Mata) */
        .password-group input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bdc3c7;
            cursor: pointer;
            z-index: 6;
            font-size: 15px;
            display: none; /* Muncul lewat JS saat ada isian */
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #000;
        }

        /* Saat field terisi, ikon mata akan tetap muncul */
        .password-group.has-value .toggle-password {
            display: block !important;
        }

        /* Sembunyikan ikon bawaan browser untuk password reveal */
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }

        /* DESAIN CUSTOM SELECT DROPDOWN */
        .select-group {
            position: relative;
            cursor: pointer;
            user-select: none;
        }

        /* Tampilan Trigger Utama Custom Dropdown */
        .custom-select-trigger {
            width: 100%;
            padding: 12px 15px 12px 45px; 
            border-radius: 10px;
            border: 1px solid #e1e8ed;
            background: #f9f9f9;
            font-size: 14px;
            color: #bdc3c7; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            box-sizing: border-box;
        }

        /* Ketika data sudah di pilih */
        .custom-select-trigger.selected {
            color: #333;
        }

        /* Hover & Focus state */
        .select-group.open .custom-select-trigger,
        .custom-select-trigger:hover {
            border-color: #000000;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        /* Ikon Panah Menggunakan FontAwesome murni */
        .custom-select-trigger .dropdown-arrow-icon {
            font-size: 14px;
            color: #bdc3c7;
            transition: transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), color 0.3s ease;
            position: static !important;
            transform: none !important;
        }

        /* Rotasi panah ke atas saat dropdown dibuka */
        .select-group.open .custom-select-trigger .dropdown-arrow-icon {
            transform: rotate(180deg) !important;
            color: #000000;
        }

        /* Desain Kontainer Opsi Dropdown yang Melayang (Floating) */
        .custom-options-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            margin-top: 5px;
            padding: 5px 0;
            list-style: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            z-index: 999; 
            
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.98);
            transition: opacity 0.3s cubic-bezier(0.25, 1, 0.5, 1), 
                        transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), 
                        visibility 0.3s;
        }

        /* Tampilkan Opsi saat Mode Open Aktif */
        .select-group.open .custom-options-list {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .custom-options-list li {
            padding: 10px 20px;
            font-size: 14px;
            color: #333;
            transition: background 0.2s ease, color 0.2s ease;
            text-align: left;
        }

        .custom-options-list li:hover {
            background: #f1f5f9;
            color: #000;
        }

        .custom-options-list li.disabled-option {
            color: #cbd5e1;
            cursor: not-allowed;
            pointer-events: none;
            border-bottom: 1px dashed #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 5px;
        }

        /* TOMBOL UTAMA */
        .btn-main {
            width: 100%; padding: 15px; background: #34495e; color: white;
            border: none; border-radius: 10px; font-weight: bold; cursor: pointer;
            text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        /* Efek Kilatan Cahaya (Shine Effect) Saat Tombol Di-hover */
        .btn-main::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s ease;
        }

        .btn-main:hover::after {
            left: 100%;
        }

        .btn-main:hover { 
            background: #2c3e50; 
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.3);
        }

        .btn-main:active {
            transform: translateY(0);
        }

        .forgot-password-link {
            font-size: 12px; 
            color: #bdc3c7; 
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .forgot-password-link:hover {
            color: #3498db !important;
            transform: translateX(-3px);
        }

        .divider {
            display: flex; align-items: center; text-align: center; margin: 25px 0 15px 0;
            color: #ccc; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #eee; }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        .social-buttons-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            width: 100%;
            margin-top: 5px;
        }

        .btn-facebook-google-style {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 240px; 
            height: 40px; 
            background-color: #ffffff;
            border: 1px solid #dadce0;
            border-radius: 4px;
            color: #3c4043;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all .218s ease;
            cursor: pointer;
            box-sizing: border-box;
        }

        .btn-facebook-google-style:hover {
            background-color: #f8f9fa;
            border-color: #c2e7ff;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            transform: translateY(-1px);
        }

        .btn-facebook-google-style i {
            color: #1877f2; 
            font-size: 18px;
            margin-right: 12px;
        }

        .btn-toggle {
            margin-top: 30px; padding: 10px 25px; border: 2px solid white; background: transparent;
            color: white; border-radius: 30px; cursor: pointer; transition: all 0.3s ease; font-weight: 600;
            position: relative;
            z-index: 2;
        }
        .btn-toggle:hover { 
            background: white; 
            color: #2c3e50; 
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>

    <div class="container <?php echo !empty($error) ? 'shake-error' : ''; ?> <?php echo $show_register_first ? 'register-mode' : 'login-mode'; ?>">
        
        <!-- SIDEBAR KIRI -->
        <div class="sidebar" id="main-sidebar">
            <i class="fas fa-paw"></i>
            <h2>PETSHOP PRO</h2>
            <p id="side-text" style="opacity: 0.8; margin-bottom: 40px; transition: opacity 0.3s ease;">
                <?php echo $show_register_first ? 'Mari bergabung bersama pecinta anabul lainnya.' : 'Manajemen Modern Anabul Anda.'; ?>
            </p>
            <p id="toggle-desc"><?php echo $show_register_first ? 'Sudah punya akun?' : 'Belum punya akun?'; ?></p>
            <button class="btn-toggle" id="btn-switch" onclick="toggleForm()">
                <?php echo $show_register_first ? 'KEMBALI LOGIN' : 'DAFTAR SEKARANG'; ?>
            </button>
        </div>

        <!-- AREA FORM KANAN -->
        <div class="form-area">
            
            <!-- TOMBOL SILANG -->
            <a href="../Dashboard/dashboard_utama.php" 
               style="position: absolute; top: 20px; right: 25px; color: #bdc3c7; text-decoration: none; font-size: 24px; transition: all 0.3s ease; z-index: 10;"
               onmouseover="this.style.color='#e74c3c'; this.style.transform='rotate(90deg) scale(1.1)';"
               onmouseout="this.style.color='#bdc3c7'; this.style.transform='rotate(0deg) scale(1)';"
               title="Kembali ke Dashboard utama">
                <i class="fas fa-times-circle"></i>
            </a>

            <!-- FORM LOGIN -->
            <div id="login-form" class="form-content <?php echo !$show_register_first ? 'active' : ''; ?>">
                <h3>Selamat Datang!</h3>
                <p class="subtitle">Silakan masuk untuk mengakses.</p>
                
                <form method="POST">
                    <div class="input-group">
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" name="username" placeholder="Nama Pengguna" required>
                    </div>
                    <div class="input-group password-group">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" id="login_password" name="password" placeholder="Kata Sandi" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('login_password', this)"></i>
                    </div>
                    <div style="text-align: right; margin-bottom: 15px;">
                        <a href="lupa_password.php" class="forgot-password-link">Lupa kata sandi?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-main">
                        <span>MASUK KE SISTEM</span> <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="divider">ATAU MASUK DENGAN</div>

                <div class="social-buttons-container">
                    <a href="<?php echo $google_login_url; ?>" class="btn-facebook-google-style">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18px" height="18px" style="margin-right: 8px; vertical-align: middle;">
                            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                            <path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.41-4.69H24v9h12.75c-.55 2.94-2.2 5.44-4.69 7.11l7.3 5.66C43.68 36.8 46.5 31 46.5 24z"/>
                            <path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.98-6.19z"/>
                            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.3-5.66c-2.03 1.37-4.63 2.18-8.59 2.18-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                        </svg>
                        <span>Masuk dengan Google</span>
                    </a>
                    <a href="<?php echo $facebook_login_url; ?>" class="btn-facebook-google-style">
                        <i class="fab fa-facebook"></i>
                        <span>Masuk dengan Facebook</span>
                    </a>
                </div>
            </div>

            <!-- FORM REGISTER -->
            <div id="register-form" class="form-content <?php echo $show_register_first ? 'active' : ''; ?>">
                <h3>Buat Akun</h3>
                <p class="subtitle">Daftar sebagai member sekarang.</p>
                <form method="POST" onsubmit="return verifikasiPendaftaran(event)">
                    <!-- Nama Lengkap -->
                    <div class="input-group" id="nama-group">
                        <i class="fas fa-id-card field-icon"></i>
                        <input type="text" id="nama" name="nama" placeholder="Nama Lengkap" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                    </div>

                    <!-- NIK / No. KTP -->
                    <div class="input-group" id="nik-group">
                        <i class="fas fa-address-card field-icon"></i>
                        <input type="text" id="nik" name="nik" placeholder="16 Digit NIK / KTP" value="<?php echo isset($_POST['nik']) ? htmlspecialchars($_POST['nik']) : ''; ?>" required maxlength="16" inputmode="numeric">
                    </div>

                    <!-- No. Telepon -->
                    <div class="input-group" id="telp-group">
                        <i class="fas fa-phone field-icon"></i>
                        <input type="text" id="telp" name="telp" placeholder="No. Telepon (Contoh: +62812xxxxxxx)" value="<?php echo isset($_POST['telp']) ? htmlspecialchars($_POST['telp']) : ''; ?>" required>
                    </div>

                    <!-- Email -->
                    <div class="input-group" id="email-group">
                        <i class="fas fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>

                    <!-- Jenis Kelamin -->
                    <div class="input-group select-group" id="custom-dropdown-container">
                        <i class="fas fa-venus-mars field-icon"></i>
                        <div class="custom-select-trigger <?php echo !empty($selected_jk) ? 'selected' : ''; ?>" onclick="toggleCustomDropdown(event)">
                            <span id="selected-gender-text"><?php echo !empty($selected_jk) ? htmlspecialchars($selected_jk) : 'Pilih Jenis Kelamin'; ?></span>
                            <i class="fas fa-caret-down dropdown-arrow-icon"></i>
                        </div>
                        <ul class="custom-options-list">
                            <li onclick="selectGenderOption('', event)" class="disabled-option">Pilih Jenis Kelamin</li>
                            <li onclick="selectGenderOption('Laki-laki', event)">Laki-laki</li>
                            <li onclick="selectGenderOption('Perempuan', event)">Perempuan</li>
                        </ul>
                        <!-- INPUT TERSEMBUNYI UNTUK PHP DATABASE -->
                        <input type="hidden" id="jenis_kelamin" name="jenis_kelamin" value="<?php echo htmlspecialchars($selected_jk); ?>" required>
                    </div>

                    <!-- Tanggal Lahir -->
                    <label class="date-label" for="tgl_lahir">Tanggal Lahir</label>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt field-icon"></i>
                        <input type="date" id="tgl_lahir" name="tgl_lahir" value="<?php echo isset($_POST['tgl_lahir']) ? htmlspecialchars($_POST['tgl_lahir']) : ''; ?>" required>
                    </div>

                    <!-- Alamat -->
                    <div class="input-group" id="alamat-group">
                        <i class="fas fa-home field-icon"></i>
                        <input type="text" id="alamat" name="alamat" placeholder="Alamat Lengkap (Contoh: Jl. Mawar No. 10)" value="<?php echo isset($_POST['alamat']) ? htmlspecialchars($_POST['alamat']) : ''; ?>" required>
                    </div>

                    <!-- Username -->
                    <div class="input-group" id="username-group">
                        <i class="fas fa-user-plus field-icon"></i>
                        <input type="text" id="reg_username" name="reg_username" placeholder="Nama Pengguna Baru" value="<?php echo isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username']) : ''; ?>" required>
                    </div>

                    <!-- Kata Sandi -->
                    <div class="input-group password-group" id="password-main-group">
                        <i class="fas fa-key field-icon"></i>
                        <input type="password" id="reg_password" name="reg_password" placeholder="Kata Sandi (Min. 8 Karakter)" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('reg_password', this)"></i>
                    </div>

                    <!-- Konfirmasi Kata Sandi -->
                    <div class="input-group password-group" id="confirm-password-group">
                        <i class="fas fa-key field-icon"></i>
                        <input type="password" id="reg_password_confirm" name="reg_password_confirm" placeholder="Konfirmasi Kata Sandi" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('reg_password_confirm', this)"></i>
                    </div>
                    
                    <button type="submit" name="register" class="btn-main" style="background: #3498db; color: white;">
                        <span>DAFTAR MEMBER</span> <i class="fas fa-check-circle"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- PENYUNTINGAN POP-UP SWEETALERT SETELAH POST PHP -->
    <?php if(!empty($error)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?php echo addslashes($error); ?>',
                confirmButtonColor: '#2c3e50'
            });
        });
    </script>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?php echo addslashes($success); ?>',
                confirmButtonColor: '#3498db'
            });
        });
    </script>
    <?php endif; ?>

    <script>
        // Pengendali State Custom Dropdown Jenis Kelamin
        function toggleCustomDropdown(event) {
            event.stopPropagation(); 
            const dropdown = document.getElementById('custom-dropdown-container');
            dropdown.classList.toggle('open');
        }

        function selectGenderOption(value, event) {
            event.stopPropagation();
            const dropdown = document.getElementById('custom-dropdown-container');
            const hiddenInput = document.getElementById('jenis_kelamin');
            const triggerText = document.getElementById('selected-gender-text');
            const trigger = document.querySelector('.custom-select-trigger');

            hiddenInput.value = value;
            if (value === '') {
                triggerText.innerText = 'Pilih Jenis Kelamin';
                trigger.classList.remove('selected');
            } else {
                triggerText.innerText = value;
                trigger.classList.add('selected');
            }
            dropdown.classList.remove('open');
        }

        // Tutup otomatis dropdown saat klik sembarang tempat di luar area menu
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('custom-dropdown-container');
            if (dropdown && !dropdown.contains(event.target)) {
                dropdown.classList.remove('open');
            }
        });

        // Tombol Mata (Show/Hide Password) - ikon muncul begitu ada isian
        function togglePasswordVisibility(inputId, iconEl) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconEl.classList.remove('fa-eye');
                iconEl.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                iconEl.classList.remove('fa-eye-slash');
                iconEl.classList.add('fa-eye');
            }
        }

        // Memantau input password secara real-time agar tombol mata tidak hilang
        function checkPasswordInputs() {
            document.querySelectorAll('.password-group input').forEach(function (input) {
                const group = input.closest('.password-group');
                if (input.value.length > 0) {
                    group.classList.add('has-value');
                } else {
                    group.classList.remove('has-value');
                }
            });
        }

        document.querySelectorAll('.password-group input').forEach(function (input) {
            input.addEventListener('input', checkPasswordInputs);
            input.addEventListener('change', checkPasswordInputs);
        });
        
        // Jalankan saat load awal untuk deteksi input terisi (autofill)
        checkPasswordInputs();

        // ================== VALIDASI REAL-TIME (Visual) ==================
        const namaRegex       = /^[a-zA-Z\s]{3,}$/;
        const nikRegex        = /^\d{16}$/;
        const telpRegex       = /^(\+628|\+638|08)\d{8,12}$/; // Internasional (ID/PH) & Lokal
        const emailRegex      = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const usernameRegex   = /^[a-zA-Z0-9]{5,}$/;
        const passwordRegex   = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        function setFieldStatus(groupEl, isValid) {
            if (isValid) {
                groupEl.classList.remove('field-invalid');
                groupEl.classList.add('field-valid');
            } else {
                groupEl.classList.remove('field-valid');
                groupEl.classList.add('field-invalid');
            }
        }

        function clearFieldStatus(groupEl) {
            groupEl.classList.remove('field-invalid', 'field-valid');
        }

        // NIK: Hanya menerima angka, maksimal 16 digit, merah jika tidak tepat 16 digit
        const nikInput = document.getElementById('nik');
        const nikGroup = document.getElementById('nik-group');
        nikInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 16);
            if (this.value.length === 0) {
                clearFieldStatus(nikGroup);
            } else {
                setFieldStatus(nikGroup, nikRegex.test(this.value));
            }
        });

        // No. Telepon: Validasi format internasional real-time
        const telpInput = document.getElementById('telp');
        const telpGroup = document.getElementById('telp-group');
        telpInput.addEventListener('input', function () {
            const value = this.value.trim();
            if (value.length === 0) {
                clearFieldStatus(telpGroup);
            } else {
                setFieldStatus(telpGroup, telpRegex.test(value));
            }
        });

        // Email: Wajib mengandung "@" dan format domain yang benar
        const emailInput = document.getElementById('email');
        const emailGroup = document.getElementById('email-group');
        emailInput.addEventListener('input', function () {
            const value = this.value.trim();
            if (value.length === 0) {
                clearFieldStatus(emailGroup);
            } else {
                setFieldStatus(emailGroup, emailRegex.test(value));
            }
        });

        // Alamat: Minimal 20 karakter, harus diawali "Jl." atau "Jalan", tidak boleh acak/keyboard-mashing
        const alamatInput = document.getElementById('alamat');
        const alamatGroup = document.getElementById('alamat-group');
        
        function validasiAlamatFormat(text) {
            const val = text.trim();
            if (val.length < 20) return false;
            
            // Wajib diawali "Jl." atau "Jalan"
            const prefixValid = /^(jl\.\s*|jalan\s+)/i.test(val);
            if (!prefixValid) return false;

            // Proteksi teks acak: Hindari susunan huruf konsonan berturut-turut sebanyak 6 karakter atau lebih
            const isConsonantMashing = /[bcdfghjklmnpqrstvwxyz]{6,}/i.test(val);
            if (isConsonantMashing) return false;

            // Proteksi teks acak: Hindari karakter berulang berturut-turut sebanyak 4 kali atau lebih
            const isRepeatMashing = /([a-zA-Z0-9])\1{3,}/.test(val);
            if (isRepeatMashing) return false;

            // Indikasi kalimat logis: Harus mengandung minimal 2 spasi pemisah kata
            const spaceCount = (val.split(" ").length - 1);
            if (spaceCount < 2) return false;

            return true;
        }

        alamatInput.addEventListener('input', function () {
            const value = this.value.trim();
            if (value.length === 0) {
                clearFieldStatus(alamatGroup);
            } else {
                setFieldStatus(alamatGroup, validasiAlamatFormat(value));
            }
        });

        // Validasi Nama Lengkap vs Username Baru
        const namaInput = document.getElementById('nama');
        const namaGroup = document.getElementById('nama-group');
        const usernameInput = document.getElementById('reg_username');
        const usernameGroup = document.getElementById('username-group');

        function checkNamaVsUsername() {
            const nama = namaInput.value.trim().toLowerCase();
            const username = usernameInput.value.trim().toLowerCase();

            if (nama.length > 0) {
                const namaOk = namaRegex.test(namaInput.value.trim()) && !(username.length > 0 && nama === username);
                setFieldStatus(namaGroup, namaOk);
            } else {
                clearFieldStatus(namaGroup);
            }

            if (username.length > 0) {
                const usernameOk = usernameRegex.test(usernameInput.value.trim()) && !(nama.length > 0 && nama === username);
                setFieldStatus(usernameGroup, usernameOk);
            } else {
                clearFieldStatus(usernameGroup);
            }
        }

        namaInput.addEventListener('input', checkNamaVsUsername);
        usernameInput.addEventListener('input', checkNamaVsUsername);

        // Lakukan inisialisasi status validasi visual jika nilai terisi (setelah pendaftaran reload gagal)
        document.addEventListener("DOMContentLoaded", function() {
            if (namaInput.value.trim() !== "" || usernameInput.value.trim() !== "") {
                checkNamaVsUsername();
            }
            if (nikInput.value.trim() !== "") {
                setFieldStatus(nikGroup, nikRegex.test(nikInput.value.trim()));
            }
            if (telpInput.value.trim() !== "") {
                setFieldStatus(telpGroup, telpRegex.test(telpInput.value.trim()));
            }
            if (emailInput.value.trim() !== "") {
                setFieldStatus(emailGroup, emailRegex.test(emailInput.value.trim()));
            }
            if (alamatInput.value.trim() !== "") {
                setFieldStatus(alamatGroup, validasiAlamatFormat(alamatInput.value.trim()));
            }
        });

        // ================== VALIDASI AKHIR SAAT SUBMIT FORM ==================
        function verifikasiPendaftaran(event) {
            const nama = document.getElementById('nama').value.trim();
            const nik = document.getElementById('nik').value.trim();
            const telp = document.getElementById('telp').value.trim();
            const email = document.getElementById('email').value.trim();
            const jenisKelamin = document.getElementById('jenis_kelamin').value;
            const tglLahir = document.getElementById('tgl_lahir').value;
            const alamat = document.getElementById('alamat').value.trim();
            const username = document.getElementById('reg_username').value.trim();
            const password = document.getElementById('reg_password').value;
            const passwordConfirm = document.getElementById('reg_password_confirm').value;
            const confirmGroup = document.getElementById('confirm-password-group');

            // Hentikan pengiriman default agar SweetAlert dapat memproses
            event.preventDefault();

            if (!namaRegex.test(nama)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Nama Lengkap hanya boleh berisi huruf dan spasi, minimal 3 karakter.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (nama.toLowerCase() === username.toLowerCase()) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Nama Lengkap tidak boleh sama dengan Nama Pengguna Baru.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (!nikRegex.test(nik)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'NIK harus berupa angka dan berjumlah tepat 16 digit.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (!telpRegex.test(telp)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'No. Telepon tidak valid. Gunakan format internasional (contoh: +62812xxxxxxx atau 0812xxxxxxx) dan hindari angka asal.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (!emailRegex.test(email)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Format Email tidak valid. Harus mengandung karakter "@" dan nama domain.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (jenisKelamin === "") {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Silakan tentukan Pilihan Jenis Kelamin Anda.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (tglLahir) {
                const tanggalLahirDate = new Date(tglLahir);
                const hariIni = new Date();
                let umur = hariIni.getFullYear() - tanggalLahirDate.getFullYear();
                const bulan = hariIni.getMonth() - tanggalLahirDate.getMonth();
                
                if (bulan < 0 || (bulan === 0 && hariIni.getDate() < tanggalLahirDate.getDate())) {
                    umur--;
                }
                
                if (umur < 17) {
                    Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Pendaftar harus berusia minimal 17 tahun.', confirmButtonColor: '#3498db' });
                    return false;
                }
            } else {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Silakan tentukan Tanggal Lahir Anda.', confirmButtonColor: '#3498db' });
                return false;
            }

            // Validasi format alamat
            if (!validasiAlamatFormat(alamat)) {
                if (!/^(jl\.\s*|jalan\s+)/i.test(alamat)) {
                    Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Alamat lengkap wajib diawali dengan singkatan "Jl." atau kata "Jalan".', confirmButtonColor: '#3498db' });
                } else if (alamat.length < 20) {
                    Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Alamat lengkap harus diisi secara jelas (minimal 20 karakter).', confirmButtonColor: '#3498db' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Alamat yang Anda masukkan terdeteksi tidak valid atau menggunakan teks acak (anonim). Silakan isi dengan alamat nyata.', confirmButtonColor: '#3498db' });
                }
                return false;
            }

            if (!usernameRegex.test(username)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Username minimal 5 karakter, hanya diperbolehkan kombinasi huruf dan angka tanpa spasi.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (!passwordRegex.test(password)) {
                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Kata Sandi minimal 8 karakter, serta wajib memuat minimal 1 huruf besar, 1 huruf kecil, dan 1 angka.', confirmButtonColor: '#3498db' });
                return false;
            }

            if (password !== passwordConfirm) {
                confirmGroup.classList.add('shake-field');
                document.getElementById('reg_password_confirm').focus();

                setTimeout(() => {
                    confirmGroup.classList.remove('shake-field');
                }, 400);

                Swal.fire({ icon: 'error', title: 'Validasi Gagal', text: 'Konfirmasi Kata Sandi tidak sesuai dengan Kata Sandi Anda.', confirmButtonColor: '#3498db' });
                return false;
            }

            // Jika validasi lolos seluruhnya, kirim form
            event.target.submit();
        }

        // Efek Transisi Antara Login dan Register Form
        function toggleForm() {
            const loginForm = document.getElementById('login-form');
            const regForm = document.getElementById('register-form');
            const btnSwitch = document.getElementById('btn-switch');
            const sideText = document.getElementById('side-text');
            const toggleDesc = document.getElementById('toggle-desc');
            const sidebar = document.getElementById('main-sidebar');
            const container = document.querySelector('.container');

            container.classList.remove('shake-error');

            if (loginForm.classList.contains('active')) {
                loginForm.style.opacity = '0';
                loginForm.style.transform = 'scale(0.96) translateY(-15px)';
                
                container.classList.remove('login-mode');
                container.classList.add('register-mode');

                setTimeout(() => {
                    loginForm.classList.remove('active');
                    regForm.classList.add('active');
                    
                    setTimeout(() => {
                        regForm.style.opacity = '1';
                        regForm.style.transform = 'scale(1) translateY(0)';
                    }, 50);

                    btnSwitch.innerText = "KEMBALI LOGIN";
                    sideText.innerText = "Mari bergabung bersama pecinta anabul lainnya.";
                    toggleDesc.innerText = "Sudah punya akun?";
                    sidebar.style.background = '#1a252f'; 
                }, 300);
            } else {
                regForm.style.opacity = '0';
                regForm.style.transform = 'scale(0.96) translateY(15px)';
                
                container.classList.remove('register-mode');
                container.classList.add('login-mode');

                setTimeout(() => {
                    regForm.classList.remove('active');
                    loginForm.classList.add('active');
                    
                    setTimeout(() => {
                        loginForm.style.opacity = '1';
                        loginForm.style.transform = 'scale(1) translateY(0)';
                    }, 50);

                    btnSwitch.innerText = "DAFTAR SEKARANG";
                    sideText.innerText = "Manajemen Modern Anabul Anda.";
                    toggleDesc.innerText = "Belum punya akun?";
                    sidebar.style.background = '#2c3e50'; 
                }, 300);
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const activeForm = document.querySelector('.form-content.active');
            if (activeForm) {
                activeForm.style.opacity = '1';
                activeForm.style.transform = 'scale(1) translateY(0)';
            }
        });
    </script>
</body>
</html>