<?php
session_start();

// MUNDUR SATU FOLDER UNTUK MENEMUKAN CONFIG
require_once '../Config/koneksi.php'; 

// 1. CEK STATUS LOGIN
if (!isset($_SESSION['role'])) {
    // JIKA BELUM LOGIN: TAMPILKAN LANDING PAGE / LOGIN
    include 'masuk_sistem.php'; 
    exit(); 
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'Pengguna';

// Logika Tema Warna dan Deskripsi Sambutan Dinamis berdasarkan Peran
if ($role == 'Admin') {
    $bg_img = "https://images.unsplash.com/photo-1512428559087-560fa5ceab42?auto=format&fit=crop&w=1920&q=80"; 
    $accent_color = "#4f46e5"; // Indigo modern
    $welcome_desc = "Semangat bekerja! Pantau performa operasional Petshop Pro hari ini dengan mudah.";
} else if (in_array($role, ['Kasir', 'Groomer', 'Dokter', 'Karyawan', 'Staff'])) {
    $bg_img = "https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?auto=format&fit=crop&w=1920&q=80"; 
    $accent_color = "#16a34a"; // Hijau Emerald
    $welcome_desc = "Akses panel kasir, kelola transaksi, dan pantau antrean perawatan hewan hari ini.";
} else if ($role == 'Supplier') {
    $bg_img = "https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1920&q=80"; 
    $accent_color = "#8e44ad"; // Ungu Amethyst
    $welcome_desc = "Pantau status pengapalan pasokan Anda dan laporkan stok masuk ke gudang kami.";
} else { 
    $bg_img = "https://images.unsplash.com/photo-1544568100-847a948585b9?auto=format&fit=crop&w=1920&q=80"; 
    $accent_color = "#ea580c"; // Jingga Warm
    $welcome_desc = "Temukan pakan bernutrisi, vitamin esensial, atau reservasi perawatan salon untuk hewan kesayangan Anda.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(244, 247, 246, 0.9), rgba(244, 247, 246, 0.9)), url('<?php echo $bg_img; ?>');
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed;
            min-height: 100vh;
        }

        /* Desain Banner Selamat Datang */
        .welcome-card { 
            background: white; 
            border-radius: 24px; 
            border-left: 10px solid <?php echo $accent_color; ?>; 
            padding: 40px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
            position: relative;
            overflow: hidden;
        }
        .role-badge { 
            background: <?php echo $accent_color; ?>; 
            color: white; 
            padding: 6px 20px; 
            border-radius: 50px; 
            font-size: 13px; 
            font-weight: 600; 
            letter-spacing: 1px;
        }

        /* Desain Seragam Kartu Statistik */
        .stat-card {
            background: white;
            border: none;
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 20px;
        }

        /* Desain Tombol & Navigasi Cepat */
        .action-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: none;
            border-bottom: 5px solid transparent;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        }
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        .action-card i {
            font-size: 2.8rem;
            margin-bottom: 15px;
            display: block;
        }

        /* Kartu Produk Pelanggan */
        .product-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            border-radius: 20px;
            overflow: hidden;
        }
        .product-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1) !important; 
        }

        /* Form Controls */
        .form-control, .form-select { 
            border-radius: 12px;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
        }
        .form-control:focus, .form-select:focus { 
            border-color: <?php echo $accent_color; ?>; 
            box-shadow: 0 0 0 0.25rem rgba(0, 0, 0, 0.05); 
        }
    </style>
</head>
<body>

<?php include '../layouts/navbar.php'; ?>

<div class="container py-5">
    <!-- Section Banner Utama (Dinamis untuk semua User) -->
    <div class="welcome-card mb-5 animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <span class="role-badge mb-3 d-inline-block text-uppercase"><?php echo htmlspecialchars($role); ?> Mode</span>
                <h1 class="fw-bold">Halo, <?php echo htmlspecialchars($nama); ?>! 🐾</h1>
                <p class="text-muted fs-5 mb-0"><?php echo htmlspecialchars($welcome_desc); ?></p>
            </div>
            <div class="col-md-4 text-center d-none d-md-block">
                <i class="fas fa-paw fa-6x opacity-25" style="color: <?php echo $accent_color; ?>;"></i>
            </div>
        </div>
    </div>

    <!-- Integrasi Konten Dinamis berdasarkan Peran -->
    <?php 
    if ($role == 'Admin') {
        include 'view_admin.php';
    } else if (in_array($role, ['Kasir', 'Groomer', 'Dokter', 'Karyawan', 'Staff'])) {
        include 'view_karyawan.php'; 
    } else if ($role == 'Supplier') {
        include 'view_supplier.php'; 
    } else {
        include 'view_customer.php'; 
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>