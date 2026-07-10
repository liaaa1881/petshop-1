<?php
session_start();
include '../Config/koneksi.php'; 

if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $params = array($user, $pass);

    // 1. CEK TABEL KARYAWAN (Admin / Karyawan) - Menggunakan Stored Procedure (sp_Karyawan_Login)
    $sql_k = "{call sp_Karyawan_Login(?, ?)}";
    $stmt_k = sqlsrv_query($conn, $sql_k, $params);

    if ($stmt_k === false) { 
        die(print_r(sqlsrv_errors(), true)); 
    }

    if ($data_k = sqlsrv_fetch_array($stmt_k, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['id_user'] = $data_k['ID_Karyawan'];
        $_SESSION['nama']    = $data_k['Nama_Karyawan'];
        $_SESSION['role']    = $data_k['Role'];
        
        header("Location: ../Dashboard/index.php");
        exit();
    } 

    // 2. CEK TABEL PELANGGAN (Customer) - Menggunakan Stored Procedure (sp_Pelanggan_Login)
    $sql_p = "{call sp_Pelanggan_Login(?, ?)}";
    $stmt_p = sqlsrv_query($conn, $sql_p, $params);
    
    if ($stmt_p === false) { 
        die(print_r(sqlsrv_errors(), true)); 
    }

    if ($data_p = sqlsrv_fetch_array($stmt_p, SQLSRV_FETCH_ASSOC)) {
        // ID User dasar sistem
        $_SESSION['id_user']      = $data_p['ID_Pelanggan']; 
        
        // SINKRONISASI: Menyimpan ID_Pelanggan ke session agar bisa dibaca oleh sistem Booking & Keranjang Belanja
        $_SESSION['id_pelanggan'] = $data_p['ID_Pelanggan']; 
        
        $_SESSION['nama']         = $data_p['Nama_Pelanggan'];
        $_SESSION['role']         = 'Customer'; 
        
        header("Location: ../Dashboard/index.php");
        exit();
    }

    // 3. CEK TABEL SUPPLIER (Kueri manual karena tidak ada Stored Procedure khusus Supplier)
    $sql_s = "SELECT * FROM Supplier WHERE Username = ? AND Password = ?";
    $stmt_s = sqlsrv_query($conn, $sql_s, $params);
    
    if ($stmt_s === false) { 
        die(print_r(sqlsrv_errors(), true)); 
    }

    if ($data_s = sqlsrv_fetch_array($stmt_s, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['id_user'] = $data_s['ID_Supplier'];
        $_SESSION['nama']    = $data_s['Nama_Supplier'];
        $_SESSION['role']    = 'Supplier'; 
        
        header("Location: ../Dashboard/index.php");
        exit();
    }

    // 4. JIKA KATA SANDI / USERNAME SALAH: Kembalikan ke halaman login dengan notifikasi pesan gagal
    header("Location: login.php?pesan=gagal");
    exit();
}
?>