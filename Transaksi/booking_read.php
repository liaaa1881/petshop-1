<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Menggunakan include_once untuk mencegah penulisan ulang fungsi koneksi database
include_once '../config/koneksi.php';

// Proteksi Admin / User
if (!isset($_SESSION['role'])) { 
    header("Location: ../../auth/login.php"); 
    exit; 
}

// Ambil data role pengguna untuk proteksi hak akses
$user_role = strtoupper($_SESSION['role']);

// Fungsi helper untuk inisial nama pelanggan secara aman
if (!function_exists('getInitialsBooking')) {
    function getInitialsBooking($name) {
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

// Definisikan variable filter agar aman
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --- KONFIGURASI PAGINATION (10 Data Per Halaman) ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- HANDLER AJAX DETAIL BOOKING ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT B.*, PL.Nama_Pelanggan, PL.No_Telepon AS Telp_Pelanggan, PL.Email AS Email_Pelanggan, PL.Status_Member,
                   L.Nama_Layanan, K.Nama_Karyawan 
            FROM Booking B
            LEFT JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan
            LEFT JOIN Layanan L ON B.ID_Layanan = L.ID_Layanan
            LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
            WHERE B.ID_Booking = ?";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        // Format Tanggal untuk JSON agar tidak tampil sebagai [object Object] di sisi klien
        if ($row['Tanggal_Booking'] instanceof DateTime) {
            $row['Tanggal_Booking'] = $row['Tanggal_Booking']->format('d M Y, H:i');
        }
        if ($row['Jadwal_Booking'] instanceof DateTime) {
            $row['Jadwal_Booking'] = $row['Jadwal_Booking']->format('d M Y, H:i');
        }
        if ($row['Book_created_date'] instanceof DateTime) {
            $row['Book_created_date'] = $row['Book_created_date']->format('d M Y, H:i');
        }
        if ($row['Book_modified_date'] instanceof DateTime) {
            $row['Book_modified_date'] = $row['Book_modified_date']->format('d M Y, H:i');
        }
        
        // Format mata uang rupiah
        $row['Harga_Layanan_Format'] = 'Rp ' . number_format($row['Harga_Layanan'], 0, ',', '.');
        $row['Diskon_Booking_Format'] = 'Rp ' . number_format($row['Diskon_Booking'], 0, ',', '.');
        $row['Total_Tarif_Format'] = 'Rp ' . number_format($row['Total_Tarif'], 0, ',', '.');

        if (ob_get_length()) ob_clean(); // Bersihkan buffer spasi tidak sengaja
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Data booking tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX LIVE SEARCH & FILTER (HTML Output) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $params = [];
    $sql = "SELECT B.*, PL.Nama_Pelanggan, L.Nama_Layanan, K.Nama_Karyawan 
            FROM Booking B
            LEFT JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan
            LEFT JOIN Layanan L ON B.ID_Layanan = L.ID_Layanan
            LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
            WHERE 1=1";
    
    if ($search != '') {
        $sql .= " AND (PL.Nama_Pelanggan LIKE ? OR B.Kode_Booking LIKE ? OR L.Nama_Layanan LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    
    if ($status_filter != '') {
        $sql .= " AND B.Status_Booking = ?";
        $params[] = $status_filter;
    }
    
    // PERBAIKAN: Diurutkan berdasarkan ID_Booking DESC agar data yang baru ke-create berada paling atas
    $sql .= " ORDER BY B.ID_Booking DESC";
    $query = sqlsrv_query($conn, $sql, $params);
    
    if ($query === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }

    $no = 1;
    if (sqlsrv_has_rows($query) === false) {
        echo '<tr>
                <td colspan="8" class="text-center py-5">
                    <div class="empty-state">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-calendar-times fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Data booking tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter status lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
        $status = $row['Status_Booking'];
        $badge = 'pill-pending';
        if ($status == 'Diproses') $badge = 'pill-proses';
        elseif ($status == 'Selesai') $badge = 'pill-selesai';
        elseif ($status == 'Dibatalkan') $badge = 'pill-batal';
        
        $jadwal = ($row['Jadwal_Booking'] instanceof DateTime) ? $row['Jadwal_Booking']->format('d M Y, H:i') : '-';
        ?>
        <tr class="align-middle booking-row animate-fade-up" id="row-<?= $row['ID_Booking'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-container me-3">
                        <div class="plg-avatar shadow-sm avatar-amber">
                            <span class="avatar-initial"><?= getInitialsBooking($row['Nama_Pelanggan']) ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold text-dark fs-6" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="fw-bold text-primary"><?= htmlspecialchars($row['Nama_Layanan']) ?></span>
            </td>
            <td>
                <div class="small fw-bold text-dark mb-1">
                    <i class="far fa-calendar-alt me-1 text-muted"></i> <?= $jadwal ?>
                </div>
            </td>
            <td>
                <!-- Pembungkusan nama terapis yang panjang agar turun ke bawah secara otomatis -->
                <span class="badge bg-light text-dark border px-2 py-1 text-wrap text-start" style="font-size: 0.8rem; display: inline-block; max-width: 100%; word-break: break-all;">
                    <i class="fas fa-user-circle me-1 text-muted"></i> <?= htmlspecialchars($row['Nama_Karyawan'] ?: '-') ?>
                </span>
            </td>
            <td class="text-end fw-bold text-dark">
                Rp <?= number_format($row['Total_Tarif'], 0, ',', '.') ?>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Booking'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                </span>
            </td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    <!-- TOMBOL LIHAT DETAIL -->
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Booking'] ?>"
                            title="Lihat Detail Booking">
                        <i class="fas fa-eye"></i>
                    </button>

                    <!-- TOGGLE PROSES (Hanya Kasir / Non-Admin) -->
                    <?php if ($status == 'Pending'): ?>
                        <?php if ($user_role !== 'ADMIN'): ?>
                            <a href="javascript:void(0)" 
                               class="btn-action btn-status-aktif toggle-status-btn" 
                               data-id="<?= $row['ID_Booking'] ?>"
                               data-target="Diproses"
                               title="Mulai Proses Layanan">
                                <i class="fas fa-play"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn-action btn-status-locked" disabled title="Akses Terkunci: Hanya Kasir yang dapat memproses antrean">
                                <i class="fas fa-ban"></i>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- TOGGLE SELESAI / BATAL (Hanya Kasir / Non-Admin) -->
                    <?php if ($status == 'Diproses'): ?>
                        <?php if ($user_role !== 'ADMIN'): ?>
                            <a href="javascript:void(0)" 
                               class="btn-action btn-status-selesai toggle-status-btn" 
                               data-id="<?= $row['ID_Booking'] ?>"
                               data-target="Selesai"
                               title="Selesaikan Layanan">
                                <i class="fas fa-check"></i>
                            </a>
                            <a href="javascript:void(0)" 
                               class="btn-action btn-status-batal toggle-status-btn" 
                               data-id="<?= $row['ID_Booking'] ?>"
                               data-target="Dibatalkan"
                               title="Batalkan Booking">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn-action btn-status-locked" disabled title="Akses Terkunci: Hanya Kasir yang dapat memproses antrean">
                                <i class="fas fa-ban"></i>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- BAN ICON (LINGKARAN) jika Selesai atau Dibatalkan (Menggantikan Gembok) -->
                    <?php if ($status == 'Selesai' || $status == 'Dibatalkan'): ?>
                        <button class="btn-action btn-status-locked" disabled title="Aksi Terkunci">
                            <i class="fas fa-ban"></i>
                        </button>
                    <?php endif; ?>

                    <!-- TOMBOL CETAK STRUK -->
                    <a href="booking_print.php?id=<?= $row['ID_Booking'] ?>" 
                       target="_blank"
                       class="btn-action btn-print print-btn" 
                       title="Cetak Struk Booking">
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
$sql_count = "SELECT COUNT(*) as total FROM Booking B LEFT JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan WHERE 1=1";
$params_count = [];
if ($search != '') {
    $sql_count .= " AND (PL.Nama_Pelanggan LIKE ? OR B.Kode_Booking LIKE ?)";
    $params_count[] = "%$search%"; $params_count[] = "%$search%";
}
if ($status_filter != '') {
    $sql_count .= " AND B.Status_Booking = ?";
    $params_count[] = $status_filter;
}
$query_count = sqlsrv_query($conn, $sql_count, $params_count);
$total_records = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// --- DATA STATISTIK UTAMA (DIHITUNG REALTIME MENGGUNAKAN USER-DEFINED FUNCTION) ---
$sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Booking");
$total_b = $sql_total ? sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_pending = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Pending', NULL, NULL) as total");
$pending_b = $sql_pending ? sqlsrv_fetch_array($sql_pending, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_proses = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Diproses', NULL, NULL) as total");
$diproses_b = $sql_proses ? sqlsrv_fetch_array($sql_proses, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_selesai = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Selesai', NULL, NULL) as total");
$selesai_b = $sql_selesai ? sqlsrv_fetch_array($sql_selesai, SQLSRV_FETCH_ASSOC)['total'] : 0;

$sql_batal = sqlsrv_query($conn, "SELECT dbo.fn_JumlahBookingByStatus('Dibatalkan', NULL, NULL) as total");
$batal_b = $sql_batal ? sqlsrv_fetch_array($sql_batal, SQLSRV_FETCH_ASSOC)['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Reservasi Booking | Petshop Pro</title>
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
            --gradient-orange: linear-gradient(135deg, #ea580c, #c2410c);
            --gradient-info: linear-gradient(135deg, #0d9488, #0f766e);
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
            font-size: 2rem;
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
        .pill-pending { background: rgba(245, 158, 11, 0.1) !important; color: #b45309 !important; }
        .pill-proses { background: rgba(13, 148, 136, 0.1) !important; color: #0f766e !important; }
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
        
        .btn-lihat { background: rgba(59, 130, 246, 0.08); color: var(--primary); }
        .btn-print { background: rgba(13, 148, 136, 0.08); color: #0d9488; }
        .btn-status-aktif { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .btn-status-selesai { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .btn-status-batal { background: rgba(239, 68, 68, 0.08) !important; color: var(--danger); }
        .btn-status-locked { background: rgba(148, 163, 184, 0.1); color: #94a3b8; cursor: not-allowed; }
        
        .btn-action:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-lihat:hover { background: var(--primary); color: #ffffff; }
        .btn-print:hover { background: #0d9488; color: #ffffff; }
        .btn-status-aktif:hover { background: var(--warning); color: #ffffff; }
        .btn-status-selesai:hover { background: var(--success); color: #ffffff; }
        .btn-status-batal:hover { background: var(--danger); color: #ffffff; }

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

        /* TOAST NOTIFICATION SYSTEM */
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

        /* MODAL DETAIL CUSTOM */
        #detailBookingModal {
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

        @media (min-width: 992px) {
            #detailBookingModal, #modalTambahBooking {
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
                <h2 class="fw-bold text-dark mb-1">Reservasi Booking Grooming 🐾</h2>
                <p class="text-muted mb-0">Kelola dan pantau seluruh antrean layanan kecantikan hewan peliharaan Anda.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nama pelanggan..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <!-- TOMBOL TAMBAH DATA (CREATE POP-UP) -->
                <button type="button" class="btn btn-primary fw-bold rounded-pill px-4" style="background: var(--primary); border:none; height: 45px; display:inline-flex; align-items:center; gap:8px;" data-bs-toggle="modal" data-bs-target="#modalTambahBooking">
                    <i class="fas fa-plus-circle"></i> Buat Booking Baru
                </button>
            </div>
        </div>

        <!-- STATS SECTION -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-blue);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Total Reservasi</p>
                            <h2 class="stat-value mb-0" id="stat-total"><?= $total_b ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-orange);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Menunggu (Pending)</p>
                            <h2 class="stat-value mb-0" id="stat-pending"><?= $pending_b ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-info);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Sedang Diproses</p>
                            <h2 class="stat-value mb-0" id="stat-proses"><?= $diproses_b ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-green);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Layanan Selesai</p>
                            <h2 class="stat-value mb-0" id="stat-selesai"><?= $selesai_b ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 animate-fade-up">
                <div class="card card-stat" style="background: var(--gradient-red);">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <div>
                            <p class="stat-label mb-1">Dibatalkan</p>
                            <h2 class="stat-value mb-0" id="stat-batal"><?= $batal_b ?></h2>
                        </div>
                        <div class="stat-icon-box">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="glass-card p-4 animate-fade-up">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Antrean Reservasi
                    <span class="badge bg-light text-primary border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="filter-container">
                        <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua</a>
                        <a href="?page=1&status_filter=Pending&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Pending') ? 'active' : '' ?>">Pending</a>
                        <a href="?page=1&status_filter=Diproses&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Diproses') ? 'active' : '' ?>">Diproses</a>
                        <a href="?page=1&status_filter=Selesai&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Selesai') ? 'active' : '' ?>">Selesai</a>
                        <a href="?page=1&status_filter=Dibatalkan&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Dibatalkan') ? 'active' : '' ?>">Batal</a>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">No</th>
                            <th style="width: 25%;">Pelanggan</th>
                            <th style="width: 20%;">Layanan Jasa</th>
                            <th style="width: 18%;">Jadwal Pelaksanaan</th>
                            <th style="width: 17%;">Petugas / Terapis</th>
                            <th class="text-end" style="width: 120px;">Total Tarif</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-center" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="booking-tbody">
                        <?php
                        $no = 1 + $offset;
                        $params = [];
                        
                        $sql = "SELECT B.*, PL.Nama_Pelanggan, L.Nama_Layanan, K.Nama_Karyawan 
                                FROM Booking B
                                LEFT JOIN Pelanggan PL ON B.ID_Pelanggan = PL.ID_Pelanggan
                                LEFT JOIN Layanan L ON B.ID_Layanan = L.ID_Layanan
                                LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
                                WHERE 1=1";
                                
                        if ($search != '') {
                            $sql .= " AND (PL.Nama_Pelanggan LIKE ? OR B.Kode_Booking LIKE ? OR L.Nama_Layanan LIKE ?)";
                            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
                        }
                        if ($status_filter != '') {
                            $sql .= " AND B.Status_Booking = ?";
                            $params[] = $status_filter;
                        }
                        // PERBAIKAN: Diurutkan berdasarkan ID_Booking DESC agar data yang baru ke-create berada paling atas
                        $sql .= " ORDER BY B.ID_Booking DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
                        
                        $params[] = $offset;
                        $params[] = $limit;
                        
                        $query = sqlsrv_query($conn, $sql, $params);

                        if ($query === false) {
                            die(print_r(sqlsrv_errors(), true));
                        }

                        if (sqlsrv_has_rows($query) === false) {
                            echo '<tr><td colspan="8" class="text-center py-5 text-muted">Tidak ada jadwal booking pada halaman ini...</td></tr>';
                        } else {
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
                                $status = $row['Status_Booking'];
                                $badge = 'pill-pending';
                                if ($status == 'Diproses') $badge = 'pill-proses';
                                elseif ($status == 'Selesai') $badge = 'pill-selesai';
                                elseif ($status == 'Dibatalkan') $badge = 'pill-batal';
                                
                                $jadwal = ($row['Jadwal_Booking'] instanceof DateTime) ? $row['Jadwal_Booking']->format('d M Y, H:i') : '-';
                            ?>
                            <tr class="align-middle booking-row" id="row-<?= $row['ID_Booking'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-container me-3">
                                            <div class="plg-avatar shadow-sm avatar-amber">
                                                <span class="avatar-initial"><?= getInitialsBooking($row['Nama_Pelanggan']) ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <!-- Menghapus Kode Booking sesuai instruksi -->
                                            <div class="fw-bold text-dark fs-6" style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($row['Nama_Pelanggan']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= htmlspecialchars($row['Nama_Layanan']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-bold text-dark mb-1">
                                        <i class="far fa-calendar-alt me-1 text-muted"></i> <?= $jadwal ?>
                                    </div>
                                </td>
                                <td style="word-break: break-word; white-space: normal;">
                                    <!-- Tampilan terapis yang sangat panjang turun ke bawah secara otomatis -->
                                    <span class="badge bg-light text-dark border px-2 py-1 text-wrap text-start" style="font-size: 0.8rem; display: inline-block; max-width: 100%; word-break: break-all;">
                                        <i class="fas fa-user-circle me-1 text-muted"></i> <?= htmlspecialchars($row['Nama_Karyawan'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-dark">
                                    Rp <?= number_format($row['Total_Tarif'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $badge ?>" id="status-pill-<?= $row['ID_Booking'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($status) ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        <!-- TOMBOL LIHAT DETAIL -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Booking'] ?>"
                                                title="Lihat Detail Booking">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOGGLE PROSES (Hanya Kasir / Non-Admin) -->
                                        <?php if ($status == 'Pending'): ?>
                                            <?php if ($user_role !== 'ADMIN'): ?>
                                                <a href="javascript:void(0)" 
                                                   class="btn-action btn-status-aktif toggle-status-btn" 
                                                   data-id="<?= $row['ID_Booking'] ?>"
                                                   data-target="Diproses"
                                                   title="Mulai Proses Layanan">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn-action btn-status-locked" disabled title="Akses Terkunci: Hanya Kasir yang dapat memproses antrean">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- TOGGLE SELESAI / BATAL (Hanya Kasir / Non-Admin) -->
                                        <?php if ($status == 'Diproses'): ?>
                                            <?php if ($user_role !== 'ADMIN'): ?>
                                                <a href="javascript:void(0)" 
                                                   class="btn-action btn-status-selesai toggle-status-btn" 
                                                   data-id="<?= $row['ID_Booking'] ?>"
                                                   data-target="Selesai"
                                                   title="Selesaikan Layanan">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="javascript:void(0)" 
                                                   class="btn-action btn-status-batal toggle-status-btn" 
                                                   data-id="<?= $row['ID_Booking'] ?>"
                                                   data-target="Dibatalkan"
                                                   title="Batalkan Booking">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php else: ?>
                                                <button class="btn-action btn-status-locked" disabled title="Akses Terkunci: Hanya Kasir yang dapat memproses antrean">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- BAN ICON (LINGKARAN) jika Selesai atau Dibatalkan (Menggantikan Gembok) -->
                                        <?php if ($status == 'Selesai' || $status == 'Dibatalkan'): ?>
                                            <button class="btn-action btn-status-locked" disabled title="Aksi Terkunci">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- TOMBOL CETAK STRUK -->
                                        <a href="booking_print.php?id=<?= $row['ID_Booking'] ?>" 
                                           target="_blank"
                                           class="btn-action btn-print print-btn" 
                                           title="Cetak Struk Booking">
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

            <!-- PAGINATION NAVIGATION SINKRON DENGAN CHIPS -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 px-2">
                <div class="text-muted small mb-2 mb-md-0">
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> data reservasi.
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

    <!-- MODAL DETAIL BOOKING -->
    <div class="modal fade" id="detailBookingModal" tabindex="-1" aria-labelledby="detailBookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0 animate-fade-up">
                
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div class="plg-avatar avatar-amber mb-3" style="width:80px; height:80px; border-radius:18px; font-size:1.8rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">
                            <span class="avatar-initial" id="detail-initial">?</span>
                        </div>
                        <h3 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Pelanggan</h3>
                        <span class="badge bg-light text-primary fw-bold mt-1" id="detail-kode-booking" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">-</span>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start">
                    <div class="row g-4">
                        <!-- Profil Pelanggan -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-user-circle me-2"></i>Data Pelanggan</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Nama</td><td class="fw-bold text-dark" id="detail-nama-p">-</td></tr>
                                    <!-- Status Member Pelanggan -->
                                    <tr><td class="text-muted">Status Member</td><td class="fw-bold text-muted" id="detail-status-member">-</td></tr>
                                    <tr><td class="text-muted">No. Telepon</td><td class="fw-bold text-dark" id="detail-telp-p">-</td></tr>
                                    <tr><td class="text-muted">Email</td><td class="fw-bold text-dark text-truncate" id="detail-email-p" style="max-width:150px;">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Detail Layanan & Petugas -->
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-spa me-2"></i>Layanan & Petugas</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Layanan Jasa</td><td class="fw-bold text-primary" id="detail-layanan">-</td></tr>
                                    <tr><td class="text-muted">Terapis/Petugas</td><td class="fw-bold text-dark" id="detail-petugas">-</td></tr>
                                    <tr><td class="text-muted">Jadwal</td><td class="fw-bold text-dark" id="detail-jadwal">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Informasi Tarif / Biaya -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-wallet me-2"></i>Rincian Biaya Layanan</h6>
                                <div class="row g-2 text-center align-items-center">
                                    <div class="col-4">
                                        <span class="text-muted d-block small mb-2">Harga Asli</span>
                                        <strong class="text-dark fs-6" id="detail-harga-layanan">-</strong>
                                    </div>
                                    <div class="col-4 border-start">
                                        <span class="text-muted d-block small mb-2">Diskon Booking</span>
                                        <div class="d-flex flex-column align-items-center justify-content-center gap-1">
                                            <strong class="text-danger fs-6 mb-1" id="detail-diskon">-</strong>
                                            <span id="detail-diskon-badge" class="badge rounded-pill fw-bold" style="font-size: 0.72rem; padding: 4px 10px; display: none;">-</span>
                                        </div>
                                    </div>
                                    <div class="col-4 border-start">
                                        <span class="text-muted d-block small mb-2">Total Tagihan</span>
                                        <strong class="text-success fs-5" id="detail-total-tarif">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan & Audit Info -->
                        <div class="col-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-comment-alt me-2"></i>Catatan Pelanggan</h6>
                                <p class="text-dark small mb-3" id="detail-catatan">-</p>
                                <hr class="my-2 text-muted">
                                <div class="row g-2 text-muted" style="font-size: 0.75rem;">
                                    <div class="col-md-6">Dibuat: <span id="detail-created-by">-</span> (<span id="detail-created-date">-</span>)</div>
                                    <div class="col-md-6 text-md-end">Diubah: <span id="detail-modified-by">-</span> (<span id="detail-modified-date">-</span>)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-between w-100">
                    <div id="modal-print-container"></div>
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal" style="border-radius:12px; font-weight:600;">Tutup Rincian</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT AJAX & LIVE SEARCH INTERAKTIF -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('booking-tbody');
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

                fetch(`booking_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
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
                        showToast('Gagal memuat data antrean.', 'danger');
                    });
            }

            // Daftarkan secara global
            window.performSearchAndFilter = performSearchAndFilter;

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Booking
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`booking_read.php?ajax=detail&id=${id}`)
                        .then(response => {
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    throw new Error(text);
                                }
                            });
                        })
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Pelanggan || '-';
                                document.getElementById('detail-kode-booking').textContent = d.Kode_Booking || '-';
                                
                                document.getElementById('detail-nama-p').textContent = d.Nama_Pelanggan || '-';
                                
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
                                document.getElementById('detail-email-p').textContent = d.Email_Pelanggan || '-';
                                
                                document.getElementById('detail-layanan').textContent = d.Nama_Layanan || '-';
                                document.getElementById('detail-petugas').textContent = d.Nama_Karyawan || '-';
                                document.getElementById('detail-jadwal').textContent = d.Jadwal_Booking || '-';

                                document.getElementById('detail-harga-layanan').textContent = d.Harga_Layanan_Format || '-';
                                document.getElementById('detail-diskon').textContent = d.Diskon_Booking_Format || 'Rp 0';
                                document.getElementById('detail-total-tarif').textContent = d.Total_Tarif_Format || '-';

                                // Menampilkan logo/lencana persentase diskon 10% untuk member, dan disembunyikan untuk non-member
                                const diskonBadge = document.getElementById('detail-diskon-badge');
                                if (diskonBadge) {
                                    const discountAmount = parseFloat(d.Diskon_Booking) || 0;
                                    if ((d.Status_Member === 'Member' || d.Status_Member === 'Premium') && discountAmount > 0) {
                                        diskonBadge.innerHTML = '<i class="fas fa-percent me-1"></i> 10% Member';
                                        diskonBadge.className = 'badge bg-danger text-white rounded-pill fw-bold';
                                        diskonBadge.style.display = 'inline-block';
                                    } else {
                                        diskonBadge.style.display = 'none'; // Sembunyikan untuk non-member atau jika diskon Rp 0
                                    }
                                }
                                
                                document.getElementById('detail-catatan').textContent = d.Catatan_Booking || 'Tidak ada catatan tambahan.';
                                
                                document.getElementById('detail-created-by').textContent = d.Book_created_by || '-';
                                document.getElementById('detail-created-date').textContent = d.Book_created_date || '-';
                                document.getElementById('detail-modified-by').textContent = d.Book_modified_by || '-';
                                document.getElementById('detail-modified-date').textContent = d.Book_modified_date || '-';

                                document.getElementById('detail-initial').textContent = getInitialsJs(d.Nama_Pelanggan);

                                // Tambahan tombol Cetak di dalam Modal Rincian jika berstatus 'Selesai'
                                const printContainer = document.getElementById('modal-print-container');
                                printContainer.innerHTML = '';
                                if (d.Status_Booking === 'Selesai') {
                                    printContainer.innerHTML = `
                                        <a href="booking_print.php?id=${d.ID_Booking}" target="_blank" class="btn btn-teal rounded-pill px-4 py-2 fw-bold text-white shadow-sm print-btn" style="background:#0d9488;">
                                            <i class="fas fa-print me-2"></i>Cetak Struk Reservasi
                                        </a>
                                    `;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailBookingModal'));
                                detailModal.show();
                            } else {
                                showToast(res.message || 'Gagal mengambil rincian data.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error detail fetch:', error);
                            showToast('Gagal memuat rincian: ' + error.message, 'danger');
                        })
                        .finally(() => {
                            icon.className = originalClass;
                            viewBtn.disabled = false;
                        });
                }
            });

            // Helper JS untuk inisial nama
            function getInitialsJs(name) {
                if (!name) return "?";
                const words = name.trim().split(/\s+/);
                let initials = words[0].charAt(0);
                if (words.length > 1) {
                    initials += words[words.length - 1].charAt(0);
                }
                return initials.toUpperCase();
            }

            // Fungsi Letup Angka Statistik
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

            // AJAX TOGGLE STATUS
            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                
                if (toggleBtn) {
                    e.preventDefault();
                    
                    const id = toggleBtn.getAttribute('data-id');
                    const targetStatus = toggleBtn.getAttribute('data-target');
                    const parentRow = toggleBtn.closest('tr');
                    
                    toggleBtn.disabled = true;

                    fetch('booking_toggle_status.php?id=' + id + '&status=' + encodeURIComponent(targetStatus))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                animateStatTextUpdate('stat-total', data.total_b);
                                animateStatTextUpdate('stat-pending', data.pending_b);
                                animateStatTextUpdate('stat-proses', data.diproses_b);
                                animateStatTextUpdate('stat-selesai', data.selesai_b);
                                animateStatTextUpdate('stat-batal', data.batal_b);

                                showToast(`Antrean berhasil diproses ke status: ${targetStatus}`, 'success');

                                if (currentStatusFilter !== '' && currentStatusFilter !== targetStatus) {
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
                                showToast(data.message || 'Gagal mengubah status antrean.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error Toggle Status:', error);
                            showToast('Gagal memproses status antrean.', 'danger');
                        })
                        .finally(() => {
                            toggleBtn.disabled = false;
                        });
                }
            });

            // VALIDASI: Cegah cetak struk jika status belum Selesai
            document.addEventListener('click', function(e) {
                const printBtn = e.target.closest('.print-btn, .btn-print, a[href*="booking_print"]');
                
                if (printBtn) {
                    let isNotSelesai = false;

                    const row = printBtn.closest('.booking-row');
                    if (row) {
                        const statusPill = row.querySelector('.status-text');
                        if (statusPill && statusPill.textContent.trim() !== 'Selesai') {
                            isNotSelesai = true;
                        }
                    }

                    const modal = printBtn.closest('#detailBookingModal');
                    if (modal) {
                        const statusBadge = modal.querySelector('#detail-status-badge');
                        if (statusBadge && !statusBadge.textContent.includes('Selesai')) {
                            isNotSelesai = true;
                        }
                    }

                    if (isNotSelesai) {
                        e.preventDefault();
                        showToast('Struk reservasi hanya dapat dicetak apabila layanan telah Selesai!', 'warning');
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODAL POP UP TAMBAH BOOKING -->
    <?php include_once 'booking_create.php'; ?>
</body>
</html>
