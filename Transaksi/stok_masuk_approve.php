<?php
// Pastikan koneksi database ($conn) dan $id_stok sudah didefinisikan

// 1. Mulai transaksi database
sqlsrv_begin_transaction($conn);

// 2. Update status Stok_Masuk dari 'Pending' menjadi 'Diterima'
$sql_status = "UPDATE Stok_Masuk SET Status = 'Diterima' WHERE ID_Stok = ? AND Status = 'Pending'";
$stmt_status = sqlsrv_query($conn, $sql_status, array($id_stok));

if ($stmt_status && sqlsrv_rows_affected($stmt_status) > 0) {
    
    // 3. Ambil daftar barang dan kuantitasnya dari Detail_Stok_Masuk
    $sql_items = "SELECT ID_Barang, Jumlah_Masuk FROM Detail_Stok_Masuk WHERE ID_Stok = ?";
    $stmt_items = sqlsrv_query($conn, $sql_items, array($id_stok));
    
    $success = true;
    if ($stmt_items !== false) {
        while ($row = sqlsrv_fetch_array($stmt_items, SQLSRV_FETCH_ASSOC)) {
            $id_barang = $row['ID_Barang'];
            $jumlah = $row['Jumlah_Masuk'];
            
            // 4. Tambahkan stok ke masing-masing barang di tabel Barang
            $sql_update_stok = "UPDATE Barang SET Stok = Stok + ? WHERE ID_Barang = ?";
            $stmt_update_stok = sqlsrv_query($conn, $sql_update_stok, array($jumlah, $id_barang));
            
            if ($stmt_update_stok === false) {
                $success = false;
                break;
            }
        }
    } else {
        $success = false;
    }

    // 5. Commit jika semua proses berhasil, Rollback jika ada yang gagal
    if ($success) {
        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui dan stok telah ditambahkan.']);
    } else {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui stok barang.']);
    }
} else {
    sqlsrv_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Faktur tidak ditemukan atau status sudah bukan Pending.']);
}
?>