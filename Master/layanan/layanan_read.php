

<?php
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

// Normalisasikan role pekerja ke Karyawan agar konsisten dengan navbar & barang_tampil
$employee_roles = ['Staff', 'Karyawan', 'Kasir', 'Groomer', 'Dokter'];
if (in_array($role, $employee_roles)) { 
    $role = 'Karyawan'; 
}

// Fungsi helper untuk menghasilkan inisial nama layanan secara aman
if (!function_exists('getInitialsLayanan')) {
    function getInitialsLayanan($name) {
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

// =========================================================================
// 1. UNIFIED POST HANDLER (DIPROTEKSI HANYA UNTUK ADMIN)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'Admin') {
    header('Content-Type: application/json');

    $id                = $_POST['id'] ?? null;
    $id_kat            = $_POST['ID_Kategori'] ?? '';
    $kode_layanan      = $_POST['Kode_Layanan'] ?? '';
    $nama              = $_POST['Nama_Layanan'] ?? '';
    $harga             = $_POST['Harga_Layanan'] ?? 0;
    $durasi            = $_POST['Durasi'] ?? 0;
    $deskripsi_layanan = $_POST['Deskripsi_Layanan'] ?? '';

    $foto_baru = null;
    $upload_ok = true;
    $error_message = "";

    // Validasi Sisi Server
    if ($harga < 0 || $durasi < 0) {
        $error_message = 'Harga Layanan dan Durasi tidak boleh bernilai negatif!';
        $upload_ok = false;
    } 
    elseif (preg_match('/[0-9]/', $nama)) {
        $error_message = "Nama layanan tidak boleh mengandung karakter angka!";
        $upload_ok = false;
    } 
    elseif (strlen(trim($deskripsi_layanan)) < 20) {
        $error_message = "Rincian deskripsi layanan terlalu pendek! Tuliskan minimal 20 karakter.";
        $upload_ok = false;
    }

    if ($upload_ok) {
        $target_dir = "../../assets/uploads/layanan/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Ambil data foto lama jika dalam mode edit
        if ($id) {
            $sql_cek = "EXEC sp_Layanan_Read @ID_Layanan = ?";
            $query_cek = sqlsrv_query($conn, $sql_cek, array($id));
            if ($query_cek !== false) {
                $old_data = sqlsrv_fetch_array($query_cek, SQLSRV_FETCH_ASSOC);
                $foto_baru = $old_data['Foto_Layanan'] ?? null;
            }
        }

        // Proses unggah berkas jika ada file baru yang dikirim
        if (isset($_FILES['Foto_Layanan']) && $_FILES['Foto_Layanan']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['Foto_Layanan']['name'];
            $file_size = $_FILES['Foto_Layanan']['size'];
            $file_tmp  = $_FILES['Foto_Layanan']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = array("jpg", "jpeg", "png", "webp");

            if (!in_array($file_ext, $allowed_extensions)) {
                $upload_ok = false;
                $error_message = "Format foto tidak didukung! Gunakan format JPG, JPEG, PNG, atau WEBP.";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $upload_ok = false;
                $error_message = "Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.";
            } else {
                $nama_file_acak = "service_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $target_file = $target_dir . $nama_file_acak;
                
                if (move_uploaded_file($file_tmp, $target_file)) {
                    // Hapus file lama jika ada dalam mode edit
                    if ($id && !empty($old_data['Foto_Layanan']) && file_exists($target_dir . $old_data['Foto_Layanan'])) {
                        unlink($target_dir . $old_data['Foto_Layanan']);
                    }
                    $foto_baru = $nama_file_acak;
                } else {
                    $upload_ok = false;
                    $error_message = "Gagal mengunggah foto ke server.";
                }
            }
        } elseif (!$id) {
            // Foto wajib ada saat menambahkan data baru
            $upload_ok = false;
            $error_message = "Foto layanan wajib diunggah!";
        }
    }

    if ($upload_ok) {
        if ($id) {
            // Mode Update Data
            $modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';
            $sql_up = "EXEC sp_Layanan_Update 
                        @ID_Layanan = ?, 
                        @ID_Kategori = ?, 
                        @Kode_Layanan = ?, 
                        @Nama_Layanan = ?, 
                        @Harga_Layanan = ?, 
                        @Durasi = ?, 
                        @Deskripsi_Layanan = ?, 
                        @Foto_Layanan = ?, 
                        @Lay_status = ?, 
                        @Lay_modified_by = ?";

            $params = array(
                $id, $id_kat, $kode_layanan, $nama, $harga,
                !empty($durasi) ? $durasi : null,
                !empty($deskripsi_layanan) ? $deskripsi_layanan : null,
                $foto_baru, $old_data['Lay_status'] ?? 'Aktif', $modified_by
            );
            $stmt = sqlsrv_query($conn, $sql_up, $params);
            $msg_sukses = 'Informasi layanan berhasil diperbarui!';
        } else {
            // Mode Simpan Baru
            $created_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';
            $sql_ins = "{CALL sp_Layanan_Create(?, ?, ?, ?, ?, ?, ?, ?, ?)}";
            $params = array(
                $id_kat, $kode_layanan, $nama, $harga,
                !empty($durasi) ? $durasi : null,
                !empty($deskripsi_layanan) ? $deskripsi_layanan : null,
                $foto_baru, 'Aktif', $created_by
            );
            $stmt = sqlsrv_query($conn, $sql_ins, $params);
            $msg_sukses = 'Layanan baru berhasil ditambahkan!';
        }

        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => $msg_sukses]);
        } else {
            $errors = sqlsrv_errors();
            $db_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $code = $err['code'] ?? null;
                    $message = $err['message'] ?? '';
                    
                    // Deteksi jika terjadi pelanggaran UNIQUE KEY constraint atau duplikasi data
                    if ($code === 2627 || $code === 2601 || stripos($message, 'UNIQUE KEY') !== false || stripos($message, 'duplicate') !== false) {
                        preg_match('/\((.*?)\)/', $message, $matches);
                        $duplicate_val = isset($matches[1]) ? " '{$matches[1]}'" : "";
                        $db_err = "Nama layanan{$duplicate_val} sudah terdaftar di dalam sistem. Silakan gunakan nama layanan yang lain.";
                        break; // Utamakan pesan validasi bisnis yang ramah pengguna
                    } else {
                        $clean_msg = preg_replace('/\[[^\]]+\]/', '', $message);
                        $db_err .= trim($clean_msg) . " ";
                    }
                }
            } else {
                $db_err = 'Terjadi kesalahan sistem saat menyimpan data.';
            }
            echo json_encode(['status' => 'error', 'message' => $db_err]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    }
    exit;
}

// Ambil parameter filter pencarian
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --- KONFIGURASI PAGINATION ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- HANDLER AJAX DETAIL LAYANAN ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "EXEC sp_Layanan_Read @ID_Layanan = ?";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Layanan tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX HAPUS LAYANAN (SOFT DELETE) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $delete_id = $_GET['id'];
    
    // Perbarui kolom Lay_is_deleted menjadi 1 untuk menandai bahwa data telah dihapus
    $sql_del = "UPDATE Layanan SET Lay_is_deleted = 1 WHERE ID_Layanan = ?";
    $stmt_del = sqlsrv_query($conn, $sql_del, array($delete_id));
    
    if ($stmt_del) {
        echo json_encode(['success' => true, 'message' => 'Layanan berhasil dihapus dari sistem.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus layanan dari database.', 'error' => sqlsrv_errors()]);
    }
    exit;
}

// --- HANDLER AJAX UNTUK LIVE SEARCH & FILTER ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    $params = [];
    $sql = "SELECT L.*, K.Nama_Kategori FROM Layanan L 
            LEFT JOIN Kategori K ON L.ID_Kategori = K.ID_Kategori 
            WHERE (L.Lay_is_deleted = 0 OR L.Lay_is_deleted IS NULL)";
    
    if ($search != '') {
        $sql .= " AND (L.Nama_Layanan LIKE ? OR L.Deskripsi_Layanan LIKE ? OR K.Nama_Kategori LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    
    if ($status_filter != '') {
        $sql .= " AND L.Lay_status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY L.ID_Layanan DESC";
    $query = sqlsrv_query($conn, $sql, $params);
    
    if ($query === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }

    $no = 1;
    if (sqlsrv_has_rows($query) === false) {
        echo '<tr>
                <td colspan="6" class="text-center py-5">
                    <div class="empty-state animate-fade-in">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-concierge-bell fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Layanan tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter keaktifan lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
        $is_aktif = (($row['Lay_status'] ?? 'Aktif') == 'Aktif');
        $foto_db = $row['Foto_Layanan'] ?? null;
        $foto_path = "../../assets/uploads/layanan/" . $foto_db;
        ?>
        <tr class="align-middle layanan-row animate-fade-up" id="row-<?= $row['ID_Layanan'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="avatar-container">
                    <div class="plg-avatar shadow-sm avatar-teal">
                        <?php if (!empty($foto_db) && file_exists($foto_path)): ?>
                            <img src="<?= $foto_path ?>" alt="Foto">
                        <?php else: ?>
                            <span class="avatar-initial"><?= getInitialsLayanan($row['Nama_Layanan']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                </div>
            </td>
            <td>
                <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Layanan']) ?>"><?= htmlspecialchars($row['Nama_Layanan']) ?></div>
            </td>
            <td class="text-center">
                <span class="badge rounded-pill duration-badge px-3 py-2">
                    <i class="far fa-clock me-1"></i> <?= $row['Durasi'] ?: '0' ?> Jam
                </span>
            </td>
            <td class="text-end price-text">
                Rp <?= number_format($row['Harga_Layanan'], 0, ',', '.') ?>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Layanan'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Lay_status'] ?? 'Aktif') ?></span>
                </span>
            </td>
            <?php if($role == 'Admin') : ?>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    <button type="button" class="btn-action btn-lihat view-details-btn" data-id="<?= $row['ID_Layanan'] ?>" title="Lihat Detail Layanan">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="javascript:void(0)" class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" data-id="<?= $row['ID_Layanan'] ?>" data-current="<?= htmlspecialchars($row['Lay_status'] ?? 'Aktif') ?>" id="toggle-btn-<?= $row['ID_Layanan'] ?>" title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                        <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>
                    <a href="<?= $is_aktif ? 'layanan_read.php?id=' . $row['ID_Layanan'] : 'javascript:void(0)' ?>" class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" id="edit-btn-<?= $row['ID_Layanan'] ?>" title="<?= $is_aktif ? 'Edit Layanan' : 'Layanan Non-Aktif tidak dapat diedit' ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </a>
                    <button type="button" class="btn-action btn-hard delete-trigger-btn" data-bs-toggle="modal" data-bs-target="#confirmModal" data-href="layanan_read.php?action=delete&id=<?= $row['ID_Layanan'] ?>" data-id="<?= $row['ID_Layanan'] ?>" data-title="Hapus Layanan" data-message="Apakah Anda yakin ingin menghapus data layanan <b><?= htmlspecialchars($row['Nama_Layanan']) ?></b>?" data-color="btn-danger">
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

// Menghitung Total Record Layanan untuk Pagination
$sql_count = "SELECT COUNT(*) as total FROM Layanan WHERE (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)";
$params_count = [];
if ($search != '') {
    $sql_count .= " AND (Nama_Layanan LIKE ? OR Deskripsi_Layanan LIKE ?)";
    $params_count[] = "%$search%"; $params_count[] = "%$search%";
}
if ($status_filter != '') {
    $sql_count .= " AND Lay_status = ?";
    $params_count[] = $status_filter;
}
$query_count = sqlsrv_query($conn, $sql_count, $params_count);
$total_records = sqlsrv_fetch_array($query_count, SQLSRV_FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Data Statistik Utama
$sql_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Layanan WHERE (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
$total_l = sqlsrv_fetch_array($sql_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

$sql_avg = sqlsrv_query($conn, "SELECT AVG(Harga_Layanan) as rata FROM Layanan WHERE (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
$rata_harga = sqlsrv_fetch_array($sql_avg, SQLSRV_FETCH_ASSOC)['rata'] ?? 0;

$sql_aktif = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Layanan WHERE Lay_status = 'Aktif' AND (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
$total_a = sqlsrv_fetch_array($sql_aktif, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

$sql_off = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Layanan WHERE Lay_status = 'Non-Aktif' AND (Lay_is_deleted = 0 OR Lay_is_deleted IS NULL)");
$total_o = sqlsrv_fetch_array($sql_off, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

$pct_aktif = $total_l > 0 ? round(($total_a / $total_l) * 100) : 0;
$pct_off = $total_l > 0 ? round(($total_o / $total_l) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Layanan | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0d9488;
            --primary-light: #14b8a6; 
            --primary-glow: rgba(13, 148, 136, 0.15);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.15);
            --warning: #f59e0b;
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.15);
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.6);
            --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
            --card-shadow-hover: 0 20px 35px -10px rgba(13, 148, 136, 0.12), 0 1px 5px rgba(0, 0, 0, 0.03);
            --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
            --primary-gradient: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%);
        }

        body { 
            background: #f4f6fa; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            overflow-x: hidden;
            color: var(--slate-800);
        }

        .animate-fade-up { animation: fadeInUp 0.8s var(--ease-out-expo) both; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out both; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.15s; }
        .delay-3 { animation-delay: 0.2s; }
        .delay-4 { animation-delay: 0.25s; }
        .delay-5 { animation-delay: 0.3s; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .pop-effect { animation: popBounce 0.4s var(--ease-out-expo); }
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
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
            width: 320px;
            outline: none;
        }
        .search-wrapper .input-search:focus + .search-icon {
            color: var(--primary);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        @media (max-width: 1500px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }

        /* STAT CARDS */
        .card-stat { 
            border-radius: 24px; 
            border: none; 
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
        .card-stat:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
        }
        .card-stat-total:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(13, 148, 136, 0.12); border-color: rgba(13, 148, 136, 0.25); }
        .card-stat-avg:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-category:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(6, 182, 212, 0.12); border-color: rgba(6, 182, 212, 0.25); }
        .card-stat-aktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.25); }
        .card-stat-nonaktif:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.25); }

        .card-stat .stat-label {
            color: #64748b;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .card-stat .stat-value {
            color: var(--slate-800);
            font-weight: 800;
            font-size: 2.2rem;
            line-height: 1.2;
        }
        .stat-icon-box {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        .card-stat:hover .stat-icon-box {
            transform: scale(1.1) rotate(5deg);
        }

        .icon-box-total { background: linear-gradient(135deg, #ccfbf1, #99f6e4); color: var(--primary); }
        .icon-box-avg { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #059669; }
        .icon-box-category { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0284c7; }
        .icon-box-aktif { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: var(--success); }
        .icon-box-nonaktif { background: linear-gradient(135deg, #fee2e2, #fca5a5); color: var(--danger); }

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

        /* PREMIUM GLASS CARD & CONTAINER */
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
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
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
            background-color: rgba(13, 148, 136, 0.015);
            transform: scale(1.002);
        }

        /* AVATAR INISIAL */
        .avatar-container {
            position: relative;
            width: 48px;
            height: 48px;
        }
        .plg-avatar {
            width: 100%; height: 100%; border-radius: 14px;
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
        .avatar-teal { background: linear-gradient(135deg, #ccfbf1, #99f6e4); color: var(--primary); }

        .avatar-status-indicator {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
        }
        .status-online { background-color: var(--success); }
        .status-offline { background-color: #94a3b8; }

        /* Badge tipe */
        .duration-badge { background: #f0fdfa; color: #0d9488; font-weight: 600; border: 1px solid rgba(13, 148, 136, 0.15); }
        .price-text { font-weight: 700; color: var(--primary); font-size: 1.05rem; }

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
        .pill-aktif { background: rgba(16, 185, 129, 0.1) !important; color: #065f46 !important; }
        .pill-off { background: rgba(239, 68, 68, 0.08) !important; color: #991b1b !important; }

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
        .btn-status-aktif { background: rgba(16, 185, 129, 0.1); color: var(--success); } 
        .btn-status-off { background: var(--slate-100); color: #64748b; }   
        .btn-edit { background: rgba(13, 148, 136, 0.08); color: var(--primary); }           
        .btn-hard { background: rgba(239, 68, 68, 0.08); color: var(--danger); }           
        
        .btn-action:hover { transform: translateY(-3px); }
        .btn-action:hover i { transform: scale(1.1); }
        .btn-lihat:hover { background: #0ea5e9; color: #ffffff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15); }
        .btn-status-aktif:hover { background: var(--success); color: #ffffff; box-shadow: 0 4px 12px var(--success-glow); }
        .btn-status-off:hover { background: #64748b; color: #ffffff; }
        .btn-edit:hover { background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow); }
        .btn-hard:hover { background: var(--danger); color: #ffffff; box-shadow: 0 4px 12px var(--danger-glow); }

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
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 10px var(--primary-glow);
        }
        .pagination .page-link:hover {
            border-color: var(--primary-light);
            background: var(--slate-50);
            color: var(--slate-800);
        }

        /* PRESERVASI STYLE MODAL DETAIL */
        #detailLayananModal {
            z-index: 1060 !important;
            backdrop-filter: blur(8px);
            background-color: rgba(15, 23, 42, 0.4);
        }

        @media (min-width: 992px) {
            #detailLayananModal {
                padding-left: 260px !important; 
            }
        }

        #detailLayananModal.show .modal-content-custom {
            animation: modalZoomInLayanan 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
            border-left: 4px solid var(--primary);
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
        .toast-modern.success { border-left-color: var(--primary); }
        .toast-modern.warning { border-left-color: var(--warning); }
        .toast-modern.danger { border-left-color: var(--danger); }

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
            background: var(--primary);
            color: white;
            border-radius: 50px;
            padding: 12px 28px;
            font-weight: 750;
            box-shadow: 0 4px 12px var(--primary-glow);
            transition: 0.3s;
            border: none;
            text-decoration: none;
        }
        .btn-tambah:hover {
            background: #0f766e;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(13, 148, 136, 0.25);
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
                <h2 class="fw-bold text-dark mb-1">Katalog Layanan Jasa 🏷️</h2>
                <p class="text-muted mb-0">Kelola dan pantau daftar harga dan durasi pengerjaan jasa layanan Petshop Pro.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari nama layanan pengerjaan..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <?php if($role == 'Admin') : ?>
                <button type="button" class="btn btn-tambah" data-bs-toggle="modal" data-bs-target="#modalTambahLayanan">
                    <i class="fas fa-plus-circle me-2"></i> Tambah Layanan
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="card card-stat card-stat-total animate-fade-up delay-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Total Layanan</p>
                        <h2 class="stat-value mb-0" id="stat-total"><?= $total_l ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-total">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: var(--primary);"></div>
                </div>
            </div>

            <div class="card card-stat card-stat-category animate-fade-up delay-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Klasifikasi Jasa</p>
                        <h2 class="stat-value mb-0 text-info" id="stat-category">Semua</h2>
                    </div>
                    <div class="stat-icon-box icon-box-category">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: #0284c7;"></div>
                </div>
            </div>

            <div class="card card-stat card-stat-avg animate-fade-up delay-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Rerata Tarif</p>
                        <h2 class="stat-value mb-0 text-success" id="stat-avg" style="font-size: 1.45rem;">Rp <?= number_format($rata_harga, 0, ',', '.') ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-avg">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: #059669;"></div>
                </div>
            </div>

            <div class="card card-stat card-stat-aktif animate-fade-up delay-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Layanan Tersedia</p>
                        <h2 class="stat-value mb-0 text-success" id="stat-aktif"><?= $total_a ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-aktif">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-aktif" style="width: <?= $pct_aktif ?>%; background: var(--success);"></div>
                </div>
            </div>

            <div class="card card-stat card-stat-nonaktif animate-fade-up delay-5">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Tidak Tersedia</p>
                        <h2 class="stat-value mb-0 text-danger" id="stat-off"><?= $total_o ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-nonaktif">
                        <i class="fas fa-user-slash"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-off" style="width: <?= $pct_off ?>%; background: var(--danger);"></div>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="glass-card p-4 animate-fade-up delay-4">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 px-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    Daftar Katalog Jasa <span class="badge bg-light text-primary border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="filter-container">
                    <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua</a>
                    <a href="?page=1&status_filter=Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Aktif') ? 'active' : '' ?>">Tersedia</a>
                    <a href="?page=1&status_filter=Non-Aktif&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Non-Aktif') ? 'active' : '' ?>">Tidak Tersedia</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">No</th>
                            <th style="width: 15%;">Visual</th>
                            <th style="width: 30%;">Nama Jasa Layanan</th>
                            <th class="text-center" style="width: 18%;">Estimasi Durasi</th>
                            <th class="text-end" style="width: 18%;">Harga Jasa</th>
                            <th class="text-center" style="width: 130px;">Status</th>
                            <?php if($role == 'Admin') : ?>
                            <th class="text-center" style="width: 200px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="layanan-tbody">
                        <?php
                        $no = 1 + $offset;
                        $params = [];
                        
                        $sql = "SELECT L.*, K.Nama_Kategori FROM Layanan L 
                                LEFT JOIN Kategori K ON L.ID_Kategori = K.ID_Kategori 
                                WHERE (L.Lay_is_deleted = 0 OR L.Lay_is_deleted IS NULL)";
                        if ($search != '') {
                            $sql .= " AND (L.Nama_Layanan LIKE ? OR L.Deskripsi_Layanan LIKE ? OR K.Nama_Kategori LIKE ?)";
                            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
                        }
                        if ($status_filter != '') {
                            $sql .= " AND L.Lay_status = ?";
                            $params[] = $status_filter;
                        }
                        
                        // Menghindari konversi driver parametrik SQLSRV yang tidak stabil dengan OFFSET/FETCH
                        $sql .= " ORDER BY L.ID_Layanan DESC OFFSET " . (int)$offset . " ROWS FETCH NEXT " . (int)$limit . " ROWS ONLY";
                        
                        $query = sqlsrv_query($conn, $sql, $params);

                        if ($query === false) {
                            die(print_r(sqlsrv_errors(), true));
                        }

                        if (sqlsrv_has_rows($query) === false) {
                            echo '<tr><td colspan="7" class="text-center py-5 text-muted">Tidak ada data layanan jasa pada halaman ini...</td></tr>';
                        } else {
                            while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) { 
                                $is_aktif = (($row['Lay_status'] ?? 'Aktif') == 'Aktif');
                                $foto_db = $row['Foto_Layanan'] ?? null;
                                $foto_path = "../../assets/uploads/layanan/" . $foto_db;
                            ?>
                            <tr class="align-middle layanan-row" id="row-<?= $row['ID_Layanan'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="avatar-container">
                                        <div class="plg-avatar shadow-sm avatar-teal">
                                            <?php if (!empty($foto_db) && file_exists($foto_path)): ?>
                                                <img src="<?= $foto_path ?>" alt="Foto">
                                            <?php else: ?>
                                                <span class="avatar-initial"><?= getInitialsLayanan($row['Nama_Layanan']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Layanan']) ?>"><?= htmlspecialchars($row['Nama_Layanan']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill duration-badge px-3 py-2">
                                        <i class="far fa-clock me-1"></i> <?= $row['Durasi'] ?: '0' ?> Jam
                                    </span>
                                </td>
                                <td class="text-end price-text">
                                    Rp <?= number_format($row['Harga_Layanan'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Layanan'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Lay_status'] ?? 'Aktif') ?></span>
                                    </span>
                                </td>
                                <?php if($role == 'Admin') : ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        <button type="button" class="btn-action btn-lihat view-details-btn" data-id="<?= $row['ID_Layanan'] ?>" title="Lihat Detail Layanan">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="javascript:void(0)" class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" data-id="<?= $row['ID_Layanan'] ?>" data-current="<?= htmlspecialchars($row['Lay_status'] ?? 'Aktif') ?>" id="toggle-btn-<?= $row['ID_Layanan'] ?>" title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                                            <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </a>
                                        <a href="<?= $is_aktif ? 'layanan_read.php?id=' . $row['ID_Layanan'] : 'javascript:void(0)' ?>" class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" id="edit-btn-<?= $row['ID_Layanan'] ?>" title="<?= $is_aktif ? 'Edit Layanan' : 'Layanan Non-Aktif tidak dapat diedit' ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <button type="button" class="btn-action btn-hard delete-trigger-btn" data-bs-toggle="modal" data-bs-target="#confirmModal" data-href="layanan_read.php?action=delete&id=<?= $row['ID_Layanan'] ?>" data-id="<?= $row['ID_Layanan'] ?>" data-title="Hapus Layanan" data-message="Apakah Anda yakin ingin menghapus data layanan <b><?= htmlspecialchars($row['Nama_Layanan']) ?></b>?" data-color="btn-danger">
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
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> layanan tersaring.
                </div>
                <nav id="pagination-nav">
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

    <!-- MODAL DETAIL LAYANAN -->
    <div class="modal fade" id="detailLayananModal" tabindex="-1" aria-labelledby="detailLayananModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0">
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div id="detail-avatar-container" class="mb-3"></div>
                        <h2 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Layanan Jasa</h2>
                        <span class="badge bg-light text-dark fw-bold mt-1" id="detail-kode" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">Kode: -</span>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-concierge-bell me-2"></i>Identitas Jasa</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:40%;">Klasifikasi</td><td class="fw-bold text-dark" id="detail-kategori">Layanan Jasa</td></tr>
                                    <tr><td class="text-muted">Estimasi Waktu</td><td class="fw-bold text-dark" id="detail-durasi">- Jam</td></tr>
                                    <tr><td class="text-muted">Status Jasa</td><td class="fw-bold text-dark" id="detail-status-txt">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-0 p-3 shadow-sm h-100" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-coins me-2"></i>Finansial Tarif Jasa</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:35%;">Tarif Jasa</td><td class="fw-bold text-success fs-5" id="detail-tarif">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-file-alt me-2"></i>Rincian Deskripsi & Prosedur Jasa</h6>
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
                    <div class="mb-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(13, 148, 136, 0.08);">
                        <i class="fas fa-exclamation-triangle text-teal fs-3" style="color: var(--primary);"></i>
                    </div>
                    <p id="confirmMessageText" class="text-muted mb-0 fs-6">Apakah Anda yakin dengan tindakan ini?</p>
                </div>
                <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-center gap-2" style="border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-bold" data-bs-dismiss="modal" style="border-radius: 12px;">Batal</button>
                    <button type="button" id="confirmExecuteBtn" class="btn px-4 py-2 rounded-pill fw-bold text-white shadow-sm" style="background: var(--primary); border: none;">Proses</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('layanan-tbody');
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

            // Fungsi refresh halaman berbasis AJAX DOM Swapping
            function refreshPageContent() {
                tbody.style.opacity = '0.4';
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // 1. Perbarui daftar body table
                        const newTbody = doc.getElementById('layanan-tbody');
                        if (newTbody) {
                            tbody.innerHTML = newTbody.innerHTML;
                        }
                        
                        // 2. Perbarui nilai statistik pada kartu atas & badge count
                        ['stat-total', 'stat-aktif', 'stat-off', 'stat-avg', 'table-count-badge'].forEach(id => {
                            const oldEl = document.getElementById(id);
                            const newEl = doc.getElementById(id);
                            if (oldEl && newEl) {
                                oldEl.innerHTML = newEl.innerHTML;
                            }
                        });
                        
                        // 3. Perbarui visual progress bar
                        ['progress-aktif', 'progress-off'].forEach(id => {
                            const oldEl = document.getElementById(id);
                            const newEl = doc.getElementById(id);
                            if (oldEl && newEl) {
                                oldEl.style.width = newEl.style.width;
                            }
                        });
                        
                        // 4. Perbarui navigasi pagination
                        const oldPagination = document.getElementById('pagination-nav');
                        const newPagination = doc.getElementById('pagination-nav');
                        if (oldPagination && newPagination) {
                            oldPagination.innerHTML = newPagination.innerHTML;
                        }
                        
                        tbody.style.opacity = '1';
                    })
                    .catch(err => {
                        console.error('Error refreshing content:', err);
                        tbody.style.opacity = '1';
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

                fetch(`layanan_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        tbody.style.opacity = '1';
                        
                        const rows = tbody.querySelectorAll('.layanan-row');
                        document.getElementById('table-count-badge').textContent = rows.length;
                    })
                    .catch(error => {
                        console.error('Error Search AJAX:', error);
                        tbody.style.opacity = '1';
                        showToast('Gagal memuat data layanan jasa.', 'danger');
                    });
            }

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Layanan (Tombol Lihat)
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`layanan_read.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Layanan || '-';
                                document.getElementById('detail-kode').textContent = 'Kode: ' + (d.Kode_Layanan || '-');
                                document.getElementById('detail-kategori').textContent = d.Nama_Kategori || 'Layanan Jasa';
                                document.getElementById('detail-durasi').textContent = d.Durasi_Format || (d.Durasi ? d.Durasi + ' Jam' : '-');
                                document.getElementById('detail-status-txt').textContent = d.Lay_status || 'Aktif';
                                
                                const formatRupiah = (val) => 'Rp ' + parseFloat(val).toLocaleString('id-ID');
                                document.getElementById('detail-tarif').textContent = formatRupiah(d.Harga_Layanan || 0);
                                document.getElementById('detail-deskripsi').textContent = d.Deskripsi_Layanan || 'Tidak ada deskripsi detail untuk layanan.';

                                const modalAvatarContainer = document.getElementById('detail-avatar-container');
                                if (d.Foto_Layanan && d.Foto_Layanan !== '') {
                                    modalAvatarContainer.innerHTML = `<img src="../../assets/uploads/layanan/${d.Foto_Layanan}" style="width:90px; height:90px; border-radius:18px; object-fit:cover; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">`;
                                } else {
                                    const initials = getInitialsLayananJs(d.Nama_Layanan);
                                    modalAvatarContainer.innerHTML = `<div class="plg-avatar avatar-teal" style="width:90px; height:90px; border-radius:18px; font-size:2rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);"><span class="avatar-initial">${initials}</span></div>`;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailLayananModal'));
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

            function getInitialsLayananJs(name) {
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
                        element.classList.remove('pop-stat');
                        void element.offsetWidth;
                        element.classList.add('pop-stat');
                        
                        setTimeout(() => {
                            element.classList.remove('pop-stat');
                        }, 450);
                    }
                }
            }

            // --- SISTEM TOGGLE STATUS AJAX LAYANAN ---
            tbody.addEventListener('click', function (e) {
                const toggleBtn = e.target.closest('.toggle-status-btn');
                
                if (toggleBtn) {
                    e.preventDefault();
                    
                    const id = toggleBtn.getAttribute('data-id');
                    const currentStatus = toggleBtn.getAttribute('data-current');
                    const parentRow = toggleBtn.closest('tr');
                    const indicator = parentRow.querySelector('.avatar-status-indicator');
                    const statusPill = document.getElementById(`status-pill-${id}`);
                    
                    toggleBtn.classList.add('pop-effect');
                    if (statusPill) statusPill.classList.add('pop-effect');

                    fetch(`layanan_toggle_status.php?id=${id}&current=${encodeURIComponent(currentStatus)}`)
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

                                if (statusPill) {
                                    const statusText = statusPill.querySelector('.status-text');
                                    statusText.textContent = newStatus;
                                    statusPill.className = newStatus === 'Aktif' ? 'status-pill pill-aktif' : 'status-pill pill-off';
                                }

                                const editBtn = document.getElementById(`edit-btn-${id}`);
                                if (editBtn) {
                                    if (newStatus === 'Aktif') {
                                        editBtn.classList.remove('disabled');
                                        editBtn.setAttribute('href', `layanan_read.php?id=${id}`);
                                    } else {
                                        editBtn.classList.add('disabled');
                                        editBtn.setAttribute('href', 'javascript:void(0)');
                                    }
                                }

                                animateStatTextUpdate('stat-aktif', data.total_a);
                                animateStatTextUpdate('stat-off', data.total_o);

                                const totalVal = parseInt(document.getElementById('stat-total').textContent) || 1;
                                if (data.total_a !== undefined) {
                                    document.getElementById('progress-aktif').style.width = Math.round((data.total_a / totalVal) * 100) + '%';
                                }
                                if (data.total_o !== undefined) {
                                    document.getElementById('progress-off').style.width = Math.round((data.total_o / totalVal) * 100) + '%';
                                }

                                showToast(`Status layanan berhasil diubah menjadi ${newStatus}`, 'success');

                                if (currentStatusFilter !== '' && currentStatusFilter !== newStatus) {
                                    parentRow.style.opacity = '0';
                                    parentRow.style.transform = 'translateY(10px)';
                                    setTimeout(() => {
                                        parentRow.remove();
                                        document.getElementById('table-count-badge').textContent = tbody.querySelectorAll('.layanan-row').length;
                                        refreshPageContent(); 
                                    }, 300);
                                }
                            } else {
                                showToast('Gagal memperbarui status layanan jasa.', 'danger');
                            }
                        })
                        .catch(error => {
                            console.error('Error Toggle Status:', error);
                            showToast('Gagal memproses perubahan status.', 'danger');
                        })
                        .finally(() => {
                            setTimeout(() => {
                                toggleBtn.classList.remove('pop-effect');
                                if (statusPill) statusPill.classList.remove('pop-effect');
                            }, 300);
                        });
                }
            });

            // Delegasi Hapus Layanan
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
                        .then(res => {
                            if (res.success) {
                                if (bsConfirmModalInstance) {
                                    bsConfirmModalInstance.hide();
                                }
                                
                                const targetRow = document.getElementById(`row-${targetRowId}`);
                                if (targetRow) {
                                    targetRow.style.opacity = '0';
                                    targetRow.style.transform = 'translateX(50px) scale(0.95)';
                                    targetRow.style.transition = 'all 0.5s var(--ease-out-expo)';
                                    
                                    setTimeout(() => {
                                        targetRow.remove();
                                        const rows = tbody.querySelectorAll('.layanan-row');
                                        document.getElementById('table-count-badge').textContent = rows.length;
                                        refreshPageContent(); 
                                    }, 500);
                                }

                                showToast('Layanan berhasil dihapus', 'success');
                            } else {
                                showToast(res.message || 'Gagal menghapus data layanan.', 'danger');
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

            // =========================================================================
            // INTERSEPT FORM TAMBAH & UPDATE LAYANAN (DENGAN SWEETALERT & JEDA)
            // =========================================================================
            document.addEventListener('submit', function(e) {
                const form = e.target;
                
                // Cari tahu apakah form berada di dalam modal tambah atau update
                const isCreateForm = form.closest('#modalTambahLayanan');
                const isUpdateForm = form.closest('#modalUpdateLayanan') || form.closest('#modalEditLayanan') || form.closest('#modalLayananUpdate') || form.closest('[id*="modalUpdate"]') || form.closest('[id*="modalEdit"]');

                if (isCreateForm || isUpdateForm) {
                    e.preventDefault();
                    
                    // Tampilkan SweetAlert Loading
                    Swal.fire({
                        title: 'Memproses Data...',
                        text: 'Harap tunggu sebentar, data sedang diproses.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(form);
                    const startTime = Date.now();

                    fetch('layanan_read.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        const elapsedTime = Date.now() - startTime;
                        const minDelay = 1500; // Jeda minimal 1.5 detik agar transisi mulus
                        const remainingDelay = Math.max(minDelay - elapsedTime, 0);

                        setTimeout(() => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });

                                // Tutup modal secara otomatis
                                const modalElement = form.closest('.modal');
                                if (modalElement) {
                                    const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                                    modalInstance.hide();
                                    
                                    // Bersihkan sisa backdrop modal
                                    const backdrop = document.querySelector('.modal-backdrop');
                                    if (backdrop) backdrop.remove();
                                    document.body.style.overflow = '';
                                    document.body.style.paddingRight = '';
                                }

                                // Reset form isian
                                form.reset();

                                // Bersihkan preview foto jika ada
                                const previewImg = form.querySelector('.img-preview');
                                if (previewImg) {
                                    previewImg.src = '';
                                    previewImg.style.display = 'none';
                                }

                                // Bersihkan parameter GET ?id= di URL jika dalam mode edit
                                if (isUpdateForm) {
                                    const url = new URL(window.location.href);
                                    url.searchParams.delete('id');
                                    window.history.pushState({}, '', url);
                                }

                                // Perbarui data pada tabel tanpa melakukan reload halaman
                                refreshPageContent();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message || 'Terjadi kesalahan saat memproses data.',
                                    confirmButtonColor: '#0d9488'
                                });
                            }
                        }, remainingDelay);
                    })
                    .catch(error => {
                        console.error('Error Form Submit AJAX:', error);
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Kesalahan Koneksi',
                                text: 'Gagal terhubung ke server. Silakan coba kembali.',
                                confirmButtonColor: '#0d9488'
                            });
                        }, 1000);
                    });
                }
            });

        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODAL POP UP DIPROTEKSI HANYA UNTUK ADMIN -->
    <?php if ($role === 'Admin') : ?>
        <?php include 'layanan_create.php'; ?>
        <?php include 'layanan_update.php'; ?>
    <?php endif; ?>
</body>
</html>
