<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Atur header response agar selalu berupa JSON murni
header('Content-Type: application/json');

// 1. Proteksi Otoritas Role Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    echo json_encode([
        'success' => false, 
        'message' => 'Tindakan ditolak. Anda tidak memiliki akses admin.'
    ]);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Parameter ID produk tidak valid atau tidak ditemukan.'
    ]);
    exit;
}

// 2. Eksekusi Soft Delete menggunakan Stored Procedure database (sp_Barang_Delete)
$deleted_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';
$sql_del = "{CALL sp_Barang_Delete(?, ?)}";
$stmt_del = sqlsrv_query($conn, $sql_del, array($id, $deleted_by));

if ($stmt_del === false) {
    $errors = sqlsrv_errors();
    $db_err = "";
    if ($errors !== null) {
        foreach ($errors as $err) {
            $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err['message']);
            $db_err .= trim($clean_msg) . " ";
        }
    } else {
        $db_err = 'Gagal memproses penghapusan data di server database.';
    }
    echo json_encode([
        'success' => false, 
        'message' => $db_err
    ]);
    exit;
}

// 3. Ambil ulang statistik inventaris terbaru untuk dikirim kembali ke halaman utama (UI)
$query_stats = sqlsrv_query($conn, "{CALL sp_Barang_Read(NULL, NULL, NULL)}");
$total_b = 0;
$total_k = 0;
$total_unit_stok = 0;
$total_ba = 0;
$total_bna = 0;

if ($query_stats !== false) {
    while ($r = sqlsrv_fetch_array($query_stats, SQLSRV_FETCH_ASSOC)) {
        $total_b++;
        $total_unit_stok += (int)$r['Stok'];
        if ($r['Status_Stok'] == 'Habis' || $r['Status_Stok'] == 'Stok Rendah') {
            $total_k++;
        }
        if ($r['Bar_status'] == 'Aktif') {
            $total_ba++;
        } else {
            $total_bna++;
        }
    }
}

// Kirim balik data dalam format JSON yang valid
echo json_encode([
    'success' => true,
    'message' => 'Produk berhasil dinonaktifkan secara aman.',
    'total_b' => $total_b,
    'total_k' => $total_k,
    'total_unit_stok' => $total_unit_stok,
    'total_ba' => $total_ba,
    'total_bna' => $total_bna
]);
exit;