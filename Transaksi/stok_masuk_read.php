
<?php
session_start();
include_once '../config/koneksi.php';

// Proteksi Akses
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Ambil data role pengguna untuk proteksi hak akses
$user_role = strtoupper($_SESSION['role']);

// Fungsi helper untuk inisial nama supplier secara aman
if (!function_exists('getInitialsSupplier')) {
    function getInitialsSupplier($name) {
        $words = explode(" ", trim($name));
        $initials = "";
        if (isset($words[0])) {
            $initials .= substr($words[0], 0, 1);
        }
        if (count($words) > 1 && isset($words[count($words) - 1])) {
            $initials .= substr($words[count($words) - 1], 0, 1);
        }
        return strtoupper($initials);
    }
}

// Definisikan variable filter agar aman dari undefined warning saat pertama kali load
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --- KONFIGURASI PAGINATION (10 Data Per Halaman) ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- HANDLER AJAX DETAIL STOK MASUK ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // 1. Ambil Data Header Stok_Masuk
    $sql = "SELECT SM.*, S.Nama_Supplier, S.No_Telepon AS Telp_Supplier,
                   K.Nama_Karyawan AS Nama_Penerima
            FROM Stok_Masuk SM
            LEFT JOIN Supplier S ON SM.ID_Supplier = S.ID_Supplier
            LEFT JOIN Karyawan K ON SM.ID_Karyawan = K.ID_Karyawan
            WHERE SM.ID_Stok = ?";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        // Format Tanggal untuk JSON
        if ($row['Tanggal_Masuk'] instanceof DateTime) {
            $row['Tanggal_Masuk_Raw'] = $row['Tanggal_Masuk']->format('Y-m-d\TH:i');
            $row['Tanggal_Masuk'] = $row['Tanggal_Masuk']->format('d M Y, H:i');
        }
        if ($row['Tanggal_Diterima'] instanceof DateTime) {
            $row['Tanggal_Diterima_Raw'] = $row['Tanggal_Diterima']->format('Y-m-d\TH:i');
            $row['Tanggal_Diterima'] = $row['Tanggal_Diterima']->format('d M Y, H:i');
        } else {
            $row['Tanggal_Diterima_Raw'] = '';
            $row['Tanggal_Diterima'] = '-';
        }
        
        // Format mata uang rupiah
        $row['Subtotal_Stok_Format'] = 'Rp ' . number_format($row['Subtotal_Stok'], 0, ',', '.');
        $row['Pajak_Stok_Format'] = 'Rp ' . number_format($row['Pajak_Stok'], 0, ',', '.');
        $row['Total_Harga_Format'] = 'Rp ' . number_format($row['Total_Harga'], 0, ',', '.');

        // 2. Ambil Rincian Item Barang yang Masuk (Detail_Stok_Masuk terintegrasi dengan UDF fn_StokBarang)
        $sql_items = "SELECT DSM.*, B.Nama_Barang 
                      FROM Detail_Stok_Masuk DSM 
                      JOIN dbo.fn_StokBarang(NULL) B ON DSM.ID_Barang = B.ID_Barang 
                      WHERE DSM.ID_Stok = ?";
        $query_items = sqlsrv_query($conn, $sql_items, array($id));
        $items = [];
        
        if ($query_items !== false) {
            while ($item = sqlsrv_fetch_array($query_items, SQLSRV_FETCH_ASSOC)) {
                $item['Harga_Beli_Format'] = 'Rp ' . number_format($item['Harga_Beli'], 0, ',', '.');
                $item['Subtotal_Format'] = 'Rp ' . number_format($item['Subtotal'], 0, ',', '.');
                if ($item['Tanggal_Kadaluarsa'] instanceof DateTime) {
                    $item['Tanggal_Kadaluarsa_Raw'] = $item['Tanggal_Kadaluarsa']->format('Y-m-d');
                    $item['Tanggal_Kadaluarsa'] = $item['Tanggal_Kadaluarsa']->format('d M Y');
                } else {
                    $item['Tanggal_Kadaluarsa_Raw'] = '';
                    $item['Tanggal_Kadaluarsa'] = '-';
                }
                $items[] = $item;
            }
        }
        $row['items'] = $items;

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Faktur stok masuk tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX LIVE SEARCH & FILTER (HTML Output) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $params = [];
    $sql = "SELECT SM.*, S.Nama_Supplier, K.Nama_Karyawan AS Nama_Penerima
            FROM Stok_Masuk SM
            LEFT JOIN Supplier S ON SM.ID_Supplier = S.ID_Supplier
            LEFT JOIN Karyawan K ON SM.ID_Karyawan = K.ID_Karyawan
            WHERE 1=1";
    
    if ($search != '') {
        $sql .= " AND (S.Nama_Supplier LIKE ? OR SM.No_Faktur LIKE ? OR K.Nama_Karyawan LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    
    if ($status_filter != '') {
        $sql .= " AND SM.Status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY SM.Tanggal_Masuk DESC";
    $query = sqlsrv_query($conn, $sql, $params);
    
    if ($query === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }

    $no = 1;
    if (sqlsrv_has_rows($query) === false) {
        echo '<tr>
                <td colspan="7" class="text-center py-5">
                    <div class="empty-state">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-boxes-packing fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Riwayat stok masuk tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter status lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
        $status = $row['Status'];
        $badge = ($status == 'Diterima') ? 'pill-selesai' : 'pill-batal';
        
        $tgl = ($row['Tanggal_Masuk'] instanceof DateTime) ? $row['Tanggal_Masuk']->format('d M Y, H:i') : '-';
        ?>
        <tr class="align-middle booking-row animate-fade-up" id="row-<?= $row['ID_Stok'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['No_Faktur']) ?></div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-container me-3">
                        <div class="plg-avatar shadow-sm avatar-amber">
                            <span class="avatar-initial"><?= getInitialsSupplier($row['Nama_Supplier'] ?: 'Supplier') ?></span>
                        </div>
                    </div>
                    <div class="text-truncate">
                        <div class="fw-bold text-dark text-truncate fs-6"><?= htmlspecialchars($row['Nama_Supplier'] ?: 'Supplier Umum') ?></div>
                        <div class="text-muted small text-truncate">Petugas: <?= htmlspecialchars($row['Nama_Penerima'] ?: '-') ?></div>
                    </div>
                </div>
            </td>
            <td>
                <div class="small fw-bold text-dark mb-1">
                    <i class="far fa-calendar-alt me-1 text-muted"></i> <?= $tgl ?>
                </div>
            </td>
            <td class="text-end fw-bold text-dark">
                Rp <?= number_format($row['Total_Harga'], 0, ',', '.') ?>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Stok'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                </span>
            </td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    <!-- TOMBOL LIHAT DETAIL (MATA BIRU) -->
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Stok'] ?>"
                            title="Lihat Detail Stok Masuk">
                        <i class="fas fa-eye"></i>
                    </button>

                    <!-- TOMBOL STATUS / KONFIRMASI (TENGAH) -->
                    <?php if ($status == 'Pending' && $user_role === 'SUPPLIER'): ?>
                        <a href="javascript:void(0)" 
                           class="btn-action btn-status-selesai toggle-status-btn" 
                           data-id="<?= $row['ID_Stok'] ?>"
                           data-target="Diterima"
                           title="Konfirmasi Terima Barang">
                            <i class="fas fa-check-circle"></i>
                        </a>
                    <?php else: ?>
                        <!-- LOCK / DISABLED ICON jika sudah Diterima atau bukan Supplier -->
                        <button class="btn-action btn-status-locked" disabled title="Aksi Terkunci">
                            <i class="fas fa-ban"></i>
                        </button>
                    <?php endif; ?>

                    <!-- TOMBOL PRINT NOTA (PRINTER HIJAU) -->
                    <a href="stok_masuk_print.php?id=<?= $row['ID_Stok'] ?>" target="_blank" class="btn-action btn-cetak" title="Cetak Surat Jalan / Faktur">
                        <i class="fas fa-print"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php
    }
    exit;
}

// --- PERHITUNGAN TOTAL RECORD UNTUK PAGINATION ---
$sql_count = "SELECT COUNT(*) as total FROM Stok_Masuk SM LEFT JOIN Supplier S ON SM.ID_Supplier = S.ID_Supplier WHERE 1=1";
$params_count = [];
if ($search != '') {
    $sql_count .= " AND (S.Nama_Supplier LIKE ? OR SM.No_Faktur LIKE ?)";
    $params_count[] = "%$search%"; $params_count[] = "%$search%";
}
if ($status_filter != '') {
    $sql_count .= " AND SM.Status = ?";
    $params_count[] = $status_filter;
}
$query_count = sqlsrv_query($conn, $sql_count, $params_count);
$total_records = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// --- DATA STATISTIK UTAMA (DIHITUNG REALTIME) ---
$sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk");
$total_stok = ($sql_total) ? sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_pending = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk WHERE Status = 'Pending'");
$pending_stok = ($sql_pending) ? sqlsrv_fetch_array($sql_pending, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_done = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Stok_Masuk WHERE Status = 'Diterima'");
$diterima_stok = ($sql_done) ? sqlsrv_fetch_array($sql_done, SQLSRV_FETCH_ASSOC)['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok Masuk | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 Library CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #3b82f6; /* Blue-600 */
            --primary-light: #60a5fa;
            --primary-glow: rgba(59, 130, 246, 0.15);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --danger: #ef4444;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.6);
            --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
            --card-shadow-hover: 0 20px 35px -10px rgba(59, 130, 246, 0.12), 0 1px 5px rgba(0, 0, 0, 0.03);
            --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
            --gradient-blue: linear-gradient(135deg, #2563eb, #1d4ed8);
            --gradient-orange: linear-gradient(135deg, #ea580c, #c2410c);
            --gradient-green: linear-gradient(135deg, #059669, #047857);
            --gradient-red: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        body { 
            background: #f4f6fa; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            overflow-x: hidden;
            color: var(--slate-800);
        }

        .animate-fade-up { animation: fadeInUp 0.8s var(--ease-out-expo) both; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pop-effect-stat { animation: popBounce 0.4s var(--ease-out-expo); }
        @keyframes popBounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* SEARCH BAR */
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 280px;
            transition: all 0.3s var(--ease-out-expo);
        }
        .search-wrapper .search-icon {
            position: absolute;
            left: 15px;
            color: #94a3b8;
            pointer-events: none;
            z-index: 5;
        }
        .search-wrapper .input-search {
            padding-left: 42px !important;
            padding-right: 15px;
            border-radius: 14px;
            border: 1px solid var(--slate-200);
            height: 45px;
            width: 100%;
            background: #ffffff;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.3s var(--ease-out-expo);
        }
        .search-wrapper .input-search:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
            width: 320px;
            outline: none;
        }

        /* STAT CARDS */
        .card-stat { 
            border-radius: 20px; 
            border: none; 
            transition: all 0.4s var(--ease-out-expo); 
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            padding: 24px;
            color: #ffffff;
        }
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        .card-stat .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-stat .stat-value {
            font-weight: 800;
            font-size: 1.8rem;
            line-height: 1.2;
        }
        .stat-icon-box {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            transition: transform 0.3s ease;
        }
        .card-stat:hover .stat-icon-box {
            transform: scale(1.1) rotate(5deg);
        }

        /* GLASS CARD CONTAINER */
        .glass-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 24px; 
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
        }

        /* CHIPS STATUS FILTERS */
        .filter-container {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        .filter-chip {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid var(--slate-200);
            background: #ffffff;
            color: var(--slate-700);
            cursor: pointer;
            transition: all 0.3s var(--ease-out-expo);
            white-space: nowrap;
            text-decoration: none;
        }
        .filter-chip:hover {
            border-color: var(--primary-light);
            color: var(--slate-800);
        }
        .filter-chip.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* TABLE STYLE */
        .table thead th {
            background: var(--slate-100);
            border: none;
            padding: 1.1rem 1.2rem;
            color: var(--slate-700);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.8px;
        }
        .table tbody tr {
            transition: all 0.3s var(--ease-out-expo);
            border-bottom: 1px solid var(--slate-100);
        }
        .table tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.015);
        }

        /* AVATAR SYSTEM */
        .avatar-container {
            position: relative;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
        }
        .plg-avatar {
            width: 100%; height: 100%; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.05rem;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .avatar-amber { 
            background: linear-gradient(135deg, #eff6ff, #dbeafe); 
            color: var(--primary); 
        }

        /* STATUS PILL BADGES */
        .status-pill {
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.78rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 110px; 
            height: 32px;
            transition: all 0.3s var(--ease-out-expo);
        }
        .pill-selesai { background: rgba(16, 185, 129, 0.1) !important; color: #065f46 !important; }
        .pill-batal { background: rgba(239, 68, 68, 0.08) !important; color: #b91c1c !important; }

        /* ACTION BUTTONS */
        .action-gap { gap: 6px; }
        .btn-action { 
            width: 36px; height: 36px; 
            border-radius: 10px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.25s var(--ease-out-expo);
            border: none;
            text-decoration: none;
        }
        .btn-action i { font-size: 1rem; }
        
        .btn-lihat { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .btn-cetak { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .btn-status-selesai { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .btn-status-locked { background: rgba(148, 163, 184, 0.1); color: #94a3b8; cursor: not-allowed; }
        
        .btn-action:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-lihat:hover { background: #3b82f6; color: #ffffff; }
        .btn-cetak:hover { background: #10b981; color: #ffffff; }
        .btn-status-selesai:hover { background: #10b981; color: #ffffff; }

        /* PAGINATION */
        .pagination .page-link {
            border: 1px solid var(--slate-200);
            color: var(--slate-700);
            background: #ffffff;
            font-weight: 600;
            padding: 8px 14px;
            font-size: 0.85rem;
        }
        .pagination .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 10px var(--primary-glow);
        }

        /* MODAL DETAIL CUSTOM */
        #detailStokModal {
            z-index: 1060 !important;
            backdrop-filter: blur(8px);
            background-color: rgba(15, 23, 42, 0.4);
        }
        .modal-content-custom { 
            background: #ffffff; 
            border: none; 
            border-radius: 1.5rem; 
            overflow: hidden; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header-centered {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 2.5rem 2rem;
            color: white;
            text-align: center;
            position: relative;
        }

        .toast-container-modern {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1090;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast-modern {
            background: #ffffff;
            border-left: 4px solid var(--primary);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .toast-modern.show { transform: translateX(0); }
        .toast-modern.success { border-left-color: var(--success); }
        .toast-modern.warning { border-left-color: var(--warning); }
        .toast-modern.danger { border-left-color: var(--danger); }

        @media (min-width: 992px) {
            #detailStokModal, #modalTambahStok {
                padding-left: 260px !important;
            }
            .swal2-container {
                padding-left: 260px !important;
            }
        }
    </style>
</head>
<body>
    <?php include_once '../layouts/navbar.php'; ?>

    <div class="toast-container-modern" id="toastContainer"></div>

    <div class="container-fluid px-4 py-5">
        
        <!-- HEADER SECTION -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-fade-up">
            <div>
                <h2 class="fw-bold text-dark mb-1">Pengadaan Stok Masuk Gudang 🚛</h2>
                <p class="text-muted mb-0">Kelola dan pantau seluruh pasokan logistik barang masuk dari supplier.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nomor faktur atau supplier..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <!-- TOMBOL TAMBAH DATA (CREATE POP-UP) -->
                <button type="button" class="btn btn-primary fw-bold rounded-pill px-4" style="background: var(--primary); border:none; height: 45px; display:inline-flex; align-items:center; gap:8px;" data-bs-toggle="modal" data-bs-target="#modalTambahStok">
                    <i class="fas fa-plus-circle"></i> Tambah Stok Masuk
                </button>
            </div>
        </div>

        <!-- STATS SECTION -->
        <div class="row g-4 mb-5">
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-blue);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Total Transaksi</p>
                            <h2 class="stat-value mb-0" id="stat-total"><?= $total_stok ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-orange);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Pending (Pengecekan)</p>
                            <h2 class="stat-value mb-0" id="stat-pending"><?= $pending_stok ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-green);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Stok Diterima</p>
                            <h2 class="stat-value mb-0" id="stat-diterima"><?= $diterima_stok ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="glass-card p-4 animate-fade-up">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Nota Stok Masuk
                    <span class="badge bg-light text-primary border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="filter-container">
                        <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua</a>
                        <a href="?page=1&status_filter=Pending&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Pending') ? 'active' : '' ?>">Pending</a>
                        <a href="?page=1&status_filter=Diterima&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Diterima') ? 'active' : '' ?>">Diterima</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">No</th>
                            <th style="width: 22%;">No. Faktur</th>
                            <th style="width: 28%;">Mitra Supplier & Penerima</th>
                            <th style="width: 18%;">Tanggal Masuk</th>
                            <th class="text-end" style="width: 150px;">Total Tagihan</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="stok-tbody">
                        <?php
                        $no = 1 + $offset;
                        $params = [];
                        
                        $sql = "SELECT SM.*, S.Nama_Supplier, K.Nama_Karyawan AS Nama_Penerima
                                FROM Stok_Masuk SM
                                LEFT JOIN Supplier S ON SM.ID_Supplier = S.ID_Supplier
                                LEFT JOIN Karyawan K ON SM.ID_Karyawan = K.ID_Karyawan
                                WHERE 1=1";
                                
                        if ($search != '') {
                            $sql .= " AND (S.Nama_Supplier LIKE ? OR SM.No_Faktur LIKE ? OR K.Nama_Karyawan LIKE ?)";
                            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
                        }
                        if ($status_filter != '') {
                            $sql .= " AND SM.Status = ?";
                            $params[] = $status_filter;
                        }
                        $sql .= " ORDER BY SM.Tanggal_Masuk DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                        
                        $params[] = $offset;
                        $params[] = $limit;
                        
                        $query = sqlsrv_query($conn, $sql, $params);

                        if ($query === false) {
                            die(print_r(sqlsrv_errors(), true));
                        }

                        if (sqlsrv_has_rows($query) === false) {
                            echo '<tr><td colspan="7" class="text-center py-5 text-muted">Tidak ada data stok masuk pada halaman ini...</td></tr>';
                        } else {
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
                                $status = $row['Status'];
                                $badge = ($status == 'Diterima') ? 'pill-selesai' : 'pill-batal';
                                
                                $tgl = ($row['Tanggal_Masuk'] instanceof DateTime) ? $row['Tanggal_Masuk']->format('d M Y, H:i') : '-';
                            ?>
                            <tr class="align-middle booking-row" id="row-<?= $row['ID_Stok'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['No_Faktur']) ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-container me-3">
                                            <div class="plg-avatar shadow-sm avatar-amber">
                                                <span class="avatar-initial"><?= getInitialsSupplier($row['Nama_Supplier'] ?: 'Supplier') ?></span>
                                            </div>
                                        </div>
                                        <div class="text-truncate">
                                            <div class="fw-bold text-dark text-truncate fs-6"><?= htmlspecialchars($row['Nama_Supplier'] ?: 'Supplier Umum') ?></div>
                                            <div class="text-muted small text-truncate">Petugas: <?= htmlspecialchars($row['Nama_Penerima'] ?: '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark mb-1">
                                        <i class="far fa-calendar-alt me-1 text-muted"></i> <?= $tgl ?>
                                    </div>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format($row['Total_Harga'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Stok'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        <!-- TOMBOL LIHAT DETAIL (MATA BIRU) -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Stok'] ?>"
                                                title="Lihat Detail Stok Masuk">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOMBOL STATUS / KONFIRMASI (TENGAH) -->
                                        <?php if ($status == 'Pending' && $user_role === 'SUPPLIER'): ?>
                                            <a href="javascript:void(0)" 
                                               class="btn-action btn-status-selesai toggle-status-btn" 
                                               data-id="<?= $row['ID_Stok'] ?>"
                                               data-target="Diterima"
                                               title="Konfirmasi Terima Barang">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php else: ?>
                                            <!-- LOCK / DISABLED ICON jika sudah Diterima atau bukan Supplier -->
                                            <button class="btn-action btn-status-locked" disabled title="Aksi Terkunci">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- TOMBOL PRINT NOTA (PRINTER HIJAU) -->
                                        <a href="stok_masuk_print.php?id=<?= $row['ID_Stok'] ?>" target="_blank" class="btn-action btn-cetak" title="Cetak Surat Jalan / Faktur">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            }
                        } 
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION NAVIGATION -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 px-2">
                <div class="text-muted small mb-2 mb-md-0">
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> pengadaan stok masuk.
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0 gap-1">
                        <!-- Tombol Prev -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-3" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Nomor Halaman -->
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link rounded-3" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Tombol Next -->
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-3" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- MODAL DETAIL STOK MASUK (CENTERED DENGAN RINCIAN TABEL ITEM BARANG MASUK) -->
    <div class="modal fade" id="detailStokModal" tabindex="-1" aria-labelledby="detailStokModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0 animate-fade-up">
                
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div class="plg-avatar shadow-sm avatar-amber mb-3" style="width:80px; height:80px; border-radius:18px; font-size:1.8rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">
                            <span class="avatar-initial" id="detail-initial">?</span>
                        </div>
                        <h3 class="fw-bold mb-1 text-white" id="detail-supplier" style="letter-spacing:-0.5px;">Nama Supplier</h3>
                        <span class="badge bg-light text-primary fw-bold mt-1" id="detail-no-faktur" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">Faktur</span>
                        <div id="detail-status-badge" class="mt-2"></div>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start" style="max-height: 450px; overflow-y: auto;">
                    <div class="row g-4">
                        <!-- Profil Supplier & Petugas -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-building me-2"></i>Informasi Mitra & Petugas</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Mitra Supplier</td><td class="fw-bold text-dark" id="detail-supplier-nama">-</td></tr>
                                    <tr><td class="text-muted">No. Telepon</td><td class="fw-bold text-dark" id="detail-telp-s">-</td></tr>
                                    <tr><td class="text-muted">Penerima Gudang</td><td class="fw-bold text-dark" id="detail-penerima">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Detail Waktu Stok Masuk -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-calendar-check me-2"></i>Waktu & Catatan</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:45%;">Tanggal Faktur</td><td class="fw-bold text-dark" id="detail-tanggal">-</td></tr>
                                    <tr><td class="text-muted">Tanggal Diterima</td><td class="fw-bold text-success" id="detail-tanggal-terima">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- TABEL DETAIL ITEM BARANG MASUK -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-box me-2"></i>Daftar Rincian Produk Masuk Gudang</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 small" style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nama Barang / Produk</th>
                                                <th class="text-center" style="width: 80px;">Jumlah</th>
                                                <th class="text-end" style="width: 120px;">Harga Beli</th>
                                                <th class="text-center" style="width: 110px;">No. Batch</th>
                                                <th class="text-center" style="width: 130px;">Kadaluarsa</th>
                                                <th class="text-end" style="width: 130px;">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detail-items-tbody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Ringkasan Biaya Belanja -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-calculator me-2"></i>Ringkasan Nominal Pengadaan</h6>
                                <div class="row g-3 small text-center mb-2">
                                    <div class="col-4">
                                        <span class="text-muted d-block small">Subtotal Stok</span>
                                        <strong class="text-dark fs-6" id="detail-subtotal">-</strong>
                                    </div>
                                    <div class="col-4 border-start">
                                        <span class="text-muted d-block small">PPN Masukan</span>
                                        <strong class="text-warning fs-6" id="detail-pajak">-</strong>
                                    </div>
                                    <div class="col-4 border-start">
                                        <span class="text-muted d-block small">Total Tagihan Supplier</span>
                                        <strong class="text-success fs-5" id="detail-total-harga">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Transaksi -->
                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-comment-alt me-2"></i>Catatan Pengiriman / Kondisi Fisik</h6>
                                <p class="text-dark small mb-0" id="detail-catatan">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-between align-items-center">
                    <div id="modal-action-container">
                    </div>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Tutup Rincian</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT AJAX & LIVE SEARCH INTERAKTIF -->
    <script>
        const userRole = "<?= $user_role ?>";

        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('stok-tbody');
            const toastContainer = document.getElementById('toastContainer');
            
            let currentStatusFilter = '<?= htmlspecialchars($status_filter) ?>';

            // Toast Notification Handler
            function showToast(message, type = 'success') {
                const id = 'toast_' + Math.random().toString(36).substr(2, 9);
                let icon = 'fa-check-circle';
                if(type === 'warning') icon = 'fa-exclamation-circle';
                if(type === 'danger') icon = 'fa-times-circle';

                const toastHTML = `
                    <div class="toast-modern ${type}" id="${id}">
                        <i class="fas ${icon} fs-5 text-${type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'danger')}"></i>
                        <div class="flex-grow-1">
                            <p class="mb-0 fw-bold small text-dark">${message}</p>
                        </div>
                    </div>
                `;
                toastContainer.insertAdjacentHTML('beforeend', toastHTML);
                
                const toastElement = document.getElementById(id);
                setTimeout(() => { toastElement.classList.add('show'); }, 50);

                setTimeout(() => {
                    toastElement.classList.remove('show');
                    setTimeout(() => { toastElement.remove(); }, 400);
                }, 3500);
            }

            function debounce(func, delay) {
                let timer;
                return function (...args) {
                    clearTimeout(timer);
                    timer = setTimeout(() => func.apply(this, args), delay);
                };
            }

            // AJAX Live Search
            function performSearchAndFilter() {
                const queryValue = searchInput.value;
                tbody.style.opacity = '0.4';

                fetch(`stok_masuk_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        tbody.style.opacity = '1';
                        
                        const rows = tbody.querySelectorAll('.booking-row');
                        document.getElementById('table-count-badge').textContent = rows.length;
                    })
                    .catch(error => {
                        console.error('Error Search AJAX:', error);
                        tbody.style.opacity = '1';
                        showToast('Gagal memuat data logistik.', 'danger');
                    });
            }

            window.performSearchAndFilter = performSearchAndFilter;

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Stok Masuk
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`stok_masuk_read.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-supplier').textContent = d.Nama_Supplier || 'Supplier Umum';
                                document.getElementById('detail-no-faktur').textContent = d.No_Faktur || '-';
                                
                                document.getElementById('detail-supplier-nama').textContent = d.Nama_Supplier || 'Supplier Umum';
                                document.getElementById('detail-telp-s').textContent = d.Telp_Supplier || '-';
                                document.getElementById('detail-penerima').textContent = d.Nama_Penerima || '-';
                                
                                document.getElementById('detail-tanggal').textContent = d.Tanggal_Masuk || '-';
                                document.getElementById('detail-tanggal-terima').textContent = d.Tanggal_Diterima || '-';

                                document.getElementById('detail-subtotal').textContent = d.Subtotal_Stok_Format || 'Rp 0';
                                document.getElementById('detail-pajak').textContent = d.Pajak_Stok_Format || 'Rp 0';
                                document.getElementById('detail-total-harga').textContent = d.Total_Harga_Format || 'Rp 0';
                                
                                document.getElementById('detail-catatan').textContent = d.Catatan_Masuk || 'Tidak ada catatan pengiriman.';
                                document.getElementById('detail-initial').textContent = getInitialsJs(d.Nama_Supplier || 'Supplier');

                                // Render badge status di modal header
                                let statusBadgeHTML = '';
                                if (d.Status === 'Diterima') {
                                    statusBadgeHTML = `<span class="badge bg-success text-white fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-check-circle me-1"></i>Diterima</span>`;
                                } else {
                                    statusBadgeHTML = `<span class="badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-clock me-1"></i>Pending</span>`;
                                }
                                document.getElementById('detail-status-badge').innerHTML = statusBadgeHTML;

                                // Render rincian item logistik di tabel modal
                                const itemsTbody = document.getElementById('detail-items-tbody');
                                itemsTbody.innerHTML = '';
                                if (d.items && d.items.length > 0) {
                                    d.items.forEach(item => {
                                        itemsTbody.innerHTML += `
                                            <tr>
                                                <td><span class="fw-bold text-dark">${item.Nama_Barang}</span></td>
                                                <td class="text-center font-monospace fw-bold">${item.Jumlah_Masuk}</td>
                                                <td class="text-end">${item.Harga_Beli_Format}</td>
                                                <td class="text-center text-muted font-monospace small">${item.No_Batch || '-'}</td>
                                                <td class="text-center text-muted small">${item.Tanggal_Kadaluarsa || '-'}</td>
                                                <td class="text-end fw-bold text-dark">${item.Subtotal_Format}</td>
                                            </tr>
                                        `;
                                    });
                                } else {
                                    itemsTbody.innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Tidak ada rincian logistik barang masuk.</td></tr>';
                                }

                                // Render tombol aksi terima otomatis di modal footer (Hanya untuk peran SUPPLIER)
                                const actionContainer = document.getElementById('modal-action-container');
                                actionContainer.innerHTML = '';
                                if (d.Status === 'Pending') {
                                    if (userRole === 'SUPPLIER') {
                                        actionContainer.innerHTML = `
                                            <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-bold text-white modal-toggle-btn shadow-sm" data-id="${d.ID_Stok}" data-target="Diterima">
                                                <i class="fas fa-check-circle me-2"></i>Konfirmasi Terima Barang
                                            </button>
                                        `;
                                    } else {
                                        actionContainer.innerHTML = `
                                            <span class="text-muted small fw-bold"><i class="fas fa-ban me-1"></i> Aksi Terkunci</span>
                                        `;
                                    }
                                } else {
                                    actionContainer.innerHTML = `
                                        <span class="text-muted small fw-bold"><i class="fas fa-ban me-1"></i> Aksi Terkunci</span>
                                    `;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailStokModal'));
                                detailModal.show();
                            } else {
                                showToast(res.message || 'Gagal mengambil rincian data.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error detail fetch:', error);
                            showToast('Terjadi kesalahan koneksi data detail.', 'danger');
                        })
                        .finally(() => {
                            icon.className = originalClass;
                            viewBtn.disabled = false;
                        });
                }
            });

            // Helper JS untuk inisial nama instan di sisi klien
            function getInitialsJs(name) {
                if (!name) return "?";
                const words = name.trim().split(/\s+/);
                let initials = words[0].charAt(0);
                if (words.length > 1) {
                    initials += words[words.length - 1].charAt(0);
                }
                return initials.toUpperCase();
            }

            // Fungsi Pembantu Animasi Letup Angka Statistik
            function animateStatTextUpdate(elementId, newValue) {
                const element = document.getElementById(elementId);
                if (element) {
                    const currentVal = element.textContent.trim();
                    if (currentVal !== String(newValue)) {
                        element.textContent = newValue;
                        element.classList.remove('pop-effect-stat');
                        void element.offsetWidth;
                        element.classList.add('pop-effect-stat');
                        
                        setTimeout(() => { element.classList.remove('pop-effect-stat'); }, 450);
                    }
                }
            }

            // Fungsi Sentral Pembaruan Status Stok Masuk AJAX
            function updateStokStatus(id, targetStatus) {
                const parentRow = document.getElementById('row-' + id);

                fetch('stok_masuk_toggle_status.php?id=' + id + '&status=' + encodeURIComponent(targetStatus))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update angka statistik dasbor atas secara realtime
                            animateStatTextUpdate('stat-total', data.total_stok);
                            animateStatTextUpdate('stat-pending', data.pending_stok);
                            animateStatTextUpdate('stat-diterima', data.diterima_stok);

                            showToast(`Status pengadaan logistik berhasil diubah ke: ${targetStatus}`, 'success');

                            // Sembunyikan baris jika status baru tidak sesuai filter aktif
                            if (currentStatusFilter !== '' && currentStatusFilter !== targetStatus) {
                                if (parentRow) {
                                    parentRow.style.opacity = '0';
                                    parentRow.style.transform = 'translateY(10px)';
                                    parentRow.style.transition = 'all 0.4s var(--ease-out-expo)';
                                    setTimeout(() => {
                                        parentRow.remove();
                                        document.getElementById('table-count-badge').textContent = tbody.querySelectorAll('.booking-row').length;
                                    }, 400);
                                } else {
                                    performSearchAndFilter();
                                }
                            } else {
                                performSearchAndFilter();
                            }
                        } else {
                            showToast(data.message || 'Gagal memperbarui status pengiriman.', 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error Toggle Status:', error);
                        showToast('Gagal memproses verifikasi barang masuk.', 'danger');
                    });
            }

            // Handler Klik Toggle Status di Tabel Utama (Hanya untuk peran SUPPLIER)
            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                if (toggleBtn) {
                    e.preventDefault();
                    if (userRole !== 'SUPPLIER') {
                        showToast('Hanya akun dengan hak akses Supplier yang dapat melakukan konfirmasi ini.', 'warning');
                        return;
                    }
                    const id = toggleBtn.getAttribute('data-id');
                    const targetStatus = toggleBtn.getAttribute('data-target');
                    
                    toggleBtn.disabled = true;
                    updateStokStatus(id, targetStatus);
                }
            });

            // Handler Klik Toggle Status di Dalam Pop-up Modal Detail (Direct Action)
            document.addEventListener('click', function(e) {
                const modalToggleBtn = e.target.closest('.modal-toggle-btn');
                if (modalToggleBtn) {
                    e.preventDefault();
                    if (userRole !== 'SUPPLIER') {
                        showToast('Hanya akun dengan hak akses Supplier yang dapat melakukan konfirmasi ini.', 'warning');
                        return;
                    }
                    const id = modalToggleBtn.getAttribute('data-id');
                    const targetStatus = modalToggleBtn.getAttribute('data-target');
                    
                    // Tutup modal detail terlebih dahulu sebelum proses update
                    const detailModalEl = document.getElementById('detailStokModal');
                    const detailModalInstance = bootstrap.Modal.getInstance(detailModalEl);
                    if (detailModalInstance) {
                        detailModalInstance.hide();
                    }

                    updateStokStatus(id, targetStatus);
                }
            });

            // =========================================================================
            // VALIDASI: Cegah cetak dokumen jika status stok masih Pending
            // =========================================================================
            document.addEventListener('click', function(e) {
                const printBtn = e.target.closest('.print-btn, .btn-print, a[href*="print"], a[href*="stok_masuk_print"]');
                
                if (printBtn) {
                    let isPending = false;

                    // Kasus 1: Tombol print diklik dari baris tabel utama
                    const row = printBtn.closest('.booking-row');
                    if (row) {
                        const statusText = row.querySelector('.status-text');
                        if (statusText && statusText.textContent.trim() === 'Pending') {
                            isPending = true;
                        }
                    }

                    // Kasus 2: Tombol print diklik dari dalam Modal Detail
                    const modal = printBtn.closest('#detailStokModal');
                    if (modal) {
                        const badge = modal.querySelector('#detail-status-badge');
                        if (badge && badge.textContent.includes('Pending')) {
                            isPending = true;
                        }
                    }

                    // Blokir proses cetak jika terdeteksi masih Pending
                    if (isPending) {
                        e.preventDefault();
                        showToast('Dokumen pengadaan hanya dapat dicetak apabila status telah Diterima!', 'warning');
                    }
                }
            });
            // =========================================================================
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- MODAL POP UP TAMBAH STOK MASUK (stok_masuk_create.php) DI SINI -->
    <?php include_once 'stok_masuk_create.php'; ?>
</body>
</html>
