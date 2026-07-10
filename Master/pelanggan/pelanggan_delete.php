<?php
session_start();
include '../../config/koneksi.php';

// 1. Proteksi Role: Hanya Admin yang boleh menghapus
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') { 
    header("Location: ../../dashboard/index.php"); 
    exit; 
}

// 2. Ambil ID dari URL dan pastikan berupa angka (Integer)
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$deleted_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

if ($id > 0) {
    // 3. Query Delete menggunakan Stored Procedure sp_Pelanggan_Delete (Soft Delete)
    $sql = "EXEC sp_Pelanggan_Delete @ID_Pelanggan = ?, @Pel_deleted_by = ?";
    $params = array($id, $deleted_by);
    
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $errorMessage = "Gagal menghapus data! Kesalahan Database.";
        
        if ($errors !== null) {
            foreach ($errors as $error) {
                // Bersihkan prefix SQL Server untuk mendapatkan pesan murni
                $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
                $errorMessage = trim($clean_msg);
            }
        }

        // Cek jika dipanggil via AJAX Fetch
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        }

        echo "<script>
                alert('" . addslashes($errorMessage) . "');
                window.location='pelanggan_read.php';
              </script>";
    } else {
        // Cek jika dipanggil via AJAX Fetch
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']))) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Pelanggan berhasil dihapus']);
            exit;
        }

        echo "<script>
                window.location='pelanggan_read.php';
              </script>";
    }
} else {
    // Jika ID tidak valid
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }
    header("Location: pelanggan_read.php");
    exit;
}
?>