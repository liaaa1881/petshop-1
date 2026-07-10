<?php
session_start();
// Menggunakan huruf kecil (lowercase) untuk folder config
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman Pembeli (Session Customer)
if (!isset($_SESSION['id_pelanggan'])) {
    echo "<script>window.location.href = '../auth/login.php';</script>";
    exit;
}

// 2. Tangkap parameter ID_Nota yang mau dibayar pembeli
if (!isset($_GET['nota']) || trim($_GET['nota']) === '') {
    echo "<script>window.location.href = 'keranjang.php';</script>";
    exit;
}

$id_nota = intval($_GET['nota']);
$id_pelanggan = intval($_SESSION['id_pelanggan']);
$username_aktif = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Customer';

if (!$conn) {
    die("<pre>Koneksi database gagal terhubung.</pre>");
}

// 3. Validasi: Pastikan nota ini memang berstatus 'Belum Lunas' dan benar milik pembeli tersebut
$sql_cek = "SELECT Grand_Total FROM Penjualan WHERE ID_Nota = ? AND ID_Pelanggan = ? AND Status_Pembayaran = 'Belum Lunas'";
$stmt_cek = sqlsrv_query($conn, $sql_cek, array($id_nota, $id_pelanggan));

if ($stmt_cek === false) {
    die("<pre>Gagal membaca database saat validasi nota:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

$data_nota = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

if (!$data_nota) {
    echo "<script>window.location.href = 'keranjang.php';</script>";
    exit;
}

// 4. OTOMATISASI ONLINE: Ubah status langsung jadi 'Lunas', set metode (misal: Qris)
// Catatan: trigger "trg_Penjualan_PoinMember" akan otomatis menghitung dan menambahkan poin member saat update ini berhasil.
$sql_update = "UPDATE Penjualan 
               SET Status_Pembayaran = 'Lunas', 
                   Metode_Pembayaran = 'Qris', 
                   ID_Karyawan = NULL,         
                   Tanggal_Penjualan = GETDATE(),
                   Pen_modified_by = ?,
                   Pen_modified_date = GETDATE()              
               WHERE ID_Nota = ? AND ID_Pelanggan = ?";

$params = array($username_aktif, $id_nota, $id_pelanggan);
$stmt_update = sqlsrv_query($conn, $sql_update, $params);

if ($stmt_update === false) {
    die("<pre>Gagal memproses pembayaran otomatis online:\n" . print_r(sqlsrv_errors(), true) . "</pre>");
}

// 5. Selesai! Lempar pembeli langsung ke halaman Detail Riwayat Nota mereka
echo "<script>window.location.href = 'riwayat_detail.php?nota=" . $id_nota . "';</script>";
exit;
?>