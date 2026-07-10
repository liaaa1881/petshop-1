<?php
// Memastikan session jalan untuk validasi login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// SMART PATH FINDER (Mencari file koneksi.php)
// ==========================================
$possible_paths = [
    'koneksi.php',
    '../koneksi.php',
    '../../koneksi.php',
    '../config/koneksi.php',
    '../../config/koneksi.php',
    __DIR__ . '/koneksi.php',
    __DIR__ . '/../koneksi.php',
    __DIR__ . '/../../koneksi.php',
];

$db_connected = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        if (isset($conn) && is_resource($conn)) {
            $db_connected = true;
            break;
        }
    }
}

if (!$db_connected) {
    echo "<div style='color: #ffffff; background: #ff4757; padding: 25px; border-radius: 15px; font-family: \"Plus Jakarta Sans\", sans-serif; margin: 30px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 10px 30px rgba(255, 71, 87, 0.3);'>";
    echo "<h4 style='margin-top: 0; font-weight: 800;'><i class='fas fa-exclamation-triangle'></i> Berkas 'koneksi.php' Tidak Ditemukan!</h4>";
    echo "<p>Sistem tidak dapat menemukan file konfigurasi database Anda. Harap pastikan beberapa hal berikut:</p>";
    echo "<ol style='line-height: 1.6;'>";
    echo "<li>Pastikan Anda sudah membuat file bernama <strong>koneksi.php</strong>.</li>";
    echo "<li>Pindahkan atau salin file <strong>koneksi.php</strong> tersebut ke dalam folder root proyek Anda (<code>C:/xampp/htdocs/PETSHOP_new/</code>) atau di dalam folder yang sama dengan file ini (<code>C:/xampp/htdocs/PETSHOP_new/Dashboard/</code>).</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

// ==========================================
// PROSES PENGAMBILAN DATA PRODUK (MENGGUNAKAN UDF)
// ==========================================

$categoryFilter = isset($_GET['kategori']) ? $_GET['kategori'] : '';

// Menggunakan UDF dbo.fn_StokBarang(NULL) di-join dengan tabel Barang asli untuk mengambil properti gambar
$sql = "SELECT b.ID_Barang, b.Nama_Barang, b.Harga_Jual, b.Stok, b.Stok_Minimum, 
               orig.Foto_Barang, b.Nama_Kategori, orig.Bar_status, b.Satuan
        FROM dbo.fn_StokBarang(NULL) b
        INNER JOIN Barang orig ON b.ID_Barang = orig.ID_Barang
        WHERE orig.Bar_status = 'Aktif'";

$params = array();

if (!empty($categoryFilter)) {
    $sql .= " AND b.Nama_Kategori = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY b.ID_Barang DESC";

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die("Gagal mengambil data produk: " . print_r(sqlsrv_errors(), true));
}

$products = array();
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $products[] = $row;
}
sqlsrv_free_stmt($stmt);


$sqlKategori = "SELECT DISTINCT Nama_Kategori FROM Kategori";
$stmtKat = sqlsrv_query($conn, $sqlKategori);

$categories = array();
if ($stmtKat !== false) {
    while ($rowKat = sqlsrv_fetch_array($stmtKat, SQLSRV_FETCH_ASSOC)) {
        $categories[] = $rowKat;
    }
    sqlsrv_free_stmt($stmtKat);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetShop Pro - Premium Supplies</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-glow: #0dcaf0;
            --accent-pink: #ff4757;
            --bg-dark: #07090e;
            --card-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-hover: rgba(13, 202, 240, 0.15);
        }

        body {
            background-color: var(--bg-dark);
            color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            overflow-x: hidden;
        }

        .fw-800 { font-weight: 800; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 6px; }
        ::-webkit-scrollbar-track { background: #07090e; }
        ::-webkit-scrollbar-thumb { background: #1a2332; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-glow); }

        /* Header Section */
        .shop-header {
            padding: 80px 0 40px;
            background: radial-gradient(circle at 10% 20%, rgba(13, 202, 240, 0.08) 0%, transparent 50%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        /* Modern Horizontal Scroll Category Bar */
        .category-scroll-wrapper {
            position: relative;
            margin-top: -20px;
            padding: 20px 0;
            z-index: 10;
        }

        .category-scroll-container {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            white-space: nowrap;
            padding: 10px 5px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .category-scroll-container::-webkit-scrollbar {
            display: none; 
        }
        .category-scroll-container {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .filter-btn {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            color: rgba(255, 255, 255, 0.7);
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            display: inline-block;
        }

        .filter-btn:hover {
            color: #ffffff;
            border-color: var(--primary-glow);
            background: var(--glass-hover);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: var(--primary-glow);
            color: #000000;
            border-color: var(--primary-glow);
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(13, 202, 240, 0.35);
        }

        /* Product Card Styling */
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 18px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            backdrop-filter: blur(20px);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-card:hover {
            transform: translateY(-10px);
            border-color: rgba(13, 202, 240, 0.4);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4), 0 0 30px rgba(13, 202, 240, 0.05);
        }

        .product-img-container {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            height: 200px;
            background: #0f131a;
            margin-bottom: 16px;
        }

        .product-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        .product-card:hover .product-img {
            transform: scale(1.08);
        }

        .category-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary-glow);
            margin-bottom: 6px;
            display: block;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.4;
            color: #ffffff;
            margin-bottom: 8px;
            height: 44px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* Wishlist Heart Button */
        .btn-love {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 10;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.08);
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-love:hover {
            background: rgba(255,255,255,0.1);
            transform: scale(1.1);
        }

        .btn-love.active {
            background: #ffffff;
            color: var(--accent-pink);
            border-color: #ffffff;
            box-shadow: 0 0 15px rgba(255, 71, 87, 0.4);
        }

        /* Stock Progress Bar */
        .stock-info {
            font-size: 0.75rem;
            margin-top: 15px;
        }

        .progress {
            height: 6px;
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            margin-top: 6px;
        }

        .progress-bar {
            background: linear-gradient(90deg, #0dcaf0, #0081a7);
        }

        /* Float Cart Button */
        .float-cart {
            position: fixed;
            bottom: 40px;
            right: 30px;
            z-index: 1000;
            background-color: var(--primary-glow); 
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #07090e;
            text-decoration: none;
            box-shadow: 0 8px 32px rgba(13, 202, 240, 0.4);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .float-cart:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 40px rgba(13, 202, 240, 0.6);
            color: #07090e;
        }

        .cart-count {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--accent-pink);
            color: white;
            font-size: 10px;
            padding: 3px 7px;
            border-radius: 10px;
            font-weight: 800;
            border: 2px solid var(--bg-dark);
        }

        .catalog-title-section {
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 15px;
        }
    </style>
</head>
<body>

    <!-- Tombol Keranjang Melayang -->
    <a href="javascript:void(0)" onclick="handleCartClick()" class="float-cart">
        <i class="fas fa-shopping-basket fa-lg"></i>
        <span class="cart-count" id="cartCount">0</span>
    </a>

    <!-- Header Section -->
    <header class="shop-header">
        <div class="container">
            <div class="row">
                <div class="col-lg-8" data-aos="fade-right">
                    <span class="badge bg-info text-dark mb-3 px-3 py-2 rounded-pill fw-bold">EXCLUSIVE PET SUPPLIES</span>
                    <h1 class="display-4 fw-800 mb-3">Lengkapi Kebutuhan <span class="text-info">Anabul.</span></h1>
                    <p class="fs-5 opacity-75">Dari nutrisi harian hingga aksesoris premium, kami menyediakan semua yang terbaik untuk hewan kesayangan Anda.</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Baris Horizontal Scroll Kategori Khusus -->
    <section class="category-scroll-wrapper">
        <div class="container">
            <div class="category-scroll-container" data-aos="fade-up">
                <a href="dashboard_utama.php" class="filter-btn <?= empty($categoryFilter) ? 'active' : '' ?>">
                    Semua Produk
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="dashboard_utama.php?kategori=<?= urlencode($cat['Nama_Kategori']) ?>" 
                       class="filter-btn <?= $categoryFilter === $cat['Nama_Kategori'] ? 'active' : '' ?>">
                       <?= htmlspecialchars($cat['Nama_Kategori']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Main Shop Section -->
    <section class="py-5">
        <div class="container">
            <!-- Catalog Title Section -->
            <div class="d-flex justify-content-between align-items-center mb-5 catalog-title-section" data-aos="fade-up">
                <h3 class="fw-800 m-0"><i class="fas fa-th-large text-info me-2"></i>Katalog Produk</h3>
                <div>
                    <select class="form-select bg-dark text-white border-secondary rounded-pill px-4" onchange="location = this.value;">
                        <option value="dashboard_utama.php">Terbaru</option>
                    </select>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="row g-4">
                <?php
                if (count($products) > 0):
                    foreach($products as $p):
                        $maxCapacity = max(100, $p['Stok'] + ($p['Stok_Minimum'] ?? 10));
                        $stockPercent = min(100, ($p['Stok'] / $maxCapacity) * 100);

                        $foto = trim($p['Foto_Barang'] ?? '');
                        
                        // Pembersihan nama file murni menggunakan regex split
                        $parts = preg_split('/[\\\\\/]/', $foto);
                        $clean_filename = end($parts);
                        
                        // Menggunakan rute absolut dari domain root utama
                        $base_root = "http://localhost:3000/";
                        $primary_src = !empty($clean_filename) ? $base_root . "uploads/barang/" . $clean_filename : "";
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6" data-aos="zoom-in" data-aos-duration="600">
                    <div class="product-card">
                        <div>
                            <div class="btn-love" onclick="toggleWishlist(this, '<?= addslashes(htmlspecialchars($p['Nama_Barang'])) ?>')">
                                <i class="fas fa-heart"></i>
                            </div>

                            <div class="product-img-container position-relative">
                                <!-- Layer 1: Placeholder Ikon Box Default -->
                                <div class="w-100 h-100 position-absolute d-flex align-items-center justify-content-center bg-dark" style="z-index: 1;">
                                    <i class="fas fa-box-open fa-3x text-info opacity-25"></i>
                                </div>
                                
                                <!-- Layer 2: Gambar Asli Produk (Akan menimpa placeholder jika sukses memuat) -->
                                <?php if (!empty($foto)): ?>
                                    <img src="<?= $primary_src; ?>" 
                                         data-filename="<?= htmlspecialchars($clean_filename); ?>"
                                         data-attempt="0"
                                         class="product-img position-absolute w-100 h-100" 
                                         style="object-fit: cover; z-index: 2;" 
                                         alt="<?= htmlspecialchars($p['Nama_Barang']); ?>"
                                         onerror="tryNextPath(this)">
                                <?php endif; ?>
                            </div>

                            <span class="category-label"><?= htmlspecialchars($p['Nama_Kategori'] ?? 'Lain-lain') ?></span>
                            <h6 class="product-title" title="<?= htmlspecialchars($p['Nama_Barang']) ?>"><?= htmlspecialchars($p['Nama_Barang']) ?></h6>
                        </div>

                        <div>
                            <h5 class="text-info fw-800 mb-3">Rp <?= number_format($p['Harga_Jual'], 0, ',', '.') ?></h5>

                            <div class="stock-info">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="opacity-50">Sisa Stok:</span>
                                    <span class="fw-bold"><?= htmlspecialchars($p['Stok']) ?> <?= htmlspecialchars($p['Satuan'] ?? 'Pcs') ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $stockPercent ?>%"></div>
                                </div>
                            </div>

                            <button onclick="checkLoginBeforeAdd('<?= $p['ID_Barang'] ?>')" class="btn btn-outline-info w-100 rounded-pill mt-4 fw-bold shadow-sm">
                                <i class="fas fa-cart-plus me-2"></i>TAMBAH
                            </button>
                        </div>
                    </div>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted fs-5"><i class="fas fa-box-open me-2"></i>Tidak ada produk aktif dalam kategori ini.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

    <script>
    // Penyesuaian ke session ID Pelanggan yang aktif untuk singkronisasi login
    const isLoggedInStatus = <?= isset($_SESSION['id_pelanggan']) ? 'true' : 'false' ?>;

    AOS.init({
        once: true,
        duration: 800
    });

    // Detektor Jalur File Berlapis untuk Halaman Utama
    function tryNextPath(img) {
        const filename = img.getAttribute('data-filename');
        const base_root = "http://localhost:3000/";
        
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

    // Fungsi Pop-up Validasi Harus Login via SweetAlert2
    function requireLoginAlert(pesanAksi) {
        Swal.fire({
            title: 'Akses Terbatas 🐾',
            text: 'Silakan masuk (login) terlebih dahulu untuk ' + pesanAksi + '.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0dcaf0',
            cancelButtonColor: '#ff4757',
            confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i> Login Sekarang',
            cancelButtonText: 'Nanti Saja',
            background: '#0d1117',
            color: '#ffffff',
            customClass: {
                popup: 'border border-secondary rounded-4 shadow-lg'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../auth/login.php';
            }
        });
    }

    function toggleWishlist(btn, productName) {
        if (!isLoggedInStatus) {
            requireLoginAlert('menyimpan produk ke wishlist');
            return; 
        }

        btn.classList.toggle('active');
        const icon = btn.querySelector('i');
        
        if(btn.classList.contains('active')) {
            icon.classList.remove('far');
            icon.classList.add('fas');
            showNotification('❤ ' + productName + ' Berhasil disimpan!');
            
            confetti({
                particleCount: 50,
                spread: 60,
                origin: { y: 0.8 },
                colors: ['#ff4757', '#ffffff']
            });
        } else {
            icon.classList.remove('fas');
            icon.classList.add('far');
        }
    }

    function checkLoginBeforeAdd(idBarang) {
        if (!isLoggedInStatus) {
            requireLoginAlert('menambahkan produk ke keranjang');
        } else {
            // Arahkan ke file pemroses keranjang
            window.location.href = 'keranjang_proses.php?id=' + idBarang + '&jumlah=1';
        }
    }

    function handleCartClick() {
        if (!isLoggedInStatus) {
            requireLoginAlert('melihat keranjang belanja');
        } else {
            window.location.href = 'keranjang.php';
        }
    }

    function showNotification(msg) {
        const notif = document.createElement('div');
        notif.style.cssText = `
            position: fixed;
            bottom: 120px;
            right: 30px;
            background: rgba(13, 202, 240, 0.95);
            color: #000;
            padding: 15px 25px;
            border-radius: 20px;
            font-weight: 800;
            z-index: 9999;
            box-shadow: 0 10px 40px rgba(13, 202, 240, 0.4);
            backdrop-filter: blur(10px);
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: translateX(100px);
            opacity: 0;
            border: 1px solid white;
        `;
        notif.innerHTML = msg;
        document.body.appendChild(notif);
        
        setTimeout(() => {
            notif.style.transform = 'translateX(0)';
            notif.style.opacity = '1';
        }, 100);

        setTimeout(() => {
            notif.style.transform = 'translateX(100px)';
            notif.style.opacity = '0';
            setTimeout(() => notif.remove(), 500);
        }, 3000);
    }
</script>

</body>
</html>