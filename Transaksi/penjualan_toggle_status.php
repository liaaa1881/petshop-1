<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak valid.']);
    exit;
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    header('Content-Type: application/json');

    $id_nota = intval($_GET['id']);
    $status_target = $_GET['status'];
    $modified_by = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Kasir';

    if ($status_target !== 'Lunas') {
        echo json_encode(['success' => false, 'message' => 'Status target tidak valid.']);
        exit;
    }

    // 1. Eksekusi pembaruan status transaksi pembayaran
    // Catatan: trigger "trg_Penjualan_PoinMember" akan otomatis menghitung 
    // dan menambahkan poin pelanggan apabila status berubah menjadi 'Lunas'
    $sql_update = "UPDATE Penjualan 
                   SET Status_Pembayaran = ?, 
                       Jumlah_Bayar = Grand_Total, 
                       Kembalian = 0,
                       Pen_modified_by = ?,
                       Pen_modified_date = GETDATE()
                   WHERE ID_Nota = ?";
                   
    $stmt_update = sqlsrv_query($conn, $sql_update, array($status_target, $modified_by, $id_nota));

    if ($stmt_update === false) {
        echo json_encode([
            'success' => false, 
            'message' => 'Gagal memperbarui status nota di basis data.', 
            'error' => sqlsrv_errors()
        ]);
        exit;
    }

    // 2. Ambil data statistik terbaru untuk respons letupan UI menggunakan UDF
    // Total Seluruh Transaksi Aktif
    $sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Penjualan WHERE Pen_status = 'Aktif'");
    $total_t = $sql_total ? sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Total Pendapatan Bersih (Lunas) sepanjang masa menggunakan UDF fn_TotalPenjualan
    $sql_revenue = sqlsrv_query($conn, "SELECT dbo.fn_TotalPenjualan('1900-01-01', '2099-12-31') as total");
    $total_r = $sql_revenue ? (sqlsrv_fetch_array($sql_revenue, SQLSRV_FETCH_ASSOC)['total'] ?? 0) : 0;
    $total_revenue_format = 'Rp ' . number_format($total_r, 0, ',', '.');

    // Total Transaksi Lunas Khusus Hari Ini menggunakan UDF fn_JumlahTransaksi
    $sql_today = sqlsrv_query($conn, "SELECT dbo.fn_JumlahTransaksi(CAST(GETDATE() AS DATE), CAST(GETDATE() AS DATE)) as total");
    $total_td = $sql_today ? sqlsrv_fetch_array($sql_today, SQLSRV_FETCH_ASSOC)['total'] : 0;

    echo json_encode([
        'success' => true,
        'total_t' => $total_t,
        'total_revenue_format' => $total_revenue_format,
        'total_td' => $total_td
    ]);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}