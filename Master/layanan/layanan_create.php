
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../config/koneksi.php';

// Proteksi Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../../dashboard/index.php");
    exit;
}

$error_message = "";
$success_message = "";

// Menggunakan REQUEST_METHOD POST agar AJAX dapat masuk dan terproses dengan andal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id_kat            = $_POST['ID_Kategori'] ?? '';
    $kode_layanan      = $_POST['Kode_Layanan'] ?? '';
    $nama              = $_POST['Nama_Layanan'] ?? '';
    $harga             = $_POST['Harga_Layanan'] ?? 0;
    $durasi            = $_POST['Durasi'] ?? 0;
    $deskripsi_layanan = $_POST['Deskripsi_Layanan'] ?? '';

    // Parameter Audit
    $lay_status  = 'Aktif'; 
    $created_by  = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    $foto_name = null;
    $upload_ok = true;

    // 1. Verifikasi Sisi Server (Validasi Angka, Tipe Data, Karakter)
    if ($harga < 0 || $durasi < 0) {
        $error_message = "Nilai harga dan durasi layanan tidak boleh bernilai negatif!";
        $upload_ok = false;
    } 
    // Validasi Nama Layanan tidak boleh mengandung angka
    elseif (preg_match('/[0-9]/', $nama)) {
        $error_message = "Nama layanan tidak boleh mengandung karakter angka!";
        $upload_ok = false;
    } 
    // Validasi Deskripsi minimal 20 karakter
    elseif (strlen(trim($deskripsi_layanan)) < 20) {
        $error_message = "Deskripsi layanan terlalu pendek! Tuliskan minimal 20 karakter.";
        $upload_ok = false;
    } else {
        // Proses Unggah Foto Layanan
        if (isset($_FILES['Foto_Layanan']) && $_FILES['Foto_Layanan']['error'] == 0) {
            $target_dir = "../../assets/uploads/layanan/";
            
            // Buat folder penyimpanan jika belum tersedia
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_name = $_FILES['Foto_Layanan']['name'];
            $file_size = $_FILES['Foto_Layanan']['size'];
            $file_tmp  = $_FILES['Foto_Layanan']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_extensions = array("jpg", "jpeg", "png", "webp");
            
            // Validasi format file
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = "Format berkas tidak didukung! Hanya diperbolehkan JPG, JPEG, PNG, dan WEBP.";
                $upload_ok = false;
            } 
            // Validasi ukuran file (Maksimal 2MB)
            elseif ($file_size > 2 * 1024 * 1024) {
                $error_message = "Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.";
                $upload_ok = false;
            } else {
                // Berikan nama acak unik untuk menghindari duplikasi berkas
                $foto_name = "service_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $target_file = $target_dir . $foto_name;
                
                if (!move_uploaded_file($file_tmp, $target_file)) {
                    $error_message = "Gagal mengunggah foto layanan ke server.";
                    $upload_ok = false;
                }
            }
        } else {
            $error_message = "Foto layanan wajib diunggah!";
            $upload_ok = false;
        }
    }

    // Simpan data jika seluruh proses validasi dan unggah file berhasil
    if ($upload_ok) {
        // Pemanggilan Stored Procedure menggunakan sintaks ODBC CALL
        $sql = "{CALL sp_Layanan_Create(?, ?, ?, ?, ?, ?, ?, ?, ?)}";
        
        $params = array(
            $id_kat, 
            $kode_layanan, 
            $nama, 
            $harga, 
            !empty($durasi) ? $durasi : null, 
            !empty($deskripsi_layanan) ? $deskripsi_layanan : null, 
            $foto_name, 
            $lay_status, 
            $created_by
        );
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Layanan baru berhasil ditambahkan!']);
        } else {
            // Ambil pesan kesalahan dari pemicu RAISERROR di SQL Server
            $errors = sqlsrv_errors();
            $db_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err['message']);
                    $db_err .= trim($clean_msg) . " ";
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
?>

<!-- MEMASTIKAN PUSTAKA SWEETALERT, JQUERY, DAN SELECT2 SIAP -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- STYLE KHUSUS MODAL TAMBAH LAYANAN (TEMA TEAL PREMIUM) DENGAN ANIMASI -->
<style>
    :root { 
        --primary-gradient-layanan: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%);
        --accent-color-layanan: #0d9488; 
        --dark-teal: #0f766e;
        --border-color-layanan: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahLayanan {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalTambahLayanan {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInLayanan {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahLayanan.show .modal-content-custom {
        animation: modalZoomInLayanan 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: white; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-layanan { 
        background: var(--primary-gradient-layanan); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-layanan i {
        animation: pulseLayanan 2.5s infinite;
    }

    @keyframes pulseLayanan {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahLayanan .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-layanan { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--dark-teal); 
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
        border: 1.5px solid var(--border-color-layanan); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-layanan);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
        outline: none;
    }

    /* CUSTOM SELECT2 DESIGN UNTUK TEMA TEAL LAYANAN */
    .select2-container {
        z-index: 9999999 !important;
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-layanan) !important;
        border-radius: 0.75rem !important;
        background-color: #f8fafc !important;
        display: flex;
        align-items: center;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #0f172a !important;
        font-size: 0.9rem !important;
        padding-left: 15px !important;
    }

    .select2-container--open.select2-container--default .select2-selection--single {
        border-color: var(--accent-color-layanan) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-layanan) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
        z-index: 9999999 !important;
        overflow: hidden;
    }

    .select2-search--dropdown {
        padding: 0.6rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
    }

    .select2-search--dropdown::after {
        content: "\f002"; 
        font-family: "Font Awesome 5 Free", "Font Awesome 6 Free", sans-serif;
        font-weight: 900;
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 0.85rem;
    }

    .select2-search--dropdown .select2-search__field {
        border: 1.5px solid var(--border-color-layanan) !important;
        border-radius: 0.6rem !important;
        padding: 0.55rem 2.2rem 0.55rem 0.9rem !important;
        font-size: 0.875rem !important;
        outline: none !important;
        background: #ffffff !important;
        color: #0f172a;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent-color-layanan) !important;
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
    }

    .select2-results__options {
        max-height: 200px !important;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-layanan) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #f0fdfa !important;
        color: var(--dark-teal) !important;
        font-weight: 600;
    }

    .input-group-custom {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .input-group-custom .input-group-text-custom {
        background-color: #cbd5e1;
        border: 1.5px solid var(--border-color-layanan);
        border-right: none;
        color: #334155;
        font-weight: 700;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        border-top-left-radius: 0.75rem;
        border-bottom-left-radius: 0.75rem;
        transition: all 0.25s ease;
    }

    .input-group-custom .form-control {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        flex: 1 1 auto;
        width: 1%;
    }

    .input-group-custom:focus-within .input-group-text-custom {
        border-color: var(--accent-color-layanan);
        background-color: #e2e8f0;
    }

    .btn-simpan-layanan { 
        background: var(--primary-gradient-layanan); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(13, 148, 136, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan-layanan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(13, 148, 136, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal-layanan {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .btn-batal-layanan:hover {
        background-color: #f1f5f9;
        transform: translateY(-1px);
    }

    .icon-box-layanan { 
        width: 32px; 
        height: 32px; 
        background: #f0fdfa; 
        color: var(--dark-teal); 
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
        background: var(--primary-gradient-layanan);
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
        display: none;
    }
</style>

<!-- MODAL CONTAINER TAMBAH LAYANAN -->
<div class="modal fade" id="modalTambahLayanan" tabindex="-1" aria-labelledby="modalTambahLayananLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-layanan">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-concierge-bell fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Tambah Layanan Baru</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Lengkapi formulir terperinci untuk menambah jasa layanan Petshop Pro</p>
            </div>

            <form id="formTambahLayanan" action="" method="POST" enctype="multipart/form-data">
                <div class="form-container">
                    
                    <!-- BAGIAN 1: IDENTITAS JASA -->
                    <div class="section-title-layanan d-flex align-items-center">
                        <div class="icon-box-layanan"><i class="fas fa-tag"></i></div>
                        Identitas Jasa Layanan
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Kode Jasa / Layanan<span class="text-danger-marker">*</span></label>
                            <input type="text" name="Kode_Layanan" class="form-control" placeholder="Contoh: LYN-GRM-01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap Jasa Layanan<span class="text-danger-marker">*</span></label>
                            <input type="text" id="lay_nama" name="Nama_Layanan" class="form-control" placeholder="Contoh: Mandi Kutu & Jamur" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategori Klasifikasi Jasa<span class="text-danger-marker">*</span></label>
                            <select name="ID_Kategori" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kategori Layanan...</option>
                                <?php
                                $sql_kat = "SELECT * FROM Kategori WHERE Tipe_Kategori = 'Layanan' AND (Kat_is_deleted = 0 OR Kat_is_deleted IS NULL) ORDER BY Nama_Kategori ASC";
                                $query_kat = sqlsrv_query($conn, $sql_kat);
                                while($kat = sqlsrv_fetch_array($query_kat, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$kat['ID_Kategori']."'>".$kat['Nama_Kategori']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estimasi Durasi Kerja (Jam)<span class="text-danger-marker">*</span></label>
                            <input type="number" step="0.1" name="Durasi" class="form-control" placeholder="Contoh: 1.5" min="0" required>
                        </div>
                    </div>

                    <!-- BAGIAN 2: FINANSIAL JASA -->
                    <div class="section-title-layanan d-flex align-items-center">
                        <div class="icon-box-layanan"><i class="fas fa-coins"></i></div>
                        Tarif Finansial Jasa Layanan
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Harga Jasa Layanan<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-group-text-custom">Rp</span>
                                <input type="number" id="lay_harga" name="Harga_Layanan" class="form-control" placeholder="0" min="0" required>
                            </div>
                        </div>
                    </div>

                    <!-- BAGIAN 3: BERKAS & DESKRIPSI -->
                    <div class="section-title-layanan d-flex align-items-center">
                        <div class="icon-box-layanan"><i class="fas fa-file-image"></i></div>
                        Visual & Rincian Deskripsi Jasa
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Rincian Deskripsi Jasa (Minimal 20 Karakter)<span class="text-danger-marker">*</span></label>
                            <textarea id="lay_deskripsi" name="Deskripsi_Layanan" class="form-control" rows="3" placeholder="Tuliskan keterangan detail prosedur pengerjaan jasa layanan secara ringkas..." required></textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="avatar-container" class="avatar-preview-circle">
                                    <span id="avatar-initials">?</span>
                                    <img id="avatar-image-preview" src="" alt="Pratinjau Foto">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Unggah Foto Brosur / Katalog Jasa<span class="text-danger-marker">*</span></label>
                                    <input type="file" id="foto-input" name="Foto_Layanan" class="form-control" accept="image/*" required>
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG, WEBP</strong>. Maksimal ukuran berkas: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal-layanan" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="simpan" class="btn btn-simpan-layanan">
                            <i class="fas fa-save me-2"></i>Simpan Layanan
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- VALIDASI SISI KLIEN, PREVIEW FOTO & INTEGRASI SUBMIT AJAX -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // SOLUSI UTAMA: Menggunakan native capturing listener (true) agar input pencarian Select2 bisa diketik secara normal
    document.addEventListener('focusin', function(e) {
        if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
            e.stopImmediatePropagation();
        }
    }, true);

    // INISIALISASI SELECT2 DENGAN PORTING KE BODY DOKUMEN (TEMA TEAL)
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
    $('#modalTambahLayanan').on('shown.bs.modal', function() {
        $(document).off('focusin.bs.modal');
    });

    const namaInput = document.getElementById('lay_nama');
    const initialsSpan = document.getElementById('avatar-initials');
    const imagePreview = document.getElementById('avatar-image-preview');
    const fileInput = document.getElementById('foto-input');
    const form = document.getElementById('formTambahLayanan');

    const hargaInput = document.getElementById('lay_harga');
    const deskripsiInput = document.getElementById('lay_deskripsi');

    // LOGIKA GENERATOR INISIAL & FILTER REAL-TIME ANGKA DI NAMA LAYANAN
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

    if (namaInput) {
        namaInput.addEventListener('input', function() {
            // Filter menghapus karakter angka secara real-time saat mengetik
            this.value = this.value.replace(/[0-9]/g, '');
            updateInitials();
        });
    }

    // Menampilkan pratinjau foto secara real-time
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                const fileExtension = file.name.split('.').pop().toLowerCase();

                if (!allowedExtensions.includes(fileExtension)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Salah',
                        text: 'Hanya format JPG, JPEG, PNG, dan WEBP yang diperbolehkan!',
                        confirmButtonColor: '#0d9488'
                    });
                    this.value = '';
                    imagePreview.style.display = 'none';
                    initialsSpan.style.display = 'block';
                    updateInitials();
                    return;
                }

                // Validasi Ukuran Berkas (Maksimal 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Ukuran Berkas Melebihi Batas',
                        text: 'Batas maksimum ukuran berkas foto adalah 2 MB.',
                        confirmButtonColor: '#0d9488'
                    });
                    this.value = '';
                    imagePreview.style.display = 'none';
                    initialsSpan.style.display = 'block';
                    updateInitials();
                    return;
                }

                const reader = new FileReader();
                reader.addEventListener('load', function() {
                    imagePreview.src = this.result;
                    imagePreview.style.display = 'block';
                    initialsSpan.style.display = 'none';
                });
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
                initialsSpan.style.display = 'block';
                updateInitials();
            }
        });
    }

    // PENANGANAN FORM SUBMIT VIA AJAX FETCH (ANTI REFRESH / NO LOSS DATA)
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Menghentikan muat ulang halaman secara manual

            const nama = namaInput.value.trim();
            const harga = parseFloat(hargaInput.value) || 0;
            const durasi = parseFloat(form.elements['Durasi'].value) || 0;
            const deskripsi = deskripsiInput.value.trim();

            // 1. Validasi Angka Negatif
            if (harga < 0 || durasi < 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Input Tidak Valid',
                    text: 'Nilai Harga Layanan dan Durasi tidak boleh bernilai negatif.',
                    confirmButtonColor: '#0d9488'
                });
                return;
            }

            // 2. Validasi Karakter Angka pada Nama Layanan (Double-check)
            if (/[0-9]/.test(nama)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Input Tidak Valid',
                    text: 'Nama layanan tidak boleh mengandung karakter angka.',
                    confirmButtonColor: '#0d9488'
                });
                return;
            }

            // 3. Validasi Batas Minimal Karakter Deskripsi
            if (deskripsi.length < 20) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Deskripsi Kurang Detail',
                    text: 'Silakan isi rincian deskripsi minimal 20 karakter agar informasi jasa lebih jelas.',
                    confirmButtonColor: '#0d9488'
                });
                return;
            }

            // 4. Validasi Keberadaan Foto
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Foto Wajib Diunggah',
                    text: 'Silakan pilih foto katalog jasa terlebih dahulu sebelum menyimpan.',
                    confirmButtonColor: '#0d9488'
                });
                return;
            }

            // PENGIRIMAN DATA FORM MENGGUNAKAN FETCH API (AJAX)
            const formData = new FormData(form);
            formData.append('simpan', '1'); 

            Swal.fire({
                title: 'Sedang Memproses...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text()) // Menangkap raw text respon terlebih dahulu jika ada error PHP
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        // Tutup modal asinkronus jika sukses
                        var modalEl = document.getElementById('modalTambahLayanan');
                        if (modalEl) {
                            var modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#0f766e',
                            timer: 2000,
                            timerProgressBar: true,
                            willClose: () => {
                                window.location.href = 'layanan_read.php';
                            }
                        }).then(() => {
                            window.location.href = 'layanan_read.php';
                        });
                    } else {
                        // Validasi server gagal, pertahankan formulir tetap utuh
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: data.message,
                            confirmButtonColor: '#0d9488'
                        });
                    }
                } catch (e) {
                    console.error("Kesalahan Parsing JSON. Respon mentah server:", text);
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan sistem internal saat memproses data layanan.',
                        confirmButtonColor: '#0d9488'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi kesalahan sistem atau database saat menghubungi server.',
                    confirmButtonColor: '#0d9488'
                });
            });
        });
    }

    // Pembersihan parameter URL atau pengembalian halaman saat modal ditutup
    $('#modalTambahLayanan').on('hidden.bs.modal', function () {
        window.location.href = 'layanan_read.php';
    });
});
</script>
