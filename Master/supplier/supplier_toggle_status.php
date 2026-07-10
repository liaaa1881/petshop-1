<?php
session_start();
header('Content-Type: application/json');
include '../../config/koneksi.php';

// Validasi Hak Akses Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') { 
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit; 
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$current_status = isset($_GET['current']) ? $_GET['current'] : '';

if ($id === 0 || empty($current_status)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
    exit;
}

// Menentukan status baru
$new_status = ($current_status == 'Aktif') ? 'Non-Aktif' : 'Aktif';
$modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? 'Admin';

// Update status menggunakan Stored Procedure sp_Supplier_Update via CALL dengan 22 parameter
$sql_update = "{CALL sp_Supplier_Update(?, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, ?, ?)}";
$params_update = [$id, $new_status, $modified_by];
$stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

if ($stmt_update === false) {
    $errors = sqlsrv_errors();
    $errorMessage = "Gagal memperbarui status supplier.";
    if ($errors !== null) {
        foreach ($errors as $error) {
            $clean_msg = preg_replace('/\[[^\]]+\]/', '', $error['message']);
            $errorMessage = trim($clean_msg);
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage, 'error' => sqlsrv_errors()]);
    exit;
}

// Menghitung ulang statistik aktif & non-aktif memanfaatkan sp_Supplier_Read demi konsistensi data harian
$query_stats = sqlsrv_query($conn, "{CALL sp_Supplier_Read(NULL, NULL)}");
$total_aktif = 0;
$total_off = 0;

if ($query_stats !== false) {
    while ($r = sqlsrv_fetch_array($query_stats, SQLSRV_FETCH_ASSOC)) {
        if ($r['Sup_status'] == 'Aktif') {
            $total_aktif++;
        } else {
            $total_off++;
        }
    }
}

echo json_encode([
    'success' => true,
    'new_status' => $new_status,
    'total_a' => $total_aktif,
    'total_o' => $total_off
]);
exit;
?>