<?php
session_start();
include '../../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

if (isset($_GET['id']) && isset($_GET['current'])) {
    $id = $_GET['id'];
    $current = $_GET['current'];
    $new_status = ($current === 'Aktif') ? 'Non-Aktif' : 'Aktif';
    $modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // PEMANGGILAN STORED PROCEDURE (sp_Kategori_Update)
    // Parameter diurutkan sesuai definisi SP:
    // 1. @ID_Kategori, 2. @Nama_Kategori (NULL), 3. @Deskripsi (NULL), 4. @Foto_Kategori (NULL), 5. @Tipe_Kategori (NULL), 6. @Foto_Barang (NULL), 7. @Kat_status, 8. @Kat_modified_by
    $sql = "{call sp_Kategori_Update(?, NULL, NULL, NULL, NULL, NULL, ?, ?)}";
    $stmt = sqlsrv_query($conn, $sql, array($id, $new_status, $modified_by));

    if ($stmt) {
        // Hitung ulang statistik keaktifan dari Stored Procedure pembacaan aktif
        $sql_sp = "{call sp_Kategori_Read(NULL)}";
        $query_sp = sqlsrv_query($conn, $sql_sp);
        
        $total_a = 0;
        $total_o = 0;
        
        if ($query_sp !== false) {
            while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
                $status = $row['Kat_status'] ?? 'Aktif';
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
    } else {
        $errors = sqlsrv_errors();
        $error_msg = ($errors !== null) ? $errors[0]['message'] : 'Gagal memperbarui status.';
        echo json_encode(['success' => false, 'message' => $error_msg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
}
?>