<?php
session_start();
header('Content-Type: application/json');

include '../config/koneksi.php';

// Proteksi Akses: Cek apakah user sudah login
if (!isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi Anda telah berakhir. Silakan login kembali.'
    ]);
    exit;
}

// Validasi Input Parameter
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter ID atau Status tidak lengkap.'
    ]);
    exit;
}

$id_booking = intval($_GET['id']);
$target_status = trim($_GET['status']);
$allowed_status = ['Pending', 'Diproses', 'Selesai', 'Dibatalkan'];

// Validasi nilai status yang diperbolehkan
if (!in_array($target_status, $allowed_status)) {
    echo json_encode([
        'success' => false,
        'message' => 'Status tidak valid.'
    ]);
    exit;
}

$user_aktif = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'Kasir';

if ($conn) {
    // 1. Eksekusi UPDATE status booking dan audit log
    $sql_update = "UPDATE Booking 
                   SET Status_Booking = ?, 
                       Book_modified_by = ?, 
                       Book_modified_date = GETDATE() 
                   WHERE ID_Booking = ?";
                   
    $params_update = [$target_status, $user_aktif, $id_booking];
    $stmt_update = sqlsrv_query($conn, $sql_update, $params_update);

    // Antisipasi jika update dibatalkan oleh trigger validasi jadwal (trg_Booking_ValidasiJadwal)
    if ($stmt_update === false) {
        $errors = sqlsrv_errors();
        $custom_error_message = 'Gagal memperbarui status di database.';
        
        if ($errors !== null) {
            foreach ($errors as $error) {
                if (strpos($error['message'], 'Jadwal karyawan bentrok') !== false) {
                    $custom_error_message = 'Perubahan status dibatalkan karena jadwal terapis bentrok dengan booking aktif lainnya (toleransi selisih minimal 2 jam).';
                    break;
                }
            }
        }
        
        echo json_encode([
            'success' => false,
            'message' => $custom_error_message,
            'error'   => $errors
        ]);
        exit;
    }

    // 2. Ambil data statistik terbaru untuk respons letupan UI menggunakan UDF
    // Total Booking
    $sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Booking");
    $total_b = ($sql_total) ? sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Pending
    $sql_pending = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Pending', NULL, NULL) as total");
    $pending_b = ($sql_pending) ? sqlsrv_fetch_array($sql_pending, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Diproses
    $sql_proses = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Diproses', NULL, NULL) as total");
    $diproses_b = ($sql_proses) ? sqlsrv_fetch_array($sql_proses, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Selesai
    $sql_selesai = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Selesai', NULL, NULL) as total");
    $selesai_b = ($sql_selesai) ? sqlsrv_fetch_array($sql_selesai, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Dibatalkan
    $sql_batal = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Dibatalkan', NULL, NULL) as total");
    $batal_b = ($sql_batal) ? sqlsrv_fetch_array($sql_batal, SQLSRV_FETCH_ASSOC)['total'] : 0;

    // Kirim balasan sukses dengan data statistik terbaru
    echo json_encode([
        'success'      => true,
        'new_status'   => $target_status,
        'total_b'      => $total_b,
        'pending_b'    => $pending_b,
        'diproses_b'   => $diproses_b,
        'selesai_b'    => $selesai_b,
        'batal_b'      => $batal_b
    ]);
    exit;
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi ke server database terputus.'
    ]);
    exit;
}