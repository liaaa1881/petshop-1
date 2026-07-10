

<?php
ob_start(); // Pengaman output buffering di baris pertama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// --- INTERSEPSI REQUEST POST (AJAX) SEBELUM HTML DI-RENDER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan'])) {
        include 'supplier_create.php';
        exit;
    }
    if (isset($_POST['update'])) {
        include 'supplier_update.php';
        exit;
    }
}

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') { 
    header("Location: ../../dashboard/index.php"); 
    exit; 
}

$role = $_SESSION['role'];

// Fungsi helper untuk menghasilkan inisial nama secara aman
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

// Definisikan variable agar aman dari undefined warning
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --- KONFIGURASI PAGINATION (10 Data Per Halaman) ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- HANDLER AJAX DETAIL SUPPLIER ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "{CALL sp_Supplier_Read(?, NULL)}";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supplier tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX UNTUK LIVE SEARCH & FILTER (PROSES DI MEMORI PHP) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $all_suppliers = [];
    $sql_sp = "{CALL sp_Supplier_Read(NULL, NULL)}";
    $query_sp = sqlsrv_query($conn, $sql_sp);
    
    if ($query_sp === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }
    while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
        $all_suppliers[] = $row;
    }
    sqlsrv_free_stmt($query_sp);

    // Proses penyaringan kata kunci pencarian dan status di memori PHP
    $filtered = $all_suppliers;
    if ($search != '') {
        $filtered = array_filter($filtered, function($sup) use ($search) {
            return (stripos($sup['Nama_Supplier'], $search) !== false) || 
                   (stripos($sup['Username'], $search) !== false);
        });
    }
    if ($status_filter != '') {
        $filtered = array_filter($filtered, function($sup) use ($status_filter) {
            return $sup['Sup_status'] === $status_filter;
        });
    }

    // URUTKAN: Supplier ID secara DESCENDING agar data baru muncul paling atas
    usort($filtered, function($a, $b) {
        return (int)$b['ID_Supplier'] <=> (int)$a['ID_Supplier'];
    });

    $no = 1;
    if (empty($filtered)) {
        echo '<tr>
                <td colspan="6" class="text-center py-5">
                    <div class="empty-state animate-fade-in">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-truck-slash fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Data supplier tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    foreach ($filtered as $row) { 
        $is_aktif = ($row['Sup_status'] == 'Aktif');
        ?>
        <tr class="align-middle supplier-row animate-fade-up" id="row-<?= $row['ID_Supplier'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-container me-3">
                        <div class="plg-avatar shadow-sm avatar-emerald">
                            <?php if (!empty($row['Foto_Supplier']) && file_exists("../../assets/uploads/supplier/" . $row['Foto_Supplier'])): ?>
                                <img src="../../assets/uploads/supplier/<?= $row['Foto_Supplier'] ?>" alt="Logo">
                            <?php else: ?>
                                <span class="avatar-initial"><?= getInitialsSupplier($row['Nama_Supplier']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                    </div>
                    <div class="text-truncate" style="max-width: 250px;">
                        <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Supplier']) ?>"><?= htmlspecialchars($row['Nama_Supplier']) ?></div>
                        <div class="text-muted small text-truncate"><?= htmlspecialchars($row['Email'] ?: 'Tidak ada email') ?></div>
                    </div>
                </div>
            </td>
            <td>
                <div class="small fw-bold text-emerald mb-1">
                    <i class="fab fa-whatsapp me-1"></i> <?= htmlspecialchars($row['No_Telepon'] ?: '-') ?>
                </div>
                <div class="text-muted text-truncate" style="font-size: 0.75rem; max-width: 250px;" title="<?= htmlspecialchars($row['Alamat']) ?>">
                    <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($row['Alamat'] ?: '-') ?>
                </div>
            </td>
            <td>
                <span class="badge badge-username text-truncate d-inline-block" style="max-width: 100%;">
                    @<?= htmlspecialchars($row['Username'] ?? '') ?>
                </span>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Supplier'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Sup_status'] ?: 'Non-Aktif') ?></span>
                </span>
            </td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    
                    <!-- TOMBOL LIHAT DETAIL -->
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Supplier'] ?>"
                            title="Lihat Detail Supplier">
                        <i class="fas fa-eye"></i>
                    </button>

                    <!-- TOMBOL TOGGLE STATUS -->
                    <a href="javascript:void(0)" 
                       class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                       data-id="<?= $row['ID_Supplier'] ?>"
                       data-current="<?= htmlspecialchars($row['Sup_status'] ?: 'Non-Aktif') ?>"
                       id="toggle-btn-<?= $row['ID_Supplier'] ?>"
                       title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                        <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>

                    <!-- TOMBOL EDIT -->
                    <a href="<?= $is_aktif ? 'supplier_read.php?id=' . $row['ID_Supplier'] : 'javascript:void(0)' ?>" 
                       class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                       id="edit-btn-<?= $row['ID_Supplier'] ?>"
                       title="<?= $is_aktif ? 'Edit Mitra' : 'Supplier Non-Aktif tidak dapat diedit' ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </a>

                    <!-- TOMBOL HAPUS -->
                    <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                            data-bs-toggle="modal" data-bs-target="#confirmModal" 
                            data-href="supplier_delete.php?id=<?= $row['ID_Supplier'] ?>&type=soft"
                            data-id="<?= $row['ID_Supplier'] ?>"
                            data-action-type="softdelete"
                            data-title="Hapus Supplier"
                            data-message="Apakah Anda yakin ingin menghapus data supplier <b><?= htmlspecialchars($row['Nama_Supplier']) ?></b>?"
                            data-color="btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }
    exit;
}

// Tarik seluruh data supplier awal menggunakan Stored Procedure
$all_suppliers = [];
$sql_sp = "{CALL sp_Supplier_Read(NULL, NULL)}";
$query_sp = sqlsrv_query($conn, $sql_sp);
if ($query_sp === false) {
    die(print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
    $all_suppliers[] = $row;
}
sqlsrv_free_stmt($query_sp);

// --- PRE-CALCULATE DATA STATISTIK DI SISI PHP (6 Variabel) ---
$total_s = count($all_suppliers);
$total_a = count(array_filter($all_suppliers, function($s) { return $s['Sup_status'] === 'Aktif'; }));
$total_o = count(array_filter($all_suppliers, function($s) { return $s['Sup_status'] === 'Non-Aktif'; }));
$total_cp = count(array_filter($all_suppliers, function($s) { return !empty($s['Nama_CP']); }));
$total_rek = count(array_filter($all_suppliers, function($s) { return !empty($s['No_Rekening']); }));

$unique_cities = [];
foreach ($all_suppliers as $s) {
    if (!empty($s['Kota_Kabupaten'])) {
        $unique_cities[strtolower(trim($s['Kota_Kabupaten']))] = true;
    }
}
$total_cities = count($unique_cities);

$pct_aktif = $total_s > 0 ? round(($total_a / $total_s) * 100) : 0;
$pct_off = $total_s > 0 ? round(($total_o / $total_s) * 100) : 0;
$pct_cp = $total_s > 0 ? round(($total_cp / $total_s) * 100) : 0;

// Proses Filter Sesuai Parameter URL
$filtered_main = $all_suppliers;
if ($search != '') {
    $filtered_main = array_filter($filtered_main, function($sup) use ($search) {
        return (stripos($sup['Nama_Supplier'], $search) !== false) || 
               (stripos($sup['Username'], $search) !== false);
    });
}
if ($status_filter != '') {
    $filtered_main = array_filter($filtered_main, function($sup) use ($status_filter) {
        return $sup['Sup_status'] === $status_filter;
    });
}

// URUTKAN: Supplier ID DESCENDING agar data baru ter-render paling atas
usort($filtered_main, function($a, $b) {
    return (int)$b['ID_Supplier'] <=> (int)$a['ID_Supplier'];
});

// Hitung total record tersaring & total halaman paginasi
$total_records = count($filtered_main);
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Ambil potongan item array sesuai halaman saat ini
$paged_suppliers = array_slice($filtered_main, $offset, $limit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Supplier | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-emerald: #10b981; 
            --primary-light: #34d399; 
            --primary-glow: rgba(16, 185, 129, 0.15);
            --success-dark: #059669;
            --warning-amber: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.15);
            --danger-red: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.15);
            --info-cyan: #06b6d4;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.6);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.02);
            --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
            --primary-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        }

        body { 
            background: #f4f6fa; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            overflow-x: hidden;
            color: var(--slate-800);
        }

        .animate-fade-up { animation: fadeInUp 0.8s var(--ease-out-expo) both; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out both; }
        .delay-1 { animation-delay: 0.08s; }
        .delay-2 { animation-delay: 0.16s; }
        .delay-3 { animation-delay: 0.24s; }
        .delay-4 { animation-delay: 0.32s; }
        .delay-5 { animation-delay: 0.40s; }
        .delay-6 { animation-delay: 0.48s; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .pop-effect { animation: popBounce 0.4s var(--ease-out-expo); }
        .pop-stat { 
            animation: statPop 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            display: inline-block; 
        }

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
            transition: color 0.3s ease;
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
            border-color: var(--primary-emerald);
            box-shadow: 0 0 0 4px var(--primary-glow);
            width: 320px;
            outline: none;
        }
        .search-wrapper .input-search:focus + .search-icon {
            color: var(--primary-emerald);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1.25rem;
            margin-bottom: 3rem;
        }
        @media (max-width: 1600px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }

        /* STAT CARDS */
        .card-stat { 
            border-radius: 24px; 
            border: 1px solid rgba(255, 255, 255, 0.8); 
            transition: all 0.4s var(--ease-out-expo); 
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem 1.25rem;
            min-height: 140px;
        }
        .card-stat-total:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-aktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-nonaktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(100, 116, 139, 0.15); border-color: rgba(100, 116, 139, 0.25); }
        .card-stat-cp:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(6, 182, 212, 0.15); border-color: rgba(6, 182, 212, 0.25); }
        .card-stat-bank:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.25); }
        .card-stat-kota:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.25); }

        .card-stat .stat-label {
            color: #64748b;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0px;
        }
        .card-stat .stat-value {
            color: var(--slate-800);
            font-weight: 800;
            font-size: 1.85rem;
            line-height: 1.2;
            margin-top: 0.25rem;
            display: inline-block;
        }
        .stat-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        .card-stat:hover .stat-icon-box {
            transform: scale(1.12) rotate(5deg);
        }

        .icon-box-total { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: var(--primary-emerald); }
        .icon-box-aktif { background: linear-gradient(135deg, #d1fae5, #34d399); color: var(--success-dark); }
        .icon-box-nonaktif { background: linear-gradient(135deg, #f1f5f9, #cbd5e1); color: #64748b; }
        .icon-box-cp { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: var(--info-cyan); }
        .icon-box-bank { background: linear-gradient(135deg, #fef3c7, #fde68a); color: var(--warning-amber); }
        .icon-box-kota { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #6366f1; }

        .stat-progress-bar {
            height: 6px;
            width: 100%;
            background: var(--slate-100);
            border-radius: 10px;
            margin-top: 1rem;
            overflow: hidden;
        }
        .stat-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s var(--ease-out-expo);
        }

        /* PREMIUM GLASS CARD */
        .glass-card { 
            background: var(--glass-bg); 
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 28px; 
            border: 1px solid var(--glass-border);
            box-shadow: 0 15px 35px rgba(0,0,0,0.02), var(--card-shadow);
        }

        /* QUICK FILTER CHIPS */
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
            background: var(--slate-50);
            color: var(--slate-800);
        }
        .filter-chip.active {
            background: var(--primary-emerald);
            color: #ffffff;
            border-color: var(--primary-emerald);
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* TABLE STYLE */
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: var(--slate-200) transparent;
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
            transition: all 0.4s var(--ease-out-expo);
            border-bottom: 1px solid var(--slate-100);
        }
        .table tbody tr:hover {
            background-color: rgba(16, 185, 129, 0.015);
            transform: scale(1.001);
        }

        /* AVATAR STYLE */
        .avatar-container {
            position: relative;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }
        .plg-avatar {
            width: 100%; height: 100%; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.15rem;
            border: 2px solid #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .plg-avatar img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .avatar-initial {
            display: flex; align-items: center; justify-content: center;
            width: 100%; height: 100%;
        }
        .avatar-emerald { 
            background: linear-gradient(135deg, #059669 0%, #10b981 100%); 
            color: #ffffff; 
        }

        .avatar-status-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
        }
        .status-online { background-color: var(--primary-emerald); }
        .status-offline { background-color: #94a3b8; }

        .badge-username {
            background: #ffffff;
            color: var(--slate-700);
            border: 1px solid var(--slate-200);
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .text-emerald { color: var(--primary-emerald) !important; }

        /* Status Pill */
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
            box-sizing: border-box;
            transition: all 0.3s var(--ease-out-expo);
        }
        .pill-aktif { background: rgba(16, 185, 129, 0.1) !important; color: #065f46 !important; border: 1.5px solid rgba(16, 185, 129, 0.25); }
        .pill-off { background: rgba(239, 68, 68, 0.08) !important; color: #991b1b !important; border: 1.5px solid rgba(239, 68, 68, 0.25); }

        /* Action Buttons */
        .action-gap {
            gap: 6px;
        }
        .btn-action { 
            width: 38px; height: 38px; 
            border-radius: 12px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            transition: all 0.25s var(--ease-out-expo);
            border: none;
            text-decoration: none;
        }
        .btn-action i { 
            font-size: 1.05rem; 
            transition: transform 0.25s ease;
        }
        
        .btn-lihat { background: rgba(14, 165, 233, 0.08); color: #0ea5e9; }
        .btn-status-aktif { background: rgba(16, 185, 129, 0.1); color: var(--primary-emerald); } 
        .btn-status-off { background: var(--slate-100); color: #64748b; }   
        .btn-edit { background: rgba(16, 185, 129, 0.08); color: var(--success-dark); }           
        .btn-hard { background: rgba(239, 68, 68, 0.08); color: var(--danger-red); }           
        
        .btn-action:hover { transform: translateY(-3px); }
        .btn-action:hover i { transform: scale(1.1); }
        .btn-lihat:hover { background: #0ea5e9; color: #ffffff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15); }
        .btn-status-aktif:hover { background: var(--primary-emerald); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow); }
        .btn-status-off:hover { background: #64748b; color: #ffffff; }
        .btn-edit:hover { background: var(--primary-emerald); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow); }
        .btn-hard:hover { background: var(--danger-red); color: #ffffff; box-shadow: 0 4px 12px var(--danger-glow); }

        .btn-edit.disabled {
            background: var(--slate-100) !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.55;
            transform: none !important;
        }

        /* PAGINATION STYLE */
        .pagination .page-link {
            border: 1px solid var(--slate-200);
            color: var(--slate-700);
            background: #ffffff;
            font-weight: 600;
            padding: 8px 14px;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        .pagination .page-item.active .page-link {
            background: var(--primary-emerald);
            border-color: var(--primary-emerald);
            color: #ffffff;
            box-shadow: 0 4px 10px var(--primary-glow);
        }
        .pagination .page-link:hover {
            border-color: var(--primary-light);
            background: var(--slate-50);
            color: var(--slate-800);
        }

        /* PRESERVASI STYLE MODAL DETAIL */
        #detailSupplierModal {
            z-index: 1060 !important;
            backdrop-filter: blur(8px);
            background-color: rgba(15, 23, 42, 0.4);
        }

        @media (min-width: 992px) {
            #detailSupplierModal {
                padding-left: 260px !important; 
            }
        }

        #detailSupplierModal.show .modal-content-custom {
            animation: modalZoomIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .modal-content-custom { 
            background: #ffffff; 
            border: none; 
            border-radius: 1.5rem; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
            overflow: hidden; 
        }

        .modal-header-centered {
            background: var(--primary-gradient);
            padding: 2.5rem 2rem;
            color: white;
            text-align: center;
            position: relative;
        }

        /* TOAST NOTIFICATION */
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
            border-left: 4px solid var(--primary-emerald);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 420px;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .toast-modern.show { transform: translateX(0); }
        .toast-modern.success { border-left-color: var(--primary-emerald); }
        .toast-modern.warning { border-left-color: var(--warning-amber); }
        .toast-modern.danger { border-left-color: var(--danger-red); }

        .empty-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--slate-100);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            animation: floating 3s ease-in-out infinite;
        }

        .btn-tambah {
            background: var(--primary-emerald);
            color: white;
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 700;
            box-shadow: 0 4px 12px var(--primary-glow);
            transition: 0.3s;
            border: none;
            text-decoration: none;
        }
        .btn-tambah:hover {
            background: var(--success-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.25);
        }
    </style>
</head>
<body>
    <?php include '../../layouts/navbar.php'; ?>

    <div class="toast-container-modern" id="toastContainer"></div>

    <div class="container-fluid px-4 py-5">
        
        <!-- HEADER SECTION -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-fade-up">
            <div>
                <h2 class="fw-bold text-dark mb-1">Mitra Supplier 🚛</h2>
                <p class="text-muted mb-0">Kelola dan pantau seluruh rantai pasokan inventaris Petshop Pro.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nama atau username..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <button type="button" class="btn btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambahSupplier">
                    <i class="fas fa-plus-circle me-2"></i> Tambah Mitra
                </button>
            </div>
        </div>

        <!-- STATS SECTION (6 CARDS GRID MATCHING INVENTORY STYLE) -->
        <div class="stats-grid">
            <!-- STAT 1: TOTAL SUPPLIER -->
            <div class="card card-stat card-stat-total animate-fade-up delay-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Total Supplier</p>
                        <h2 class="stat-value mb-0" id="stat-total"><?= $total_s ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-total">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: var(--primary-emerald);"></div>
                </div>
            </div>

            <!-- STAT 2: STATUS AKTIF -->
            <div class="card card-stat card-stat-aktif animate-fade-up delay-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Status Aktif</p>
                        <h2 class="stat-value mb-0 text-success" id="stat-aktif"><?= $total_a ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-aktif">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-aktif" style="width: <?= $pct_aktif ?>%; background: var(--success-dark);"></div>
                </div>
            </div>

            <!-- STAT 3: STATUS NON-AKTIF -->
            <div class="card card-stat card-stat-nonaktif animate-fade-up delay-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Non-Aktif</p>
                        <h2 class="stat-value mb-0 text-muted" id="stat-off"><?= $total_o ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-nonaktif">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-off" style="width: <?= $pct_off ?>%; background: #64748b;"></div>
                </div>
            </div>

            <!-- STAT 4: TOTAL CP / PIC -->
            <div class="card card-stat card-stat-cp animate-fade-up delay-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Memiliki PIC/CP</p>
                        <h2 class="stat-value mb-0 text-info" id="stat-cp"><?= $total_cp ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-cp">
                        <i class="fas fa-id-badge"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-cp" style="width: <?= $pct_cp ?>%; background: var(--info-cyan);"></div>
                </div>
            </div>

            <!-- STAT 5: REKENING TERDAFTAR -->
            <div class="card card-stat card-stat-bank animate-fade-up delay-5">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Rekening Bank</p>
                        <h2 class="stat-value mb-0 text-warning" id="stat-bank"><?= $total_rek ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-bank">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: <?= $total_s > 0 ? round(($total_rek / $total_s) * 100) : 0 ?>%; background: var(--warning-amber);"></div>
                </div>
            </div>

            <!-- STAT 6: JANGKAUAN KOTA -->
            <div class="card card-stat card-stat-kota animate-fade-up delay-6">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Kota Jangkauan</p>
                        <h2 class="stat-value mb-0 text-indigo" id="stat-kota"><?= $total_cities ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-kota">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: #6366f1;"></div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="glass-card p-4 animate-fade-up delay-5">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Supplier Aktif
                    <span class="badge bg-light text-success border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="filter-container">
                        <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua Status</a>
                        <a href="?page=1&status_filter=Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Aktif') ? 'active' : '' ?>">Aktif</a>
                        <a href="?page=1&status_filter=Non-Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Non-Aktif') ? 'active' : '' ?>">Non-Aktif</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">No</th>
                            <th style="width: 30%;">Informasi Perusahaan</th>
                            <th style="width: 25%;">Kontak & Alamat</th>
                            <th style="width: 15%;">Username</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-center" style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="supplier-tbody">
                        <?php
                        $no = 1 + $offset;
                        if (empty($paged_suppliers)) {
                            echo '<tr><td colspan="6" class="text-center py-5 text-muted">Tidak ada data supplier pada halaman ini...</td></tr>';
                        } else {
                            foreach ($paged_suppliers as $row) { 
                                $is_aktif = ($row['Sup_status'] == 'Aktif');
                            ?>
                            <tr class="align-middle supplier-row" id="row-<?= $row['ID_Supplier'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-container me-3">
                                            <div class="plg-avatar shadow-sm avatar-emerald">
                                                <?php if (!empty($row['Foto_Supplier']) && file_exists("../../assets/uploads/supplier/" . $row['Foto_Supplier'])): ?>
                                                    <img src="../../assets/uploads/supplier/<?= $row['Foto_Supplier'] ?>" alt="Logo">
                                                <?php else: ?>
                                                    <span class="avatar-initial"><?= getInitialsSupplier($row['Nama_Supplier']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                                        </div>
                                        <div class="text-truncate" style="max-width: 250px;">
                                            <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Supplier']) ?>"><?= htmlspecialchars($row['Nama_Supplier']) ?></div>
                                            <div class="text-muted small text-truncate"><?= htmlspecialchars($row['Email'] ?: 'Tidak ada email') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-bold text-emerald mb-1">
                                        <i class="fab fa-whatsapp me-1"></i> <?= htmlspecialchars($row['No_Telepon'] ?: '-') ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size: 0.75rem;" title="<?= htmlspecialchars($row['Alamat']) ?>">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($row['Alamat'] ?: '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-username text-truncate d-inline-block" style="max-width: 100%;">
                                        @<?= htmlspecialchars($row['Username'] ?? '') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Supplier'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Sup_status'] ?: 'Non-Aktif') ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        
                                        <!-- TOMBOL LIHAT DETAIL -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Supplier'] ?>"
                                                title="Lihat Detail Supplier">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOMBOL TOGGLE STATUS -->
                                        <a href="javascript:void(0)" 
                                           class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                                           data-id="<?= $row['ID_Supplier'] ?>"
                                           data-current="<?= htmlspecialchars($row['Sup_status'] ?: 'Non-Aktif') ?>"
                                           id="toggle-btn-<?= $row['ID_Supplier'] ?>"
                                           title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                                            <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </a>

                                        <!-- TOMBOL EDIT -->
                                        <a href="<?= $is_aktif ? 'supplier_read.php?id=' . $row['ID_Supplier'] : 'javascript:void(0)' ?>" 
                                           class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                                           id="edit-btn-<?= $row['ID_Supplier'] ?>"
                                           title="<?= $is_aktif ? 'Edit Mitra' : 'Supplier Non-Aktif tidak dapat diedit' ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>

                                        <!-- TOMBOL HAPUS -->
                                        <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                                                data-bs-toggle="modal" data-bs-target="#confirmModal" 
                                                data-href="supplier_delete.php?id=<?= $row['ID_Supplier'] ?>&type=soft"
                                                data-id="<?= $row['ID_Supplier'] ?>"
                                                data-action-type="softdelete"
                                                data-title="Hapus Supplier"
                                                data-message="Apakah Anda yakin ingin menghapus data supplier <b><?= htmlspecialchars($row['Nama_Supplier']) ?></b>?"
                                                data-color="btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

            <!-- KOMPONEN NAVIGASI PAGINATION SINKRON DENGAN CHIPS -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 px-2">
                <div class="text-muted small mb-2 mb-md-0">
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> supplier tersaring.
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

    <!-- MODAL DETAIL SUPPLIER -->
    <div class="modal fade" id="detailSupplierModal" tabindex="-1" aria-labelledby="detailSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0">
                <!-- Header Card Pusat -->
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div id="detail-avatar-container" class="mb-3">
                            <!-- Diisi via JS (Foto atau Inisial) -->
                        </div>
                        <h2 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Supplier</h2>
                        <span class="badge bg-light text-dark fw-bold mt-1" id="detail-username" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">@username</span>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start">
                    <div class="row g-4">
                        <!-- Informasi Perusahaan -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-building me-2"></i>Profil Perusahaan</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Email Kantor</td><td class="fw-bold text-dark" id="detail-email">-</td></tr>
                                    <tr><td class="text-muted">No. Telepon</td><td class="fw-bold text-dark" id="detail-telp">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Data Kontak CP (PIC) -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-id-badge me-2"></i>Kontak Person (CP / PIC)</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:45%;">Nama CP</td><td class="fw-bold text-dark" id="detail-nama-cp">-</td></tr>
                                    <tr><td class="text-muted">Jabatan CP</td><td class="fw-bold text-dark" id="detail-jabatan-cp">-</td></tr>
                                    <tr><td class="text-muted">No. Telepon CP</td><td class="fw-bold text-dark" id="detail-telp-cp">-</td></tr>
                                    <tr><td class="text-muted">Email CP</td><td class="fw-bold text-dark" id="detail-email-cp">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Rekening Bank Transfer -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-wallet me-2"></i>Informasi Rekening Bank Transfer</h6>
                                <div class="row g-2 small">
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Nama Bank</span>
                                        <strong class="text-dark" id="detail-nama-bank">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Nomor Rekening</span>
                                        <strong class="text-dark" id="detail-no-rekening">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Atas Nama Rekening</span>
                                        <strong class="text-dark" id="detail-an-rekening">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alamat Kantor Lengkap -->
                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-map-marker-alt me-2"></i>Informasi Alamat Lengkap</h6>
                                <div class="row g-2 small">
                                    <div class="col-12"><span class="text-muted d-block">Alamat Lengkap</span><strong class="text-dark" id="detail-alamat">-</strong></div>
                                    <div class="col-md-3 mt-3"><span class="text-muted d-block">Kelurahan</span><strong class="text-dark" id="detail-kelurahan">-</strong></div>
                                    <div class="col-md-3 mt-3"><span class="text-muted d-block">Kecamatan</span><strong class="text-dark" id="detail-kecamatan">-</strong></div>
                                    <div class="col-md-3 mt-3"><span class="text-muted d-block">Kota / Kabupaten</span><strong class="text-dark" id="detail-kota">-</strong></div>
                                    <div class="col-md-3 mt-3"><span class="text-muted d-block">Provinsi (Kode Pos)</span><strong class="text-dark"><span id="detail-provinsi">-</span> (<span id="detail-kodepos">-</span>)</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Tutup Detail</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL POP UP KONFIRMASI -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                <div class="modal-header border-0 bg-light py-3 px-4" style="border-top-left-radius: 24px; border-top-right-radius: 24px;">
                    <h5 class="modal-title fw-bold text-dark" id="confirmModalLabel">Konfirmasi Tindakan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 px-4 text-center">
                    <div class="mb-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(16, 185, 129, 0.08);">
                        <i class="fas fa-exclamation-triangle text-emerald fs-3"></i>
                    </div>
                    <p id="confirmMessageText" class="text-muted mb-0 fs-6">Apakah Anda yakin dengan tindakan ini?</p>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-center gap-2" style="border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-bold" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="button" id="confirmExecuteBtn" class="btn px-4 py-2 rounded-pill fw-bold text-white shadow-sm">Proses</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT JAVASCRIPT AJAX & PAGINATION UTAMA -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('supplier-tbody');
            const toastContainer = document.getElementById('toastContainer');
            const confirmModalElement = document.getElementById('confirmModal');
            const confirmExecuteBtn = document.getElementById('confirmExecuteBtn');
            
            let bsConfirmModalInstance = null;
            if (confirmModalElement) {
                bsConfirmModalInstance = new bootstrap.Modal(confirmModalElement);
            }
            
            let currentStatusFilter = '<?= htmlspecialchars($status_filter) ?>';

            let targetRowId = null;
            let targetActionUrl = '';
            let targetActionType = '';

            // Penunjuk batas indeks awal berdasarkan pagination php
            let tableStartNumber = <?= 1 + $offset ?>;

            // Fungsi untuk mengurutkan kembali nomor baris secara dinamis di klien
            function updateRowNumbers() {
                const rows = tbody.querySelectorAll('.supplier-row');
                rows.forEach((row, index) => {
                    const noCell = row.querySelector('td:first-child');
                    if (noCell) {
                        noCell.textContent = tableStartNumber + index;
                    }
                });
            }

            // Fungsi untuk memperbarui table secara asinkron (mengalirkan data secara dinamis)
            function refreshTable() {
                const urlParams = new URLSearchParams(window.location.search);
                // Mencegah cache browser agar selalu menarik data terbaru dari database
                urlParams.set('_t', Date.now());
                const url = `supplier_read.php?${urlParams.toString()}`;
                
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Perbarui Body Tabel
                        const newTbody = doc.getElementById('supplier-tbody');
                        if (newTbody && tbody) {
                            tbody.innerHTML = newTbody.innerHTML;
                        }
                        
                        // Perbarui Badge Jumlah Data
                        const newBadge = doc.getElementById('table-count-badge');
                        const currentBadge = document.getElementById('table-count-badge');
                        if (newBadge && currentBadge) {
                            currentBadge.textContent = newBadge.textContent;
                        }
                        
                        // Perbarui Info Pagination (Menampilkan X sampai Y...)
                        const infoContainer = document.querySelector('.glass-card .text-muted.small');
                        const newInfoContainer = doc.querySelector('.glass-card .text-muted.small');
                        if (infoContainer && newInfoContainer) {
                            infoContainer.innerHTML = newInfoContainer.innerHTML;
                        }
                        
                        // Perbarui Komponen Navigasi Halaman
                        const navContainer = document.querySelector('nav');
                        const newNavContainer = doc.querySelector('nav');
                        if (navContainer && newNavContainer) {
                            navContainer.innerHTML = newNavContainer.innerHTML;
                        } else if (!newNavContainer && navContainer) {
                            navContainer.innerHTML = ''; 
                        }

                        // Perbarui Kartu Statistik
                        const stats = ['stat-total', 'stat-aktif', 'stat-off', 'stat-cp', 'stat-bank', 'stat-kota'];
                        stats.forEach(id => {
                            const newVal = doc.getElementById(id);
                            if (newVal) {
                                animateStatTextUpdate(id, newVal.textContent.trim());
                            }
                        });

                        // Perbarui Progress Bars
                        const progressBars = ['progress-aktif', 'progress-off', 'progress-cp'];
                        progressBars.forEach(id => {
                            const newBar = doc.getElementById(id);
                            const currentBar = document.getElementById(id);
                            if (newBar && currentBar) {
                                currentBar.style.width = newBar.style.width;
                            }
                        });

                        // Update penomoran halaman kembali
                        updateRowNumbers();
                    })
                    .catch(error => {
                        console.error('Error refreshing table data:', error);
                    });
            }

            // Toast Notifikasi
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
                setTimeout(() => {
                    toastElement.classList.add('show');
                }, 50);

                setTimeout(() => {
                    toastElement.classList.remove('show');
                    setTimeout(() => {
                        toastElement.remove();
                    }, 400);
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

                fetch(`supplier_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        tbody.style.opacity = '1';
                        
                        // Setel penomoran awal ke 1 apabila mencari via ajax karena mem-bypass paginasi
                        tableStartNumber = 1;

                        const rows = tbody.querySelectorAll('.supplier-row');
                        document.getElementById('table-count-badge').textContent = rows.length;
                    })
                    .catch(error => {
                        console.error('Error Search AJAX:', error);
                        tbody.style.opacity = '1';
                        showToast('Gagal memuat data.', 'danger');
                    });
            }

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Supplier (Tombol Lihat)
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`supplier_read.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Supplier || '-';
                                document.getElementById('detail-username').textContent = d.Username ? '@' + d.Username : '(Portal Belum Terdaftar)';
                                document.getElementById('detail-email').textContent = d.Email || '-';
                                document.getElementById('detail-telp').textContent = d.No_Telepon || '-';
                                
                                document.getElementById('detail-nama-cp').textContent = d.Nama_CP || '-';
                                document.getElementById('detail-jabatan-cp').textContent = d.Jabatan_CP || '-';
                                document.getElementById('detail-telp-cp').textContent = d.No_Telepon_CP || '-';
                                document.getElementById('detail-email-cp').textContent = d.Email_CP || '-';

                                document.getElementById('detail-nama-bank').textContent = d.Nama_Bank || '-';
                                document.getElementById('detail-no-rekening').textContent = d.No_Rekening || '-';
                                document.getElementById('detail-an-rekening').textContent = d.Atas_Nama_Rekening || '-';
                                
                                document.getElementById('detail-alamat').textContent = d.Alamat || '-';
                                document.getElementById('detail-kelurahan').textContent = d.Kelurahan || '-';
                                document.getElementById('detail-kecamatan').textContent = d.Kecamatan || '-';
                                document.getElementById('detail-kota').textContent = d.Kota_Kabupaten || '-';
                                document.getElementById('detail-provinsi').textContent = d.Provinsi || '-';
                                document.getElementById('detail-kodepos').textContent = d.Kode_Pos || '-';

                                const modalAvatarContainer = document.getElementById('detail-avatar-container');
                                if (d.Foto_Supplier && d.Foto_Supplier !== '') {
                                    modalAvatarContainer.innerHTML = `<img src="../../assets/uploads/supplier/${d.Foto_Supplier}" style="width:90px; height:90px; border-radius:18px; object-fit:cover; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">`;
                                } else {
                                    const initials = getInitialsSupplierJs(d.Nama_Supplier);
                                    modalAvatarContainer.innerHTML = `<div class="plg-avatar avatar-emerald" style="width:90px; height:90px; border-radius:18px; font-size:2rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);"><span class="avatar-initial">${initials}</span></div>`;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailSupplierModal'));
                                detailModal.show();
                            } else {
                                showToast(res.message || 'Gagal mengambil data detail.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error detail fetch:', error);
                            showToast('Terjadi kesalahan koneksi.', 'danger');
                        })
                        .finally(() => {
                            icon.className = originalClass;
                            viewBtn.disabled = false;
                        });
                }
            });

            // Helper JS untuk inisial nama instan di sisi klien
            function getInitialsSupplierJs(name) {
                if (!name) return "?";
                const words = name.trim().split(/\s+/);
                let initials = words[0].charAt(0);
                if (words.length > 1) {
                    initials += words[words.length - 1].charAt(0);
                }
                return initials.toUpperCase();
            }

            // Animasi Letup Angka Statistik
            function animateStatTextUpdate(elementId, newValue) {
                const element = document.getElementById(elementId);
                if (element) {
                    const currentVal = element.textContent.trim();
                    if (currentVal !== String(newValue)) {
                        element.textContent = newValue;
                        element.classList.remove('pop-stat');
                        void element.offsetWidth;
                        element.classList.add('pop-stat');
                        
                        setTimeout(() => {
                            element.classList.remove('pop-stat');
                        }, 450);
                    }
                }
            }

            // Toggle Status
            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                
                if (toggleBtn) {
                    e.preventDefault();
                    
                    const id = toggleBtn.getAttribute('data-id');
                    const currentStatus = toggleBtn.getAttribute('data-current');
                    const parentRow = toggleBtn.closest('tr');
                    const indicator = parentRow.querySelector('.avatar-status-indicator');
                    
                    toggleBtn.classList.add('pop-effect');

                    fetch('supplier_toggle_status.php?id=' + id + '&current=' + encodeURIComponent(currentStatus))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const newStatus = data.new_status;

                                toggleBtn.setAttribute('data-current', newStatus);
                                toggleBtn.setAttribute('title', `Ubah Status ke ${newStatus === 'Aktif' ? 'Non-Aktif' : 'Aktif'}`);
                                
                                const icon = toggleBtn.querySelector('i');
                                if (newStatus === 'Aktif') {
                                    toggleBtn.className = 'btn-action toggle-status-btn btn-status-aktif';
                                    icon.className = 'fas fa-toggle-on';
                                    if(indicator) indicator.className = 'avatar-status-indicator status-online';
                                } else {
                                    toggleBtn.className = 'btn-action toggle-status-btn btn-status-off';
                                    icon.className = 'fas fa-toggle-off';
                                    if(indicator) indicator.className = 'avatar-status-indicator status-offline';
                                }

                                const editBtn = document.getElementById(`edit-btn-${id}`);
                                if (editBtn) {
                                    if (newStatus === 'Aktif') {
                                        editBtn.classList.remove('disabled');
                                        editBtn.setAttribute('href', `supplier_read.php?id=${id}`);
                                    } else {
                                        editBtn.classList.add('disabled');
                                        editBtn.setAttribute('href', 'javascript:void(0)');
                                    }
                                }

                                animateStatTextUpdate('stat-aktif', data.total_a);
                                animateStatTextUpdate('stat-off', data.total_o);

                                const totalVal = parseInt(document.getElementById('stat-total').textContent) || 1;
                                if(data.total_a !== undefined) {
                                    document.getElementById('progress-aktif').style.width = Math.round((data.total_a / totalVal) * 100) + '%';
                                }
                                if(data.total_o !== undefined) {
                                    document.getElementById('progress-off').style.width = Math.round((data.total_o / totalVal) * 100) + '%';
                                }

                                showToast(`Status supplier berhasil diubah ke ${newStatus}`, 'success');

                                if(currentStatusFilter !== '' && currentStatusFilter !== newStatus) {
                                    parentRow.style.opacity = '0';
                                    parentRow.style.transform = 'translateY(10px)';
                                    parentRow.style.transition = 'all 0.3s ease';
                                    setTimeout(() => {
                                        refreshTable();
                                    }, 300);
                                }
                            } else {
                                showToast('Gagal memperbarui status supplier.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error Toggle Status:', error);
                            showToast('Gagal memproses perubahan status.', 'danger');
                        })
                        .finally(() => {
                            setTimeout(() => {
                                toggleBtn.classList.remove('pop-effect');
                            }, 300);
                        });
                }
            });

            // Ganti Event Handler Konfirmasi Modal: Ambil data secara akurat menggunakan native event relatedTarget dari Bootstrap
            if (confirmModalElement) {
                confirmModalElement.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget; // Tombol Hapus asli yang diklik user
                    if (button) {
                        targetRowId = button.getAttribute('data-id');
                        targetActionUrl = button.getAttribute('data-href');
                        targetActionType = button.getAttribute('data-action-type');
                        
                        confirmModalElement.querySelector('#confirmModalLabel').textContent = button.getAttribute('data-title') || 'Konfirmasi';
                        confirmModalElement.querySelector('#confirmMessageText').innerHTML = button.getAttribute('data-message') || 'Apakah Anda yakin?';
                        confirmExecuteBtn.className = 'btn px-4 py-2 rounded-pill fw-bold text-white shadow-sm ' + (button.getAttribute('data-color') || 'btn-danger');
                    }
                });
            }

            if (confirmExecuteBtn) {
                confirmExecuteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!targetActionUrl) return;

                    confirmExecuteBtn.disabled = true;
                    confirmExecuteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';

                    // Tambahkan AJAX Flag dan Cache-Buster khusus pada proses Delete agar server wajib mengeksekusi penghapusan di database
                    let deleteUrl = targetActionUrl;
                    if (deleteUrl.indexOf('?') !== -1) {
                        deleteUrl += '&ajax=1&_t=' + Date.now();
                    } else {
                        deleteUrl += '?ajax=1&_t=' + Date.now();
                    }

                    fetch(deleteUrl, { 
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (bsConfirmModalInstance) bsConfirmModalInstance.hide();
                            
                            if (data.success) {
                                const targetRow = document.getElementById(`row-${targetRowId}`);
                                if (targetRow) {
                                    // Jalankan efek fade-out pada UI klien terlebih dahulu demi kenyamanan visual user
                                    targetRow.style.opacity = '0';
                                    targetRow.style.transform = 'translateX(50px) scale(0.95)';
                                    targetRow.style.transition = 'all 0.4s ease-out';
                                    
                                    setTimeout(() => {
                                        // Tarik data paling baru dari SQL Server secara asinkron
                                        refreshTable();
                                        showToast(data.message || 'Mitra Supplier berhasil dinonaktifkan!', 'success');
                                    }, 400);
                                } else {
                                    refreshTable();
                                    showToast(data.message || 'Mitra Supplier berhasil dinonaktifkan!', 'success');
                                }
                            } else {
                                showToast(data.message || 'Gagal menghapus mitra supplier.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error Action AJAX:', error);
                            showToast('Terjadi kesalahan saat memproses data.', 'danger');
                        })
                        .finally(() => {
                            confirmExecuteBtn.disabled = false;
                            confirmExecuteBtn.textContent = 'Proses';
                            targetRowId = null; targetActionUrl = ''; targetActionType = '';
                        });
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODAL POP UP MITRA SUPPLIER AKTIF DI BAWAH INI -->
    <?php include 'supplier_create.php'; ?>
    <?php include 'supplier_update.php'; ?>
</body>
</html>
