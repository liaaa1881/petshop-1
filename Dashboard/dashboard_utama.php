<?php
// WAJIB: Pastikan session_start hanya dipanggil sekali di file index utama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petshop Pro - Premium Pet Ecosystem</title>
    
    <!-- CSS Eksternal -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

    <style>
        :root { 
            --primary: #0dcaf0; 
            --cyan-glow: #0dcaf0;
            --dark: #0a0a0a; 
            --dark-bg: #050505;
            --card: #141414; 
        }
        
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--dark-bg) !important; 
            color: white; 
            overflow-x: hidden; 
            padding-top: 85px !important; 
        }

        /* CSS UNTUK SISTEM LANDING PAGE (Semua halaman tampil berurutan ke bawah) */
        .page-content {  
            animation: slideUp 0.6s ease forwards; 
            scroll-margin-top: 95px; /* Offset agar bagian atas konten tidak tertutup navbar */
            margin-bottom: 80px;     /* Jarak antar halaman/section */
        }
        
        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        .btn-main { padding: 12px 30px; border-radius: 50px; background: var(--primary); color: #000; font-weight: 700; text-decoration: none; transition: 0.3s; display: inline-block; border: none; }
        .btn-main:hover { box-shadow: 0 0 20px rgba(13, 202, 240, 0.5); transform: scale(1.05); color: #000; }

        /* Styles Navbar Terintegrasi */
        .navbar-custom {
            background: rgba(10, 10, 10, 0.9) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(13, 202, 240, 0.2);
            padding: 15px 0;
            z-index: 1000;
        }

        .logo-box {
            background: var(--cyan-glow);
            width: 35px; height: 35px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.5);
        }
        .logo-box i { color: #000; }
        .brand-name { font-weight: 800; letter-spacing: 2px; color: white; text-decoration: none; }
        .text-cyan { color: var(--cyan-glow) !important; }

        .nav-link {
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.6) !important;
            padding: 10px 20px !important;
            transition: 0.3s;
            position: relative;
        }

        .nav-link:hover, .nav-active {
            color: var(--cyan-glow) !important;
        }

        .nav-active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 25px;
            height: 3px;
            background: var(--cyan-glow);
            border-radius: 10px;
            box-shadow: 0 0 10px var(--cyan-glow);
        }

        /* Icons & Buttons */
        .icon-btn { background: transparent; border: none; color: white; font-size: 1.3rem; transition: 0.3s; }
        .icon-btn:hover { color: var(--cyan-glow); transform: translateY(-2px); }
        
        .badge-custom {
            position: absolute; top: -5px; right: -8px;
            background: #ff4757; color: white; font-size: 10px;
            min-width: 18px; height: 18px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
        }
        .bg-cyan { background: var(--cyan-glow); color: #000; }

        .login-glow-btn {
            background: var(--cyan-glow); color: #000;
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
            text-decoration: none;
        }
        .login-glow-btn:hover { transform: scale(1.1); box-shadow: 0 0 20px var(--cyan-glow); color: #000;}

        /* Tema Gelap SweetAlert2 Premium */
        .premium-swal-popup {
            border-radius: 24px !important;
            border: 1px solid rgba(13, 202, 240, 0.2) !important;
            box-shadow: 0 15px 50px rgba(0,0,0,0.8) !important;
            padding: 35px 30px !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
        .premium-swal-confirm {
            background-color: var(--cyan-glow) !important;
            color: #000 !important;
            font-weight: 800 !important;
            border-radius: 12px !important;
            padding: 12px 35px !important;
            margin: 5px !important;
            border: none !important;
            transition: 0.3s !important;
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.3) !important;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .premium-swal-confirm:hover { transform: scale(1.03); box-shadow: 0 0 25px var(--cyan-glow) !important; }
        .premium-swal-cancel {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: #fff !important;
            font-weight: 800 !important;
            border-radius: 12px !important;
            padding: 12px 35px !important;
            margin: 5px !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            transition: 0.3s !important;
            cursor: pointer;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .premium-swal-cancel:hover { background-color: rgba(255, 255, 255, 0.12) !important; }
    </style>
</head>
<body>

    <!-- NAVBAR INTEGRASI (Menempel di atas halaman) -->
    <nav class="navbar navbar-expand-lg fixed-top navbar-custom">
        <div class="container">
            <!-- Logo - Klik logo meluncur ke halaman Produk (Atas) -->
            <a class="navbar-brand d-flex align-items-center text-decoration-none" href="javascript:void(0)" onclick="showPage('produk')">
                <div class="logo-box me-2">
                    <i class="fas fa-paw"></i>
                </div>
                <span class="brand-name">PETSHOP <span class="text-cyan">PRO</span></span>
            </a>

            <!-- Menu Navigation (Menuju ke koordinat section masing-masing) -->
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav main-nav-list">
                    <li class="nav-item">
                        <!-- HOME menuju ke section produk_utama (ID: produk) -->
                        <a class="nav-link nav-active" id="link-produk" href="javascript:void(0)" onclick="showPage('produk')">HOME</a>
                    </li>
                    <li class="nav-item">
                        <!-- EDUKASI menuju ke section edukasi_utama (ID: layanan) -->
                        <a class="nav-link" id="link-layanan" href="javascript:void(0)" onclick="showPage('layanan')">EDUKASI</a>
                    </li>
                   
                    <li class="nav-item">
                        <!-- TENTANG KAMI menuju ke section tentang_utama (ID: tentang) -->
                        <a class="nav-link" id="link-tentang" href="javascript:void(0)" onclick="showPage('tentang')">TENTANG KAMI</a>
                    </li>
                </ul>
            </div>

            <!-- Action Icons -->
            <div class="nav-actions d-flex align-items-center gap-3">
                <button class="icon-btn position-relative" onclick="handleWishlistClick()" title="Wishlist">
                    <i class="fas fa-heart"></i>
                    <span class="badge-custom wishlist-count" id="wishlist-badge">0</span>
                </button>
                
                <button class="icon-btn position-relative" onclick="handleCartClick()" title="Keranjang Belanja">
                    <i class="fas fa-shopping-basket"></i>
                    <span class="badge-custom bg-cyan" id="cart-nav-badge">0</span>
                </button>

                <div class="profile-wrapper">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <a href="profile.php" class="user-avatar-btn" title="Lihat Profil">
                            <i class="fas fa-user-circle" style="font-size: 2rem; color: var(--cyan-glow);"></i>
                        </a>
                    <?php else: ?>
                        <a href="../Auth/login.php" class="login-glow-btn" title="Login ke Sistem">
                            <i class="fas fa-user-astronaut"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- KUMPULAN SECTION LANDING PAGE (Semua tampil berurutan ke bawah secara vertikal) -->
    
    <!-- 1. HOME memanggil produk_utama.php -->
    <div id="page-produk" class="page-content">
        <?php include 'produk_utama.php'; ?>
    </div>

    <!-- 2. EDUKASI memanggil edukasi_utama.php -->
    <div id="page-layanan" class="page-content">
        <?php include 'edukasi_utama.php'; ?>
    </div>

    <!-- 4. TENTANG KAMI memanggil tentang_utama.php -->
    <div id="page-tentang" class="page-content">
        <?php include 'tentang_utama.php'; ?>
    </div>

    <!-- MODAL WISHLIST & LOGIN (Global) -->
    <?php include 'modals_utama.php'; ?>

    <!-- Scripts Utama -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    
    <script>
        // Ambil status login secara aman menggunakan JSON Encode bawaan PHP
        const isLoggedIn = <?php echo json_encode(isset($_SESSION['user_id'])); ?>;

        // Load SweetAlert2 secara aman jika belum terunduh pada dokumen utama
        if (typeof Swal === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }

        AOS.init({ duration: 800 });

        /**
         * FUNGSI SMOOTH SCROLL (Meluncur ke Section Tanpa Reload)
         */
        function showPage(pageId) {
            const targetSection = document.getElementById('page-' + pageId);
            if (targetSection) {
                // Meluncur ke section terpilih secara vertikal
                targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        /**
         * DETEKSI SCROLL OTOMATIS (Intersection Observer)
         * Berfungsi memperbarui tanda garis aktif di navbar saat pengguna menggulir halaman ke bawah secara manual
         */
        const observerOptions = {
            root: null,
            rootMargin: '-100px 0px -60% 0px', // Memicu pergantian status navigasi sebelum konten mencapai area atas layar
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id.replace('page-', '');
                    
                    // Hilangkan tanda aktif dari semua tombol menu
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('nav-active');
                    });
                    
                    // Tambahkan tanda aktif ke menu yang sedang terlihat di layar
                    const activeLink = document.getElementById('link-' + id);
                    if (activeLink) {
                        activeLink.classList.add('nav-active');
                    }
                }
            });
        }, observerOptions);

        // Pasangkan observer ke seluruh element page-content
        document.querySelectorAll('.page-content').forEach(section => {
            observer.observe(section);
        });

        /**
         * Memunculkan SweetAlert Premium kustom bertema gelap
         */
        function showLoginAlert() {
            Swal.fire({
                title: 'Akses Terbatas!',
                html: '<div style="font-size: 1.5rem; margin-bottom: 20px;"><i class="fas fa-lock" style="color: #0dcaf0; font-size: 3.5rem; filter: drop-shadow(0 0 10px rgba(13,202,240,0.4));"></i></div><p style="color: #86868b; font-size: 0.95rem; line-height: 1.6;">Sabar bang, lu harus login dulu buat bisa belanja atau masukin barang ke keranjang.</p>',
                background: '#111418',
                color: '#ffffff',
                showCancelButton: true,
                confirmButtonText: 'GAS LOGIN',
                cancelButtonText: 'NANTI SAJA',
                customClass: {
                    popup: 'premium-swal-popup',
                    confirmButton: 'premium-swal-confirm',
                    cancelButton: 'premium-swal-cancel'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../Auth/login.php"; 
                }
            });
        }

        // Logic Keranjang Belanja
        function handleCartClick() {
            if (!isLoggedIn) {
                showLoginAlert();
            } else {
                window.location.href = "keranjang.php"; 
            }
        }

        // Logic Wishlist
        function handleWishlistClick() {
            if (!isLoggedIn) {
                showLoginAlert();
            } else {
                let wishModal = new bootstrap.Modal(document.getElementById('wishlistModal'));
                wishModal.show();
            }
        }

        // Logic Wishlist Global
        let wishlist = JSON.parse(localStorage.getItem('petWishlist')) || [];
        function updateWishlistUI() {
            const count = document.querySelector('.wishlist-count');
            if(count) count.innerText = wishlist.length;
            const container = document.getElementById('wishlistItems');
            if(!container) return;
            if(wishlist.length === 0) {
                container.innerHTML = '<p class="text-center opacity-50">Wishlist kosong.</p>';
            } else {
                container.innerHTML = wishlist.map(i => `
                    <div class="d-flex align-items-center justify-content-between mb-3 bg-dark p-2 rounded-4">
                        <div class="d-flex align-items-center gap-3">
                            <img src="${i.img}" style="width:50px; height:50px; object-fit:cover; border-radius:10px;">
                            <div><h6 class="m-0">${i.name}</h6><small class="text-info">Rp ${i.price}</small></div>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromWishlist('${i.name}')">×</button>
                    </div>
                `).join('');
            }
        }
        function removeFromWishlist(name) {
            wishlist = wishlist.filter(i => i.name !== name);
            localStorage.setItem('petWishlist', JSON.stringify(wishlist));
            updateWishlistUI();
        }
        updateWishlistUI();
    </script>
</body>
</html>