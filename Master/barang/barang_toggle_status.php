<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

header('Content-Type: application/json');

// Proteksi Sesi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$current_status = $_GET['current'] ?? 'Aktif';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Parameter ID tidak valid.']);
    exit;
}

$new_status = ($current_status === 'Aktif') ? 'Non-Aktif' : 'Aktif';
$modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

// Memperbarui status menggunakan Stored Procedure sp_Barang_Update via CALL
$sql_update = "{CALL sp_Barang_Update(?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?, ?)}";
$params = array($id, $new_status, $modified_by);
$stmt = sqlsrv_query($conn, $sql_update, $params);

if ($stmt === false) {
    $errors = sqlsrv_errors();
    $errorMessage = "Gagal memperbarui status produk.";
    if ($errors !== null) {
        foreach ($errors as $error) {
            $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
            $errorMessage = trim($clean_msg);
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage, 'error' => sqlsrv_errors()]);
    exit;
}

// Menghitung ulang statistik produk aktif & non-aktif demi sinkronisasi visual
$query_stats = sqlsrv_query($conn, "{CALL sp_Barang_Read(NULL, NULL, NULL)}");
$total_ba = 0;
$total_bna = 0;

if ($query_stats !== false) {
    while ($r = sqlsrv_fetch_array($query_stats, SQLSRV_FETCH_ASSOC)) {
        if ($r['Bar_status'] == 'Aktif') {
            $total_ba++;
        } else {
            $total_bna++;
        }
    }
}

echo json_encode([
    'success' => true,
    'new_status' => $new_status,
    'total_ba' => $total_ba,
    'total_bna' => $total_bna
]);
exit;
?>