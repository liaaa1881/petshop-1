<?php
// 1. Identitas Server (Buka SSMS, copy nama Server kamu)
$serverName = "localhost";

// 2. Info Koneksi
$connectionInfo = array(
    "Database" => "petshop"
    // Tanpa UID dan PWD, ini akan otomatis pakai akun laptop kamu
);

// 3. Menjalankan Fungsi Koneksi
$conn = sqlsrv_connect($serverName, $connectionInfo);

// 4. Cek Koneksi (Logika Pengetesan)
// ... kodingan yang tadi ...
if ($conn) {
   
} else {
    echo "Koneksi Gagal!<br />"; //hsihdiwhid hwudwuhswhq
    die(print_r(sqlsrv_errors(), true));
}