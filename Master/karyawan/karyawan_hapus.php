<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Atur header response agar dikenali sebagai JSON
header('Content-Type: application/json');

// Proteksi Akses Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses Ditolak']);
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $admin = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // PEMANGGILAN STORED PROCEDURE (sp_Karyawan_Delete)
    $sql = "{call sp_Karyawan_Delete(?, ?)}";
    $params = array($id, $admin);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Staff berhasil dinonaktifkan secara sistem.']);
    } else {
        // Menangkap pesan kesalahan yang dilempar oleh RAISERROR dari Stored Procedure
        $errors = sqlsrv_errors();
        $error_msg = ($errors !== null) ? $errors[0]['message'] : 'Gagal menghapus data staff.';
        
        http_response_code(400); // Status 400 agar AJAX catch mendeteksi kegagalan
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter ID tidak lengkap.']);
}
exit;
?>