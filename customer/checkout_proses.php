<?php
session_start();
// Jalur koneksi disesuaikan ke folder config (huruf kecil)
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman Pembeli
if (!isset($_SESSION['id_pelanggan'])) {
    header("Location: ../auth/login.php");
    exit();
}

$id_pelanggan = intval($_SESSION['id_pelanggan']);
$username = $_SESSION['username'] ?? 'Customer';

// 2. Tangkap parameter ID_Nota dari URL
if (!isset($_GET['nota']) || trim($_GET['nota']) === '') {
    header("Location: keranjang.php");
    exit();
}

$id_nota = intval($_GET['nota']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Memproses Pembayaran - Petshop Pro</title>
    <!-- Load Pustaka SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f8f9fa; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
        }
    </style>
</head>
<body>

<?php
if (!$conn) {
    echo "<script>
            Swal.fire({
                title: 'Koneksi Gagal',
                text: 'Koneksi ke database server terputus.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                window.location.href = 'keranjang.php';
            });
          </script>";
    exit();
}

// 3. Validasi Nota di SQL Server
$sql_cek = "SELECT Grand_Total FROM Penjualan WHERE ID_Nota = ? AND ID_Pelanggan = ? AND Status_Pembayaran = 'Belum Lunas'";
$stmt_cek = sqlsrv_query($conn, $sql_cek, array($id_nota, $id_pelanggan));

if ($stmt_cek === false) {
    echo "<script>
            Swal.fire({
                title: 'Kesalahan Sistem',
                text: 'Gagal mencocokkan draf belanja di database.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                window.location.href = 'keranjang.php';
            });
          </script>";
    exit();
}

$data_nota = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

if (!$data_nota) {
    echo "<script>
            Swal.fire({
                title: 'Transaksi Tidak Valid',
                text: 'Draf belanja tidak ditemukan atau sedang diproses.',
                icon: 'warning',
                confirmButtonColor: '#ffc107'
            }).then(() => {
                window.location.href = 'keranjang.php';
            });
          </script>";
    exit();
}

// 4. Update status transaksi menjadi BELUM LUNAS (Menunggu verifikasi kasir)
$sql_update = "UPDATE Penjualan 
               SET Status_Pembayaran = 'Belum Lunas', 
                   Metode_Pembayaran = 'Qris', 
                   ID_Karyawan = NULL,         
                   Tanggal_Penjualan = GETDATE(),
                   Jumlah_Bayar = 0, -- Set 0 karena belum dikonfirmasi lunas oleh kasir
                   Pen_modified_by = ?,
                   Pen_modified_date = GETDATE(),
                   Pen_status = 'Aktif' -- Agar muncul di riwayat transaksi kasir             
               WHERE ID_Nota = ? AND ID_Pelanggan = ?";

$params = array($username, $id_nota, $id_pelanggan);
$stmt_update = sqlsrv_query($conn, $sql_update, $params);

if ($stmt_update === false) {
    echo "<script>
            Swal.fire({
                title: 'Transaksi Gagal',
                text: 'Gagal memproses draf transaksi Anda.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                window.location.href = 'keranjang.php';
            });
          </script>";
    exit();
}

// 5. Alihkan Halaman dengan pesan menunggu konfirmasi Kasir
echo "<script>
        Swal.fire({
            title: 'Checkout Berhasil!',
            text: 'Pesanan Anda telah dibuat. Silakan lakukan pembayaran dan konfirmasi di Kasir.',
            icon: 'success',
            confirmButtonColor: '#198754',
            confirmButtonText: 'Lihat Detail Nota'
        }).then(() => {
            window.location.href = 'riwayat_detail.php?nota=" . $id_nota . "'; 
        });
      </script>";
exit();
?>

</body>
</html>