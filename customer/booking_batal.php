<?php
session_start();
include '../config/koneksi.php'; // Menyesuaikan path koneksi database Anda

// 1. Pastikan Pelanggan telah login
if (!isset($_SESSION['id_pelanggan'])) {
    echo "<script>window.location='../auth/login.php';</script>";
    exit();
}

// 2. Ambil ID_Booking dari URL (metode GET)
$id_booking = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_pelanggan = $_SESSION['id_pelanggan'];

if ($id_booking > 0 && $conn) {
    // 3. Validasi Keamanan: Pastikan data booking tersebut memang milik pelanggan yang login
    // dan statusnya masih 'Pending' (belum diproses atau dikonfirmasi oleh admin/kasir)
    $sql_cek = "SELECT Status_Booking FROM Booking WHERE ID_Booking = ? AND ID_Pelanggan = ?";
    $stmt_cek = sqlsrv_query($conn, $sql_cek, array($id_booking, $id_pelanggan));
    
    if ($stmt_cek === false) {
        die("Gagal memverifikasi data reservasi: " . print_r(sqlsrv_errors(), true));
    }
    
    $data = sqlsrv_fetch_array($stmt_cek, SQLSRV_FETCH_ASSOC);

    if ($data) {
        if ($data['Status_Booking'] === 'Pending') {
            // 4. Ubah status menjadi 'Dibatalkan' (Soft-Cancel) agar tetap tercatat dalam log audit
            // serta dapat dihitung secara akurat oleh fungsi UDF fn_JumlahBookingByStatus('Dibatalkan')
            $sql_cancel = "UPDATE Booking 
                           SET Status_Booking = 'Dibatalkan',
                               Book_modified_by = 'Customer',
                               Book_modified_date = GETDATE()
                           WHERE ID_Booking = ? AND ID_Pelanggan = ? AND Status_Booking = 'Pending'";
                           
            $stmt_cancel = sqlsrv_query($conn, $sql_cancel, array($id_booking, $id_pelanggan));

            if ($stmt_cancel) {
                // Berhasil membatalkan, arahkan kembali ke riwayat booking pelanggan
                echo "<script>
                        alert('Reservasi Anda berhasil dibatalkan.');
                        window.location='booking_saya.php';
                      </script>";
                exit();
            } else {
                die("Gagal membatalkan reservasi: " . print_r(sqlsrv_errors(), true));
            }
        } else {
            // Jika status sudah Diproses/Selesai, tidak boleh dibatalkan secara sepihak oleh pelanggan
            echo "<script>
                    alert('Pembatalan ditolak. Reservasi Anda sudah dalam proses pelayanan.');
                    window.location='booking_saya.php';
                  </script>";
            exit();
        }
    } else {
        echo "<script>
                alert('Data reservasi tidak ditemukan.');
                window.location='booking_saya.php';
              </script>";
        exit();
    }
} else {
    echo "<script>window.location='booking_saya.php';</script>";
    exit();
}
?>