<?php
session_start();
require_once '../Config/koneksi.php'; // Pastikan path koneksi benar

// Ambil token credential yang dikirim oleh tombol Google Login
$id_token = $_POST['credential'] ?? $_GET['id_token'] ?? null;

// VALIDASI: Jika token kosong, jangan panggil API Google, kembalikan ke login
if (!$id_token) {
    header("Location: login.php?error=Token tidak ditemukan");
    exit();
}

// Jika token ada, baru lakukan pemanggilan ke Google API
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);

// Proses pengambilan data user dari Google menggunakan curl atau file_get_contents...
$response = file_get_contents($url);
$user_data = json_decode($response, true);

if (isset($user_data['email'])) {
    $email = $user_data['email'];
    $nama  = $user_data['name'];
    $google_id = $user_data['sub']; // ID unik user di Google

    // 2. Cek apakah Email sudah terdaftar di tabel Pelanggan
    $sql_cek = "SELECT * FROM Pelanggan WHERE Email = ? AND Pel_is_deleted = 0";
    $params_cek = array($email);
    $stmt_cek = sqlsrv_query($conn, $sql_cek, $params_cek);
    $user_pel = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($user_pel) {
        // --- USER SUDAH ADA: SET SESSION ---
        $_SESSION['user_id'] = $user_pel['ID_Pelanggan'];
        $_SESSION['nama'] = $user_pel['Nama_Pelanggan'];
        $_SESSION['role'] = 'Pelanggan';
        header("Location: ../Dashboard/index.php");
        exit();
    } else {
        // --- USER BARU: DAFTARKAN OTOMATIS ---
        // Buat username unik dari email
        $username_baru = explode('@', $email)[0] . rand(10, 99);
        
        $sql_ins = "INSERT INTO Pelanggan (Nama_Pelanggan, No_Telepon, Email, Username, Password, Status_Member, Pel_status, Pel_created_date, Pel_is_deleted) 
                    VALUES (?, '-', ?, ?, 'LOGIN_VIA_GOOGLE', 'Non Member', 'Aktif', GETDATE(), 0)";
        $params_ins = array($nama, $email, $username_baru);
        $stmt_ins = sqlsrv_query($conn, $sql_ins, $params_ins);

        if ($stmt_ins) {
            // Ambil ID yang baru saja dibuat
            $sql_id = "SELECT TOP 1 ID_Pelanggan FROM Pelanggan ORDER BY ID_Pelanggan DESC";
            $res_id = sqlsrv_query($conn, $sql_id);
            $row_id = sqlsrv_fetch_array($res_id, SQLSRV_FETCH_ASSOC);

            $_SESSION['user_id'] = $row_id['ID_Pelanggan'];
            $_SESSION['nama'] = $nama;
            $_SESSION['role'] = 'Pelanggan';
            header("Location: ../Dashboard/index.php");
            exit();
        } else {
            echo "Gagal mendaftarkan user baru via Google.";
        }
    }
} else {
    echo "Token Google tidak valid.";
}
?>