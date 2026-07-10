
<?php
session_start();
include_once '../../config/koneksi.php';

// Proteksi Admin / Karyawan / Staff
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Karyawan' && $_SESSION['role'] !== 'Staff') {
    header("Location: ../../dashboard/index.php");
    exit();
}

// ==================== CONTROLLER POST HANDLER (DENGAN PENERJEMAH ERROR DATABASE) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 1. HANDLER EDIT KATEGORI (UPDATE)
    if (isset($_POST['id']) && !empty($_POST['id']) && isset($_POST['Nama_Kategori'])) {
        $id_kategori = $_POST['id'];
        $nama        = $_POST['Nama_Kategori'] ?? '';
        $tipe        = $_POST['Tipe_Kategori'] ?? '';
        $deskripsi   = $_POST['Deskripsi'] ?? '';
        $modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

        // Ambil data lama dari database
        $sql_ref = "{call sp_Kategori_Read(?)}";
        $query_ref = sqlsrv_query($conn, $sql_ref, array($id_kategori));
        $data_lama = ($query_ref !== false) ? sqlsrv_fetch_array($query_ref, SQLSRV_FETCH_ASSOC) : null;

        if (!$data_lama) {
            echo json_encode(['status' => 'error', 'message' => 'Data kategori asli tidak ditemukan di sistem.']);
            exit;
        }

        $foto_baru = $data_lama['Foto_Kategori']; // Default menggunakan foto lama
        $upload_ok = true;

        if (!preg_match('/^[a-zA-Z\s]+$/', $nama)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama kategori hanya diperbolehkan berisi huruf alfabet dan spasi!']);
            exit;
        }
        if (strlen(trim($deskripsi)) < 20) {
            echo json_encode(['status' => 'error', 'message' => 'Deskripsi kategori minimal harus terdiri dari 20 karakter!']);
            exit;
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $foto_name = $_FILES['foto']['name'];
            $tmp_name  = $_FILES['foto']['tmp_name'];
            $file_size = $_FILES['foto']['size'];
            $ekstensi  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $ekstensi_diperbolehkan = array('jpg', 'jpeg', 'png', 'webp');

            if (in_array($ekstensi, $ekstensi_diperbolehkan)) {
                if ($file_size <= 2 * 1024 * 1024) {
                    $foto_baru = "cat_" . time() . "." . $ekstensi;
                    $target_dir = "../../assets/uploads/kategori/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    if (move_uploaded_file($tmp_name, $target_dir . $foto_baru)) {
                        if (!empty($data_lama['Foto_Kategori']) && file_exists($target_dir . $data_lama['Foto_Kategori'])) {
                            unlink($target_dir . $data_lama['Foto_Kategori']);
                        }
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan berkas foto kategori ke server.']);
                        exit;
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.']);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Format berkas foto tidak valid! Gunakan JPG, JPEG, PNG atau WEBP.']);
                exit;
            }
        }

        $sql_up = "{call sp_Kategori_Update(?, ?, ?, ?, ?, ?, ?, ?)}";
        $foto_barang = null;
        $kat_status = null;

        $params = array($id_kategori, $nama, $deskripsi, $foto_baru, $tipe, $foto_barang, $kat_status, $modified_by);
        $stmt = sqlsrv_query($conn, $sql_up, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $friendly_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $err_message = $err['message'];
                    $err_code = $err['code'] ?? 0;

                    // Terjemahkan error duplikasi UNIQUE KEY agar rapi dan mudah dibaca user
                    if ($err_code == 2627 || $err_code == 2601 || stripos($err_message, 'UNIQUE KEY') !== false || stripos($err_message, 'duplicate') !== false) {
                        $friendly_err = "Nama kategori ini sudah terdaftar di sistem. Silakan gunakan nama kategori lain yang unik!";
                        break;
                    } else {
                        $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err_message);
                        $friendly_err .= trim($clean_msg) . " ";
                    }
                }
            } else {
                $friendly_err = 'Terjadi kesalahan sistem saat menghubungi database.';
            }
            echo json_encode(['status' => 'error', 'message' => $friendly_err]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Informasi kategori berhasil diperbarui!']);
            sqlsrv_free_stmt($stmt);
        }
        exit;
    }

    // 2. HANDLER TAMBAH KATEGORI (CREATE)
    if (isset($_POST['Nama_Kategori']) && (!isset($_POST['id']) || empty($_POST['id']))) {
        $nama      = $_POST['Nama_Kategori'] ?? '';
        $tipe      = $_POST['Tipe_Kategori'] ?? '';
        $deskripsi = $_POST['Deskripsi'] ?? '';

        $kat_status  = 'Aktif'; 
        $foto_barang = null; 
        $created_by  = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

        $foto_baru = null; 

        if (!preg_match('/^[a-zA-Z\s]+$/', $nama)) {
            echo json_encode(['status' => 'error', 'message' => 'Nama kategori hanya diperbolehkan berisi huruf alfabet dan spasi!']);
            exit;
        }
        if (strlen(trim($deskripsi)) < 20) {
            echo json_encode(['status' => 'error', 'message' => 'Deskripsi kategori minimal harus terdiri dari 20 karakter!']);
            exit;
        }

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $foto_name = $_FILES['foto']['name'];
            $tmp_name  = $_FILES['foto']['tmp_name'];
            $file_size = $_FILES['foto']['size'];
            $ekstensi  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $ekstensi_diperbolehkan = array('jpg', 'jpeg', 'png', 'webp');

            if (in_array($ekstensi, $ekstensi_diperbolehkan)) {
                if ($file_size <= 2 * 1024 * 1024) {
                    $foto_baru = "cat_" . time() . "." . $ekstensi;
                    $target_dir = "../../assets/uploads/kategori/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    if (!move_uploaded_file($tmp_name, $target_dir . $foto_baru)) {
                        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan berkas foto kategori ke server.']);
                        exit;
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.']);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Format berkas foto tidak valid! Gunakan JPG, JPEG, PNG atau WEBP.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Foto kategori wajib diunggah!']);
            exit;
        }

        $sql = "{call sp_Kategori_Create(?, ?, ?, ?, ?, ?, ?)}";
        $params = array($nama, $deskripsi, $foto_baru, $tipe, $foto_barang, $kat_status, $created_by);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $friendly_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $err_message = $err['message'];
                    $err_code = $err['code'] ?? 0;

                    // Terjemahkan error duplikasi UNIQUE KEY agar rapi dan mudah dibaca user
                    if ($err_code == 2627 || $err_code == 2601 || stripos($err_message, 'UNIQUE KEY') !== false || stripos($err_message, 'duplicate') !== false) {
                        $friendly_err = "Nama kategori ini sudah terdaftar di sistem. Silakan gunakan nama kategori lain yang unik!";
                        break;
                    } else {
                        $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err_message);
                        $friendly_err .= trim($clean_msg) . " ";
                    }
                }
            } else {
                $friendly_err = 'Terjadi kesalahan sistem saat menghubungi database.';
            }
            echo json_encode(['status' => 'error', 'message' => $friendly_err]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Kategori baru berhasil ditambahkan!']);
            sqlsrv_free_stmt($stmt);
        }
        exit;
    }
}

// Fungsi helper untuk menghasilkan inisial nama kategori secara aman
if (!function_exists('getInitialsKategori')) {
    function getInitialsKategori($name) {
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
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : ''; // Menyaring tipe kategori 'Barang' atau 'Layanan'

// --- KONFIGURASI PAGINATION (10 Data Per Halaman) ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- HANDLER AJAX DETAIL KATEGORI ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'detail' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "{call sp_Kategori_Read(?)}";
    $query = sqlsrv_query($conn, $sql, array($id));
    
    if ($query === false) {
        echo json_encode(['success' => false, 'error' => sqlsrv_errors()]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kategori tidak ditemukan.']);
    }
    exit;
}

// --- HANDLER AJAX UNTUK LIVE SEARCH & FILTER (Output HTML) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == 'search') {
    // Ambil semua data via Stored Procedure
    $all_categories = [];
    $sql_sp = "{call sp_Kategori_Read(NULL)}";
    $query_sp = sqlsrv_query($conn, $sql_sp);
    
    if ($query_sp === false) {
        die(json_encode(['success' => false, 'error' => sqlsrv_errors()]));
    }
    while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
        $all_categories[] = $row;
    }
    sqlsrv_free_stmt($query_sp);

    // Proses Filter di PHP
    $filtered = $all_categories;
    if ($search != '') {
        $filtered = array_filter($filtered, function($cat) use ($search) {
            return (stripos($cat['Nama_Kategori'], $search) !== false) || 
                   (stripos($cat['Deskripsi'], $search) !== false);
        });
    }
    if ($status_filter != '') {
        $filtered = array_filter($filtered, function($cat) use ($status_filter) {
            return $cat['Tipe_Kategori'] === $status_filter;
        });
    }

    // Urutkan berdasarkan ID_Kategori DESC agar data terbaru selalu berada di No. 1
    usort($filtered, function($a, $b) {
        return (int)$b['ID_Kategori'] - (int)$a['ID_Kategori'];
    });

    $no = 1;
    if (empty($filtered)) {
        echo '<tr>
                <td colspan="7" class="text-center py-5">
                    <div class="empty-state animate-fade-in">
                        <div class="empty-icon-wrapper mb-3">
                            <i class="fas fa-layer-group fs-2 text-muted"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Kategori tidak ditemukan</h6>
                        <p class="text-muted small mb-0">Coba gunakan kata kunci atau filter lain.</p>
                    </div>
                </td>
              </tr>';
        exit;
    }

    foreach ($filtered as $row) { 
        $is_barang = ($row['Tipe_Kategori'] == 'Barang');
        $is_aktif = (($row['Kat_status'] ?? 'Aktif') == 'Aktif');
        $badgeClass = $is_barang ? 'bg-barang' : 'bg-layanan';
        ?>
        <tr class="align-middle kategori-row animate-fade-up" id="row-<?= $row['ID_Kategori'] ?>">
            <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
            <td>
                <div class="avatar-container">
                    <div class="plg-avatar shadow-sm <?= $is_barang ? 'avatar-indigo' : 'avatar-violet' ?>">
                        <?php if (!empty($row['Foto_Kategori']) && file_exists("../../assets/uploads/kategori/" . $row['Foto_Kategori'])): ?>
                            <img src="../../assets/uploads/kategori/<?= $row['Foto_Kategori'] ?>" alt="Visual">
                        <?php else: ?>
                            <span class="avatar-initial"><?= getInitialsKategori($row['Nama_Kategori']) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                </div>
            </td>
            <td>
                <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Kategori']) ?>"><?= htmlspecialchars($row['Nama_Kategori']) ?></div>
                <div class="text-muted small text-truncate">ID: #KAT-<?= $row['ID_Kategori'] ?></div>
            </td>
            <td>
                <small class="text-muted"><?= $row['Deskripsi'] ? htmlspecialchars(substr($row['Deskripsi'], 0, 50)).'...' : '-' ?></small>
            </td>
            <td>
                <span class="badge-tipe <?= $badgeClass ?>">
                    <?= $row['Tipe_Kategori'] ?>
                </span>
            </td>
            <td class="text-center">
                <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Kategori'] ?>">
                    <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Kat_status'] ?? 'Aktif') ?></span>
                </span>
            </td>
            <td class="text-center">
                <div class="d-flex justify-content-center align-items-center action-gap">
                    
                    <!-- TOMBOL LIHAT DETAIL -->
                    <button type="button" class="btn-action btn-lihat view-details-btn" 
                            data-id="<?= $row['ID_Kategori'] ?>"
                            title="Lihat Detail Kategori">
                        <i class="fas fa-eye"></i>
                    </button>

                    <!-- TOMBOL SAKLAR STATUS KATEGORI -->
                    <a href="javascript:void(0)" 
                       class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                       data-id="<?= $row['ID_Kategori'] ?>"
                       data-current="<?= htmlspecialchars($row['Kat_status'] ?? 'Aktif') ?>"
                       id="toggle-btn-<?= $row['ID_Kategori'] ?>"
                       title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                        <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </a>

                    <!-- TOMBOL EDIT (Kondisi Terkunci Jika Non-Aktif) -->
                    <a href="<?= $is_aktif ? 'kategori_read.php?id=' . $row['ID_Kategori'] : 'javascript:void(0)' ?>" 
                       class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                       id="edit-btn-<?= $row['ID_Kategori'] ?>"
                       title="<?= $is_aktif ? 'Edit Kategori' : 'Kategori Non-Aktif tidak dapat diedit' ?>">
                        <i class="fas fa-pencil-alt"></i>
                    </a>
                    
                    <!-- TOMBOL HAPUS -->
                    <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                            data-bs-toggle="modal" data-bs-target="#confirmModal" 
                            data-href="kategori_delete.php?id=<?= $row['ID_Kategori'] ?>"
                            data-id="<?= $row['ID_Kategori'] ?>"
                            data-title="Hapus Kategori"
                            data-message="Apakah Anda yakin ingin menghapus data kategori <b><?= htmlspecialchars($row['Nama_Kategori']) ?></b>?"
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

// Ambil semua data via Stored Procedure untuk render halaman utama
$all_categories = [];
$sql_sp = "{call sp_Kategori_Read(NULL)}";
$query_sp = sqlsrv_query($conn, $sql_sp);
if ($query_sp === false) {
    die(print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($query_sp, SQLSRV_FETCH_ASSOC)) {
    $all_categories[] = $row;
}
sqlsrv_free_stmt($query_sp);

// --- PRE-CALCULATE DATA STATISTIK DI SISI PHP ---
$total_k = count($all_categories);
$total_b = count(array_filter($all_categories, function($c) { return $c['Tipe_Kategori'] === 'Barang'; }));
$total_l = count(array_filter($all_categories, function($c) { return $c['Tipe_Kategori'] === 'Layanan'; }));
$total_a = count(array_filter($all_categories, function($c) { return ($c['Kat_status'] ?? 'Aktif') === 'Aktif'; }));
$total_o = count(array_filter($all_categories, function($c) { return ($c['Kat_status'] ?? 'Aktif') === 'Non-Aktif'; }));

$pct_barang = $total_k > 0 ? round(($total_b / $total_k) * 100) : 0;
$pct_layanan = $total_k > 0 ? round(($total_l / $total_k) * 100) : 0;
$pct_aktif = $total_k > 0 ? round(($total_a / $total_k) * 100) : 0;
$pct_off = $total_k > 0 ? round(($total_o / $total_k) * 100) : 0;

// Filter Utama Sesuai Parameter URL
$filtered_main = $all_categories;
if ($search != '') {
    $filtered_main = array_filter($filtered_main, function($cat) use ($search) {
        return (stripos($cat['Nama_Kategori'], $search) !== false) || 
               (stripos($cat['Deskripsi'], $search) !== false);
    });
}
if ($status_filter != '') {
    $filtered_main = array_filter($filtered_main, function($cat) use ($status_filter) {
        return $cat['Tipe_Kategori'] === $status_filter;
    });
}

// Urutkan berdasarkan ID_Kategori DESC agar data terbaru selalu berada di No. 1
usort($filtered_main, function($a, $b) {
    return (int)$b['ID_Kategori'] - (int)$a['ID_Kategori'];
});

// Hitung total record tersaring & total halaman
$total_records = count($filtered_main);
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $limit;

// Ambil potongan item array sesuai halaman saat ini
$paged_categories = array_slice($filtered_main, $offset, $limit);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kategori | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #8b5cf6; /* Royal Violet */
            --primary-light: #a78bfa; 
            --primary-glow: rgba(139, 92, 246, 0.15);
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
            --card-shadow-hover: 0 20px 35px -10px rgba(139, 92, 246, 0.12), 0 1px 5px rgba(0, 0, 0, 0.03);
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

        /* 5-COLUMN STATS GRID */
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
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem 1.25rem;
            min-height: 140px;
            box-shadow: var(--card-shadow);
        }
        .card-stat:hover {
            transform: translateY(-6px);
            box-shadow: var(--card-shadow-hover);
        }
        .card-stat-total:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(139, 92, 246, 0.12); border-color: rgba(139, 92, 246, 0.25); }
        .card-stat-barang:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(234, 88, 12, 0.12); border-color: rgba(234, 88, 12, 0.25); }
        .card-stat-layanan:hover { transform: translateY(-6px); box-shadow: 0 20px 35px -10px rgba(2, 132, 199, 0.12); border-color: rgba(2, 132, 199, 0.25); }
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

        .icon-box-total { background: linear-gradient(135deg, #ddd6fe, #c7d2fe); color: var(--primary); }
        .icon-box-barang { background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #ea580c; }
        .icon-box-layanan { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0284c7; }
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
            background-color: rgba(139, 92, 246, 0.015);
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
        .avatar-indigo { background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #ea580c; }
        .avatar-violet { background: linear-gradient(135deg, #f3e8ff, #ddd6fe); color: var(--primary); }

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
        .badge-tipe { padding: 8px 14px; border-radius: 10px; font-weight: 600; font-size: 0.8rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .bg-barang { background: #fff7ed; color: #f97316; border: 1px solid rgba(249, 115, 22, 0.15); }
        .bg-layanan { background: #f5f3ff; color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.15); }

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
        .btn-status-aktif { background: rgba(16, 185, 129, 0.1); color: var(--success); } 
        .btn-status-off { background: var(--slate-100); color: #64748b; }   
        .btn-edit { background: rgba(139, 92, 246, 0.08); color: var(--primary); }           
        .btn-hard { background: rgba(239, 68, 68, 0.08); color: var(--danger); }           
        
        .btn-action:hover { transform: translateY(-3px); }
        .btn-action:hover i { transform: scale(1.1); }
        .btn-lihat:hover { background: #0ea5e9; color: #ffffff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15); }
        .btn-status-aktif:hover { background: var(--success); color: #ffffff; box-shadow: 0 4px 12px var(--success-glow); }
        .btn-status-off:hover { background: #64748b; color: #ffffff; }
        
        .btn-edit.disabled {
            background: var(--slate-100) !important;
            color: #94a3b8 !important;
            cursor: not-allowed !important;
            pointer-events: none;
            opacity: 0.55;
            transform: none !important;
        }
        .btn-edit:hover:not(.disabled) { background: var(--primary); color: #ffffff; box-shadow: 0 4px 12px var(--primary-glow); }
        .btn-hard:hover { background: var(--danger); color: #ffffff; box-shadow: 0 4px 12px var(--danger-glow); }

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

        /* PRESERVASI STYLE MODAL DETAIL - SEJAJAR & KETENGAH */
        #detailKategoriModal {
            z-index: 1060 !important;
            backdrop-filter: blur(8px);
            background-color: rgba(15, 23, 42, 0.4);
        }

        @media (min-width: 992px) {
            #detailKategoriModal {
                padding-left: 260px !important; 
            }
        }

        #detailKategoriModal.show .modal-content-custom {
            animation: modalZoomInKategori 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
    </style>
</head>
<body>
    <?php include '../../layouts/navbar.php'; ?>

    <div class="toast-container-modern" id="toastContainer"></div>

    <div class="container-fluid px-4 py-5">
        
        <!-- HEADER SECTION -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 animate-fade-up">
            <div>
                <h2 class="fw-bold text-dark mb-1">Manajemen Kategori 🏷️</h2>
                <p class="text-muted mb-0">Kelola rincian tipe klasifikasi kategori barang dan layanan jasa Petshop Pro.</p>
            </div>
            <div class="d-flex flex-wrap gap-3 align-items-center mt-3 mt-md-0">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <form id="search-form" method="GET" action="" class="m-0 w-100">
                        <input type="hidden" name="status_filter" value="<?= htmlspecialchars($status_filter) ?>">
                        <input type="text" id="search-input" name="search" class="form-control input-search" 
                            placeholder="Cari kategori..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                    </form>
                </div>
                <!-- TOMBOL TAMBAH KATEGORI (DENGAN PEMICU POP-UP MODAL) -->
                <button type="button" class="btn btn-tambah" style="background: var(--primary); border:none; height: 45px; display:inline-flex; align-items:center; gap:8px;" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                    <i class="fas fa-plus-circle"></i> Tambah Kategori
                </button>
            </div>
        </div>

        <!-- 5-COLUMN STATS SECTION -->
        <div class="stats-grid">
            
            <!-- STAT 1: TOTAL KATEGORI -->
            <div class="card card-stat card-stat-total animate-fade-up delay-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Total Kategori</p>
                        <h2 class="stat-value mb-0" id="stat-total"><?= $total_k ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-total">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" style="width: 100%; background: var(--primary);"></div>
                </div>
            </div>

            <!-- STAT 2: KATEGORI BARANG -->
            <div class="card card-stat card-stat-barang animate-fade-up delay-2">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Kategori Barang</p>
                        <h2 class="stat-value mb-0" id="stat-barang"><?= $total_b ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-barang">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-barang" style="width: <?= $pct_barang ?>%; background: #ea580c;"></div>
                </div>
            </div>

            <!-- STAT 3: KATEGORI LAYANAN -->
            <div class="card card-stat card-stat-layanan animate-fade-up delay-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="stat-label mb-1">Kategori Layanan</p>
                        <h2 class="stat-value mb-0" id="stat-layanan"><?= $total_l ?></h2>
                    </div>
                    <div class="stat-icon-box icon-box-layanan">
                        <i class="fas fa-concierge-bell"></i>
                    </div>
                </div>
                <div class="stat-progress-bar">
                    <div class="stat-progress-fill" id="progress-layanan" style="width: <?= $pct_layanan ?>%; background: #0284c7;"></div>
                </div>
            </div>

            <!-- STAT 4: LAYANAN TERSEDIA -->
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

            <!-- STAT 5: LAYANAN TIDAK TERSEDIA -->
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
                    Daftar Klasifikasi Kategori <span class="badge bg-light text-primary border rounded-pill" style="font-size: 0.75rem;" id="table-count-badge"><?= $total_records ?></span>
                </h5>
                <div class="filter-container">
                    <a href="?page=1&status_filter=&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == '') ? 'active' : '' ?>">Semua Status</a>
                    <a href="?page=1&status_filter=Barang&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Barang') ? 'active' : '' ?>">Barang</a>
                    <a href="?page=1&status_filter=Layanan&search=<?= urlencode($search) ?>" class="filter-chip <?= ($status_filter == 'Layanan') ? 'active' : '' ?>">Layanan</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 70px;">No</th>
                            <th style="width: 15%;">Visual</th>
                            <th style="width: 25%;">Nama Kategori</th>
                            <th style="width: 30%;">Deskripsi</th>
                            <th style="width: 15%;">Tipe</th>
                            <th class="text-center" style="width: 120px;">Status</th>
                            <th class="text-center" style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="kategori-tbody">
                        <?php
                        $no = 1 + $offset;
                        if (empty($paged_categories)) {
                            echo '<tr><td colspan="7" class="text-center py-5 text-muted">Tidak ada data kategori pada halaman ini...</td></tr>';
                        } else {
                            foreach($paged_categories as $row) { 
                                $is_aktif = (($row['Kat_status'] ?? 'Aktif') == 'Aktif');
                                $is_barang = ($row['Tipe_Kategori'] == 'Barang');
                                $badgeClass = $is_barang ? 'bg-barang' : 'bg-layanan';
                            ?>
                            <tr class="align-middle kategori-row" id="row-<?= $row['ID_Kategori'] ?>">
                                <td class="text-center fw-bold text-muted"><?= $no++ ?></td>
                                <td>
                                    <div class="avatar-container">
                                        <div class="plg-avatar shadow-sm <?= $is_barang ? 'avatar-indigo' : 'avatar-violet' ?>">
                                            <?php if (!empty($row['Foto_Kategori']) && file_exists("../../assets/uploads/kategori/" . $row['Foto_Kategori'])): ?>
                                                <img src="../../assets/uploads/kategori/<?= $row['Foto_Kategori'] ?>" alt="Visual">
                                            <?php else: ?>
                                                <span class="avatar-initial"><?= getInitialsKategori($row['Nama_Kategori']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="avatar-status-indicator <?= $is_aktif ? 'status-online' : 'status-offline' ?>"></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark text-truncate fs-6" title="<?= htmlspecialchars($row['Nama_Kategori']) ?>"><?= htmlspecialchars($row['Nama_Kategori']) ?></div>
                                    <div class="text-muted small text-truncate">ID: #KAT-<?= $row['ID_Kategori'] ?></div>
                                </td>
                                <td class="text-muted small text-truncate">
                                    <?= $row['Deskripsi'] ? htmlspecialchars($row['Deskripsi']) : '-' ?>
                                </td>
                                <td>
                                    <span class="badge-tipe <?= $badgeClass ?>">
                                        <?= $row['Tipe_Kategori'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="status-pill <?= $is_aktif ? 'pill-aktif' : 'pill-off' ?>" id="status-pill-<?= $row['ID_Kategori'] ?>">
                                        <span class="dot">●</span> <span class="status-text"><?= htmlspecialchars($row['Kat_status'] ?? 'Aktif') ?></span>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center action-gap">
                                        
                                        <!-- TOMBOL LIHAT DETAIL -->
                                        <button type="button" class="btn-action btn-lihat view-details-btn" 
                                                data-id="<?= $row['ID_Kategori'] ?>"
                                                title="Lihat Detail Kategori">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TOMBOL SAKLAR STATUS KATEGORI -->
                                        <a href="javascript:void(0)" 
                                           class="btn-action toggle-status-btn <?= $is_aktif ? 'btn-status-aktif' : 'btn-status-off' ?>" 
                                           data-id="<?= $row['ID_Kategori'] ?>"
                                           data-current="<?= htmlspecialchars($row['Kat_status'] ?? 'Aktif') ?>"
                                           id="toggle-btn-<?= $row['ID_Kategori'] ?>"
                                           title="Ubah Status ke <?= $is_aktif ? 'Non-Aktif' : 'Aktif' ?>">
                                            <i class="fas <?= $is_aktif ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </a>

                                        <!-- TOMBOL EDIT -->
                                        <a href="<?= $is_aktif ? 'kategori_read.php?id=' . $row['ID_Kategori'] : 'javascript:void(0)' ?>" 
                                           class="btn-action btn-edit <?= !$is_aktif ? 'disabled' : '' ?>" 
                                           id="edit-btn-<?= $row['ID_Kategori'] ?>"
                                           title="<?= $is_aktif ? 'Edit Kategori' : 'Kategori Non-Aktif tidak dapat diedit' ?>">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>

                                        <!-- TOMBOL HAPUS -->
                                        <button type="button" class="btn-action btn-hard delete-trigger-btn" 
                                                data-bs-toggle="modal" data-bs-target="#confirmModal" 
                                                data-href="kategori_delete.php?id=<?= $row['ID_Kategori'] ?>"
                                                data-id="<?= $row['ID_Kategori'] ?>"
                                                data-title="Hapus Kategori"
                                                data-message="Apakah Anda yakin ingin menghapus data kategori <b><?= htmlspecialchars($row['Nama_Kategori']) ?></b>?"
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
                    Menampilkan <strong><?= min($offset + 1, $total_records) ?></strong> sampai <strong><?= min($offset + $limit, $total_records) ?></strong> dari total <strong><?= $total_records ?></strong> kategori tersaring.
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

    <!-- MODAL DETAIL KATEGORI (ALIGNMENT & BACKDROP KETENGAH) -->
    <div class="modal fade" id="detailKategoriModal" tabindex="-1" aria-labelledby="detailKategoriModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modal-content-custom border-0">
                <!-- Header Card Pusat (Diketengahkah Sepenuhnya) -->
                <div class="modal-header-centered">
                    <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="d-flex flex-column align-items-center text-center">
                        <div id="detail-avatar-container" class="mb-3">
                            <!-- Diisi via JS (Foto atau Inisial) -->
                        </div>
                        <h2 class="fw-bold mb-1 text-white" id="detail-nama" style="letter-spacing:-0.5px;">Nama Kategori</h2>
                        <span class="badge bg-light text-dark fw-bold mt-1" id="detail-tipe" style="font-size:0.9rem; padding: 6px 16px; border-radius: 50px;">Tipe: -</span>
                    </div>
                </div>
                
                <div class="modal-body p-4 bg-light text-start">
                    <div class="row g-4">
                        <!-- Informasi Utama -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Informasi Utama Kategori</h6>
                                <table class="table table-borderless table-sm mb-0 small">
                                    <tr><td class="text-muted" style="width:25%;">ID Kategori</td><td class="fw-bold text-dark">#KAT-<span id="detail-id">-</span></td></tr>
                                    <tr><td class="text-muted">Nama Kategori</td><td class="fw-bold text-dark" id="detail-nama-txt">-</td></tr>
                                    <tr><td class="text-muted">Tipe Klasifikasi</td><td class="fw-bold text-dark text-uppercase" id="detail-tipe-txt">-</td></tr>
                                </table>
                            </div>
                        </div>

                        <!-- Deskripsi Lengkap -->
                        <div class="col-md-12">
                            <div class="card border-0 p-3 shadow-sm" style="border-radius: 16px; background:#fff;">
                                <h6 class="fw-bold text-primary mb-3"><i class="fas fa-file-alt me-2"></i>Rincian Deskripsi Kategori</h6>
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
                    <div class="mb-3 d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: rgba(139, 92, 246, 0.08);">
                        <i class="fas fa-exclamation-triangle text-purple fs-3" style="color: var(--primary);"></i>
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

    <!-- SCRIPT JAVASCRIPT AJAX & PAGINATION UTAMA -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            
            const searchInput = document.getElementById('search-input');
            const tbody = document.getElementById('kategori-tbody');
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

            // Fungsi untuk memperbarui komponen halaman utama via DOM Swapping (Tanpa Reload Halaman Penuh)
            function refreshPageContent() {
                tbody.style.opacity = '0.4';
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // 1. Perbarui data baris tabel
                        const newTbody = doc.getElementById('kategori-tbody');
                        if (newTbody) {
                            tbody.innerHTML = newTbody.innerHTML;
                        }
                        
                        // 2. Perbarui nilai numerik di kartu statistik & lencana jumlah tabel
                        ['stat-total', 'stat-barang', 'stat-layanan', 'stat-aktif', 'stat-off', 'table-count-badge'].forEach(id => {
                            const oldEl = document.getElementById(id);
                            const newEl = doc.getElementById(id);
                            if (oldEl && newEl) {
                                oldEl.innerHTML = newEl.innerHTML;
                            }
                        });
                        
                        // 3. Perbarui visual persentase progress bar
                        ['progress-barang', 'progress-layanan', 'progress-aktif', 'progress-off'].forEach(id => {
                            const oldEl = document.getElementById(id);
                            const newEl = doc.getElementById(id);
                            if (oldEl && newEl) {
                                oldEl.style.width = newEl.style.width;
                            }
                        });
                        
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

                fetch(`kategori_read.php?ajax=search&search=${encodeURIComponent(queryValue)}&status_filter=${encodeURIComponent(currentStatusFilter)}`)
                    .then(response => response.text())
                    .then(html => {
                        tbody.innerHTML = html;
                        tbody.style.opacity = '1';
                        
                        const rows = tbody.querySelectorAll('.kategori-row');
                        document.getElementById('table-count-badge').textContent = rows.length;
                    })
                    .catch(error => {
                        console.error('Error Search AJAX:', error);
                        tbody.style.opacity = '1';
                        showToast('Gagal memuat data kategori.', 'danger');
                    });
            }

            if (searchInput) {
                const debouncedSearch = debounce(performSearchAndFilter, 300);
                searchInput.addEventListener('input', debouncedSearch);
            }

            // AJAX Detail Kategori (Tombol Lihat)
            tbody.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-details-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const id = viewBtn.getAttribute('data-id');
                    
                    const icon = viewBtn.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';
                    viewBtn.disabled = true;

                    fetch(`kategori_read.php?ajax=detail&id=${id}`)
                        .then(response => response.json())
                        .then(res => {
                            if (res.success) {
                                const d = res.data;
                                
                                document.getElementById('detail-nama').textContent = d.Nama_Kategori || '-';
                                document.getElementById('detail-nama-txt').textContent = d.Nama_Kategori || '-';
                                document.getElementById('detail-id').textContent = d.ID_Kategori || '-';
                                document.getElementById('detail-tipe').textContent = d.Tipe_Kategori || '-';
                                document.getElementById('detail-tipe-txt').textContent = d.Tipe_Kategori || '-';
                                document.getElementById('detail-deskripsi').textContent = d.Deskripsi || 'Tidak ada deskripsi tertulis untuk kategori ini.';

                                const modalAvatarContainer = document.getElementById('detail-avatar-container');
                                if (d.Foto_Kategori && d.Foto_Kategori !== '') {
                                    modalAvatarContainer.innerHTML = `<img src="../../assets/uploads/kategori/${d.Foto_Kategori}" style="width:90px; height:90px; border-radius:18px; object-fit:cover; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);">`;
                                } else {
                                    const initials = getInitialsKategoriJs(d.Nama_Kategori);
                                    const isBarang = d.Tipe_Kategori === 'Barang';
                                    const bgClass = isBarang ? 'avatar-indigo' : 'avatar-violet';
                                    
                                    modalAvatarContainer.innerHTML = `<div class="plg-avatar ${bgClass}" style="width:90px; height:90px; border-radius:18px; font-size:2rem; font-weight:800; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.15);"><span class="avatar-initial">${initials}</span></div>`;
                                }

                                const detailModal = new bootstrap.Modal(document.getElementById('detailKategoriModal'));
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
            function getInitialsKategoriJs(name) {
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

            // --- SISTEM TOGGLE STATUS AJAX KATEGORI ---
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

                    fetch(`kategori_toggle_status.php?id=${id}&current=${encodeURIComponent(currentStatus)}`)
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
                                        editBtn.setAttribute('href', `kategori_read.php?id=${id}`);
                                    } else {
                                        editBtn.classList.add('disabled');
                                        editBtn.setAttribute('href', 'javascript:void(0)');
                                    }
                                }

                                // Sinkronisasi & Letup angka statistik keaktifan
                                animateStatTextUpdate('stat-aktif', data.total_a);
                                animateStatTextUpdate('stat-off', data.total_o);

                                // Sinkronisasi progress-fill bar
                                const totalVal = parseInt(document.getElementById('stat-total').textContent) || 1;
                                if (data.total_a !== undefined) {
                                    document.getElementById('progress-aktif').style.width = Math.round((data.total_a / totalVal) * 100) + '%';
                                }
                                if (data.total_o !== undefined) {
                                    document.getElementById('progress-off').style.width = Math.round((data.total_o / totalVal) * 100) + '%';
                                }

                                showToast(`Status kategori berhasil diubah menjadi ${newStatus}`, 'success');

                                if (currentStatusFilter !== '' && currentStatusFilter !== newStatus) {
                                    parentRow.style.opacity = '0';
                                    parentRow.style.transform = 'translateY(10px)';
                                    setTimeout(() => {
                                        parentRow.remove();
                                        document.getElementById('table-count-badge').textContent = tbody.querySelectorAll('.kategori-row').length;
                                    }, 300);
                                }
                            } else {
                                showToast('Gagal memperbarui status kategori.', 'danger');
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

            // Delegasi Hapus Kategori
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
                        .then(response => response.text())
                        .then(text => {
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
                                    refreshPageContent();
                                }, 500);
                            }

                            showToast('Kategori berhasil dihapus secara permanen', 'success');
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
            // INTERSEPT FORM TAMBAH & EDIT KATEGORI (DENGAN MEMUAT ULANG DATA VIA AJAX)
            // =========================================================================
            document.addEventListener('submit', function(e) {
                const form = e.target;
                
                // Deteksi form kategori secara aman (baik modal tambah maupun edit memiliki input Nama_Kategori)
                const isKategoriForm = form.querySelector('[name="Nama_Kategori"]') !== null;

                if (isKategoriForm) {
                    e.preventDefault();
                    e.stopPropagation(); // Mencegah pemicu ganda dari script lain di luar berkas ini
                    
                    // Memunculkan loading indikator SweetAlert2
                    Swal.fire({
                        title: 'Memproses Data...',
                        text: 'Harap tunggu sebentar, kategori sedang disimpan.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const formData = new FormData(form);
                    const startTime = Date.now();

                    fetch('kategori_read.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Membaca respon sebagai text terlebih dahulu untuk mengantisipasi error PHP/DB berupa plain-text HTML
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (err) {
                                console.error("Respon Server Bukan JSON:", text);
                                let cleanText = text.replace(/<[^>]*>/g, '').trim();
                                if (cleanText.length > 150) {
                                    cleanText = cleanText.substring(0, 150) + '...';
                                }
                                return { 
                                    status: 'error', 
                                    message: cleanText || 'Terjadi gangguan internal pada server saat memproses data.' 
                                };
                            }
                        });
                    })
                    .then(data => {
                        const elapsedTime = Date.now() - startTime;
                        const minDelay = 1500; // Delay waktu minimal 1.5 detik agar transisi smooth
                        const remainingDelay = Math.max(minDelay - elapsedTime, 0);

                        setTimeout(() => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    showConfirmButton: true, // Memunculkan tombol konfirmasi OK
                                    confirmButtonText: 'OK',
                                    confirmButtonColor: '#8b5cf6' // Menggunakan warna tema primer
                                }).then(() => {
                                    // Menutup modal bootstrap dan memperbarui halaman SETELAH tombol OK diklik oleh pengguna
                                    const modalElement = form.closest('.modal');
                                    if (modalElement) {
                                        const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
                                        modalInstance.hide();
                                        
                                        // Bersihkan sisa backdrop abu-abu modal
                                        const backdrop = document.querySelector('.modal-backdrop');
                                        if (backdrop) backdrop.remove();
                                        document.body.style.overflow = '';
                                        document.body.style.paddingRight = '';
                                    }

                                    // Reset isi form
                                    form.reset();

                                    // Bersihkan preview gambar jika ada di dalam form
                                    const previewImg = form.querySelector('.img-preview');
                                    if (previewImg) {
                                        previewImg.src = '';
                                        previewImg.style.display = 'none';
                                    }

                                    // Hapus parameter GET ?id= di URL bar jika dalam mode edit agar modal tidak berulang kali terbuka
                                    const url = new URL(window.location.href);
                                    if (url.searchParams.has('id')) {
                                        // Jika URL mengandung parameter ?id= (dibuka dari tautan edit), lakukan redirect penuh untuk membersihkan halaman
                                        window.location.href = 'kategori_read.php';
                                    } else {
                                        // Jika dibuka secara dinamis biasa, cukup perbarui data baris menggunakan DOM Swapping (Tanpa Refresh Halaman)
                                        refreshPageContent();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message || 'Terjadi kesalahan saat memproses data.',
                                    confirmButtonColor: '#8b5cf6'
                                });
                            }
                        }, remainingDelay);
                    })
                    .catch(error => {
                        console.error('Error Form Submit AJAX:', error);
                        setTimeout(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Kesalahan Sistem',
                                text: 'Gagal terhubung ke server. Silakan coba beberapa saat lagi.',
                                confirmButtonColor: '#8b5cf6'
                            });
                        }, 1000);
                    });
                }
            });

        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- INKLUSI DINAMIS POP-UP MODAL (TAMBAH & EDIT) -->
    <?php include 'kategori_create.php'; ?>
    <?php include 'kategori_edit.php'; ?>
</body>
</html>
