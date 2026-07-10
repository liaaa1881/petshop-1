<?php
session_start();
// Menggunakan huruf kecil (lowercase) untuk folder config
require_once '../config/koneksi.php'; 

// 1. Proteksi: Pastikan Pelanggan Sudah Login
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php"); 
    exit();
}

$id_pelanggan = $_SESSION['id_pelanggan'] ?? 0;

// 2. Tangkap Data dari parameter URL
if (!isset($_GET['id']) && !isset($_GET['id_barang'])) {
    header("Location: ../customer/keranjang.php");
    exit();
}

$id_barang = isset($_GET['id']) ? intval($_GET['id']) : intval($_GET['id_barang']);
$jumlah = isset($_GET['jumlah']) ? intval($_GET['jumlah']) : 1;

if (!$conn) {
    die("<pre>Koneksi database gagal terhubung.</pre>");
}

// 3. Ambil Harga_Jual asli dari database
$sql_barang = "SELECT Harga_Jual, Stok FROM Barang WHERE ID_Barang = ?";
$stmt_barang = sqlsrv_query($conn, $sql_barang, array($id_barang));
$barang = sqlsrv_fetch_array($stmt_barang, SQLSRV_FETCH_ASSOC);

if (!$barang) {
    header("Location: ../customer/keranjang.php");
    exit();
}

if ($barang['Stok'] < $jumlah) {
    echo "<script>
            alert('Maaf, kuantitas stok barang tidak mencukupi kebutuhan Anda.');
            window.location.href = '../customer/keranjang.php';
          </script>";
    exit();
}

$harga_satuan = $barang['Harga_Jual'];
$subtotal = $harga_satuan * $jumlah;

// Memulai Transaksi SQL Server
sqlsrv_begin_transaction($conn);

try {
    // 4. Cari tahu apakah pelanggan memiliki Nota Draf (Belum Lunas) yang aktif
    $sql_nota = "SELECT TOP 1 ID_Nota FROM Penjualan WHERE ID_Pelanggan = ? AND Status_Pembayaran = 'Belum Lunas' ORDER BY ID_Nota DESC";
    $stmt_nota = sqlsrv_query($conn, $sql_nota, array($id_pelanggan));
    $nota = sqlsrv_fetch_array($stmt_nota, SQLSRV_FETCH_ASSOC);

    if ($nota) {
        $id_nota = $nota['ID_Nota'];
    } else {
        // Generate kode unik No_Nota draft sementara
        $no_nota = "INV-DRAFT-" . date('YmdHis') . "-" . rand(100, 999);
        
        $sql_insert_nota = "INSERT INTO Penjualan (No_Nota, ID_Pelanggan, Tanggal_Penjualan, Subtotal_Penjualan, Total_Diskon, Pajak_PPN, Grand_Total, Jumlah_Bayar, Status_Pembayaran, Pen_created_by, Pen_created_date) 
                            VALUES (?, ?, GETDATE(), 0, 0, 0, 0, 0, 'Belum Lunas', ?, GETDATE())";
        
        $stmt_insert_nota = sqlsrv_query($conn, $sql_insert_nota, array($no_nota, $id_pelanggan, ($_SESSION['username'] ?? 'Customer')));
        
        if ($stmt_insert_nota === false) {
            throw new Exception("Gagal membuat draf nota baru.");
        }
        
        // Dapatkan ID IDENTITY yang baru dibuat
        $sql_get_id = "SELECT @@IDENTITY AS ID_Baru";
        $stmt_id = sqlsrv_query($conn, $sql_get_id);
        $row_id = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
        $id_nota = $row_id['ID_Baru'];
    }

    // 5. Periksa apakah barang sudah terdaftar di Detail_Penjualan pada draf nota ini
    $sql_cek_detail = "SELECT ID_Detail, Jumlah FROM Detail_Penjualan WHERE ID_Nota = ? AND ID_Barang = ?";
    $stmt_cek_detail = sqlsrv_query($conn, $sql_cek_detail, array($id_nota, $id_barang));
    $detail = sqlsrv_fetch_array($stmt_cek_detail, SQLSRV_FETCH_ASSOC);

    if ($detail) {
        $old_jumlah = intval($detail['Jumlah']);
        $jumlah_baru = $old_jumlah + $jumlah;
        $subtotal_baru = $jumlah_baru * $harga_satuan;

        // A. Kembalikan stok lama ke database (agar sisa stok di Barang pulih sebelum dihapus)
        $sql_restore = "UPDATE Barang SET Stok = Stok + ? WHERE ID_Barang = ?";
        sqlsrv_query($conn, $sql_restore, array($old_jumlah, $id_barang));

        // B. Hapus detail draf keranjang yang lama
        $sql_delete_old = "DELETE FROM Detail_Penjualan WHERE ID_Detail = ?";
        sqlsrv_query($conn, $sql_delete_old, array($detail['ID_Detail']));

        // C. Lakukan INSERT baru untuk jumlah baru (Memicu trigger trg_DetailPenjualan_UpdateStok untuk jumlah baru)
        $sql_insert_detail = "INSERT INTO Detail_Penjualan (ID_Nota, ID_Barang, Jumlah, Harga_Satuan, Diskon_Item, Subtotal, DetPen_created_by, DetPen_created_date) 
                              VALUES (?, ?, ?, ?, 0, ?, ?, GETDATE())";
        $stmt_final = sqlsrv_query($conn, $sql_insert_detail, array($id_nota, $id_barang, $jumlah_baru, $harga_satuan, $subtotal_baru, ($_SESSION['username'] ?? 'Customer')));
    } else {
        // Tambahkan baris data barang baru (Trigger trg_DetailPenjualan_UpdateStok langsung berjalan)
        $sql_insert_detail = "INSERT INTO Detail_Penjualan (ID_Nota, ID_Barang, Jumlah, Harga_Satuan, Diskon_Item, Subtotal, DetPen_created_by, DetPen_created_date) 
                              VALUES (?, ?, ?, ?, 0, ?, ?, GETDATE())";
        $stmt_final = sqlsrv_query($conn, $sql_insert_detail, array($id_nota, $id_barang, $jumlah, $harga_satuan, $subtotal, ($_SESSION['username'] ?? 'Customer')));
    }

    if ($stmt_final === false) {
        throw new Exception("Gagal memproses detail item belanjaan.");
    }

    // 6. Hitung ulang total akumulasi belanja draf
    $sql_sum = "SELECT SUM(Subtotal) AS total_sum FROM Detail_Penjualan WHERE ID_Nota = ?";
    $stmt_sum = sqlsrv_query($conn, $sql_sum, array($id_nota));
    $row_sum = sqlsrv_fetch_array($stmt_sum, SQLSRV_FETCH_ASSOC);
    $total_sum = $row_sum['total_sum'] ?? 0;

    // Sinkronisasikan kolom hitungan finansial pada tabel Penjualan
    $sql_update_total = "UPDATE Penjualan SET Subtotal_Penjualan = ?, Grand_Total = ? WHERE ID_Nota = ?";
    $stmt_sync = sqlsrv_query($conn, $sql_update_total, array($total_sum, $total_sum, $id_nota));

    if ($stmt_sync === false) {
        throw new Exception("Gagal menyinkronkan total nominal pembayaran.");
    }

    sqlsrv_commit($conn);
    header("Location: ../customer/keranjang.php");
    exit();

} catch (Exception $e) {
    sqlsrv_rollback($conn);
    echo "<script>
            alert('" . htmlspecialchars($e->getMessage()) . "');
            window.location.href = '../customer/keranjang.php';
          </script>";
    exit();
}
?>