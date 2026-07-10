<?php
session_start();
include '../../config/koneksi.php';

// Proteksi Admin: Hanya Admin yang diperbolehkan melakukan penghapusan
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    exit("Akses Ditolak");
}

if (isset($_GET['id']) && isset($_GET['type'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'];
    $admin = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    $stmt = false;
    $msg = "";
    $redir = "supplier_read.php";

    if ($type == 'soft') {
        // Menggunakan Stored Procedure sp_Supplier_Delete untuk Soft Delete via CALL
        $sql = "{CALL sp_Supplier_Delete(?, ?)}";
        $params = array($id, $admin);
        $msg = "Mitra Supplier berhasil dinonaktifkan!";
        $redir = "supplier_read.php";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
    } 
    elseif ($type == 'restore') {
        // Melakukan pemulihan (restore) status supplier ke aktif kembali
        $sql = "UPDATE Supplier SET Sup_is_deleted = 0, Sup_deleted_by = NULL, Sup_deleted_date = NULL, Sup_status = 'Aktif' WHERE ID_Supplier = ?";
        $params = array($id);
        $msg = "Mitra Supplier berhasil dipulihkan!";
        $redir = "supplier_read.php?view=trash";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
    } 
    elseif ($type == 'hard') {
        // Melakukan penghapusan permanen dari database
        $sql = "DELETE FROM Supplier WHERE ID_Supplier = ?";
        $params = array($id);
        $msg = "Data Mitra Supplier dihapus secara permanen!";
        $redir = "supplier_read.php?view=trash";
        
        $stmt = sqlsrv_query($conn, $sql, $params);
    }

    if ($stmt) {
        // Deteksi jika dipanggil via AJAX Fetch dari halaman utama
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $msg]);
            exit;
        }

        echo "<script>alert('$msg'); window.location.href='$redir';</script>";
    } else {
        $errors = sqlsrv_errors();
        $errorMessage = "Gagal memproses penghapusan data.";
        if ($errors !== null) {
            foreach ($errors as $error) {
                $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
                $errorMessage = trim($clean_msg);
            }
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_GET['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }

        die(print_r(sqlsrv_errors(), true));
    }
} else {
    header("Location: supplier_read.php");
    exit;
}
?>