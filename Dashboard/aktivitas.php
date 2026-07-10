<?php
session_start();
include '../Config/koneksi.php'; // Sesuaikan arah folder koneksi Anda

// Pastikan yang mengakses halaman ini adalah Customer yang sudah login
if (!isset($_SESSION['role'])) {
    header("Location: ../Auth/login.php"); // Mundur satu folder, masuk ke Auth
    exit();
}

$id_pelanggan = $_SESSION['id_pelanggan'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aktivitas Saya - Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container my-5">
    <div class="row mb-4">
        <div class="col">
            <h4 class="fw-bold text-dark"><i class="fas fa-history text-info me-2"></i>Riwayat & Aktivitas Saya</h4>
            <p class="text-muted">Pantau status reservasi layanan perawatan (grooming) anabul Anda di bawah ini.</p>
        </div>
        <div class="col text-end">
            <a href="index.php" class="btn btn-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">No</th>
                            <th>Jenis Layanan</th>
                            <th>Jadwal Kedatangan</th>
                            <th>Harga Layanan</th>
                            <th>Status Pemesanan</th>
                            <th class="pe-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query mengambil data booking milik customer yang sedang login saat ini
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
                        $ada_data = false;

                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $ada_data = true;
                            
                            // Mengonversi data objek tanggal dari SQL Server agar bisa dibaca rapi di PHP
                            $jadwal = $row['Jadwal_Booking'];
                            $string_jadwal = ($jadwal instanceof DateTime) ? $jadwal->format('d M Y - H:i') : $row['Jadwal_Booking'];
                            
                            // Pengondisian warna Badge Status
                            $status = $row['Status_Booking'] ?? 'Pending';
                            if ($status == 'Pending') {
                                $badge_color = 'bg-warning text-dark';
                            } elseif ($status == 'Dikonfirmasi' || $status == 'Selesai') {
                                $badge_color = 'bg-success text-white';
                            } else {
                                $badge_color = 'bg-danger text-white';
                            }
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= $no++; ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['Nama_Layanan']); ?></td>
                            <td><i class="far fa-clock text-secondary me-1"></i> <?= $string_jadwal; ?> WIB</td>
                            <td class="fw-bold text-info">Rp <?= number_format($row['Harga_Layanan'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="badge <?= $badge_color; ?> rounded-pill px-3 py-2 small fw-bold">
                                    <?= $status; ?>
                                </span>
                            </td>
                            <td class="pe-4 text-center">
                                <?php if ($status == 'Pending'): ?>
                                    <a href="booking_batal.php?id=<?= $row['ID_Booking'] ?? $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin membatalkan reservasi ini?')">
                                        <i class="fas fa-times me-1"></i> Batalkan
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-light border rounded-pill px-3 text-muted" disabled>
                                        <i class="fas fa-check-double text-success"></i> Terkunci
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        } 
                        
                        if (!$ada_data) {
                            echo "<tr><td colspan='6' class='text-center py-5 text-muted'><i class='fas fa-calendar-times fa-3x opacity-25 mb-3 d-block'></i>Belum ada riwayat pengajuan jadwal perawatan mandiri.</td></tr>";
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