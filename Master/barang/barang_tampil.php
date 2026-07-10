<?php
ob_start(); // Tambahan pengaman output buffering di baris pertama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Proteksi Login & Otoritas Role
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

$role = $_SESSION['role'];

// Normalisasikan role pekerja ke Karyawan agar konsisten dengan navbar
$employee_roles = ['Staff', 'Karyawan', 'Kasir', 'Groomer', 'Dokter'];
if (in_array($role, $employee_roles)) { 
    $role = 'Karyawan'; 
}

// --- INTERSEPSI REQUEST POST (AJAX) HANYA UNTUK ADMIN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Admin') {
    if (isset($_POST['simpan'])) {
        include 'barang_tambah.php';
        exit;
    }
    if (isset($_POST['update'])) {
        include 'barang_edit.php';
        exit;
    }
}

// Fungsi helper untuk menghasilkan inisial nama barang secara aman
if (!function_exists('getInitialsBarang')) {
    function getInitialsBarang($name) {
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

// Ambil parameter untuk inisialisasi awal
$search = isset($_GET['search']) ? $_GET['search'] : '';
$kategori_filter = isset($_GET['kategori_filter']) ? $_GET['kategori_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --- KONFIGURASI PAGINATION (10 Data Per Halaman) ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- HANDLER AJAX DETAIL BARANG (Menggunakan sp_Barang_Read via CALL) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "{CALL sp_Barang_Read(?, NULL, NULL)}";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX UNTUK LIVE SEARCH & FILTER (Output HTML) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $sql = "{CALL sp_Barang_Read(NULL, ?, ?)}";
    $query = sqlsrv_query($conn, $sql, array(
        !empty($kategori_filter) ? (int)$kategori_filter : null,
        !empty($search) ? $search : null
    ));
    
    if ($query === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }

    $records = [];
    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        // Penyaringan filter status di sisi PHP
        if ($status_filter != '') {
            if ($status_filter === 'Kritis' && $row['Status_Stok'] !== 'Stok Rendah' && $row['Status_Stok'] !== 'Habis') {
                continue;
            }
            if ($status_filter === 'Aman' && $row['Status_Stok'] !== 'Aman' && $row['Status_Stok'] !== 'Tidak Ada Minimum') {
                continue;
            }
            if ($status_filter !== 'Kritis' && $status_filter !== 'Aman' && $row['Bar_status'] !== $status_filter) {
                continue;
            }
        }
        $records[] = $row;
    }

    // Urutkan data terbaru di atas (ID_Barang Descending)
    usort($records, function($a, $b) {
        return (int)$b['ID_Barang'] <=> (int)$a['ID_Barang'];
    });

    $no = 1;
    if (empty($records)) {
        echo '<tr>
                <td colspan="7" class="text-center py-5">
                    <div class="empty-state animate-fade-in">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-boxes fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Produk tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter klasifikasi lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    foreach ($records as $row) { 
        $is_aktif = (($row['Bar_status'] ?? 'Aktif') == 'Aktif');
        $is_kritis = ($row['Status_Stok'] == 'Habis' || $row['Status_Stok'] == 'Stok Rendah');
        $foto_db = $row['Foto_Barang'] ?? null;
        $foto_path = "../../uploads/barang/" . $foto_db;
        ?>
        <tr class="align-middle barang-row animate-fade-up" id="row-<?= $row['ID_Barang'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-container me-3">
                        <?php if (!empty($foto_db) && file_exists($foto_path)): ?>
                            <img src="<?= $foto_path ?>" class="brg-avatar shadow-sm border border-light" alt="Foto">
                        <?php else: ?>
                            <div class="avatar-initials-circle">
                                <?= getInitialsBarang($row['Nama_Barang']) ?>
                            </div>
                        <?php endif; ?>
                        <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                    </div>
                    <div class="text-truncate" style="max-width: 250px;">
                        <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Barang']) ?>"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge badge-kategori text-truncate d-inline-block">
                    <i class="fas fa-tag me-1 text-emerald"></i> <?= htmlspecialchars($row['Nama_Kategori'] ?: 'Umum') ?>
                </span>
            </td>
            <td class="text-end fw-bold text-emerald">
                Rp <?= number_format($row['Harga_Jual'], 0, ',', '.') ?>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $is_kritis ? 'pill-kritis' : 'pill-aman' ?>" id="stok-pill-<?= $row['ID_Barang'] ?>">
                    <?= $is_kritis ? '⚠️ KRITIS: ' : '✓ AMAN: ' ?><?= $row['Stok'] ?>
                </span>
            </td>
            <td class="text-center text-muted small fw-bold"><?= htmlspecialchars($row['Satuan'] ?: 'Pcs') ?></td>
            <?php if($role == 'Admin') : ?>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    
                    <!-- TOMBOL LIHAT DETAIL -->
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Barang'] ?>"
                            title="Lihat Detail Produk">
                        <i class="fas fa-eye"></i>
                    </button>

                    <!-- TOMBOL STATUS SAKLAR (TOGGLE STATUS BARANG) -->
                    <a href="javascript:void(0)" 
                       class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                       data-id="<?= $row['ID_Barang'] ?>"
                       data-current="<?= htmlspecialchars($row['Bar_status'] ?: 'Non-Aktif') ?>"
                       id="toggle-btn-<?= $row['ID_Barang'] ?>"
                       title="<?= $is_aktif ? 'Tangguhkan Penjualan Produk' : 'Aktifkan Penjualan Produk' ?>">
                        <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>

                    <!-- TOMBOL EDIT -->
                    <a href="<?= $is_aktif ? 'barang_tampil.php?id=' . $row['ID_Barang'] : 'javascript:void(0)' ?>" 
                       class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                       id="edit-btn-<?= $row['ID_Barang'] ?>"
                       title="<?= $is_aktif ? 'Edit Data Produk' : 'Produk Non-Aktif tidak dapat diedit' ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </a>

                    <!-- TOMBOL HAPUS -->
                    <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                            data-bs-toggle="modal" data-bs-target="#confirmModal" 
                            data-href="barang_hapus.php?id=<?= $row['ID_Barang'] ?>"
                            data-id="<?= $row['ID_Barang'] ?>"
                            data-title="Hapus Produk"
                            data-message="Apakah Anda yakin ingin menghapus produk <b><?= htmlspecialchars($row['Nama_Barang']) ?></b> secara permanen?"
                            data-color="btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
            <?php endif; ?>
        </tr>
        <?php
    }
    exit;
}

// --- AMBIL SEMUA DATA SESUAI FILTER UNTUK PAGINASI LOKAL DAN REKAPITULASI ---
$query_all = sqlsrv_query($conn, "{CALL sp_Barang_Read(NULL, ?, ?)}", array(
    !empty($kategori_filter) ? (int)$kategori_filter : null,
    !empty($search) ? $search : null
));

if ($query_all === false) {
    die(print_r(sqlsrv_errors(), true));
}

$all_records = [];
while ($row = sqlsrv_fetch_array($query_all, SQLSRV_FETCH_ASSOC)) {
    // Saring filter status di sisi PHP
    if ($status_filter != '') {
        if ($status_filter === 'Kritis' && $row['Status_Stok'] !== 'Stok Rendah' && $row['Status_Stok'] !== 'Habis') {
            continue;
        }
        if ($status_filter === 'Aman' && $row['Status_Stok'] !== 'Aman' && $row['Status_Stok'] !== 'Tidak Ada Minimum') {
            continue;
        }
        if ($status_filter !== 'Kritis' && $status_filter !== 'Aman' && $row['Bar_status'] !== $status_filter) {
            continue;
        }
    }
    $all_records[] = $row;
}

// Urutkan data terbaru di atas (ID_Barang Descending)
usort($all_records, function($a, $b) {
    return (int)$b['ID_Barang'] <=> (int)$a['ID_Barang'];
});

$total_records = count($all_records);
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Iris data untuk paginasi lokal halaman saat ini
$paginated_records = array_slice($all_records, $offset, $limit);

// --- PERHITUNGAN STATISTIK LOKAL DARI HASIL KESELURUHAN DATA AKTIF ---
$query_stats = sqlsrv_query($conn, "{CALL sp_Barang_Read(NULL, NULL, NULL)}");
$total_b = 0;
$total_k = 0;
$total_unit_stok = 0;
$total_ba = 0;
$total_bna = 0;

if ($query_stats !== false) {
    while ($r = sqlsrv_fetch_array($query_stats, SQLSRV_FETCH_ASSOC)) {
        $total_b++;
        $total_unit_stok += (int)$r['Stok'];
        if ($r['Status_Stok'] == 'Habis' || $r['Status_Stok'] == 'Stok Rendah') {
            $total_k++;
        }
        if ($r['Bar_status'] == 'Aktif') {
            $total_ba++;
        } else {
            $total_bna++;
        }
    }
}

// Hitung Kategori menggunakan query Kategori biasa (Tipe='Barang')
$sql_kat = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Kategori WHERE Tipe_Kategori = 'Barang' AND (Kat_is_deleted = 0 OR Kat_is_deleted IS NULL)");
$total_kt = sqlsrv_fetch_array($sql_kat, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

$pct_kritis = $total_b > 0 ? round(($total_k / $total_b) * 100) : 0;
$pct_aktif = $total_b > 0 ? round(($total_ba / $total_b) * 100) : 0;
$pct_nonaktif = $total_b > 0 ? round(($total_bna / $total_b) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Inventaris | Petshop Pro</title>
    <!-- CSS Dependencies -->
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
            box-shadow: var(--card-shadow);
        }
        .card-stat-total:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-kritis:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.25); }
        .card-stat-kat:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(6, 182, 212, 0.15); border-color: rgba(6, 182, 212, 0.25); }
        .card-stat-stokunit:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.25); }
        .card-stat-aktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-nonaktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(100, 116, 139, 0.15); border-color: rgba(100, 116, 139, 0.25); }

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
        .icon-box-kritis { background: linear-gradient(135deg, #fee2e2, #fca5a5); color: var(--danger-red); }
        .icon-box-kat { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: var(--info-cyan); }
        .icon-box-stokunit { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #6366f1; }
        .icon-box-aktif { background: linear-gradient(135deg, #d1fae5, #34d399); color: var(--success-dark); }
        .icon-box-nonaktif { background: linear-gradient(135deg, #f1f5f9, #cbd5e1); color: #64748b; }

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

        /* AVATAR INISIAL BARANG */
        .avatar-container {
            position: relative;
            width: 48px;
            height: 48px;
        }
        .brg-avatar {
            width: 100%; height: 100%; border-radius: 12px;
            object-fit: cover;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }
        .avatar-initials-circle {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
            font-size: 1.15rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            border: 2px solid #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            overflow: hidden;
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
        .status-online { background-color: #10b981; }
        .status-offline { background-color: #94a3b8; }

        .badge-kategori {
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
            width: 125px; 
            height: 32px;
            box-sizing: border-box;
            transition: all 0.3s var(--ease-out-expo);
        }
        .pill-kritis { background: rgba(239, 68, 68, 0.1) !important; color: #b91c1c !important; border: 1.5px solid rgba(239, 68, 68, 0.25); }
        .pill-aman { background: rgba(16, 185, 129, 0.1) !important; color: #047857 !important; border: 1.5px solid rgba(16, 185, 129, 0.25); }

        /* Action Buttons */
        .action-gap { gap: 6px; }
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

        /* PRESERVASI STYLE MODAL DETAIL - SEJAJAR & KETENGAH */
        #detailBarangModal {
            z-index: 1060 !important;
            backdrop-filter: blur(8px);
            background-color: rgba(15, 23, 42, 0.4);
        }

        @media (min-width: 992px) {
            #detailBarangModal {
                padding-left: 260px !important; 
            }
        }

        #detailBarangModal.show .modal-content-custom {
            animation: modalZoomInBarang 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
                <h2 class="fw-bold text-dark mb-1">Manajemen Inventaris Gudang 📦</h2>
                <p class="text-muted mb-0">Kelola persediaan barang, pantau limit stok minimum, dan kontrol harga produk.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nama barang..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <?php if($role == 'Admin') : ?>
                <!-- TOMBOL TAMBAH PRODUK -->
                <button type="button" class="btn btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambahBarang">
                    <i class="fas fa-plus-circle me-2"></i> Tambah Produk
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS SECTION -->
        <div class="stats-grid">
            
            <!-- STAT 1: TOTAL PRODUK -->
            <div class="card card-stat card-stat-total animate-fade-up delay-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Total Produk</p>
                        <h2 class="stat-value mb-0" id="stat-total"><?= $total_b ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-total">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: var(--primary-emerald);"></div>
                </div>
            </div>

            <!-- STAT 2: STOK KRITIS -->
            <div class="card card-stat card-stat-kritis animate-fade-up delay-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Stok Kritis</p>
                        <h2 class="stat-value mb-0 text-danger" id="stat-kritis"><?= $total_k ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-kritis">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-kritis" style="width: <?= $pct_kritis ?>%; background: var(--danger-red);"></div>
                </div>
            </div>

            <!-- STAT 3: TOTAL KATEGORI -->
            <div class="card card-stat card-stat-kat animate-fade-up delay-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Kategori Produk</p>
                        <h2 class="stat-value mb-0" id="stat-kat"><?= $total_kt ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-kat">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: var(--info-cyan);"></div>
                </div>
            </div>

            <!-- STAT 4: TOTAL UNIT STOK -->
            <div class="card card-stat card-stat-stokunit animate-fade-up delay-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Kuantitas Stok (Unit)</p>
                        <h2 class="stat-value mb-0 text-indigo" id="stat-totalunit"><?= $total_unit_stok ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-stokunit">
                        <i class="fas fa-warehouse"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: #6366f1;"></div>
                </div>
            </div>

            <!-- STAT 5: PRODUK AKTIF -->
            <div class="card card-stat card-stat-aktif animate-fade-up delay-5">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Produk Aktif</p>
                        <h2 class="stat-value mb-0 text-success" id="stat-aktif"><?= $total_ba ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-aktif">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-aktif" style="width: <?= $pct_aktif ?>%; background: var(--success-dark);"></div>
                </div>
            </div>

            <!-- STAT 6: PRODUK NON-AKTIF -->
            <div class="card card-stat card-stat-nonaktif animate-fade-up delay-6">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Ditangguhkan</p>
                        <h2 class="stat-value mb-0 text-muted" id="stat-nonaktif"><?= $total_bna ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-nonaktif">
                        <i class="fas fa-ban"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-nonaktif" style="width: <?= $pct_nonaktif ?>%; background: #64748b;"></div>
                </div>
            </div>

        </div>

        <!-- TABLE SECTION -->
        <div class="glass-card p-4 animate-fade-up delay-5">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Inventaris Produk
                    <span class="badge bg-light text-success border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="filter-container">
                        <!-- Integrasi URL Reload pada Filter status barang agar Pagination Sinkron Sempurna -->
                        <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua</a>
                        <a href="?page=1&status_filter=Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Aktif') ? 'active' : '' ?>">Aktif</a>
                        <a href="?page=1&status_filter=Non-Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Non-Aktif') ? 'active' : '' ?>">Non-Aktif</a>
                        <a href="?page=1&status_filter=Kritis&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Kritis') ? 'active' : '' ?>">🔴 Stok Kritis</a>
                        <a href="?page=1&status_filter=Aman&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Aman') ? 'active' : '' ?>">🟢 Stok Aman</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
               <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">No</th>
                            <th style="width: 32%;">Profil Produk</th>
                            <th style="width: 18%;">Kategori</th>
                            <th class="text-end" style="width: 15%;">Harga Jual</th>
                            <th class="text-center" style="width: 15%;">Status Stok</th>
                            <th class="text-center" style="width: 10%;">Satuan</th>
                            <?php if($role == 'Admin') : ?>
                            <th class="text-center" style="width: 200px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="barang-tbody">
                        <?php
                        $no = 1 + $offset;
                        if (empty($paginated_records)) {
                            echo '<tr><td colspan="7" class="text-center py-5 text-muted">Tidak ada data produk pada halaman ini...</td></tr>';
                        } else {
                            foreach($paginated_records as $row) { 
                                $is_aktif = (($row['Bar_status'] ?? 'Aktif') == 'Aktif');
                                $is_kritis = ($row['Status_Stok'] == 'Habis' || $row['Status_Stok'] == 'Stok Rendah');
                                $foto_db = $row['Foto_Barang'] ?? null;
                                $foto_path = "../../uploads/barang/" . $foto_db;
                            ?>
                            <tr class="align-middle barang-row" id="row-<?= $row['ID_Barang'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-container me-3">
                                            <?php if (!empty($foto_db) && file_exists($foto_path)): ?>
                                                <img src="<?= $foto_path ?>" class="brg-avatar shadow-sm border border-light" alt="Foto">
                                            <?php else: ?>
                                                <div class="avatar-initials-circle">
                                                    <?= getInitialsBarang($row['Nama_Barang']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                                        </div>
                                        <div class="text-truncate" style="max-width: 250px;">
                                            <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Barang']) ?>"><?= htmlspecialchars($row['Nama_Barang']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-kategori text-truncate d-inline-block">
                                        <i class="fas fa-tag me-1 text-emerald"></i> <?= htmlspecialchars($row['Nama_Kategori'] ?: 'Umum') ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-emerald">
                                    Rp <?= number_format($row['Harga_Jual'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $is_kritis ? 'pill-kritis' : 'pill-aman' ?>" id="stok-pill-<?= $row['ID_Barang'] ?>">
                                        <?= $is_kritis ? '⚠️ KRITIS: ' : '✓ AMAN: ' ?><?= $row['Stok'] ?>
                                    </span>
                                </td>
                                <td class="text-center text-muted small fw-bold"><?= htmlspecialchars($row['Satuan'] ?: 'Pcs') ?></td>
                                <?php if($role == 'Admin') : ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        
                                        <!-- TOMBOL LIHAT DETAIL -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Barang'] ?>"
                                                title="Lihat Detail Produk">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOMBOL SAKLAR STATUS BARANG -->
                                        <a href="javascript:void(0)" 
                                           class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                                           data-id="<?= $row['ID_Barang'] ?>"
                                           data-current="<?= htmlspecialchars($row['Bar_status'] ?: 'Non-Aktif') ?>"
                                           id="toggle-btn-<?= $row['ID_Barang'] ?>"
                                           title="<?= $is_aktif ? 'Tangguhkan Penjualan Produk' : 'Aktifkan Penjualan Produk' ?>">
                                            <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </a>

                                        <!-- TOMBOL EDIT -->
                                        <a href="<?= $is_aktif ? 'barang_tampil.php?id=' . $row['ID_Barang'] : 'javascript:void(0)' ?>" 
                                           class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                                           id="edit-btn-<?= $row['ID_Barang'] ?>"
                                           title="<?= $is_aktif ? 'Edit Data Produk' : 'Produk Non-Aktif tidak dapat diedit' ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>

                                        <!-- TOMBOL HAPUS -->
                                        <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                                                data-bs-toggle="modal" data-bs-target="#confirmModal" 
                                                data-href="barang_hapus.php?id=<?= $row['ID_Barang'] ?>"
                                                data-id="<?= $row['ID_Barang'] ?>"
                                                data-title="Hapus Produk"
                                                data-message="Apakah Anda yakin ingin menghapus produk <b><?= htmlspecialchars($row['Nama_Barang']) ?></b> secara permanen?"
                                                data-color="btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
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
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> produk tersaring.
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

    <!-- MODAL DETAIL PRODUK BARANG (ALIGNMENT & BACKDROP KETENGAH) -->
    <div class="modal fade" id="detailBarangModal" tabindex="-1" aria-labelledby="detailBarangModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0">
                <!-- Header Card Pusat -->
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div id="detail-avatar-container" class="mb-3">
                            <!-- Diisi via JS (Foto atau Inisial) -->
                        </div>
                        <h2 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Produk</h2>
                        <span class="badge bg-light text-dark fw-bold mt-1" id="detail-sku" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">SKU: -</span>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start">
                    <div class="row g-4">
                        <!-- Detail Identitas Produk -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-box me-2"></i>Identitas Produk</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Kategori</td><td class="fw-bold text-dark" id="detail-kategori">-</td></tr>
                                    <tr><td class="text-muted">Satuan Unit</td><td class="fw-bold text-dark" id="detail-satuan">-</td></tr>
                                    <tr><td class="text-muted">Status Produk</td><td class="fw-bold text-dark" id="detail-status">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Data Finansial -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-coins me-2"></i>Finansial Produk</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:45%;">Harga Beli</td><td class="fw-bold text-dark" id="detail-harga-beli">-</td></tr>
                                    <tr><td class="text-muted">Harga Jual</td><td class="fw-bold text-emerald" id="detail-harga-jual">-</td></tr>
                                    <tr><td class="text-muted">Potensi Margin</td><td class="fw-bold text-primary" id="detail-profit-margin">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Inventaris & Stok -->
                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-warehouse me-2"></i>Status Kuantitas Stok Gudang</h6>
                                <div class="row g-2 small text-center">
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Stok Fisik Saat Ini</span>
                                        <strong class="text-dark fs-5" id="detail-stok-gudang">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Limit Batas Minimum</span>
                                        <strong class="text-muted fs-5" id="detail-stok-minimum">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <span class="text-muted d-block">Kondisi Stok</span>
                                        <span id="detail-kondisi-stok" style="font-weight: 700;">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deskripsi Produk -->
                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-success mb-3"><i class="fas fa-file-alt me-2"></i>Deskripsi & Spesifikasi Produk</h6>
                                <p class="text-muted small mb-0" id="detail-deskripsi" style="line-height: 1.6;">-</p>
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
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="button" id="confirmExecuteBtn" class="btn px-4 py-2 rounded-pill fw-bold text-white shadow-sm">Proses</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS AJAX LIVE SEARCH, TOGGLE STATUS, LIHAT DETAIL DAN SEAMLESS DELETE -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('barang-tbody');
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

            // Penunjuk batas indeks awal berdasarkan pagination php
            let tableStartNumber = <?= 1 + $offset ?>;

            // Fungsi untuk mengurutkan kembali nomor baris secara dinamis di klien
            function updateRowNumbers() {
                const rows = tbody.querySelectorAll('.barang-row');
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
                const url = `barang_tampil.php?${urlParams.toString()}`;
                
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Perbarui Body Tabel
                        const newTbody = doc.getElementById('barang-tbody');
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

                        // Update penomoran halaman kembali
                        updateRowNumbers();
                    })
                    .catch(error => {
                        console.error('Error refreshing table data:', error);
                    });
            }

            // Sistem Toast Modern Emerald
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

            // Fungsi Debounce
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

                fetch(`barang_tampil.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        tbody.style.opacity = '1';
                        
                        // Setel penomoran awal ke 1 apabila mencari via ajax karena mem-bypass paginasi
                        tableStartNumber = 1;

                        const rows = tbody.querySelectorAll('.barang-row');
                        document.getElementById('table-count-badge').textContent = rows.length;
                    })
                    .catch(error => {
                        console.error('Error Search AJAX:', error);
                        tbody.style.opacity = '1';
                        showToast('Gagal memuat data produk.', 'danger');
                    });
            }

            if(searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Barang (Menggunakan sp_Barang_Read via AJAX)
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`barang_tampil.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Barang || '-';
                                document.getElementById('detail-sku').textContent = 'SKU: ' + (d.Kode_Barang || '-');
                                document.getElementById('detail-kategori').textContent = d.Nama_Kategori || 'Umum';
                                document.getElementById('detail-satuan').textContent = d.Satuan || 'Pcs';
                                document.getElementById('detail-status').textContent = d.Bar_status || 'Aktif';
                                
                                // Format Keuangan Rupiah
                                const formatRupiah = (val) => 'Rp ' + parseFloat(val).toLocaleString('id-ID');
                                const valBeli = parseFloat(d.Harga_Beli) || 0;
                                const valJual = parseFloat(d.Harga_Jual) || 0;
                                const valProfit = valJual - valBeli;

                                document.getElementById('detail-harga-beli').textContent = formatRupiah(valBeli);
                                document.getElementById('detail-harga-jual').textContent = formatRupiah(valJual);
                                document.getElementById('detail-profit-margin').textContent = formatRupiah(valProfit);

                                document.getElementById('detail-stok-gudang').textContent = (d.Stok ?? 0) + ' ' + (d.Satuan || 'Pcs');
                                document.getElementById('detail-stok-minimum').textContent = (d.Stok_Minimum ?? 0) + ' ' + (d.Satuan || 'Pcs');
                                
                                const isKritis = d.Status_Stok === 'Habis' || d.Status_Stok === 'Stok Rendah';
                                const kondisiSpan = document.getElementById('detail-kondisi-stok');
                                if (isKritis) {
                                    kondisiSpan.textContent = '🔴 ' + d.Status_Stok.toUpperCase() + ' (Perlu Order)';
                                    kondisiSpan.className = 'text-danger';
                                } else {
                                    kondisiSpan.textContent = '🟢 AMAN (Stok Cukup)';
                                    kondisiSpan.className = 'text-success';
                                }

                                document.getElementById('detail-deskripsi').textContent = d.Deskripsi || 'Tidak ada deskripsi spesifikasi untuk produk ini.';

                                const modalAvatarContainer = document.getElementById('detail-avatar-container');
                                if (d.Foto_Barang && d.Foto_Barang !== '') {
                                    modalAvatarContainer.innerHTML = `<img src="../../uploads/barang/${d.Foto_Barang}" style="width:90px; height:90px; border-radius:18px; object-fit:cover; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">`;
                                } else {
                                    const initials = getInitialsBarangJs(d.Nama_Barang);
                                    modalAvatarContainer.innerHTML = `<div class="avatar-initials-circle" style="width:90px; height:90px; border-radius:18px; font-size:2rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">${initials}</div>`;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailBarangModal'));
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
            function getInitialsBarangJs(name) {
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
                        void element.offsetWidth; // Memicu reflow
                        element.classList.add('pop-stat');
                        
                        setTimeout(() => {
                            element.classList.remove('pop-stat');
                        }, 450);
                    }
                }
            }

            // --- SISTEM TOGGLE STATUS AJAX BARANG ---
            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                
                if (toggleBtn) {
                    e.preventDefault();
                    
                    const id = toggleBtn.getAttribute('data-id');
                    const currentStatus = toggleBtn.getAttribute('data-current');
                    const parentRow = toggleBtn.closest('tr');
                    const indicator = parentRow.querySelector('.avatar-status-indicator');
                    
                    toggleBtn.classList.add('pop-effect');

                    fetch(`barang_toggle_status.php?id=${id}&current=${encodeURIComponent(currentStatus)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const newStatus = data.new_status;

                                toggleBtn.setAttribute('data-current', newStatus);
                                toggleBtn.setAttribute('title', newStatus === 'Aktif' ? 'Tangguhkan Penjualan Produk' : 'Aktifkan Penjualan Produk');
                                
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
                                        editBtn.setAttribute('href', `barang_tampil.php?id=${id}`);
                                    } else {
                                        editBtn.classList.add('disabled');
                                        editBtn.setAttribute('href', 'javascript:void(0)');
                                    }
                                }

                                animateStatTextUpdate('stat-aktif', data.total_ba);
                                animateStatTextUpdate('stat-nonaktif', data.total_bna);

                                const totalVal = parseInt(document.getElementById('stat-total').textContent) || 1;
                                if (data.total_ba !== undefined) {
                                    document.getElementById('progress-aktif').style.width = Math.round((data.total_ba / totalVal) * 100) + '%';
                                }
                                if (data.total_bna !== undefined) {
                                    document.getElementById('progress-nonaktif').style.width = Math.round((data.total_bna / totalVal) * 100) + '%';
                                }

                                showToast(`Produk berhasil diubah menjadi ${newStatus}`, 'success');

                                if (currentStatusFilter !== '' && currentStatusFilter !== newStatus && currentStatusFilter !== 'Kritis' && currentStatusFilter !== 'Aman') {
                                    parentRow.style.opacity = '0';
                                    parentRow.style.transform = 'translateY(10px)';
                                    setTimeout(() => {
                                        refreshTable();
                                    }, 300);
                                }
                            } else {
                                showToast('Gagal memperbarui status produk.', 'danger');
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

            // Delegasi Hapus
            tbody.addEventListener('click', function(e) {
                const triggerBtn = e.target.closest('.delete-trigger-btn');
                if (triggerBtn) {
                    targetRowId = triggerBtn.getAttribute('data-id');
                    targetActionUrl = triggerBtn.getAttribute('data-href');
                }
            });

            if (confirmModalElement) {
                confirmModalElement.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    confirmModalElement.querySelector('#confirmModalLabel').textContent = button.getAttribute('data-title');
                    confirmModalElement.querySelector('#confirmMessageText').innerHTML = button.getAttribute('data-message');
                    confirmExecuteBtn.className = 'btn px-4 py-2 rounded-pill fw-bold text-white shadow-sm ' + (button.getAttribute('data-color') || 'btn-danger');
                });
            }

            if (confirmExecuteBtn) {
                confirmExecuteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!targetActionUrl) return;

                    confirmExecuteBtn.disabled = true;
                    confirmExecuteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';

                    fetch(targetActionUrl, { method: 'GET' })
                        .then(response => response.json())
                        .then(data => {
                            if (bsConfirmModalInstance) bsConfirmModalInstance.hide();
                            
                            if (data.success) {
                                const targetRow = document.getElementById(`row-${targetRowId}`);
                                if (targetRow) {
                                    targetRow.style.opacity = '0';
                                    targetRow.style.transform = 'translateX(50px) scale(0.95)';
                                    targetRow.style.transition = 'all 0.4s ease-out';
                                    
                                    setTimeout(() => {
                                        refreshTable();
                                    }, 400);
                                }

                                animateStatTextUpdate('stat-total', data.total_b);
                                animateStatTextUpdate('stat-kritis', data.total_k);
                                animateStatTextUpdate('stat-totalunit', data.total_unit_stok);
                                animateStatTextUpdate('stat-aktif', data.total_ba);
                                animateStatTextUpdate('stat-nonaktif', data.total_bna);

                                const totalCount = parseInt(data.total_b) || 0;
                                if (document.getElementById('progress-kritis')) {
                                    document.getElementById('progress-kritis').style.width = (totalCount > 0 ? Math.round((data.total_k / totalCount) * 100) : 0) + '%';
                                }
                                if (document.getElementById('progress-aktif')) {
                                    document.getElementById('progress-aktif').style.width = (totalCount > 0 ? Math.round((data.total_ba / totalCount) * 100) : 0) + '%';
                                }
                                if (document.getElementById('progress-nonaktif')) {
                                    document.getElementById('progress-nonaktif').style.width = (totalCount > 0 ? Math.round((data.total_bna / totalCount) * 100) : 0) + '%';
                                }

                                showToast('Produk berhasil dihapus secara permanen dari gudang', 'success');
                            } else {
                                showToast('Gagal menghapus produk.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error Action AJAX:', error);
                            showToast('Terjadi kesalahan saat memproses data.', 'danger');
                        })
                        .finally(() => {
                            confirmExecuteBtn.disabled = false;
                            confirmExecuteBtn.textContent = 'Proses';
                            targetRowId = null; targetActionUrl = '';
                        });
                });
            }
        });
    </script>
   
    <!-- INKLUSI DINAMIS POP-UP MODAL (TAMBAH & EDIT) HANYA UNTUK ADMIN -->
    <?php if ($role === 'Admin') : ?>
        <?php include 'barang_tambah.php'; ?>
        <?php include 'barang_edit.php'; ?>
    <?php endif; ?>
</body>
</html>