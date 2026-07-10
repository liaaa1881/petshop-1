<?php
session_start();
include '../Config/koneksi.php'; 

if (isset($_POST['verifikasi'])) {
    $user   = $_POST['username'];
    $kontak = $_POST['kontak']; // Nomor HP

    $params = array($user, $kontak);

    // 1. CEK KARYAWAN
    $sql_karyawan = "SELECT * FROM Karyawan WHERE Username = ? AND No_Telepon = ?";
    $stmt_k = sqlsrv_query($conn, $sql_karyawan, $params);

    if ($stmt_k === false) { die(print_r(sqlsrv_errors(), true)); }

    $data_karyawan = sqlsrv_fetch_array($stmt_k, SQLSRV_FETCH_ASSOC);

    if ($data_karyawan) {
        $_SESSION['reset_user'] = $user;
        $_SESSION['reset_table'] = 'Karyawan';
        header("Location: reset_password.php");
        exit();
    }

    // 2. CEK PELANGGAN
    $sql_cust = "SELECT * FROM Pelanggan WHERE Username = ? AND No_Telepon = ?";
    $stmt_p = sqlsrv_query($conn, $sql_cust, $params);

    if ($stmt_p === false) { die(print_r(sqlsrv_errors(), true)); }

    $data_cust = sqlsrv_fetch_array($stmt_p, SQLSRV_FETCH_ASSOC);

    if ($data_cust) {
        $_SESSION['reset_user'] = $user;
        $_SESSION['reset_table'] = 'Pelanggan';
        header("Location: reset_password.php");
        exit();
    }

    // 3. CEK SUPPLIER (TAMBAHAN WAJIB)
    $sql_supplier = "SELECT * FROM Supplier WHERE Username = ? AND No_Telepon = ?";
    $stmt_s = sqlsrv_query($conn, $sql_supplier, $params);

    if ($stmt_s === false) { die(print_r(sqlsrv_errors(), true)); }

    $data_supplier = sqlsrv_fetch_array($stmt_s, SQLSRV_FETCH_ASSOC);

    if ($data_supplier) {
        $_SESSION['reset_user'] = $user;
        $_SESSION['reset_table'] = 'Supplier';
        header("Location: reset_password.php");
        exit();
    }

    // 4. GAGAL
    header("Location: lupa_password.php?pesan=gagal");
    exit();
}
?>