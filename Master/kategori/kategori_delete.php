<?php
session_start();
include '../../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$id = $_GET['id'] ?? null;
$deleted_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

if ($id) {
    // PEMANGGILAN STORED PROCEDURE (sp_Kategori_Delete)
    $sql = "{call sp_Kategori_Delete(?, ?)}";
    $params = array($id, $deleted_by);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus.']);
    } else {
        // Menangkap pesan kesalahan yang dilempar oleh RAISERROR dari Stored Procedure
        $errors = sqlsrv_errors();
        $error_msg = ($errors !== null) ? $errors[0]['message'] : 'Gagal menghapus kategori.';
        
        http_response_code(400); // Mengembalikan status 400 agar AJAX fetch mendeteksi kegagalan
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Kategori tidak ditemukan.']);
}
?>