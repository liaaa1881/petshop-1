

<?php
ob_start(); // Pengaman output buffering di baris pertama
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../dashboard/index.php");
    exit;
}

// Fungsi helper untuk inisial nama jika foto lama kosong
if (!function_exists('getInitialsSupplierEdit')) {
    function getInitialsSupplierEdit($name) {
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

$error_message = "";
$success_message = "";
$data = null;
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// 1. Ambil data lama berdasarkan ID menggunakan sp_Supplier_Read via CALL
if ($id) {
    $sql_ambil = "{CALL sp_Supplier_Read(?, NULL)}";
    $params_ambil = array($id);
    $query_ambil = sqlsrv_query($conn, $sql_ambil, $params_ambil);
    
    if ($query_ambil === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $data = sqlsrv_fetch_array($query_ambil, SQLSRV_FETCH_ASSOC);
}

// 2. Proses Update Data menggunakan sp_Supplier_Update via CALL
if (isset($_POST['update']) && $data) {
    $nama            = trim($_POST['nama']);
    $telp            = trim($_POST['telp']);
    $email           = trim($_POST['email']);
    
    $alamat          = $_POST['alamat'];
    $kelurahan       = $_POST['kelurahan'];
    $kecamatan       = $_POST['kecamatan'];
    $kota_kabupaten  = $_POST['kota_kabupaten'];
    $provinsi        = $_POST['provinsi'];
    $kode_pos        = trim($_POST['kode_pos']);
    
    $nama_cp         = trim($_POST['nama_cp']);
    $jabatan_cp      = trim($_POST['jabatan_cp']);
    $no_telepon_cp   = trim($_POST['no_telepon_cp']);
    $email_cp        = trim($_POST['email_cp']);
    
    $nama_bank       = trim($_POST['nama_bank']);
    $no_rekening     = trim($_POST['no_rekening']);
    $atas_nama       = trim($_POST['atas_nama_rekening']);
    
    $user            = trim($_POST['user']);
    $modified_by     = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    $pass_val = $_POST['pass'] ?? '';
    $pass_konf = $_POST['pass_konfirm'] ?? '';

    // --- VALIDASI SERVER-SIDE PHP ---
    if (empty($data['Foto_Supplier']) && empty($_FILES['foto']['name'])) {
        $error_message = 'Foto atau Logo mitra supplier wajib diunggah karena Anda belum memilikinya!';
    } elseif (!empty($pass_val) && $pass_val !== $pass_konf) {
        $error_message = 'Kata Sandi baru dan Konfirmasi Kata Sandi tidak cocok!';
    } elseif (!empty($pass_val) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $pass_val)) {
        $error_message = 'Kata Sandi baru minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka!';
    } elseif (!preg_match("/^[a-zA-Z0-9\s.,\-()]+$/", $nama)) {
        $error_message = 'Nama perusahaan/supplier tidak valid!';
    } elseif (strlen($nama) < 3 || strlen($nama) > 100) {
        $error_message = 'Nama perusahaan/supplier harus berada di kisaran 3 sampai 100 karakter!';
    } elseif (!preg_match("/^[a-zA-Z]+$/", $user)) {
        $error_message = 'Nama pengguna hanya boleh diisi oleh huruf alfabet tanpa angka atau spasi!';
    } elseif (strlen($user) < 5 || strlen($user) > 20) {
        $error_message = 'Nama pengguna harus berada di kisaran 5 sampai 20 karakter!';
    } elseif (!preg_match('/^(\+\d{1,3}\d{8,12}|0\d{8,13})$/', $telp)) {
        $error_message = 'Nomor telepon kantor tidak valid! Gunakan format internasional, contoh: +62812xxxxxxx atau 0812xxxxxxx.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format alamat surel kantor tidak valid!';
    } elseif (strlen($kode_pos) !== 5 || !ctype_digit($kode_pos)) {
        $error_message = 'Kode Pos tidak valid! Harus berupa angka dan tepat 5 digit.';
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $nama_cp)) {
        $error_message = 'Nama CP hanya boleh diisi oleh huruf alfabet!';
    } elseif (strlen($nama_cp) < 3 || strlen($nama_cp) > 50) {
        $error_message = 'Nama CP harus berada di kisaran 3 sampai 50 karakter!';
    } elseif (!preg_match('/^(\+\d{1,3}\d{8,12}|0\d{8,13})$/', $no_telepon_cp)) {
        $error_message = 'Nomor telepon CP tidak valid!';
    } elseif (!filter_var($email_cp, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format alamat surel CP tidak valid!';
    } elseif (!ctype_digit($no_rekening)) {
        $error_message = 'Nomor rekening bank harus berupa angka!';
    } else {
        if (!preg_match('/^(jl\.\s*|jalan\s+)/i', $alamat) || strlen($alamat) < 20) {
            $error_message = 'Alamat kantor tidak valid! Harus diawali dengan "Jl." atau "Jalan" dan minimal 20 karakter.';
        }
    }

    if (!empty($error_message)) {
        echo json_encode(['status' => 'error', 'message' => $error_message]);
        exit;
    }

    // Upload File
    $foto_baru = $data['Foto_Supplier']; 
    $upload_ok = true;

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $foto_name = $_FILES['foto']['name'];
        $tmp_name  = $_FILES['foto']['tmp_name'];
        $ekstensi  = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
        $ekstensi_diperbolehkan = array('jpg', 'jpeg', 'png');

        if (in_array($ekstensi, $ekstensi_diperbolehkan)) {
            $foto_baru = "sup_" . time() . "." . $ekstensi;
            $target_dir = "../../assets/uploads/supplier/";
            
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            if (!empty($data['Foto_Supplier']) && file_exists($target_dir . $data['Foto_Supplier'])) {
                unlink($target_dir . $data['Foto_Supplier']);
            }
            
            move_uploaded_file($tmp_name, $target_dir . $foto_baru);
        } else {
            $upload_ok = false;
            $error_message = 'Format berkas foto tidak valid! Gunakan JPG, JPEG atau PNG.';
        }
    }

    if ($upload_ok) {
        if (!empty($pass_val)) {
            $pass = password_hash($pass_val, PASSWORD_DEFAULT);
        } else {
            $pass = $data['Password'];
        }

        // Eksekusi Stored Procedure
        $sql_update = "{CALL sp_Supplier_Update(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";

        $params = array(
            $id,
            $nama, 
            $telp, 
            $email, 
            !empty($alamat) ? $alamat : null, 
            !empty($kelurahan) ? $kelurahan : null, 
            !empty($kecamatan) ? $kecamatan : null, 
            !empty($kota_kabupaten) ? $kota_kabupaten : null, 
            !empty($provinsi) ? $provinsi : null, 
            !empty($kode_pos) ? $kode_pos : null,
            !empty($nama_cp) ? $nama_cp : null, 
            !empty($jabatan_cp) ? $jabatan_cp : null, 
            !empty($no_telepon_cp) ? $no_telepon_cp : null, 
            !empty($email_cp) ? $email_cp : null, 
            !empty($nama_bank) ? $nama_bank : null, 
            !empty($no_rekening) ? $no_rekening : null, 
            !empty($atas_nama) ? $atas_nama : null,
            !empty($user) ? $user : null, 
            $pass, 
            $foto_baru, 
            $data['Sup_status'] ?? 'Aktif',
            $modified_by
        );
        
        $stmt = sqlsrv_query($conn, $sql_update, $params);

        if ($stmt) {
            $success_message = 'Informasi mitra supplier berhasil diperbarui!';
        } else {
            $errors = sqlsrv_errors();
            if ($errors !== null) {
                $raw_error = $errors[0]['message'];
                $error_message = trim(preg_replace('/^(\[[^\]]+\])+/', '', $raw_error));
            } else {
                $error_message = 'Terjadi kesalahan sistem saat memperbarui data supplier.';
            }
        }
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    if (!empty($error_message)) {
        echo json_encode(['status' => 'error', 'message' => $error_message]);
    } else {
        echo json_encode(['status' => 'success', 'message' => $success_message]);
    }
    exit;
}
?>

<!-- MEMASTIKAN PUSTAKA SWEETALERT, FONTAWESOME, JQUERY, DAN SELECT2 SIAP -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE KHUSUS MODAL EDIT SUPPLIER -->
<style>
    :root { 
        --primary-gradient-supplier: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
        --accent-color-supplier: #f59e0b; 
        --border-color-supplier: #cbd5e1;
        --text-danger: #ef4444;
    }

    #modalEditSupplier {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalEditSupplier {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInSupplier {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalEditSupplier.show .modal-content-custom {
        animation: modalZoomInSupplier 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-edit-supplier { 
        background: var(--primary-gradient-supplier); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
        border-top-left-radius: 1.5rem;
        border-top-right-radius: 1.5rem;
    }

    .header-bg-edit-supplier i {
        animation: pulseSupplier 2.5s infinite;
    }

    @keyframes pulseSupplier {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalEditSupplier .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-supplier { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-supplier); 
        border-bottom: 2px solid #e2e8f0; 
        padding-bottom: 0.6rem; 
        margin-bottom: 1.5rem; 
        letter-spacing: 1.5px; 
    }

    .form-label { 
        font-weight: 650; 
        color: #334155; 
        font-size: 0.85rem; 
        margin-bottom: 0.4rem; 
    }

    .text-danger-marker {
        color: var(--text-danger);
        font-weight: bold;
        margin-left: 2px;
    }

    .form-control, .form-select { 
        padding: 0.75rem 1rem; 
        border-radius: 0.75rem; 
        border: 1.5px solid var(--border-color-supplier); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.2s ease-in-out;
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-supplier);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
        outline: none;
    }

    /* EYE BUTTON/APPEND STYLE */
    .input-group-custom {
        position: relative;
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        width: 100%;
    }

    .input-group-custom .form-control {
        flex: 1 1 auto;
        width: 1%;
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .btn-toggle-pass {
        border: 1.5px solid var(--border-color-supplier);
        border-left: none;
        background-color: #f8fafc;
        color: #64748b;
        border-top-right-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
        padding: 0.75rem 1.2rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-toggle-pass:hover {
        background-color: #cbd5e1;
        color: #0f172a;
    }

    .input-group-custom:focus-within .form-control {
        border-color: var(--accent-color-supplier);
    }

    .input-group-custom:focus-within .btn-toggle-pass {
        border-color: var(--accent-color-supplier);
        background-color: #ffffff;
    }

    .btn-simpan { 
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .btn-batal:hover {
        background-color: #f1f5f9;
        transform: translateY(-1px);
    }

    .icon-box { 
        width: 32px; 
        height: 32px; 
        background: #fff7ed; 
        color: var(--accent-color-supplier); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 8px; 
        margin-right: 12px; 
        font-size: 0.9rem;
    }

    .avatar-wrapper {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        background: #f8fafc;
        padding: 1.25rem;
        border-radius: 1rem;
        border: 2px dashed #cbd5e1;
    }

    .avatar-preview-circle {
        width: 85px;
        height: 85px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        font-size: 2.25rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        border: 3px solid #ffffff;
        outline: 3px solid #e2e8f0;
        overflow: hidden;
        flex-shrink: 0;
    }

    .avatar-preview-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
</style>

<!-- RENDER MODAL DENGAN DATA VALID -->
<?php if ($data): ?>
<div class="modal fade" id="modalEditSupplier" aria-labelledby="modalEditSupplierLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-edit-supplier">
                <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-3" id="btnCloseHeader" aria-label="Close"></button>
                <i class="fas fa-user-edit fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Edit Data Mitra Supplier</h2>
            </div>

            <form id="formEditSupplier" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                
                <div class="form-container">
                    
                    <!-- BAGIAN 1: DATA PERUSAHAAN -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-building"></i></div>
                        Informasi Profil Perusahaan / Supplier
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Perusahaan / Supplier<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_nama" name="nama" class="form-control" value="<?= htmlspecialchars($data['Nama_Supplier']) ?>" placeholder="Contoh: PT. Pakan Hewan Indonesia" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon Kantor (Maksimal 15 Digit)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_telp" name="telp" class="form-control" value="<?= htmlspecialchars($data['No_Telepon']) ?>" placeholder="08xxxxxxxxxx" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Alamat Surel Resmi Perusahaan<span class="text-danger-marker">*</span></label>
                            <input type="email" id="edit_sup_email" name="email" class="form-control" value="<?= htmlspecialchars($data['Email']) ?>" placeholder="supplier@surel.com" required>
                        </div>
                    </div>

                    <!-- BAGIAN 2: ALAMAT KANTOR -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-map-marked-alt"></i></div>
                        Informasi Alamat Kantor
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Alamat Lengkap Kantor (Mulai Jl./Jalan, min 20 char)<span class="text-danger-marker">*</span></label>
                            <textarea id="edit_sup_alamat" name="alamat" class="form-control" rows="2" placeholder="Jalan Industri No. 123..." required><?= htmlspecialchars($data['Alamat']) ?></textarea>
                        </div>
                        
                        <!-- CASCADING SELECTS -->
                        <div class="col-md-6">
                            <label class="form-label">Provinsi<span class="text-danger-marker">*</span></label>
                            <select id="edit_provinsi" name="provinsi" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Provinsi']) ?>" selected><?= htmlspecialchars($data['Provinsi']) ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kota / Kabupaten<span class="text-danger-marker">*</span></label>
                            <select id="edit_kota_kabupaten" name="kota_kabupaten" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kota_Kabupaten']) ?>" selected><?= htmlspecialchars($data['Kota_Kabupaten']) ?></option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Kecamatan<span class="text-danger-marker">*</span></label>
                            <select id="edit_kecamatan" name="kecamatan" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kecamatan']) ?>" selected><?= htmlspecialchars($data['Kecamatan']) ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kelurahan<span class="text-danger-marker">*</span></label>
                            <select id="edit_kelurahan" name="kelurahan" class="form-select select2-enable" required>
                                <option value="<?= htmlspecialchars($data['Kelurahan']) ?>" selected><?= htmlspecialchars($data['Kelurahan']) ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kode Pos<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_kodepos" name="kode_pos" class="form-control" maxlength="5" value="<?= htmlspecialchars($data['Kode_Pos'] ?? '') ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                    </div>

                    <!-- BAGIAN 3: DETAIL KONTAK PIC (CP) -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-address-book"></i></div>
                        Detail Kontak Narahubung (CP)
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Narahubung (CP)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_nama_cp" name="nama_cp" class="form-control" value="<?= htmlspecialchars($data['Nama_CP'] ?? '') ?>" placeholder="Masukkan Nama Lengkap CP" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan CP<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_jabatan_cp" name="jabatan_cp" class="form-control" value="<?= htmlspecialchars($data['Jabatan_CP'] ?? '') ?>" placeholder="Contoh: Manajer Penjualan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor Telepon CP<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_telp_cp" name="no_telepon_cp" class="form-control" value="<?= htmlspecialchars($data['No_Telepon_CP'] ?? '') ?>" placeholder="Contoh: +62812xxxxxxxx" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat Surel CP<span class="text-danger-marker">*</span></label>
                            <input type="email" id="edit_sup_email_cp" name="email_cp" class="form-control" value="<?= htmlspecialchars($data['Email_CP'] ?? '') ?>" placeholder="narahubung@surel.com" required>
                        </div>
                    </div>

                    <!-- BAGIAN 4: INFORMASI REKENING BANK -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-wallet"></i></div>
                        Informasi Transfer Rekening Bank
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nama Bank<span class="text-danger-marker">*</span></label>
                            <input type="text" name="nama_bank" class="form-control" value="<?= htmlspecialchars($data['Nama_Bank'] ?? '') ?>" placeholder="Contoh: BCA, Mandiri, BRI..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nomor Rekening<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_norek" name="no_rekening" class="form-control" value="<?= htmlspecialchars($data['No_Rekening'] ?? '') ?>" placeholder="Masukkan Nomor Rekening" oninput="this.value = this.value.replace(/[^0-9]/g, '');" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Atas Nama Rekening<span class="text-danger-marker">*</span></label>
                            <input type="text" name="atas_nama_rekening" class="form-control" value="<?= htmlspecialchars($data['Atas_Nama_Rekening'] ?? '') ?>" placeholder="Masukkan Atas Nama Rekening" required>
                        </div>
                    </div>

                    <!-- BAGIAN 5: AKSES LOGIN PORTAL -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-key"></i></div>
                        Akses Login Portal Supplier
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Nama Pengguna Portal (Min. 5 Huruf)<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_sup_user" name="user" class="form-control" value="<?= htmlspecialchars(trim($data['Username'] ?? '')) ?>" placeholder="Masukkan Nama Pengguna untuk login" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kata Sandi Baru (Kosongkan jika tidak diubah)<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="edit_sup_pass" name="pass" class="form-control" placeholder="Masukkan kata sandi baru">
                                <button class="btn btn-toggle-pass" type="button" data-target="edit_sup_pass">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="edit_sup_password_strength_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Kata Sandi Baru<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <input type="password" id="edit_sup_pass_konfirm" name="pass_konfirm" class="form-control" placeholder="Ulangi kata sandi">
                                <button class="btn btn-toggle-pass" type="button" data-target="edit_sup_pass_konfirm">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="edit_sup_password_match_feedback" class="form-text fw-bold mt-1" style="font-size: 0.8rem;"></div>
                        </div>
                    </div>

                    <!-- BAGIAN 6: FOTO MITRA -->
                    <div class="section-title-supplier d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-camera"></i></div>
                        Profil Foto Supplier / Logo Mitra
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="edit-avatar-container" class="avatar-preview-circle">
                                    <span id="edit-avatar-initials">?</span>
                                    <img id="edit-avatar-image-preview" src="" alt="Pratinjau Logo">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Ganti Foto Supplier / Logo Mitra</label>
                                    <input type="file" id="edit-foto-input" name="foto" class="form-control" accept="image/*">
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG</strong>. Maksimal ukuran: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal" id="btnBatalEdit">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="update" class="btn btn-simpan">
                            <i class="fas fa-save me-2"></i>Perbarui Data Supplier
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // SOLUSI UTAMA: Menggunakan native capturing listener (true) agar input pencarian Select2 bisa diketik secara normal
        document.addEventListener('focusin', function(e) {
            if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
                e.stopImmediatePropagation();
            }
        }, true);

        // Inisialisasi modal menggunakan instansi tunggal
        var modalEl = document.getElementById('modalEditSupplier');
        var modalEdit = null;

        <?php if ($data && empty($success_message)): ?>
            if (modalEl) {
                modalEdit = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalEdit.show();
            }
        <?php endif; ?>

        // PENGALIHAN LANGSUNG SAAT KLIK TOMBOL BATAL ATAU TOMBOL SILANG (X)
        const btnBatal = document.getElementById('btnBatalEdit');
        const btnSilang = document.getElementById('btnCloseHeader');

        if (btnBatal) {
            btnBatal.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'supplier_read.php';
            });
        }

        if (btnSilang) {
            btnSilang.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = 'supplier_read.php';
            });
        }

        // Antisipasi cadangan jika modal ditutup melalui mekanisme luar bawaan Bootstrap
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', function () {
                window.location.href = 'supplier_read.php';
            });
        }

        // --- INISIALISASI SELECT2 DENGAN PORTING KE BODY DOKUMEN ---
        $('.select2-enable').select2({
            dropdownParent: $(document.body),
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                searching: function () { return 'Mencari...'; },
                noResults: function () { return 'Data tidak ditemukan'; },
                errorLoading: function () { return 'Gagal memuat data'; }
            }
        });

        // Mencegah focus trap Bootstrap menghalangi input pencarian Select2
        $('#modalEditSupplier').on('shown.bs.modal', function() {
            $(document).off('focusin.bs.modal');
        });

        // --- SHOW/HIDE PASSWORD TOGGLE ---
        const toggleButtons = document.querySelectorAll('.btn-toggle-pass');
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (targetInput.type === 'password') {
                    targetInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    targetInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // --- AVATAR PREVIEW DENGAN INISIAL NAMA ---
        const namaInput = document.getElementById('edit_sup_nama');
        const initialsSpan = document.getElementById('edit-avatar-initials');
        const imagePreview = document.getElementById('edit-avatar-image-preview');
        const fileInput = document.getElementById('edit-foto-input');
        
        const dbPhoto = <?= json_encode(trim($data['Foto_Supplier'] ?? '')); ?>;

        function updateInitials() {
            if (imagePreview.style.display === 'block') return;

            const nama = namaInput.value.trim();
            if (nama === "") {
                initialsSpan.textContent = "?";
                return;
            }

            const parts = nama.split(/\s+/);
            let initials = parts[0].charAt(0);
            if (parts.length > 1) {
                initials += parts[parts.length - 1].charAt(0);
            }
            initialsSpan.textContent = initials.toUpperCase();
        }

        // Inisialisasi awal avatar
        if (dbPhoto && dbPhoto !== '') {
            imagePreview.src = '../../assets/uploads/supplier/' + dbPhoto;
            imagePreview.style.display = 'block';
            initialsSpan.style.display = 'none';
        } else {
            imagePreview.style.display = 'none';
            initialsSpan.style.display = 'block';
            updateInitials();
        }

        if (namaInput) {
            namaInput.addEventListener('input', updateInitials);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const allowedExtensions = ['jpg', 'jpeg', 'png'];
                    const fileExtension = file.name.split('.').pop().toLowerCase();
                    
                    if (!allowedExtensions.includes(fileExtension)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Format File Salah',
                            text: 'Hanya format JPG, JPEG, dan PNG yang diperbolehkan!',
                            confirmButtonColor: '#f59e0b'
                        });
                        this.value = '';
                        return;
                    }

                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Ukuran File Terlalu Besar',
                            text: 'Maksimal batas ukuran file foto adalah 2 MB.',
                            confirmButtonColor: '#f59e0b'
                        });
                        this.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                        initialsSpan.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                } else {
                    if (dbPhoto && dbPhoto !== '') {
                        imagePreview.src = '../../assets/uploads/supplier/' + dbPhoto;
                        imagePreview.style.display = 'block';
                        initialsSpan.style.display = 'none';
                    } else {
                        imagePreview.style.display = 'none';
                        initialsSpan.style.display = 'block';
                        updateInitials();
                    }
                }
            });
        }

        // --- VALIDASI PASSWORD MATCH & STRENGTH REAL-TIME ---
        const passInput = document.getElementById('edit_sup_pass');
        const confirmInput = document.getElementById('edit_sup_pass_konfirm');
        const feedback = document.getElementById('edit_sup_password_match_feedback');
        const strengthFeedback = document.getElementById('edit_sup_password_strength_feedback');
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        function checkPasswordMatch() {
            const pVal = passInput.value;
            const cVal = confirmInput.value;

            if (pVal === "" && cVal === "") {
                feedback.textContent = "";
                confirmInput.style.borderColor = "#cbd5e1";
                return true;
            }

            if (pVal === cVal) {
                feedback.textContent = "✓ Password cocok";
                feedback.style.color = "#2ecc71";
                confirmInput.style.borderColor = "#2ecc71";
                return true;
            } else {
                feedback.textContent = "✗ Password tidak cocok";
                feedback.style.color = "#ef4444";
                confirmInput.style.borderColor = "#ef4444";
                return false;
            }
        }

        function checkPasswordStrength() {
            const val = passInput.value;
            if (val.length === 0) {
                strengthFeedback.textContent = "";
                passInput.style.borderColor = "#cbd5e1";
                return;
            }
            if (passwordRegex.test(val)) {
                strengthFeedback.textContent = "✓ Kata sandi cukup kuat";
                strengthFeedback.style.color = "#2ecc71";
                passInput.style.borderColor = "#2ecc71";
            } else {
                strengthFeedback.textContent = "Min. 8 karakter, mengandung huruf besar, huruf kecil & angka";
                strengthFeedback.style.color = "#ef4444";
                passInput.style.borderColor = "#ef4444";
            }
        }

        if (passInput && confirmInput) {
            passInput.addEventListener('input', function() {
                checkPasswordStrength();
                checkPasswordMatch();
            });
            confirmInput.addEventListener('input', checkPasswordMatch);
        }

        // ================== LOGIKA INTEGRASI API WILAYAH INDONESIA (SEQUENTIAL & FALLBACK ROBUST) ==================
        async function initCascadingRegions() {
            try {
                // Gunakan fungsi trim PHP untuk menghapus whitespace bawaan tipe data CHAR dari SQL Server
                let savedProvinsi = <?= json_encode(trim($data['Provinsi'] ?? '')) ?>;
                let savedKota = <?= json_encode(trim($data['Kota_Kabupaten'] ?? '')) ?>;
                let savedKecamatan = <?= json_encode(trim($data['Kecamatan'] ?? '')) ?>;
                let savedKelurahan = <?= json_encode(trim($data['Kelurahan'] ?? '')) ?>;

                // Fungsi bantu normalisasi penulisan agar pencocokan nama wilayah akurat (Abaikan prefiks)
                function isLocationMatch(apiName, savedName) {
                    if (!apiName || !savedName) return false;
                    let a = apiName.toLowerCase().replace(/^(kabupaten|kota|provinsi|kecamatan|kelurahan|kab\.)\s+/gi, '').trim();
                    let s = savedName.toLowerCase().replace(/^(kabupaten|kota|provinsi|kecamatan|kelurahan|kab\.)\s+/gi, '').trim();
                    return a === s || a.includes(s) || s.includes(a);
                }

                // 1. Sinkronisasi Provinsi
                let provRes = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json');
                let provinces = await provRes.json();
                let provOptions = '<option value="" disabled>Pilih Provinsi</option>';
                let activeProvId = null;
                let activeProvName = null;
                let provMatched = false;

                provinces.forEach(prov => {
                    if (isLocationMatch(prov.name, savedProvinsi)) {
                        activeProvId = prov.id;
                        activeProvName = prov.name;
                        provMatched = true;
                    }
                    provOptions += `<option value="${prov.name}" data-id="${prov.id}">${prov.name}</option>`;
                });

                // Fallback: Jika data Provinsi di DB tidak ditemukan di API (misal data tertukar/salah input), tambahkan opsi kustom otomatis
                if (!provMatched && savedProvinsi !== '') {
                    provOptions += `<option value="${savedProvinsi}" selected>${savedProvinsi}</option>`;
                    activeProvName = savedProvinsi;
                }

                $('#edit_provinsi').html(provOptions);
                if (activeProvName) {
                    $('#edit_provinsi').val(activeProvName).trigger('change.select2');
                }

                // 2. Sinkronisasi Kota / Kabupaten
                let regOptions = '<option value="" disabled>Pilih Kota/Kabupaten</option>';
                let activeRegId = null;
                let activeRegName = null;
                let regMatched = false;

                if (activeProvId) {
                    let regRes = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${activeProvId}.json`);
                    let regencies = await regRes.json();
                    regencies.forEach(reg => {
                        if (isLocationMatch(reg.name, savedKota)) {
                            activeRegId = reg.id;
                            activeRegName = reg.name;
                            regMatched = true;
                        }
                        regOptions += `<option value="${reg.name}" data-id="${reg.id}">${reg.name}</option>`;
                    });
                }

                // Fallback Kota/Kabupaten
                if (!regMatched && savedKota !== '') {
                    regOptions += `<option value="${savedKota}" selected>${savedKota}</option>`;
                    activeRegName = savedKota;
                }

                $('#edit_kota_kabupaten').html(regOptions);
                if (activeRegName) {
                    $('#edit_kota_kabupaten').val(activeRegName).trigger('change.select2');
                }

                // 3. Sinkronisasi Kecamatan
                let distOptions = '<option value="" disabled>Pilih Kecamatan</option>';
                let activeDistId = null;
                let activeDistName = null;
                let distMatched = false;

                if (activeRegId) {
                    let distRes = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${activeRegId}.json`);
                    let districts = await distRes.json();
                    districts.forEach(dist => {
                        if (isLocationMatch(dist.name, savedKecamatan)) {
                            activeDistId = dist.id;
                            activeDistName = dist.name;
                            distMatched = true;
                        }
                        distOptions += `<option value="${dist.name}" data-id="${dist.id}">${dist.name}</option>`;
                    });
                }

                // Fallback Kecamatan
                if (!distMatched && savedKecamatan !== '') {
                    distOptions += `<option value="${savedKecamatan}" selected>${savedKecamatan}</option>`;
                    activeDistName = savedKecamatan;
                }

                $('#edit_kecamatan').html(distOptions);
                if (activeDistName) {
                    $('#edit_kecamatan').val(activeDistName).trigger('change.select2');
                }

                // 4. Sinkronisasi Kelurahan
                let vilOptions = '<option value="" disabled>Pilih Kelurahan</option>';
                let activeVilName = null;
                let vilMatched = false;

                if (activeDistId) {
                    let vilRes = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${activeDistId}.json`);
                    let villages = await vilRes.json();
                    villages.forEach(vil => {
                        if (isLocationMatch(vil.name, savedKelurahan)) {
                            activeVilName = vil.name;
                            vilMatched = true;
                        }
                        vilOptions += `<option value="${vil.name}">${vil.name}</option>`;
                    });
                }

                // Fallback Kelurahan
                if (!vilMatched && savedKelurahan !== '') {
                    vilOptions += `<option value="${savedKelurahan}" selected>${savedKelurahan}</option>`;
                    activeVilName = savedKelurahan;
                }

                $('#edit_kelurahan').html(vilOptions);
                if (activeVilName) {
                    $('#edit_kelurahan').val(activeVilName).trigger('change.select2');
                }

            } catch (error) {
                console.error('Gagal menyinkronkan data wilayah:', error);
            }
        }

        // Jalankan proses penyelarasan wilayah kustom/API
        initCascadingRegions();

        // Penanganan Perubahan Manual Dropdown Provinsi oleh Pengguna
        $('#edit_provinsi').on('select2:select', function(e) {
            const provId = $(this).find(':selected').attr('data-id');
            if (provId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provId}.json`)
                    .then(response => response.json())
                    .then(regencies => {
                        let options = '<option value="" disabled selected>Pilih Kota/Kabupaten</option>';
                        regencies.forEach(reg => {
                            options += `<option value="${reg.name}" data-id="${reg.id}">${reg.name}</option>`;
                        });
                        $('#edit_kota_kabupaten').html(options).trigger('change');
                        $('#edit_kecamatan').html('<option value="" disabled selected>Pilih Kecamatan</option>').trigger('change');
                        $('#edit_kelurahan').html('<option value="" disabled selected>Pilih Kelurahan</option>').trigger('change');
                    });
            }
        });

        // Penanganan Perubahan Manual Dropdown Kota oleh Pengguna
        $('#edit_kota_kabupaten').on('select2:select', function(e) {
            const regId = $(this).find(':selected').attr('data-id');
            if (regId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regId}.json`)
                    .then(response => response.json())
                    .then(districts => {
                        let options = '<option value="" disabled selected>Pilih Kecamatan</option>';
                        districts.forEach(dist => {
                            options += `<option value="${dist.name}" data-id="${dist.id}">${dist.name}</option>`;
                        });
                        $('#edit_kecamatan').html(options).trigger('change');
                        $('#edit_kelurahan').html('<option value="" disabled selected>Pilih Kelurahan</option>').trigger('change');
                    });
            }
        });

        // Penanganan Perubahan Manual Dropdown Kecamatan oleh Pengguna
        $('#edit_kecamatan').on('select2:select', function(e) {
            const distId = $(this).find(':selected').attr('data-id');
            if (distId) {
                fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${distId}.json`)
                    .then(response => response.json())
                    .then(villages => {
                        let options = '<option value="" disabled selected>Pilih Kelurahan</option>';
                        villages.forEach(vil => {
                            options += `<option value="${vil.name}">${vil.name}</option>`;
                        });
                        $('#edit_kelurahan').html(options).trigger('change');
                    });
            }
        });

        // ================== DETEKSI & VALIDASI FORMAT ALAMAT ==================
        function validasiFormatAlamat(alamat) {
            const val = alamat.trim();
            if (val.length < 20) return false;
            
            if (!/^(jl\.\s*|jalan\s+)/i.test(val)) return false;
            if (/[bcdfghjklmnpqrstvwxyz]{6,}/i.test(val)) return false;
            if (/([a-zA-Z0-9])\1{3,}/.test(val)) return false;
            if ((val.split(" ").length - 1) < 2) return false;

            return true;
        }

        // Limitasi input field
        const userInput = document.getElementById('edit_sup_user');
        const kodePosInput = document.getElementById('edit_sup_kodepos');
        const telpInput = document.getElementById('edit_sup_telp');
        const form = document.getElementById('formEditSupplier');
        const alamatKantor = document.getElementById('edit_sup_alamat');

        if (telpInput) {
            telpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9+]/g, '');
            });
        }

        if (userInput) {
            userInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z]/g, '');
            });
        }

        const telpCpInput = document.getElementById('edit_sup_telp_cp');
        if (telpCpInput) {
            telpCpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9+]/g, '');
            });
        }

        const namaCpInput = document.getElementById('edit_sup_nama_cp');
        if (namaCpInput) {
            namaCpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            });
        }

        // ================== PENANGANAN SUBMIT EDIT DENGAN SWEETALERT2 & AJAX ==================
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // 1. Validasi Foto Profil (Lewati jika dbPhoto sudah ada / terdaftar)
                if (!dbPhoto && (!fileInput.files || fileInput.files.length === 0)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Logo Wajib Diunggah',
                        text: 'Silakan pilih foto profil atau logo supplier terlebih dahulu!',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 2. Validasi Nama Perusahaan
                if (namaInput.value.trim().length < 3 || namaInput.value.trim().length > 100) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nama Supplier Tidak Valid',
                        text: 'Panjang nama perusahaan/supplier harus berkisar antara 3 hingga 100 karakter.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 3. Validasi Username Alfabet saja (Tanpa Angka/Spasi)
                const userVal = userInput.value.trim();
                if (!/^[a-zA-Z]+$/.test(userVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Username Tidak Valid',
                        text: 'Nama pengguna portal hanya boleh mengandung huruf alfabet (A-Z, a-z) tanpa spasi.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                if (userVal.length < 5 || userVal.length > 20) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Panjang Username Tidak Valid',
                        text: 'Nama pengguna portal supplier harus berkisar antara 5 hingga 20 karakter.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 4. Validasi Kode Pos (Tepat 5 Digit)
                if (kodePosInput.value.length !== 5) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Kode Pos Tidak Valid',
                        text: 'Kode Pos wajib diisi dengan tepat 5 digit angka!',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 5. Validasi Format Alamat Kantor
                if (!validasiFormatAlamat(alamatKantor.value)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Alamat Kantor Tidak Valid',
                        text: 'Alamat harus diawali dengan "Jl." atau "Jalan", minimal 20 karakter, serta tidak menggunakan teks acak.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 6. Validasi Nomor Telepon Kantor
                const telpVal = telpInput.value.trim();
                const telpRegex = /^(\+\d{1,3}\d{8,12}|0\d{8,13})$/;
                if (!telpRegex.test(telpVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nomor Telepon Kantor Tidak Valid',
                        text: 'Gunakan format internasional (contoh: +62812xxxxxxx) atau format lokal (0812xxxxxxx).',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 7. Validasi Alamat Email Kantor
                const emailVal = document.getElementById('edit_sup_email').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Surel Kantor Tidak Valid',
                        text: 'Format alamat email kantor tidak lengkap atau salah.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 8. Validasi Nama CP (Alfabet saja)
                if (!/^[a-zA-Z\s]+$/.test(namaCpInput.value)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nama CP Tidak Valid',
                        text: 'Nama Narahubung (CP) hanya boleh mengandung huruf alfabet dan spasi.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 9. Validasi Nomor Telepon CP
                const telpCpVal = telpCpInput.value.trim();
                if (!telpRegex.test(telpCpVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nomor Telepon CP Tidak Valid',
                        text: 'Gunakan format nomor telepon yang valid pada kolom Kontak CP.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 10. Validasi Alamat Email CP
                const emailCpVal = document.getElementById('edit_sup_email_cp').value.trim();
                if (!emailRegex.test(emailCpVal)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Surel CP Tidak Valid',
                        text: 'Format alamat email CP tidak lengkap atau salah.',
                        confirmButtonColor: '#f59e0b'
                    });
                    return;
                }

                // 11. Validasi Kekuatan Password (Hanya jika diisi/ganti baru)
                if (passInput.value !== "") {
                    if (!passwordRegex.test(passInput.value)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kata Sandi Terlalu Lemah',
                            text: 'Kata sandi baru minimal 8 karakter dan harus mengandung huruf besar, huruf kecil, serta angka.',
                            confirmButtonColor: '#f59e0b'
                        });
                        return;
                    }

                    if (!checkPasswordMatch()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kata Sandi Tidak Cocok',
                            text: 'Silakan pastikan isi kolom konfirmasi kata sandi sama dengan kolom kata sandi baru.',
                            confirmButtonColor: '#f59e0b'
                        });
                        return;
                    }
                }

                // --- PENGIRIMAN DATA VIA AJAX DIRECT ---
                var formData = new FormData(this);
                formData.append('update', '1');

                $.ajax({
                    url: '<?= basename(__FILE__) ?>',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: response.message,
                                confirmButtonColor: '#203a43',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                // Tutup modal secara manual
                                var modalEl = document.getElementById('modalEditSupplier');
                                if (modalEl) {
                                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                                    if (modalInstance) {
                                        modalInstance.hide();
                                    }
                                }
                                
                                // Refresh asinkronus list supplier
                                if (typeof performSearchAndFilter === 'function') {
                                    performSearchAndFilter();
                                } else {
                                    window.location.href = 'supplier_read.php';
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan',
                                text: response.message,
                                confirmButtonColor: '#3498db'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan Sistem',
                            text: 'Gagal memproses pengiriman data ke server. Pastikan koneksi atau hak akses file sudah benar.',
                            confirmButtonColor: '#3498db'
                        });
                    }
                });
            });
        }
    });
</script>
<?php endif; ?>

<!-- PEMROSESAN STATUS ALERT SWEETALERT2 DARI SERVER-SIDE PHP (FALLBACK) -->
<?php if (!empty($error_message)): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var modalEditEl = document.getElementById('modalEditSupplier');
        if (modalEditEl) {
            var modalEdit = bootstrap.Modal.getOrCreateInstance(modalEditEl);
            modalEdit.show();
        }

        Swal.fire({
            icon: 'error',
            title: 'Terjadi Kesalahan',
            text: <?= json_encode($error_message); ?>,
            confirmButtonColor: '#3498db'
        });
    });
</script>
<?php endif; ?>
