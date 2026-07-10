<?php
session_start();
include '../config/koneksi.php'; 

// Proteksi Akun Customer
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php"); 
    exit();
}

$id_pelanggan = isset($_SESSION['id_pelanggan']) ? intval($_SESSION['id_pelanggan']) : 0;

if ($id_pelanggan <= 0) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h3>Identitas Pelanggan Tidak Valid</h3><p>Silakan login kembali.</p></div>");
}

if (!$conn) {
    die("Koneksi database gagal terhubung.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Booking Saya - Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f6fa;
        }
    </style>
</head>
<body class="bg-light">

<div class="container my-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fas fa-calendar-alt text-info me-2"></i>Daftar Booking Saya
            </h4>
            <p class="text-muted small mb-0">Pantau jadwal dan status persetujuan perawatan anabul Anda.</p>
        </div>
        
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="javascript:void(0);" onclick="history.back();" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali 
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4 py-3">No</th>
                            <th>Jenis Layanan</th>
                            <th>Jadwal Kedatangan</th>
                            <th>Biaya/Harga</th>
                            <th>Status Persetujuan</th>
                            <th class="pe-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT B.*, L.Nama_Layanan 
                                FROM Booking B
                                LEFT JOIN Layanan L ON B.ID_Layanan = L.ID_Layanan
                                WHERE B.ID_Pelanggan = ?
                                ORDER BY B.Jadwal_Booking DESC";
                        
                        $params = array($id_pelanggan);
                        $stmt = sqlsrv_query($conn, $sql, $params);

                        if ($stmt === false) {
                            die(print_r(sqlsrv_errors(), true));
                        }

                        $no = 1;
                        $jumlah_data = 0;

                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $jumlah_data++;
                            $jadwal = $row['Jadwal_Booking'];
                            $format_jadwal = ($jadwal instanceof DateTime) ? $jadwal->format('d M Y - H:i') : $row['Jadwal_Booking'];
                            $status = $row['Status_Booking'] ?? 'Pending';
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= $no++; ?></td>
                            <td><strong><?= htmlspecialchars($row['Nama_Layanan'] ?? 'Layanan Salon'); ?></strong></td>
                            <td><i class="far fa-clock me-1 text-muted"></i> <?= $format_jadwal; ?> WIB</td>
                            <td class="fw-bold text-info">Rp <?= number_format($row['Harga_Layanan'] ?? 0, 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge <?= ($status == 'Pending') ? 'bg-warning text-dark' : 'bg-success'; ?> rounded-pill px-3 py-2 small fw-bold">
                                    <?= $status; ?>
                                </span>
                            </td>
                            <td class="pe-4 text-center">
                                <?php if ($status == 'Pending'): ?>
                                    <a href="booking_batal.php?id=<?= $row['ID_Booking']; ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin membatalkan reservasi ini?')">
                                        <i class="fas fa-times me-1"></i> Batalkan
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 text-muted" disabled><i class="fas fa-lock text-success me-1"></i> Terproses</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        } 
                        if ($jumlah_data == 0) {
                            echo "<tr><td colspan='6' class='text-center py-5 text-muted'><i class='fas fa-calendar-times fa-2x mb-2 d-block opacity-50'></i>Belum ada pendaftaran booking.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>