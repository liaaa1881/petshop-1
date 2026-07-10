
<?php
session_start();
include '../config/koneksi.php';

if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Ambil data role pengguna untuk proteksi hak akses
$user_role = strtoupper($_SESSION['role']);

if (!function_exists('getInitialsPelanggan')) {
    function getInitialsPelanggan($name) {
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

$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- HANDLER AJAX DETAIL NOTA ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // MENAMBAHKAN: PL.Status_Member ke dalam query select
    $sql = "SELECT P.*, PL.Nama_Pelanggan, PL.No_Telepon AS Telp_Pelanggan, PL.Email AS Email_Pelanggan, PL.Status_Member,
                   K.Nama_Karyawan AS Nama_Kasir, B.Kode_Booking
            FROM Penjualan P
            LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
            LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
            LEFT JOIN Booking B ON P.ID_Booking = B.ID_Booking
            WHERE P.ID_Nota = ? AND P.Pen_status = 'Aktif'";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        if ($row['Tanggal_Penjualan'] instanceof DateTime) {
            $row['Tanggal_Penjualan'] = $row['Tanggal_Penjualan']->format('d M Y, H:i');
        }
        
        $row['Subtotal_Penjualan_Format'] = 'Rp ' . number_format($row['Subtotal_Penjualan'], 0, ',', '.');
        $row['Total_Diskon_Format'] = 'Rp ' . number_format($row['Total_Diskon'], 0, ',', '.');
        $row['Pajak_PPN_Format'] = 'Rp ' . number_format($row['Pajak_PPN'], 0, ',', '.');
        $row['Grand_Total_Format'] = 'Rp ' . number_format($row['Grand_Total'], 0, ',', '.');
        $row['Jumlah_Bayar_Format'] = 'Rp ' . number_format($row['Jumlah_Bayar'], 0, ',', '.');
        $row['Kembalian_Format'] = 'Rp ' . number_format($row['Kembalian'], 0, ',', '.');
        
        if (empty($row['Nama_Pelanggan'])) {
            $row['Nama_Pelanggan'] = 'Pelanggan Umum (Non-Member)';
        }

        // MENAMBAHKAN: Perhitungan persentase diskon & PPN secara dinamis berdasarkan nilai nominal
        $diskon_persen = 0;
        if ($row['Subtotal_Penjualan'] > 0) {
            $diskon_persen = round(($row['Total_Diskon'] / $row['Subtotal_Penjualan']) * 100);
        }
        $row['Diskon_Persen'] = $diskon_persen;

        $pajak_persen = 0;
        $dpp = $row['Subtotal_Penjualan'] - $row['Total_Diskon'];
        if ($dpp > 0) {
            $pajak_persen = round(($row['Pajak_PPN'] / $dpp) * 100);
        }
        $row['Pajak_Persen'] = $pajak_persen;

        $sql_items = "SELECT DP.*, B.Nama_Barang 
                      FROM Detail_Penjualan DP 
                      JOIN Barang B ON DP.ID_Barang = B.ID_Barang 
                      WHERE DP.ID_Nota = ?";
        $query_items = sqlsrv_query($conn, $sql_items, array($id));
        $items = [];
        
        if ($query_items !== false) {
            while ($item = sqlsrv_fetch_array($query_items, SQLSRV_FETCH_ASSOC)) {
                $item['Harga_Satuan_Format'] = 'Rp ' . number_format($item['Harga_Satuan'], 0, ',', '.');
                $item['Diskon_Item_Format'] = 'Rp ' . number_format($item['Diskon_Item'], 0, ',', '.');
                $item['Subtotal_Format'] = 'Rp ' . number_format($item['Subtotal'], 0, ',', '.');
                $items[] = $item;
            }
        }
        $row['items'] = $items;

        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nota penjualan tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX LIVE SEARCH & FILTER ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $params = [];
    $sql = "SELECT P.*, PL.Nama_Pelanggan, K.Nama_Karyawan AS Nama_Kasir
            FROM Penjualan P
            LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
            LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
            WHERE P.Pen_status = 'Aktif'";
    
    if ($search != '') {
        $sql .= " AND (PL.Nama_Pelanggan LIKE ? OR P.No_Nota LIKE ? OR P.Metode_Pembayaran LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    
    if ($status_filter != '') {
        $sql .= " AND P.Status_Pembayaran = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY P.Tanggal_Penjualan DESC";
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
                            <i class="fas fa-file-invoice-dollar fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Riwayat transaksi tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Gunakan kata kunci atau filter status pembayaran lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
        $status = $row['Status_Pembayaran'];
        $badge = ($status == 'Lunas') ? 'pill-selesai' : 'pill-batal';
        
        $tgl = ($row['Tanggal_Penjualan'] instanceof DateTime) ? $row['Tanggal_Penjualan']->format('d M Y, H:i') : '-';
        ?>
        <tr class="align-middle booking-row animate-fade-up" id="row-<?= $row['ID_Nota'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['No_Nota']) ?></div>
                <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?= $tgl ?></small>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-container me-3">
                        <div class="plg-avatar shadow-sm avatar-amber">
                            <span class="avatar-initial"><?= getInitialsPelanggan($row['Nama_Pelanggan'] ?: 'Umum') ?></span>
                        </div>
                    </div>
                    <div class="text-truncate">
                        <div class="fw-bold text-dark text-truncate fs-6"><?= htmlspecialchars($row['Nama_Pelanggan'] ?: 'Pelanggan Umum') ?></div>
                        <div class="text-muted small text-truncate">Kasir: <?= htmlspecialchars($row['Nama_Kasir'] ?: '-') ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.8rem;">
                    <i class="fas fa-credit-card me-1 text-muted"></i> <?= htmlspecialchars($row['Metode_Pembayaran'] ?: '-') ?>
                </span>
            </td>
            <td class="text-end fw-bold text-dark">
                Rp <?= number_format($row['Grand_Total'], 0, ',', '.') ?>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Nota'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                </span>
            </td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Nota'] ?>"
                            title="Lihat Detail Transaksi">
                        <i class="fas fa-eye"></i>
                    </button>

                    <?php if ($status == 'Belum Lunas'): ?>
                        <!-- Hanya Kasir yang boleh melakukan verifikasi transaksi lunas -->
                        <?php if ($user_role !== 'ADMIN'): ?>
                            <a href="javascript:void(0)" 
                               class="btn-action btn-status-selesai toggle-status-btn" 
                               data-id="<?= $row['ID_Nota'] ?>"
                               data-target="Lunas"
                               title="Proses Pelunasan Nota">
                                <i class="fas fa-check-circle"></i>
                            </a>
                        <?php else: ?>
                            <!-- Proteksi: Admin melihat tombol terkunci/disable -->
                            <button class="btn-action btn-status-locked" disabled title="Hanya Kasir yang dapat memproses pelunasan">
                                <i class="fas fa-ban"></i>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($status == 'Lunas'): ?>
                        <button class="btn-action btn-status-locked" disabled title="Nota Lunas & Terkunci">
                            <i class="fas fa-lock"></i>
                        </button>
                    <?php endif; ?>

                    <a href="penjualan_print.php?id=<?= $row['ID_Nota'] ?>" target="_blank" class="btn-action btn-status-aktif" title="Cetak Struk Nota">
                        <i class="fas fa-print"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php
    }
    exit;
}

// --- PERHITUNGAN TOTAL RECORD PAGINATION ---
$sql_count = "SELECT COUNT(*) as total FROM Penjualan P LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan WHERE P.Pen_status = 'Aktif'";
$params_count = [];
if ($search != '') {
    $sql_count .= " AND (PL.Nama_Pelanggan LIKE ? OR P.No_Nota LIKE ?)";
    $params_count[] = "%$search%"; $params_count[] = "%$search%";
}
if ($status_filter != '') {
    $sql_count .= " AND P.Status_Pembayaran = ?";
    $params_count[] = $status_filter;
}
$query_count = sqlsrv_query($conn, $sql_count, $params_count);
$total_records = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// --- DATA STATISTIK UTAMA (DIHITUNG REALTIME MENGGUNAKAN UDF) ---
$sql_total_trx = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Penjualan WHERE Pen_status = 'Aktif'");
$total_t = $sql_total_trx ? sqlsrv_fetch_array($sql_total_trx, SQLSRV_FETCH_ASSOC)['total'] : 0;

// Menggunakan fn_TotalPenjualan untuk menghitung akumulasi total pendapatan berstatus Lunas
$sql_revenue = sqlsrv_query($conn, "SELECT dbo.fn_TotalPenjualan('1900-01-01', '2099-12-31') as total");
$total_r = $sql_revenue ? (sqlsrv_fetch_array($sql_revenue, SQLSRV_FETCH_ASSOC)['total'] ?? 0) : 0;

// Menggunakan fn_JumlahTransaksi untuk menghitung total transaksi lunas khusus hari ini
$sql_today = sqlsrv_query($conn, "SELECT dbo.fn_JumlahTransaksi(CAST(GETDATE() AS DATE), CAST(GETDATE() AS DATE)) as total");
$total_td = $sql_today ? sqlsrv_fetch_array($sql_today, SQLSRV_FETCH_ASSOC)['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Penjualan Kasir | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 Library CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #3b82f6;
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
            --gradient-green: linear-gradient(135deg, #059669, #047857);
            --gradient-info: linear-gradient(135deg, #0d9488, #0f766e);
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

        .card-stat { 
            border-radius: 20px; 
            border: none; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
            color: white;
            transition: all 0.3s ease;
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

        .glass-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 24px; 
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
        }

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
        
        .btn-lihat { background: rgba(59, 130, 246, 0.08); color: var(--primary); }
        .btn-status-aktif { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .btn-status-selesai { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .btn-status-locked { background: var(--slate-100); color: #94a3b8; cursor: not-allowed; }
        
        .btn-action:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-lihat:hover { background: var(--primary); color: #ffffff; }
        .btn-status-aktif:hover { background: var(--warning); color: #ffffff; }
        .btn-status-selesai:hover { background: var(--success); color: #ffffff; }

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

        #detailNotaModal {
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            #detailNotaModal, #modalTambahPenjualan {
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
        
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-fade-up">
            <div>
                <h2 class="fw-bold text-dark mb-1">Riwayat Transaksi Penjualan 🧾</h2>
                <p class="text-muted mb-0">Kelola dan pantau seluruh transaksi kasir Petshop Pro secara real-time.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nomor nota atau pelanggan..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <button type="button" class="btn btn-primary fw-bold rounded-pill px-4" style="background: var(--success); border:none; height: 45px; display:inline-flex; align-items:center; gap:8px;" data-bs-toggle="modal" data-bs-target="#modalTambahPenjualan">
                    <i class="fas fa-plus-circle"></i> Transaksi Baru
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-blue);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Total Transaksi</p>
                            <h2 class="stat-value mb-0" id="stat-total"><?= $total_t ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-green);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Total Pendapatan</p>
                            <h2 class="stat-value mb-0" id="stat-pendapatan">Rp <?= number_format($total_r, 0, ',', '.') ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-info);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Transaksi Lunas Hari Ini</p>
                            <h2 class="stat-value mb-0" id="stat-hari-ini"><?= $total_td ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-4 animate-fade-up">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Nota Penjualan
                    <span class="badge bg-light text-success border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="filter-container">
                        <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua</a>
                        <a href="?page=1&status_filter=Lunas&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Lunas') ? 'active' : '' ?>">Lunas</a>
                        <a href="?page=1&status_filter=Belum Lunas&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Belum Lunas') ? 'active' : '' ?>">Belum Lunas</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">No</th>
                            <th style="width: 20%;">Nota & Tanggal</th>
                            <th style="width: 30%;">Pelanggan & Kasir</th>
                            <th style="width: 15%;">Metode</th>
                            <th class="text-end" style="width: 150px;">Total Bayar</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-center" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="booking-tbody">
                        <?php
                        $no = 1 + $offset;
                        $params = [];
                        
                        $sql = "SELECT P.*, PL.Nama_Pelanggan, K.Nama_Karyawan AS Nama_Kasir
                                FROM Penjualan P
                                LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
                                LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
                                WHERE P.Pen_status = 'Aktif'";
                        $params_count = [];
                        
                        $query = sqlsrv_query($conn, $sql, $params);
                        
                        // query data limit manual agar tidak bentrok offset SQL Server
                        $sql_page = "SELECT P.*, PL.Nama_Pelanggan, K.Nama_Karyawan AS Nama_Kasir
                                     FROM Penjualan P
                                     LEFT JOIN Pelanggan PL ON P.ID_Pelanggan = PL.ID_Pelanggan
                                     LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
                                     WHERE P.Pen_status = 'Aktif'";
                                     
                        if ($search != '') {
                            $sql_page .= " AND (PL.Nama_Pelanggan LIKE ? OR P.No_Nota LIKE ?)";
                            $params_count[] = "%$search%"; $params_count[] = "%$search%";
                        }
                        if ($status_filter != '') {
                            $sql_page .= " AND P.Status_Pembayaran = ?";
                            $params_count[] = $status_filter;
                        }
                        
                        $sql_page .= " ORDER BY P.Tanggal_Penjualan DESC OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
                        $query_page = sqlsrv_query($conn, $sql_page, $params_count);

                        if ($query_page !== false) {
                            while($row = sqlsrv_fetch_array($query_page, SQLSRV_FETCH_ASSOC)) { 
                                $status = $row['Status_Pembayaran'];
                                $badge = ($status == 'Lunas') ? 'pill-selesai' : 'pill-batal';
                                
                                $tgl = ($row['Tanggal_Penjualan'] instanceof DateTime) ? $row['Tanggal_Penjualan']->format('d M Y, H:i') : '-';
                            ?>
                            <tr class="align-middle booking-row" id="row-<?= $row['ID_Nota'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['No_Nota']) ?></div>
                                    <small class="text-muted"><i class="far fa-calendar-alt me-1"></i><?= $tgl ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-container me-3">
                                            <div class="plg-avatar shadow-sm avatar-amber">
                                                <span class="avatar-initial"><?= getInitialsPelanggan($row['Nama_Pelanggan'] ?: 'Umum') ?></span>
                                            </div>
                                        </div>
                                        <div class="text-truncate">
                                            <div class="fw-bold text-dark text-truncate fs-6"><?= htmlspecialchars($row['Nama_Pelanggan'] ?: 'Pelanggan Umum') ?></div>
                                            <div class="text-muted small text-truncate">Kasir: <?= htmlspecialchars($row['Nama_Kasir'] ?: '-') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border px-2 py-1" style="font-size: 0.8rem;">
                                        <i class="fas fa-credit-card me-1 text-muted"></i> <?= htmlspecialchars($row['Metode_Pembayaran'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format($row['Grand_Total'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Nota'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        <!-- TOMBOL LIHAT DETAIL -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Nota'] ?>"
                                                title="Lihat Detail Transaksi">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOGGLE LUNASI STATUS (Hanya Kasir / Non-Admin) -->
                                        <?php if ($status == 'Belum Lunas'): ?>
                                            <?php if ($user_role !== 'ADMIN'): ?>
                                                <a href="javascript:void(0)" 
                                                   class="btn-action btn-status-selesai toggle-status-btn" 
                                                   data-id="<?= $row['ID_Nota'] ?>"
                                                   data-target="Lunas"
                                                   title="Proses Pelunasan Nota">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <!-- Kunci akses tombol pelunasan bagi Admin -->
                                                <button class="btn-action btn-status-locked" disabled title="Akses Terkunci: Hanya Kasir yang dapat melakukan pelunasan">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- LOCK ICON jika sudah Lunas -->
                                        <?php if ($status == 'Lunas'): ?>
                                            <button class="btn-action btn-status-locked" disabled title="Nota Lunas & Terkunci">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- TOMBOL PRINT NOTA -->
                                        <a href="penjualan_print.php?id=<?= $row['ID_Nota'] ?>" target="_blank" class="btn-action btn-status-aktif" title="Cetak Struk Nota">
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

            <?php if ($total_pages > 1): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 px-2">
                <div class="text-muted small mb-2 mb-md-0">
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> data transaksi penjualan.
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0 gap-1">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link rounded-3" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link rounded-3" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status_filter=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
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

    <!-- MODAL DETAIL NOTA PENJUALAN -->
    <div class="modal fade" id="detailNotaModal" tabindex="-1" aria-labelledby="detailNotaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0 animate-fade-up">
                
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div class="plg-avatar shadow-sm avatar-amber mb-3" style="width:80px; height:80px; border-radius:18px; font-size:1.8rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">
                            <span class="avatar-initial" id="detail-initial">?</span>
                        </div>
                        <h3 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Pelanggan</h3>
                        <span class="badge bg-light text-success fw-bold mt-1" id="detail-no-nota" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">INV-00-00</span>
                        <div id="detail-status-badge" class="mt-2"></div>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start" style="max-height: 450px; overflow-y: auto;">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-user-tie me-2"></i>Informasi Transaksi</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Pelanggan</td><td class="fw-bold text-dark" id="detail-nama-p">-</td></tr>
                                    <tr><td class="text-muted">Status Member</td><td class="fw-bold text-dark" id="detail-status-member">-</td></tr>
                                    <tr><td class="text-muted">No. Telepon</td><td class="fw-bold text-dark" id="detail-telp-p">-</td></tr>
                                    <tr><td class="text-muted">Petugas Kasir</td><td class="fw-bold text-dark" id="detail-kasir">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-receipt me-2"></i>Pembayaran & Waktu</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:45%;">Metode Bayar</td><td class="fw-bold text-success" id="detail-metode">-</td></tr>
                                    <tr><td class="text-muted">Tanggal Transaksi</td><td class="fw-bold text-dark" id="detail-tanggal">-</td></tr>
                                    <tr><td class="text-muted">Kode Booking Jasa</td><td class="fw-bold text-primary" id="detail-kode-booking">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-box-open me-2"></i>Daftar Rincian Produk / Barang Belanjaan</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 small" style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nama Barang / Produk</th>
                                                <th class="text-center" style="width: 80px;">Jumlah</th>
                                                <th class="text-end" style="width: 120px;">Harga Satuan</th>
                                                <th class="text-end" style="width: 100px;">Diskon</th>
                                                <th class="text-end" style="width: 130px;">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detail-items-tbody">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-calculator me-2"></i>Ringkasan Nominal Pembayaran Kasir</h6>
                                <div class="row g-3 small text-center mb-2">
                                    <div class="col-3">
                                        <span class="text-muted d-block small">Subtotal Penjualan</span>
                                        <strong class="text-dark" id="detail-subtotal">-</strong>
                                    </div>
                                    <div class="col-3 border-start">
                                        <span class="text-muted d-block small">Potongan Diskon (<span id="detail-diskon-persen-label">0</span>%)</span>
                                        <strong class="text-danger" id="detail-total-diskon">-</strong>
                                    </div>
                                    <div class="col-3 border-start">
                                        <span class="text-muted d-block small">Pajak PPN (<span id="detail-pajak-persen-label">0</span>%)</span>
                                        <strong class="text-warning" id="detail-pajak">-</strong>
                                    </div>
                                    <div class="col-3 border-start">
                                        <span class="text-muted d-block small">Grand Total Akhir</span>
                                        <strong class="text-success fs-6" id="detail-grand-total">-</strong>
                                    </div>
                                </div>
                                <hr class="my-2">
                                <div class="row g-2 small text-center pt-1">
                                    <div class="col-6">
                                        <span class="text-muted d-block">Jumlah Uang yang Dibayar</span>
                                        <strong class="text-dark fs-6" id="detail-jumlah-bayar">-</strong>
                                    </div>
                                    <div class="col-6 border-start">
                                        <span class="text-muted d-block">Uang Kembali (Kembalian)</span>
                                        <strong class="text-primary fs-6" id="detail-kembalian">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-2"><i class="fas fa-comment-alt me-2"></i>Catatan Penjualan</h6>
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

    <script>
        // Inject JS variable dari PHP Session untuk membatasi aksi modal
        const userRole = <?= json_encode($user_role); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('booking-tbody');
            const toastContainer = document.getElementById('toastContainer');
            
            let currentStatusFilter = '<?= htmlspecialchars($status_filter) ?>';

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

            function performSearchAndFilter() {
                const queryValue = searchInput.value;
                tbody.style.opacity = '0.4';

                fetch(`penjualan_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
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
                        showToast('Gagal memuat data transaksi.', 'danger');
                    });
            }

            window.performSearchAndFilter = performSearchAndFilter;

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`penjualan_read.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Pelanggan || 'Pelanggan Umum (Non-Member)';
                                document.getElementById('detail-no-nota').textContent = d.No_Nota || '-';
                                
                                document.getElementById('detail-nama-p').textContent = d.Nama_Pelanggan || 'Pelanggan Umum (Non-Member)';
                                
                                // SET: Status Member di Rincian Nota Pelanggan secara Dinamis
                                const detailStatusMember = document.getElementById('detail-status-member');
                                if (detailStatusMember) {
                                    detailStatusMember.textContent = d.Status_Member ? d.Status_Member : 'Non Member (Regular)';
                                    if (d.Status_Member === 'Member' || d.Status_Member === 'Premium') {
                                        detailStatusMember.className = 'fw-bold text-success';
                                    } else {
                                        detailStatusMember.className = 'fw-bold text-muted';
                                    }
                                }

                                document.getElementById('detail-telp-p').textContent = d.Telp_Pelanggan || '-';
                                document.getElementById('detail-kasir').textContent = d.Nama_Kasir || '-';
                                
                                document.getElementById('detail-metode').textContent = d.Metode_Pembayaran || '-';
                                document.getElementById('detail-tanggal').textContent = d.Tanggal_Penjualan || '-';
                                document.getElementById('detail-kode-booking').textContent = d.Kode_Booking || '-';

                                document.getElementById('detail-subtotal').textContent = d.Subtotal_Penjualan_Format || 'Rp 0';
                                document.getElementById('detail-total-diskon').textContent = d.Total_Diskon_Format || 'Rp 0';
                                document.getElementById('detail-pajak').textContent = d.Pajak_PPN_Format || 'Rp 0';
                                document.getElementById('detail-grand-total').textContent = d.Grand_Total_Format || 'Rp 0';
                                document.getElementById('detail-jumlah-bayar').textContent = d.Jumlah_Bayar_Format || 'Rp 0';
                                document.getElementById('detail-kembalian').textContent = d.Kembalian_Format || 'Rp 0';
                                
                                // SET: Persentase Diskon & PPN secara Dinamis
                                const diskonLabel = document.getElementById('detail-diskon-persen-label');
                                if (diskonLabel) {
                                    diskonLabel.textContent = d.Diskon_Persen || '0';
                                }
                                const pajakLabel = document.getElementById('detail-pajak-persen-label');
                                if (pajakLabel) {
                                    pajakLabel.textContent = d.Pajak_Persen || '0';
                                }

                                document.getElementById('detail-catatan').textContent = d.Catatan_Penjualan || 'Tidak ada catatan penjualan.';
                                document.getElementById('detail-initial').textContent = getInitialsJs(d.Nama_Pelanggan || 'Umum');

                                let statusBadgeHTML = '';
                                if (d.Status_Pembayaran === 'Lunas') {
                                    statusBadgeHTML = `<span class="badge bg-success text-white fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-check-circle me-1"></i>Lunas</span>`;
                                } else {
                                    statusBadgeHTML = `<span class="badge bg-danger text-white fw-bold px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-exclamation-circle me-1"></i>Belum Lunas</span>`;
                                }
                                document.getElementById('detail-status-badge').innerHTML = statusBadgeHTML;

                                const itemsTbody = document.getElementById('detail-items-tbody');
                                itemsTbody.innerHTML = '';
                                if (d.items && d.items.length > 0) {
                                    d.items.forEach(item => {
                                        itemsTbody.innerHTML += `
                                            <tr>
                                                <td><span class="fw-bold text-dark">${item.Nama_Barang}</span></td>
                                                <td class="text-center font-monospace fw-bold">${item.Jumlah}</td>
                                                <td class="text-end">${item.Harga_Satuan_Format}</td>
                                                <td class="text-end text-danger">-${item.Diskon_Item_Format}</td>
                                                <td class="text-end fw-bold text-dark">${item.Subtotal_Format}</td>
                                            </tr>
                                        `;
                                    });
                                } else {
                                    itemsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Hanya berisi transaksi pelunasan booking jasa grooming.</td></tr>';
                                }

                                const actionContainer = document.getElementById('modal-action-container');
                                actionContainer.innerHTML = '';
                                
                                // Proteksi Hak Akses untuk tombol pelunasan di dalam modal rincian
                                if (d.Status_Pembayaran === 'Belum Lunas') {
                                    if (userRole !== 'ADMIN') {
                                        actionContainer.innerHTML = `
                                            <button type="button" class="btn btn-success rounded-pill px-4 py-2 fw-bold text-white modal-toggle-btn shadow-sm" data-id="${d.ID_Nota}" data-target="Lunas">
                                                <i class="fas fa-check-circle me-2"></i>Bagikan Pelunasan
                                            </button>
                                        `;
                                    }
                                }

                                document.getElementById('modal-action-container').innerHTML = actionContainer.innerHTML;

                                const detailModal = new bootstrap.Modal(document.getElementById('detailNotaModal'));
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

            function getInitialsJs(name) {
                if (!name) return "?";
                const words = name.trim().split(/\s+/);
                let initials = words[0].charAt(0);
                if (words.length > 1) {
                    initials += words[words.length - 1].charAt(0);
                }
                return initials.toUpperCase();
            }

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

            function updatePembayaranStatus(id, targetStatus) {
                const parentRow = document.getElementById('row-' + id);

                fetch('penjualan_toggle_status.php?id=' + id + '&status=' + encodeURIComponent(targetStatus))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            animateStatTextUpdate('stat-total', data.total_t);
                            animateStatTextUpdate('stat-pendapatan', data.total_revenue_format);
                            animateStatTextUpdate('stat-hari-ini', data.total_td);

                            showToast(`Status nota penjualan berhasil diubah ke: ${targetStatus}`, 'success');

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
                            showToast(data.message || 'Gagal memperbarui status pembayaran.', 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error Toggle Status:', error);
                        showToast('Gagal memproses status transaksi.', 'danger');
                    });
            }

            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                if (toggleBtn) {
                    e.preventDefault();
                    const id = toggleBtn.getAttribute('data-id');
                    const targetStatus = toggleBtn.getAttribute('data-target');
                    
                    toggleBtn.disabled = true;
                    updatePembayaranStatus(id, targetStatus);
                }
            });

            // Handler tombol pelunasan di dalam modal detail
            document.addEventListener('click', function(e) {
                const modalToggleBtn = e.target.closest('.modal-toggle-btn');
                if (modalToggleBtn) {
                    e.preventDefault();
                    const id = modalToggleBtn.getAttribute('data-id');
                    const targetStatus = modalToggleBtn.getAttribute('data-target');
                    
                    // Tutup modal agar efek animasi mulus
                    const detailModalEl = document.getElementById('detailNotaModal');
                    const modalInstance = bootstrap.Modal.getInstance(detailModalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    updatePembayaranStatus(id, targetStatus);
                }
            });

            // =========================================================================
            // VALIDASI: Cegah cetak struk/nota/laporan jika status pembayaran belum lunas
            // =========================================================================
            document.addEventListener('click', function(e) {
                const printBtn = e.target.closest('.print-btn, .btn-print, a[href*="print"], a[href*="struk"], a[href*="nota_print"], a[href*="penjualan_print"]');
                
                if (printBtn) {
                    let isBelumLunas = false;

                    // Kasus 1: Tombol print diklik dari baris tabel utama
                    const row = printBtn.closest('.booking-row');
                    if (row) {
                        const statusText = row.querySelector('.status-text');
                        if (statusText && statusText.textContent.trim() === 'Belum Lunas') {
                            isBelumLunas = true;
                        }
                    }

                    // Kasus 2: Tombol print diklik dari dalam Modal Detail
                    const modal = printBtn.closest('#detailNotaModal');
                    if (modal) {
                        const badge = modal.querySelector('#detail-status-badge');
                        if (badge && badge.textContent.includes('Belum Lunas')) {
                            isBelumLunas = true;
                        }
                    }

                    if (isBelumLunas) {
                        e.preventDefault();
                        showToast('Struk penjualan hanya dapat dicetak apabila transaksi telah Lunas!', 'warning');
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php include 'penjualan_create.php'; ?>
</body>
</html>
