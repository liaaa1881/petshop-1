<?php
session_start();
// Jalur koneksi disesuaikan menggunakan lowercase
require_once '../config/koneksi.php'; 

// 1. Proteksi Halaman: Pastikan customer sudah login
if (!isset($_SESSION['id_pelanggan'])) {
    echo "<script>window.location.href = '../auth/login.php';</script>";
    exit();
}

// 2. Cek apakah parameter ID Detail dikirim dari halaman keranjang
if (!isset($_GET['id_detail']) || empty($_GET['id_detail'])) {
    echo "<script>window.location.href = 'keranjang.php';</script>";
    exit();
}

$id_detail = intval($_GET['id_detail']);

if (!$conn) {
    echo "<script>
            alert('Koneksi ke database server terputus.');
            window.location.href = 'keranjang.php';
          </script>";
    exit();
}

// 3. Ambil data ID_Nota, ID_Barang, dan Jumlah sebelum item dihapus
$sql_get_nota = "SELECT ID_Nota, ID_Barang, Jumlah FROM Detail_Penjualan WHERE ID_Detail = ?";
$stmt_get_nota = sqlsrv_query($conn, $sql_get_nota, array($id_detail));

if ($stmt_get_nota === false) {
    echo "<script>
            alert('Gagal mengambil informasi draf belanja.');
            window.location.href = 'keranjang.php';
          </script>";
    exit();
}

$row_nota = sqlsrv_fetch_array($stmt_get_nota, SQLSRV_FETCH_ASSOC);

if ($row_nota) {
    $id_nota   = $row_nota['ID_Nota'];
    $id_barang = $row_nota['ID_Barang'];
    $jumlah    = intval($row_nota['Jumlah']);

    // Memulai Transaksi SQL Server
    sqlsrv_begin_transaction($conn);

    // 4. Jalankan Query Hapus Item dari tabel Detail_Penjualan
    $sql_delete = "DELETE FROM Detail_Penjualan WHERE ID_Detail = ?";
    $stmt_delete = sqlsrv_query($conn, $sql_delete, array($id_detail));

    if ($stmt_delete !== false) {
        
        // 5. Kembalikan kuantitas stok barang ke tabel Barang (Restock)
        // Langkah ini wajib karena trigger trg_DetailPenjualan_UpdateStok melakukan pengurangan saat insert keranjang
        $sql_restok = "UPDATE Barang SET Stok = Stok + ? WHERE ID_Barang = ?";
        $stmt_restok = sqlsrv_query($conn, $sql_restok, array($jumlah, $id_barang));

        if ($stmt_restok === false) {
            sqlsrv_rollback($conn);
            echo "<script>
                    alert('Gagal mengembalikan sisa persediaan barang.');
                    window.location.href = 'keranjang.php';
                  </script>";
            exit();
        }

        // 6. Cek apakah di nota ini masih ada item lain yang tersisa
        $sql_cek_sisa = "SELECT COUNT(*) AS Sisa_Item FROM Detail_Penjualan WHERE ID_Nota = ?";
        $stmt_cek_sisa = sqlsrv_query($conn, $sql_cek_sisa, array($id_nota));
        $row_sisa = sqlsrv_fetch_array($stmt_cek_sisa, SQLSRV_FETCH_ASSOC);

        if ($row_sisa['Sisa_Item'] > 0) {
            // Jika masih ada barang lain, hitung ulang Subtotal lalu update Total_Bayar di tabel Penjualan
            $sql_update_total = "UPDATE Penjualan 
                                 SET Grand_Total = (SELECT SUM(Subtotal) FROM Detail_Penjualan WHERE ID_Nota = ?),
                                     Subtotal_Penjualan = (SELECT SUM(Subtotal) FROM Detail_Penjualan WHERE ID_Nota = ?)
                                 WHERE ID_Nota = ?";
            sqlsrv_query($conn, $sql_update_total, array($id_nota, $id_nota, $id_nota));
        } else {
            // Jika keranjang benar-benar kosong, hapus juga data induknya di tabel Penjualan
            $sql_clean_nota = "DELETE FROM Penjualan WHERE ID_Nota = ? AND Status_Pembayaran = 'Belum Lunas'";
            sqlsrv_query($conn, $sql_clean_nota, array($id_nota));
        }

        sqlsrv_commit($conn);
        echo "<script>window.location.href = 'keranjang.php';</script>";
        exit();

    } else {
        sqlsrv_rollback($conn);
        echo "<script>window.location.href = 'keranjang.php';</script>";
        exit();
    }
} else {
    echo "<script>window.location.href = 'keranjang.php';</script>";
    exit();
}
?>