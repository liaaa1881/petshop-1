<?php
session_start();
include '../../config/koneksi.php';

header('Content-Type: application/json');

// Proteksi Akses Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$id = $_GET['id'] ?? null;
$current = $_GET['current'] ?? null;
$modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

if (!$id || !$current) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Menentukan status kebalikan
$new_status = ($current == 'Aktif') ? 'Non-Aktif' : 'Aktif';

// Query update status menggunakan Stored Procedure sp_Pelanggan_Update
$sql = "EXEC sp_Pelanggan_Update @ID_Pelanggan = ?, @Pel_status = ?, @Pel_modified_by = ?";
$stmt = sqlsrv_query($conn, $sql, array($id, $new_status, $modified_by));

if ($stmt) {
    // HITUNG ULANG STATISTIK UNTUK DIKIRIM KE AJAX
    $sql_a = "SELECT COUNT(*) as total FROM Pelanggan WHERE Pel_status = 'Aktif' AND (Pel_is_deleted = 0 OR Pel_is_deleted IS NULL)";
    $q_a = sqlsrv_query($conn, $sql_a);
    $total_a = sqlsrv_fetch_array($q_a, SQLSRV_FETCH_ASSOC)['total'];

    $sql_na = "SELECT COUNT(*) as total FROM Pelanggan WHERE Pel_status = 'Non-Aktif' AND (Pel_is_deleted = 0 OR Pel_is_deleted IS NULL)";
    $q_na = sqlsrv_query($conn, $sql_na);
    $total_na = sqlsrv_fetch_array($q_na, SQLSRV_FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'total_a' => $total_a,
        'total_na' => $total_na
    ]);
} else {
    $errors = sqlsrv_errors();
    $errorMessage = "Gagal memperbarui status di database";
    
    if ($errors !== null) {
        foreach ($errors as $error) {
            $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
            $errorMessage = trim($clean_msg);
        }
    }

    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'errors' => sqlsrv_errors()
    ]);
}
?>