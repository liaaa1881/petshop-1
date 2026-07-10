<?php
session_start();
require_once '../Config/koneksi.php'; 

// Proteksi Halaman
if (!isset($_SESSION['role'])) {
    header("Location: ../Auth/login.php"); 
    exit();
}

// Tangkap ID Barang dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_barang = intval($_GET['id']);

// Ambil data produk berdasarkan ID Barang
$sql = "SELECT B.*, K.Nama_Kategori 
        FROM Barang B 
        LEFT JOIN Kategori K ON B.ID_Kategori = K.ID_Kategori 
        WHERE B.ID_Barang = ?";
$stmt = sqlsrv_query($conn, $sql, array($id_barang));

if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    header("Location: index.php");
    exit();
}

$foto = trim($row['Foto_Barang'] ?? '');

// Penguraian nama file murni menggunakan regex split
$parts = preg_split('/[\\\\\/]/', $foto);
$clean_filename = end($parts);

// Perbaikan Rute Utama: Langsung diarahkan ke folder uploads/barang/
$base_root = isset($root) ? $root : "http://localhost:3000/";
$primary_src = !empty($clean_filename) ? $base_root . "uploads/barang/" . $clean_filename : "";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Produk - Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .navbar-custom { background-color: #1e272e !important; }
        .card-custom { border-radius: 20px; border: none; }
        .btn-custom { border-radius: 12px; }
    </style>
</head>
<body>

<?php include '../layouts/navbar.php'; ?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <a href="index.php" class="btn btn-white bg-white text-dark border btn-custom px-4 py-2 shadow-sm fw-medium">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Katalog
            </a>
        </div>
    </div>

    <div class="card card-custom p-4 shadow-sm bg-white">
        <div class="row g-4 align-items-center">
            
            <!-- Sisi Gambar Kiri (Metode Layering) -->
            <div class="col-md-5">
                <div class="position-relative d-flex align-items-center justify-content-center bg-light overflow-hidden rounded-4 border border-dashed" style="height: 380px; background: rgba(0, 206, 201, 0.02) !important;">
                    
                    <!-- LAPISAN 1: Ikon default dan teks cadangan -->
                    <div class="text-center py-4 position-absolute" style="z-index: 1;">
                        <i class="fas fa-box-open fa-5x text-info opacity-25 mb-3 d-block"></i>
                        <span class="text-muted small">Foto Produk Terbuka</span>
                    </div>

                    <!-- LAPISAN 2: Gambar asli produk -->
                    <?php if (!empty($foto)): ?>
                        <img src="<?= $primary_src; ?>" 
                             data-filename="<?= htmlspecialchars($clean_filename); ?>"
                             data-attempt="0"
                             class="w-100 h-100 position-absolute" 
                             style="object-fit: contain; z-index: 2;" 
                             alt="<?= htmlspecialchars($row['Nama_Barang']); ?>"
                             onerror="tryNextPath(this)">
                    <?php endif; ?>

                </div>
            </div>
            
            <!-- Sisi Kanan Informasi Detail Produk -->
            <div class="col-md-7">
                <span class="badge bg-info text-white px-3 py-2 rounded-pill small mb-2 text-uppercase"><?= htmlspecialchars($row['Nama_Kategori'] ?? 'Kategori'); ?></span>
                <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['Nama_Barang']); ?></h2>
                <h3 class="text-info fw-bold mb-4">Rp <?= number_format($row['Harga_Jual'], 0, ',', '.'); ?></h3>
                
                <hr class="opacity-10 my-4">

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4">
                            <span class="text-muted small d-block">Status Ketersediaan</span>
                            <span class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i> Stok Ready</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-4">
                            <span class="text-muted small d-block">Jumlah Sisa di Gudang</span>
                            <span class="fw-bold text-dark"><?= $row['Stok']; ?> <?= htmlspecialchars($row['Satuan']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-align-left me-1"></i> Deskripsi & Spesifikasi:</h6>
                    <p class="text-muted small bg-light p-3 rounded-4 border border-dashed"><?= nl2br(htmlspecialchars($row['Deskripsi'] ?? 'Produk berkualitas tinggi untuk hewan kesayangan Anda.')); ?></p>
                </div>

                <a href="keranjang_proses.php?id=<?= $row['ID_Barang'] ?>" class="btn btn-info text-white w-100 py-3 btn-custom fw-bold shadow-sm d-flex align-items-center justify-content-center">
                    <i class="fas fa-shopping-cart me-2 fs-5"></i> Masukkan ke Keranjang Belanja
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fungsi detektor lokasi file gambar berlapis (Anti Broken-Image)
function tryNextPath(img) {
    const filename = img.getAttribute('data-filename');
    const base_root = "<?= isset($root) ? $root : 'http://localhost:3000/'; ?>";
    
    const paths = [
        base_root + 'uploads/barang/' + filename,
        '../uploads/barang/' + filename,
        '/uploads/barang/' + filename,
        base_root + 'Master/barang/uploads/' + filename,
        '../Master/barang/uploads/' + filename
    ];
    
    let currentAttempt = parseInt(img.getAttribute('data-attempt') || '0');
    
    if (currentAttempt < paths.length) {
        img.setAttribute('data-attempt', currentAttempt + 1);
        img.src = paths[currentAttempt];
    } else {
        img.style.display = 'none';
    }
}
</script>
</body>
</html>