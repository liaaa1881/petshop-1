<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config/koneksi.php';

// Proteksi Login: Pastikan user sudah login
if (!isset($_SESSION['role'])) { 
    echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir. Silakan login kembali.']);
    exit; 
}

// Ambil parameter dari request AJAX (stok_masuk_read.php)
$id_stok      = isset($_GET['id']) ? intval($_GET['id']) : 0;
$target_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$user_name     = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Petugas Gudang';

// Validasi awal parameter
if ($id_stok <= 0 || $target_status !== 'Diterima') {
    echo json_encode(['success' => false, 'message' => 'Parameter permintaan tidak valid.']);
    exit;
}

// Memulai Transaction Database SQL Server
if (sqlsrv_begin_transaction($conn) === false) {
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal memulai transaksi sistem database.', 
        'error_details' => formatSqlErrors(sqlsrv_errors())
    ]);
    exit;
}

try {
    // 1. Verifikasi Status Awal (Harus masih 'Pending' untuk mencegah duplikasi update status)
    $sql_cek = "SELECT Status FROM Stok_Masuk WHERE ID_Stok = ?";
    $query_cek = sqlsrv_query($conn, $sql_cek, array($id_stok));
    
    if ($query_cek === false) {
        throw new Exception('Gagal memverifikasi status faktur masuk.');
    }
    
    $row_cek = sqlsrv_fetch_array($query_cek, SQLSRV_FETCH_ASSOC);
    if (!$row_cek) {
        throw new Exception('Faktur pengadaan stok tidak ditemukan.');
    }
    
    if ($row_cek['Status'] !== 'Pending') {
        throw new Exception('Status transaksi ini sudah tidak Pending (Sudah Diterima sebelumnya).');
    }

    // 2. Update status master Stok_Masuk menjadi 'Diterima' dan catat audit log
    // Catatan: Kenaikan stok fisik barang sudah ditangani otomatis oleh trigger "trg_DetailStokMasuk_TambahStok" pada tingkat database.
    $sql_update_master = "UPDATE Stok_Masuk 
                          SET Status = 'Diterima', 
                              Tanggal_Diterima = GETDATE(), 
                              SM_modified_by = ?, 
                              SM_modified_date = GETDATE() 
                          WHERE ID_Stok = ? AND Status = 'Pending'";
                          
    $stmt_master = sqlsrv_query($conn, $sql_update_master, array($user_name, $id_stok));

    if ($stmt_master === false) {
        throw new Exception('Gagal memperbarui status akhir faktur pengadaan.');
    }

    // Commit transaksi jika semua langkah di atas berjalan sukses tanpa error
    sqlsrv_commit($conn);

    // 3. Ambil data statistik akumulasi real-time terbaru untuk dikirim ke dashboard klien
    $sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk WHERE SM_status = 'Aktif'");
    $total_stok = ($sql_total) ? sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] : 0;

    $sql_pending = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk WHERE Status = 'Pending' AND SM_status = 'Aktif'");
    $pending_stok = ($sql_pending) ? sqlsrv_fetch_array($sql_pending, SQLSRV_FETCH_ASSOC)['total'] : 0;

    $sql_done = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk WHERE Status = 'Diterima' AND SM_status = 'Aktif'");
    $diterima_stok = ($sql_done) ? sqlsrv_fetch_array($sql_done, SQLSRV_FETCH_ASSOC)['total'] : 0;

    echo json_encode([
        'success' => true,
        'message' => 'Status pengadaan logistik berhasil dikonfirmasi!',
        'total_stok' => $total_stok,
        'pending_stok' => $pending_stok,
        'diterima_stok' => $diterima_stok
    ]);

} catch (Exception $e) {
    // Batalkan seluruh perubahan jika terjadi salah satu kegagalan query di atas
    sqlsrv_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => formatSqlErrors(sqlsrv_errors())
    ]);
}

// Fungsi pembantu parsing pesan error database SQL Server
function formatSqlErrors($errors) {
    $err_msgs = [];
    if ($errors) {
        foreach ($errors as $e) {
            $err_msgs[] = $e['message'];
        }
    }
    return implode("\n", $err_msgs);
}
?>