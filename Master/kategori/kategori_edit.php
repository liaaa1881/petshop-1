
<?php
// ==================== EDIT KATEGORI (RESTRUKTURISASI IDENTIK DENGAN CREATE) ====================
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
$data = null;

// Mengambil ID dari parameter GET atau POST secara aman
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// 1. Ambil data lama berdasarkan ID menggunakan Stored Procedure (sp_Kategori_Read) untuk inisialisasi form
if ($id) {
    $sql_ambil = "{call sp_Kategori_Read(?)}";
    $params_ambil = array($id);
    $query_ambil = sqlsrv_query($conn, $sql_ambil, $params_ambil);
    
    if ($query_ambil === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $data = sqlsrv_fetch_array($query_ambil, SQLSRV_FETCH_ASSOC);
}

// 2. Menggunakan Penanganan AJAX POST untuk Proses Update Data
// Handler HANYA berjalan jika ini adalah proses UPDATE (ada parameter 'id' di dalam payload POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['Nama_Kategori'])) {
    header('Content-Type: application/json');

    $id_kategori = $_POST['id'];
    $nama        = $_POST['Nama_Kategori'] ?? '';
    $tipe        = $_POST['Tipe_Kategori'] ?? '';
    $deskripsi   = $_POST['Deskripsi'] ?? '';
    $modified_by = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    // Ambil data lama kembali untuk referensi berkas foto sebelumnya
    $sql_ref = "{call sp_Kategori_Read(?)}";
    $query_ref = sqlsrv_query($conn, $sql_ref, array($id_kategori));
    $data_lama = ($query_ref !== false) ? sqlsrv_fetch_array($query_ref, SQLSRV_FETCH_ASSOC) : null;

    if (!$data_lama) {
        echo json_encode(['status' => 'error', 'message' => 'Data kategori asli tidak ditemukan di sistem.']);
        exit;
    }

    $foto_baru = $data_lama['Foto_Kategori']; // Default menggunakan foto lama
    $upload_ok = true;

    // Validasi Sisi Server: Nama Kategori Hanya Alphabet & Spasi
    if (!preg_match('/^[a-zA-Z\s]+$/', $nama)) {
        $error_message = 'Nama kategori hanya diperbolehkan berisi huruf alfabet dan spasi!';
        $upload_ok = false;
    }
    // Validasi Sisi Server: Minimal Deskripsi 20 Karakter
    elseif (strlen(trim($deskripsi)) < 20) {
        $error_message = 'Deskripsi kategori minimal harus terdiri dari 20 karakter!';
        $upload_ok = false;
    } else {
        // Proses Upload Foto Baru (Jika Ada)
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
                        // Hapus berkas lama secara aman jika berhasil mengunggah yang baru
                        if (!empty($data_lama['Foto_Kategori']) && file_exists($target_dir . $data_lama['Foto_Kategori'])) {
                            unlink($target_dir . $data_lama['Foto_Kategori']);
                        }
                    } else {
                        $upload_ok = false;
                        $error_message = 'Gagal menyimpan berkas foto kategori ke server.';
                    }
                } else {
                    $upload_ok = false;
                    $error_message = 'Ukuran berkas terlalu besar! Maksimal ukuran berkas adalah 2MB.';
                }
            } else {
                $upload_ok = false;
                $error_message = 'Format berkas foto tidak valid! Gunakan JPG, JPEG, PNG atau WEBP.';
            }
        }
    }

    if ($upload_ok) {
        // PEMANGGILAN STORED PROCEDURE (sp_Kategori_Update)
        $sql_up = "{call sp_Kategori_Update(?, ?, ?, ?, ?, ?, ?, ?)}";
        
        $foto_barang = null; // Default null untuk parameter SP
        $kat_status = null;  // Biarkan bernilai NULL agar SP mempertahankan nilai status lama via ISNULL

        // Parameter berurutan: @ID_Kategori, @Nama_Kategori, @Deskripsi, @Foto_Kategori, @Tipe_Kategori, @Foto_Barang, @Kat_status, @Kat_modified_by
        $params = array($id_kategori, $nama, $deskripsi, $foto_baru, $tipe, $foto_barang, $kat_status, $modified_by);
        $stmt = sqlsrv_query($conn, $sql_up, $params);

        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $db_err = "";
            if ($errors !== null) {
                foreach ($errors as $err) {
                    $clean_msg = preg_replace('/\[[^\]]+\]/', '', $err['message']);
                    $db_err .= trim($clean_msg) . " ";
                }
            } else {
                $db_err = 'Terjadi kesalahan sistem saat menghubungi database.';
            }
            echo json_encode(['status' => 'error', 'message' => $db_err]);
        } else {
            echo json_encode(['status' => 'success', 'message' => 'Informasi kategori berhasil diperbarui!']);
            if (is_resource($stmt)) {
                sqlsrv_free_stmt($stmt);
            }
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

<!-- STYLE KHUSUS MODAL EDIT KATEGORI (TEMA ROYAL VIOLET) DENGAN ANIMASI -->
<style>
    :root { 
        --primary-gradient-kategori: linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #8b5cf6 100%);
        --accent-color-kategori: #8b5cf6; 
        --dark-violet: #6d28d9;
        --border-color-kategori: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalEditKategori {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    /* Penyesuaian modal & SweetAlert2 pada layar desktop */
    @media (min-width: 992px) {
        #modalEditKategori {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    /* Animasi masuk Zoom Smooth */
    @keyframes modalZoomInKategori {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalEditKategori.show .modal-content-custom-kategori {
        animation: modalZoomInKategori 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom-kategori { 
        background: #ffffff; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-kategori { 
        background: var(--primary-gradient-kategori); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-kategori i {
        animation: pulseKategori 2.5s infinite;
    }

    @keyframes pulseKategori {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalEditKategori .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title-kategori { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--accent-color-kategori); 
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
        border: 1.5px solid var(--border-color-kategori); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-kategori);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        outline: none;
    }

    .btn-simpan-kategori { 
        background: var(--primary-gradient-kategori); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(109, 40, 217, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan-kategori:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(109, 40, 217, 0.3);
        color: white;
        filter: brightness(1.15);
    }

    .btn-batal-kategori {
        border-radius: 50px;
        padding: 0.85rem 2.5rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .btn-batal-kategori:hover {
        background-color: #f1f5f9;
        transform: translateY(-1px);
    }

    .icon-box-kategori { 
        width: 32px; 
        height: 32px; 
        background: #f5f3ff; 
        color: var(--accent-color-kategori); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 8px; 
        margin-right: 12px; 
        font-size: 0.9rem;
    }

    /* CUSTOM SELECT2 DESIGN UNTUK TEMA VIOLET KATEGORI */
    .select2-container-violet .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-kategori) !important;
        border-radius: 0.75rem !important;
        background-color: #f8fafc !important;
        display: flex;
        align-items: center;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .select2-container-violet .select2-selection--single .select2-selection__arrow {
        height: 46px !important;
    }
    .select2-container-violet .select2-selection--single .select2-selection__rendered {
        color: #0f172a !important;
        font-size: 0.9rem !important;
        padding-left: 15px !important;
    }

    .select2-container-violet.select2-container--open .select2-selection--single {
        border-color: var(--accent-color-kategori) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15) !important;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .select2-container-violet .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-kategori) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18) !important;
    }

    .select2-container-violet .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-kategori) !important;
        color: #ffffff !important;
    }

    .select2-container-violet .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #f5f3ff !important;
        color: var(--dark-violet) !important;
        font-weight: 600;
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
        background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%);
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

    .avatar-indigo { background: linear-gradient(135deg, #ffedd5, #fed7aa) !important; color: #ea580c !important; }
    .avatar-violet { background: linear-gradient(135deg, #f3e8ff, #ddd6fe) !important; color: #8b5cf6 !important; }

    .audit-tag { 
        font-size: 0.75rem; 
        color: #94a3b8; 
        font-style: italic; 
    }
</style>

<!-- MODAL CONTAINER EDIT KATEGORI (HANYA DI-RENDER JIKA DATA DITEMUKAN) -->
<?php if ($data): ?>
<div class="modal fade" id="modalEditKategori" tabindex="-1" aria-labelledby="modalEditKategoriLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom modal-content-custom-kategori">
            
            <div class="header-bg-kategori">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-edit fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Edit Informasi Kategori</h2>
            </div>

            <form id="formEditKategori" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                <div class="form-container">
                    
                    <!-- BAGIAN 1: INFORMASI KATEGORI -->
                    <div class="section-title-kategori d-flex align-items-center">
                        <div class="icon-box-kategori"><i class="fas fa-info-circle"></i></div>
                        Informasi Utama Kategori
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nama Kategori<span class="text-danger-marker">*</span></label>
                            <input type="text" id="edit_cat_nama" name="Nama_Kategori" class="form-control" value="<?= htmlspecialchars($data['Nama_Kategori']) ?>" required>
                            <small class="text-muted" style="font-size: 0.75rem;">Hanya diperbolehkan memasukkan karakter huruf alfabet.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipe Kategori<span class="text-danger-marker">*</span></label>
                            <select name="Tipe_Kategori" id="edit_cat_tipe" class="form-select select2-enable-kategori" required>
                                <option value="Barang" <?= ($data['Tipe_Kategori'] === 'Barang') ? 'selected' : '' ?>>Barang (Produk Fisik)</option>
                                <option value="Layanan" <?= ($data['Tipe_Kategori'] === 'Layanan') ? 'selected' : '' ?>>Layanan (Jasa / Servis)</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Deskripsi Singkat (Minimal 20 Karakter)<span class="text-danger-marker">*</span></label>
                            <textarea name="Deskripsi" id="edit_cat_deskripsi" class="form-control" rows="3" placeholder="Tuliskan keterangan fungsional mengenai kategori ini..." required><?= htmlspecialchars($data['Deskripsi'] ?? '') ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted" style="font-size: 0.75rem;">Harap tuliskan informasi deskripsi yang representatif.</small>
                                <small id="edit-char-counter" class="text-muted" style="font-size: 0.75rem;">0 / 20 karakter minimum</small>
                            </div>
                        </div>
                    </div>

                    <!-- BAGIAN 2: LOGO / IKON KATEGORI (DYNAMIC PREVIEW) -->
                    <div class="section-title-kategori d-flex align-items-center">
                        <div class="icon-box-kategori"><i class="fas fa-camera"></i></div>
                        Ikon / Foto Visual Kategori
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="edit-avatar-container-kat" class="avatar-preview-circle">
                                    <span id="edit-avatar-initials-kat">?</span>
                                    <img id="edit-avatar-image-preview-kat" src="" alt="Pratinjau Visual">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Ganti Foto / Ikon Kategori</label>
                                    <input type="file" id="edit-foto-input-kat" name="foto" class="form-control" accept="image/*">
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Abaikan jika tidak ingin mengganti foto. Format: <strong>JPG, JPEG, PNG, WEBP</strong> (Maks. 2 MB).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal-kategori" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <div class="text-end">
                            <button type="submit" class="btn btn-simpan-kategori mb-1">
                                <i class="fas fa-save me-2"></i>Perbarui Kategori
                            </button>
                            <div class="audit-tag">
                                Diperbarui terakhir oleh: <?= htmlspecialchars($data['Kat_modified_by'] ?: $data['Kat_created_by'] ?: 'Sistem') ?>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- JAVASCRIPT: PREVIEW FOTO, INISIAL GENERATOR & VALIDASI KATEGORI EDIT VIA AJAX FETCH -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tampilkan Modal secara otomatis pada saat di-render
    var modalEl = document.getElementById('modalEditKategori');
    if (modalEl) {
        var modalEdit = new bootstrap.Modal(modalEl);
        modalEdit.show();
    }

    // Bersihkan parameter ?id= dari URL browser saat modal ditutup
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            const url = new URL(window.location);
            url.searchParams.delete('id');
            window.history.pushState({}, '', url);
            window.location.href = 'kategori_read.php';
        });
    }

    // Fokus interseptor Select2
    document.addEventListener('focusin', function(e) {
        if (e.target.closest(".select2-search__field") || e.target.closest(".select2-container")) {
            e.stopImmediatePropagation();
        }
    }, true);

    // Inisialisasi Select2 Bertema Violet untuk Dropdown Tipe Kategori
    $('.select2-enable-kategori').select2({
        dropdownParent: $(document.body),
        width: '100%',
        minimumResultsForSearch: Infinity,
        containerCssClass: 'select2-container-violet',
        dropdownCssClass: 'select2-container-violet'
    });

    // Cegah intervensi fokus Bootstrap modal terhadap Select2
    $('#modalEditKategori').on('shown.bs.modal', function() {
        $(document).off('focusin.bs.modal');
    });

    // MENGGUNAKAN ID UNIK 'edit_' UNTUK MENCEGAH BENTROKAN DENGAN MODAL TAMBAH (CREATE)
    const namaInput = document.getElementById('edit_cat_nama');
    const initialsSpan = document.getElementById('edit-avatar-initials-kat');
    const imagePreview = document.getElementById('edit-avatar-image-preview-kat');
    const fileInput = document.getElementById('edit-foto-input-kat');
    const deskripsiInput = document.getElementById('edit_cat_deskripsi');
    const charCounter = document.getElementById('edit-char-counter');
    const tipeKategoriSelect = document.getElementById('edit_cat_tipe');
    const avatarContainer = document.getElementById('edit-avatar-container-kat');
    const form = document.getElementById('formEditKategori');

    const dbPhoto = <?= json_encode($data['Foto_Kategori'] ?? ''); ?>;

    // LOGIKA GENERATOR INISIAL NAMA KATEGORI
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

    // Ubah tema latar belakang penampung pratinjau berdasarkan tipe kategori
    function updateAvatarTheme() {
        if (tipeKategoriSelect.value === 'Barang') {
            avatarContainer.className = 'avatar-preview-circle avatar-indigo';
        } else {
            avatarContainer.className = 'avatar-preview-circle avatar-violet';
        }
    }

    // FUNGSI SINKRONISASI UTAMA UNTUK DATA EDIT YANG SUDAH TERISI
    function sinkronisasiNilaiAwal() {
        // 1. Sinkronisasi Penghitung Karakter Deskripsi Bawaan Database
        if (deskripsiInput && charCounter) {
            const len = deskripsiInput.value.trim().length;
            charCounter.textContent = `${len} / 20 karakter minimum`;
            if (len >= 20) {
                charCounter.classList.remove('text-muted');
                charCounter.classList.add('text-success');
                charCounter.style.fontWeight = 'bold';
            } else {
                charCounter.classList.remove('text-success');
                charCounter.classList.add('text-muted');
                charCounter.style.fontWeight = 'normal';
            }
        }

        // 2. Sinkronisasi Tema, Pratinjau Foto, dan Inisial Nama Bawaan Database
        updateAvatarTheme();
        if (dbPhoto && dbPhoto.trim() !== '') {
            imagePreview.src = '../../assets/uploads/kategori/' + dbPhoto;
            imagePreview.style.display = 'block';
            initialsSpan.style.display = 'none';
        } else {
            imagePreview.style.display = 'none';
            initialsSpan.style.display = 'block';
            updateInitials();
        }
    }

    // Jalankan sinkronisasi nilai database segera setelah inisialisasi variabel selesai
    sinkronisasiNilaiAwal();

    // Event listener pendeteksi perubahan tipe kategori untuk mengubah warna latar belakang avatar
    tipeKategoriSelect.addEventListener('change', updateAvatarTheme);

    // VALIDASI INPUT NAMA KATEGORI (Hanya Alfabet)
    if (namaInput) {
        namaInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
            updateInitials();
        });
    }

    // PENGHITUNG KARAKTER DESKRIPSI (Real-time Input)
    if (deskripsiInput) {
        deskripsiInput.addEventListener('input', function() {
            const len = this.value.trim().length;
            charCounter.textContent = `${len} / 20 karakter minimum`;
            if (len >= 20) {
                charCounter.classList.remove('text-muted');
                charCounter.classList.add('text-success');
                charCounter.style.fontWeight = 'bold';
            } else {
                charCounter.classList.remove('text-success');
                charCounter.classList.add('text-muted');
                charCounter.style.fontWeight = 'normal';
            }
        });
    }

    // LOGIKA PRATINJAU FILE FOTO KATEGORI YANG DIUNGGAH USER
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
                        confirmButtonColor: '#8b5cf6'
                    });
                    this.value = '';
                    if (dbPhoto && dbPhoto !== '') {
                        imagePreview.src = '../../assets/uploads/kategori/' + dbPhoto;
                        imagePreview.style.display = 'block';
                        initialsSpan.style.display = 'none';
                    } else {
                        imagePreview.style.display = 'none';
                        initialsSpan.style.display = 'block';
                        updateInitials();
                    }
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Ukuran Berkas Terlalu Besar',
                        text: 'Batas maksimum ukuran berkas foto adalah 2 MB.',
                        confirmButtonColor: '#8b5cf6'
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
                    imagePreview.src = '../../assets/uploads/kategori/' + dbPhoto;
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

    // SUBMIT DATA VIA AJAX UNTUK MENCEGAH REFRESH HALAMAN
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const namaKategori = namaInput.value.trim();
            const deskripsi = deskripsiInput.value.trim();

            // 1. Validasi Sisi Klien: Alphabet Nama Kategori
            const alphabetRegex = /^[a-zA-Z\s]+$/;
            if (!alphabetRegex.test(namaKategori)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Nama Kategori Tidak Valid',
                    text: 'Nama kategori hanya boleh berisi huruf alfabet dan spasi!',
                    confirmButtonColor: '#8b5cf6'
                });
                return;
            }

            // 2. Validasi Sisi Klien: Minimal Deskripsi 20 Karakter
            if (deskripsi.length < 20) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Deskripsi Kurang Lengkap',
                    text: 'Silakan isi deskripsi minimal 20 karakter sebelum menyimpan data.',
                    confirmButtonColor: '#8b5cf6'
                });
                return;
            }

            const formData = new FormData(form);

            Swal.fire({
                title: 'Memperbarui Kategori...',
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
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        // Sembunyikan modal secara asinkronus
                        var modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#6d28d9',
                            timer: 2000,
                            timerProgressBar: true,
                            willClose: () => {
                                window.location.href = 'kategori_read.php';
                            }
                        }).then(() => {
                            window.location.href = 'kategori_read.php';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: data.message,
                            confirmButtonColor: '#8b5cf6'
                        });
                    }
                } catch (err) {
                    console.error("Gagal parsing JSON server response:", text);
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan pemrosesan respons server.',
                        confirmButtonColor: '#8b5cf6'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Koneksi',
                    text: 'Gagal menghubungi server. Periksa jaringan Anda.',
                    confirmButtonColor: '#8b5cf6'
                });
            });
        });
    }
});
</script>
<?php endif; ?>

<!-- SCRIPT REDIRECT HANYA JIKA USER MENCOBA MENGAKSES ID TERTENTU TAPI TIDAK ADA DI DATABASE -->
<?php if ($id && !$data): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'error',
            title: 'Kategori Tidak Ditemukan',
            text: 'ID Kategori yang Anda cari tidak terdaftar atau telah dihapus.',
            confirmButtonColor: '#8b5cf6'
        }).then(() => {
            window.location.href = 'kategori_read.php';
        });
    });
</script>
<?php endif; ?>
