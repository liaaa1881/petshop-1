<?php
session_start();
include '../../config/koneksi.php';

header('Content-Type: application/json');

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$id = $_GET['id'] ?? null;
$current = $_GET['current'] ?? null;
$modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

if ($id && $current) {
    $new_status = ($current === 'Aktif') ? 'Non-Aktif' : 'Aktif';

    // Update status ke database menggunakan Stored Procedure sp_Layanan_Update
    $sql = "EXEC sp_Layanan_Update @ID_Layanan = ?, @Lay_status = ?, @Lay_modified_by = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id, $new_status, $modified_by));

    if ($stmt) {
        // Hitung ulang statistik keaktifan dan rata-rata harga
        $sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Layanan WHERE (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
        $total_l = sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

        $sql_aktif = "SELECT COUNT(*) as total FROM Layanan WHERE Lay_status = 'Aktif' AND (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)";
        $total_a = sqlsrv_fetch_array(sqlsrv_query($conn, $sql_aktif), SQLSRV_FETCH_ASSOC)['total'] ?? 0;

        $sql_off = "SELECT COUNT(*) as total FROM Layanan WHERE Lay_status = 'Non-Aktif' AND (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)";
        $total_o = sqlsrv_fetch_array(sqlsrv_query($conn, $sql_off), SQLSRV_FETCH_ASSOC)['total'] ?? 0;

        $sql_avg = sqlsrv_query($conn, "SELECT AVG(Harga_Layanan) as rata FROM Layanan WHERE (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
        $rata_harga = sqlsrv_fetch_array($sql_avg, SQLSRV_FETCH_ASSOC)['rata'] ?? 0;

        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'total_a' => $total_a,
            'total_o' => $total_o,
            'rata_harga' => number_format($rata_harga, 0, ',', '.')
        ]);
    } else {
        $errors = sqlsrv_errors();
        $errorMessage = "Gagal memperbarui status ke database.";
        
        if ($errors !== null) {
            foreach ($errors as $error) {
                // Menghilangkan prefix database murni dari SQL Server
                $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
                $errorMessage = trim($clean_msg);
            }
        }
        echo json_encode(['success' => false, 'message' => $errorMessage, 'error' => sqlsrv_errors()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
}
?>