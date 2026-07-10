<?php
session_start();
require_once '../Config/koneksi.php'; // Menggunakan file koneksi database Anda

// Kredensial Aplikasi Facebook Anda
$app_id = '1373784418006017';
$app_secret = '1198dc0304c0fe821450866997af0b3c'; // Ganti dengan App Secret dari menu Pengaturan > Dasar
$redirect_uri = 'http://localhost:3000/Auth/facebook-callback.php';

// 1. Cek apakah ada kiriman kode otorisasi dari Facebook
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // 2. Tukar Code dengan Access Token Facebook menggunakan cURL
    $token_url = "https://graph.facebook.com/v18.0/oauth/access_token?" . http_build_query([
        'client_id'     => $app_id,
        'client_secret' => $app_secret,
        'redirect_uri'  => $redirect_uri,
        'code'          => $code
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $token_response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($token_response, true);

    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];

        // 3. Ambil data profil (ID, Nama, Email) dari Facebook Graph API
        $graph_url = "https://graph.facebook.com/me?" . http_build_query([
            'fields'       => 'id,name,email',
            'access_token' => $access_token
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $graph_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_response = curl_exec($ch);
        curl_close($ch);

        $user_data = json_decode($user_response, true);

        // Periksa apakah email didapatkan (Facebook mewajibkan akun memiliki email terverifikasi)
        if (isset($user_data['email'])) {
            $email = $user_data['email'];
            $nama  = $user_data['name'];
            $fb_id = $user_data['id']; // ID unik pengguna di Facebook

            // 4. Cek apakah Email sudah terdaftar di tabel Pelanggan
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
                            VALUES (?, '-', ?, ?, 'LOGIN_VIA_FACEBOOK', 'Non Member', 'Aktif', GETDATE(), 0)";
                $params_ins = array($nama, $email, $username_baru);
                $stmt_ins = sqlsrv_query($conn, $sql_ins, $params_ins);

                if ($stmt_ins) {
                    // Ambil ID pelanggan yang baru saja dibuat
                    $sql_id = "SELECT TOP 1 ID_Pelanggan FROM Pelanggan ORDER BY ID_Pelanggan DESC";
                    $res_id = sqlsrv_query($conn, $sql_id);
                    $row_id = sqlsrv_fetch_array($res_id, SQLSRV_FETCH_ASSOC);

                    $_SESSION['user_id'] = $row_id['ID_Pelanggan'];
                    $_SESSION['nama'] = $nama;
                    $_SESSION['role'] = 'Pelanggan';
                    header("Location: ../Dashboard/index.php");
                    exit();
                } else {
                    echo "Gagal mendaftarkan user baru via Facebook.";
                }
            }
        } else {
            echo "Gagal mengambil data email dari akun Facebook Anda. Pastikan akun Facebook Anda memiliki email utama.";
        }
    } else {
        echo "Gagal memproses pertukaran token Facebook.";
    }
} else {
    // Jika file diakses langsung tanpa kode pengalihan
    header("Location: login.php");
    exit();
}