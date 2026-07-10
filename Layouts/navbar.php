<?php
ob_start(); // Mencegah error 'headers already sent' pada seluruh halaman yang menggunakan navbar ini

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// 1. BLOK KOMPATIBILITAS SESI (Penyelarasan Peran Karyawan & Pelanggan)
// =========================================================================
$role = $_SESSION['role'] ?? '';

// Normalisasikan semua sub-role pekerja (Kasir, Groomer, Dokter, Staff) ke kelompok Karyawan
$employee_roles = ['Staff', 'Karyawan', 'Kasir', 'Groomer', 'Dokter'];
if (in_array($role, $employee_roles)) { 
    $role = 'Karyawan'; 
}

if (($role == 'Pelanggan' || $role == 'Customer') && isset($_SESSION['user_id'])) {
    // Otomatis menyelaraskan user_id ke id_pelanggan agar file di folder customer/ tidak error
    $_SESSION['id_pelanggan'] = $_SESSION['user_id'];
}

// =========================================================================
// 2. DETEKSI ROOT PATH SECARA DINAMIS (DIPERBAIKI)
// =========================================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];

// Mendeteksi nama subfolder proyek secara otomatis agar link tidak broken
$script_name = $_SERVER['SCRIPT_NAME'];
$project_path = '/';
$subfolders = ['/dashboard/', '/Master/', '/Transaksi/', '/customer/', '/Auth/'];
foreach ($subfolders as $folder) {
    $pos = strpos($script_name, $folder);
    if ($pos !== false) {
        $project_path = substr($script_name, 0, $pos + 1);
        break;
    }
}
$root = $protocol . $domainName . $project_path; 

// Logika Deteksi Halaman Aktif
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page_name, $current_page) {
    return ($page_name == $current_page) ? 'active-link' : '';
}

function isExpanded($pages_array, $current_page) {
    return in_array($current_page, $pages_array) ? 'show' : '';
}
?>

<style>
    :root { 
        --sidebar-bg: #111111; 
        --sidebar-hover: #1e1e1e; 
        --accent-color: #0dcaf0; 
        --transition-speed: 0.3s;
    }

    body { padding-left: 260px; background-color: #f4f7f6; }

    /* SIDEBAR STYLE */
    .sidebar {
        width: 260px; height: 100vh; position: fixed; left: 0; top: 0;
        background-color: var(--sidebar-bg); color: #ffffff;
        display: flex; flex-direction: column; z-index: 1100; padding: 20px 15px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.3);
    }

    .sidebar-brand { 
        padding: 10px 15px 25px; text-decoration: none; color: white; 
        display: flex; align-items: center; gap: 10px;
    }
    .sidebar-brand i { color: var(--accent-color); font-size: 24px; }

    .user-box {
        background: rgba(255,255,255,0.05); border-radius: 12px; padding: 12px;
        margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1);
        display: flex; align-items: center; gap: 10px;
    }

    .nav-list { list-style: none; padding: 0; flex-grow: 1; overflow-y: auto; }
    
    .menu-btn {
        display: flex; align-items: center; padding: 12px 15px; color: #b3b3b3;
        text-decoration: none; border-radius: 10px; width: 100%; border: none;
        background: transparent; cursor: pointer; font-size: 0.9rem; 
        transition: all var(--transition-speed); margin-bottom: 4px;
        text-align: left;
    }

    .active-link {
        background: var(--sidebar-hover) !important;
        color: #fff !important;
        box-shadow: -4px 0 0 var(--accent-color);
        font-weight: 600;
    }

    .menu-btn:hover { 
        background: var(--sidebar-hover); 
        color: #fff; 
        transform: translateX(5px);
    }

    .menu-btn i.main-icon { transition: 0.3s; width: 20px; text-align: center; margin-right: 12px; }
    .menu-btn:hover i.main-icon, .active-link i.main-icon { color: var(--accent-color); transform: scale(1.1); }
    .menu-btn i.arrow-icon { margin-left: auto; font-size: 0.7rem; transition: 0.3s; }
    .menu-btn:not(.collapsed) i.arrow-icon { transform: rotate(90deg); color: var(--accent-color); }

    /* Sub Menu Style */
    .sub-menu { list-style: none; padding: 5px 0 5px 20px; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 10px; }
    .sub-link { 
        display: block; padding: 8px 12px; color: #b3b3b3; text-decoration: none; 
        font-size: 0.85rem; transition: 0.3s; border-radius: 6px;
    }
    .sub-link:hover, .sub-link.active-link { color: var(--accent-color); background: rgba(13, 202, 240, 0.05); }

    .collapse { transition: height 0.3s ease-out !important; }

    .sidebar-footer { padding-top: 15px; border-top: 1px solid #222; margin-top: auto; }
    .btn-logout {
        width: 100%; background: rgba(220, 53, 69, 0.1); color: #ff4d4d;
        padding: 12px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
        gap: 10px; text-decoration: none; font-weight: 600; transition: 0.3s;
    }
    .btn-logout:hover { background: #dc3545; color: white; box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3); }

    .nav-list::-webkit-scrollbar { width: 4px; }
    .nav-list::-webkit-scrollbar-thumb { background: #333; }

    /* =========================================================================
       3. ENGINE PENGATUR HAK AKSES VISUAL (ROLE KARYAWAN)
       ========================================================================= */
    <?php if ($role == 'Karyawan') : ?>
        
        /* --- A. Halaman Kategori Barang (kategori_read.php) -> PURE READ-ONLY --- */
        <?php if ($current_page == 'kategori_read.php') : ?>
            a[href*="create"], a[href*="tambah"], button[data-bs-target], .btn-primary, button.btn-primary, .btn-purple, button:contains("Tambah") {
                display: none !important;
            }
            table th:last-child, table td:last-child {
                display: none !important;
            }
        <?php endif; ?>

        /* --- B. Halaman Data Pelanggan (pelanggan_read.php) -> CRU (TANPA DELETE) --- */
        <?php if ($current_page == 'pelanggan_read.php') : ?>
            a[href*="delete"], a[href*="hapus"], button[class*="danger"], .btn-danger, i.fa-trash, i.fa-trash-alt {
                display: none !important;
            }
        <?php endif; ?>

        /* --- C. Halaman Katalog Barang (barang_tampil.php) -> PURE READ-ONLY --- */
        <?php if ($current_page == 'barang_tampil.php') : ?>
            a[href*="tambah"], a[href*="create"], button[class*="primary"], .btn-primary {
                display: none !important;
            }
            table th:last-child, table td:last-child {
                display: none !important;
            }
        <?php endif; ?>

    <?php endif; ?>
</style>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-paw"></i>
        <span class="fw-bold">PETSHOP <span class="text-info">PRO</span></span>
    </div>

    <div class="user-box">
        <i class="fas fa-user-circle fs-3 text-info"></i>
        <div>
            <span style="font-size:0.85rem; font-weight:600; display:block;"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Guest'); ?></span>
            <span style="font-size: 0.65rem; color: var(--accent-color); font-weight: 800; text-transform: uppercase;"><?php echo htmlspecialchars($role); ?></span>
        </div>
    </div>

    <div class="nav-list">
        <!-- BERANDA (Semua Role) -->
        <a href="<?php echo $root; ?>dashboard/index.php" class="menu-btn <?php echo isActive('index.php', $current_page); ?>">
            <i class="fas fa-home main-icon"></i> Beranda
        </a>

        <!-- ==================== MENU ROLE: ADMIN ==================== -->
        <?php if ($role == 'Admin') : 
            $master_pages = ['karyawan_tampil.php', 'pelanggan_read.php', 'supplier_read.php', 'barang_tampil.php', 'kategori_read.php', 'layanan_read.php'];
            $bisnis_pages = ['penjualan_read.php', 'booking_read.php', 'stok_masuk_read.php'];
        ?>
            <!-- Pusat Data Admin -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $master_pages) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropMaster">
                    <i class="fas fa-database main-icon"></i> Pusat Data <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($master_pages, $current_page); ?>" id="dropMaster">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>Master/karyawan/karyawan_tampil.php" class="sub-link <?php echo isActive('karyawan_tampil.php', $current_page); ?>">Staff Karyawan</a></li>
                        <li><a href="<?php echo $root; ?>Master/pelanggan/pelanggan_read.php" class="sub-link <?php echo isActive('pelanggan_read.php', $current_page); ?>">Member Pelanggan</a></li>
                        <li><a href="<?php echo $root; ?>Master/supplier/supplier_read.php" class="sub-link <?php echo isActive('supplier_read.php', $current_page); ?>">Mitra Supplier</a></li>
                        <li><a href="<?php echo $root; ?>Master/barang/barang_tampil.php" class="sub-link <?php echo isActive('barang_tampil.php', $current_page); ?>">Katalog Barang</a></li>
                        <li><a href="<?php echo $root; ?>Master/kategori/kategori_read.php" class="sub-link <?php echo isActive('kategori_read.php', $current_page); ?>">Kategori</a></li>
                        <li><a href="<?php echo $root; ?>Master/layanan/layanan_read.php" class="sub-link <?php echo isActive('layanan_read.php', $current_page); ?>">Daftar Layanan</a></li>
                    </ul>
                </div>
            </div>

            <!-- Aktivitas Bisnis Admin -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $bisnis_pages) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropBisnis">
                    <i class="fas fa-exchange-alt main-icon"></i> Aktivitas Bisnis <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($bisnis_pages, $current_page); ?>" id="dropBisnis">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>Transaksi/penjualan_read.php" class="sub-link <?php echo isActive('penjualan_read.php', $current_page); ?>">Transaksi Kasir</a></li>
                        <li><a href="<?php echo $root; ?>Transaksi/booking_read.php" class="sub-link <?php echo isActive('booking_read.php', $current_page); ?>">Booking Grooming</a></li>
                        <li><a href="<?php echo $root; ?>Transaksi/stok_masuk_read.php" class="sub-link <?php echo isActive('stok_masuk_read.php', $current_page); ?>">Stok Masuk</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==================== MENU ROLE: KARYAWAN / STAFF ==================== -->
        <?php if ($role == 'Karyawan') : 
            $staff_master = ['barang_tampil.php', 'layanan_read.php'];
            $staff_transaksi = ['penjualan_read.php', 'booking_read.php'];
        ?>
            <!-- Pusat Data Kerja Staff -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $staff_master) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropStaffMaster">
                    <i class="fas fa-folder-open main-icon"></i> Data Katalog <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($staff_master, $current_page); ?>" id="dropStaffMaster">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>Master/barang/barang_tampil.php" class="sub-link <?php echo isActive('barang_tampil.php', $current_page); ?>">Katalog Barang</a></li>
                        <li><a href="<?php echo $root; ?>Master/layanan/layanan_read.php" class="sub-link <?php echo isActive('layanan_read.php', $current_page); ?>">Daftar Layanan</a></li>
                    </ul>
                </div>
            </div>

            <!-- Transaksi Kasir Staff -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $staff_transaksi) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropStaffTransaksi">
                    <i class="fas fa-cash-register main-icon"></i> Pelayanan Toko <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($staff_transaksi, $current_page); ?>" id="dropStaffTransaksi">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>Transaksi/penjualan_read.php" class="sub-link <?php echo isActive('penjualan_read.php', $current_page); ?>">Kasir Penjualan</a></li>
                        <li><a href="<?php echo $root; ?>Transaksi/booking_read.php" class="sub-link <?php echo isActive('booking_read.php', $current_page); ?>">Booking Grooming</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==================== MENU ROLE: PELANGGAN ==================== -->
        <?php if ($role == 'Customer' || $role == 'Pelanggan') : 
            $pelanggan_pages = ['keranjang.php', 'booking_saya.php', 'riwayat_belanja.php'];
        ?>
            <!-- Belanja Anabul -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $pelanggan_pages) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropPelanggan">
                    <i class="fas fa-shopping-basket main-icon"></i> Menu Saya <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($pelanggan_pages, $current_page); ?>" id="dropPelanggan">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>customer/keranjang.php" class="sub-link <?php echo isActive('keranjang.php', $current_page); ?>">Keranjang Belanja</a></li>
                        <li><a href="<?php echo $root; ?>customer/booking_saya.php" class="sub-link <?php echo isActive('booking_saya.php', $current_page); ?>">Booking Grooming</a></li>
                        <li><a href="<?php echo $root; ?>customer/riwayat_belanja.php" class="sub-link <?php echo isActive('riwayat_belanja.php', $current_page); ?>">Riwayat Belanja</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- ==================== MENU ROLE: SUPPLIER ==================== -->
        <?php if ($role == 'Supplier') : 
            $supplier_pages = ['barang_tampil.php', 'stok_masuk_read.php'];
        ?>
            <!-- Aktivitas Supplier -->
            <div class="menu-wrapper">
                <button class="menu-btn <?php echo in_array($current_page, $supplier_pages) ? '' : 'collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#dropSupplier">
                    <i class="fas fa-truck-moving main-icon"></i> Kemitraan <i class="fas fa-chevron-right arrow-icon"></i>
                </button>
                <div class="collapse <?php echo isExpanded($supplier_pages, $current_page); ?>" id="dropSupplier">
                    <ul class="sub-menu">
                        <li><a href="<?php echo $root; ?>Master/barang/barang_tampil.php" class="sub-link <?php echo isActive('barang_tampil.php', $current_page); ?>">Katalog Produk</a></li>
                        <li><a href="<?php echo $root; ?>Transaksi/stok_masuk_read.php" class="sub-link <?php echo isActive('stok_masuk_read.php', $current_page); ?>">Setoran Stok</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER SIDEBAR -->
    <div class="sidebar-footer">
        <a href="<?php echo $root; ?>Auth/logout.php" class="btn-logout">
            <i class="fas fa-power-off"></i> Keluar
        </a>
    </div>
</div>