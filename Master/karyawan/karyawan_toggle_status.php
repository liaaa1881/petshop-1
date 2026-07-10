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
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit; 
}

if (isset($_GET['id']) && isset($_GET['current'])) {
    $id = $_GET['id'];
    $current_status = $_GET['current'];

    // Konversi Status
    $new_status = ($current_status == 'Aktif') ? 'Non-Aktif' : 'Aktif';
    $admin = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // PEMANGGILAN STORED PROCEDURE (sp_Karyawan_Update)
    // Parameter diurutkan sesuai definisi SP:
    // 1. @ID_Karyawan, 2-24. NULL, 25. @Kar_status, 26. @Kar_modified_by
    $sql = "{call sp_Karyawan_Update(?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?, ?)}";
    $params = array($id, $new_status, $admin);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt !== false) {
        // Hitung ulang statistik keaktifan dari Stored Procedure pembacaan aktif
        $sql_sp = "{call sp_Karyawan_Read(NULL, NULL)}";
        $query_sp = sqlsrv_query($conn, $sql_sp);
        
        $total_a = 0;
        $total_o = 0;
        
        if ($query_sp !== false) {
            while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
                $status = $row['Kar_status'] ?? 'Aktif';
                if ($status === 'Aktif') {
                    $total_a++;
                } else if ($status === 'Non-Aktif') {
                    $total_o++;
                }
            }
            sqlsrv_free_stmt($query_sp);
        }

        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'total_a' => $total_a,
            'total_o' => $total_o
        ]);
        exit;
    } else {
        $errors = sqlsrv_errors();
        $error_msg = ($errors !== null) ? $errors[0]['message'] : 'Gagal memperbarui status.';
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
exit;
?>