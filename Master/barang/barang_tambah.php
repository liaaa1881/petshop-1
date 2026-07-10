
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

    $kode_barang = $_POST['Kode_Barang'] ?? '';
    $id_kat      = $_POST['ID_Kategori'] ?? '';
    $nama        = $_POST['Nama_Barang'] ?? '';
    $harga_beli  = $_POST['Harga_Beli'] ?? 0;
    $harga_jual  = $_POST['Harga_Jual'] ?? 0;
    $stok        = $_POST['Stok'] ?? 0;
    $stok_min    = $_POST['Stok_Minimum'] ?? 0;
    $satuan      = $_POST['Satuan'] ?? '';
    $deskripsi   = $_POST['Deskripsi'] ?? '';
    
    // Parameter Audit
    $bar_status  = 'Aktif'; 
    $created_by  = $_SESSION['username'] ?? $_SESSION['Username'] ?? $_SESSION['nama'] ?? 'Admin';

    $foto_name = null;
    $upload_ok = true;

    // 1. Verifikasi Angka Negatif di Sisi Server
    if ($harga_beli < 0 || $harga_jual < 0 || $stok < 0 || $stok_min < 0) {
        $error_message = "Nilai finansial dan batas stok tidak boleh bernilai negatif!";
        $upload_ok = false;
    } elseif ($harga_beli > $harga_jual) {
        $error_message = "Rugi! Harga Beli (Modal) tidak boleh lebih besar dari Harga Jual.";
        $upload_ok = false;
    } else {
        // Proses Unggah Foto Produk
        if (isset($_FILES['Foto_Barang']) && $_FILES['Foto_Barang']['error'] == 0) {
            $target_dir = "../../uploads/barang/";
            
            // Buat folder penyimpanan jika belum tersedia
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_name = $_FILES['Foto_Barang']['name'];
            $file_size = $_FILES['Foto_Barang']['size'];
            $file_tmp  = $_FILES['Foto_Barang']['tmp_name'];
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
                $foto_name = "barang_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
                $target_file = $target_dir . $foto_name;
                
                if (!move_uploaded_file($file_tmp, $target_file)) {
                    $error_message = "Gagal mengunggah foto barang ke server.";
                    $upload_ok = false;
                }
            }
        } else {
            $error_message = "Foto produk wajib diunggah!";
            $upload_ok = false;
        }
    }

    // Simpan data jika seluruh proses validasi dan unggah file berhasil
    if ($upload_ok) {
        // Pemanggilan Stored Procedure menggunakan sintaks ODBC CALL
        $sql = "{CALL sp_Barang_Create(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)}";
        
        $params = array(
            $id_kat, 
            $kode_barang, 
            $nama, 
            $harga_beli, 
            $harga_jual, 
            $stok, 
            !empty($stok_min) ? $stok_min : null, 
            !empty($deskripsi) ? $deskripsi : null, 
            !empty($satuan) ? $satuan : null, 
            $foto_name, 
            $bar_status, 
            $created_by
        );
        
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            echo json_encode(['status' => 'success', 'message' => 'Produk baru berhasil ditambahkan ke inventaris!']);
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

<!-- STYLE KHUSUS MODAL TAMBAH BARANG (TEMA EMERALD) DENGAN ANIMASI -->
<style>
    :root { 
        --primary-gradient-barang: linear-gradient(135deg, #059669 0%, #10b981 100%);
        --accent-color-barang: #10b981; 
        --dark-emerald: #059669;
        --border-color-barang: #cbd5e1;
        --text-danger: #ef4444;
    }
    
    #modalTambahBarang {
        z-index: 1060 !important;
        backdrop-filter: blur(8px);
        background-color: rgba(15, 23, 42, 0.4);
    }

    @media (min-width: 992px) {
        #modalTambahBarang {
            padding-left: 260px !important; 
        }
        .swal2-container {
            padding-left: 260px !important;
        }
    }

    @keyframes modalZoomInBarang {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    #modalTambahBarang.show .modal-content-custom {
        animation: modalZoomInBarang 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .modal-content-custom { 
        background: white; 
        border: none; 
        border-radius: 1.5rem; 
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); 
        overflow: visible; 
    }

    .header-bg-barang { 
        background: var(--primary-gradient-barang); 
        padding: 2.5rem 2rem; 
        color: white; 
        text-align: center; 
        position: relative;
    }

    .header-bg-barang i {
        animation: pulseBarang 2.5s infinite;
    }

    @keyframes pulseBarang {
        0% { transform: scale(1); }
        50% { transform: scale(1.03); }
        100% { transform: scale(1); }
    }

    #modalTambahBarang .modal-dialog {
        max-width: 850px;
        margin-top: 2rem;
        margin-bottom: 2rem;
    }

    .form-container { 
        padding: 2.5rem 3rem; 
    }

    .section-title { 
        font-size: 0.9rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: var(--dark-emerald); 
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
        border: 1.5px solid var(--border-color-barang); 
        background-color: #f8fafc;
        font-size: 0.9rem;
        color: #0f172a;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .form-control:focus, .form-select:focus { 
        border-color: var(--accent-color-barang);
        background-color: #ffffff;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        outline: none;
    }

    /* CUSTOM SELECT2 DESIGN UNTUK TEMA EMERALD BARANG */
    .select2-container {
        z-index: 9999999 !important;
    }

    .select2-container--default .select2-selection--single {
        height: 48px !important;
        border: 1.5px solid var(--border-color-barang) !important;
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
        border-color: var(--accent-color-barang) !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1.5px solid var(--border-color-barang) !important;
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
        border: 1.5px solid var(--border-color-barang) !important;
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
        border-color: var(--accent-color-barang) !important;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    .select2-results__options {
        max-height: 200px !important;
    }

    .select2-results__option--highlighted[aria-selected] {
        background-color: var(--accent-color-barang) !important;
        color: #ffffff !important;
    }

    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background-color: #ecfdf5 !important;
        color: var(--dark-emerald) !important;
        font-weight: 600;
    }

    .input-group-custom {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .input-group-custom .input-group-text-custom {
        background-color: #cbd5e1;
        border: 1.5px solid var(--border-color-barang);
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
        border-color: var(--accent-color-barang);
        background-color: #e2e8f0;
    }

    .btn-simpan { 
        background: var(--primary-gradient-barang); 
        color: white; 
        border: none; 
        padding: 0.85rem 3rem; 
        border-radius: 50px; 
        font-weight: 700; 
        font-size: 0.95rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        transition: all 0.3s ease; 
    }

    .btn-simpan:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
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
        background: #ecfdf5; 
        color: var(--dark-emerald); 
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
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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

<!-- MODAL CONTAINER TAMBAH BARANG -->
<div class="modal fade" id="modalTambahBarang" tabindex="-1" aria-labelledby="modalTambahBarangLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="header-bg-barang">
                <button type="button" class="btn-close btn-close-white position-absolute m-3 top-0 end-0" data-bs-dismiss="modal" aria-label="Close"></button>
                <i class="fas fa-box-open fa-3x mb-3 text-white"></i>
                <h2 class="fw-bold mb-1" style="letter-spacing: -0.5px; color: white;">Tambah Produk Inventaris</h2>
                <p class="opacity-75 mb-0" style="font-size: 0.95rem; color: white;">Registrasi identitas fisik produk, detail stok, harga, dan berkas foto</p>
            </div>

            <form id="formTambahBarang" action="" method="POST" enctype="multipart/form-data">
                <div class="form-container">
                    
                    <!-- BAGIAN 1: IDENTITAS PRODUK -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-tag"></i></div>
                        Identitas Dasar Produk
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">SKU / Kode Barang<span class="text-danger-marker">*</span></label>
                            <input type="text" name="Kode_Barang" class="form-control" placeholder="Contoh: RC-MABC-2KG" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Lengkap Barang<span class="text-danger-marker">*</span></label>
                            <input type="text" id="brg_nama" name="Nama_Barang" class="form-control" placeholder="Contoh: Royal Canin Mother & Babycat 2kg" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategori Produk<span class="text-danger-marker">*</span></label>
                            <select name="ID_Kategori" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Kategori...</option>
                                <?php
                                $sql_kat = "SELECT * FROM Kategori WHERE Tipe_Kategori = 'Barang' ORDER BY Nama_Kategori ASC";
                                $query_kat = sqlsrv_query($conn, $sql_kat);
                                while($kat = sqlsrv_fetch_array($query_kat, SQLSRV_FETCH_ASSOC)) {
                                    echo "<option value='".$kat['ID_Kategori']."'>".$kat['Nama_Kategori']."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satuan Ukuran<span class="text-danger-marker">*</span></label>
                            <select name="Satuan" class="form-select select2-enable" required>
                                <option value="" disabled selected>Pilih Satuan...</option>
                                <option value="Buah">Buah</option>
                                <option value="Pak">Pak</option>
                                <option value="Bungkus">Bungkus</option>
                                <option value="Karung">Karung</option>
                                <option value="Pcs">Pcs</option>
                                <option value="Botol">Botol</option>
                                <option value="Sachet">Sachet</option>
                                <option value="Kg">Kg</option>
                                <option value="Gram">Gram</option>
                                <option value="Liter">Liter</option>
                            </select>
                        </div>
                    </div>

                    <!-- BAGIAN 2: HARGA & REGULASI STOK -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-coins"></i></div>
                        Finansial & Regulasi Stok
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Harga Beli Awal<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-group-text-custom">Rp</span>
                                <input type="number" id="brg_harga_beli" name="Harga_Beli" class="form-control" placeholder="0" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Harga Jual Produk<span class="text-danger-marker">*</span></label>
                            <div class="input-group-custom">
                                <span class="input-group-text-custom">Rp</span>
                                <input type="number" id="brg_harga_jual" name="Harga_Jual" class="form-control" placeholder="0" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Stok Awal<span class="text-danger-marker">*</span></label>
                            <input type="number" name="Stok" class="form-control" value="0" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stok Minimum (Batas Minimum)<span class="text-danger-marker">*</span></label>
                            <input type="number" name="Stok_Minimum" class="form-control" value="5" min="0" required>
                            <small class="text-muted" style="font-size: 0.75rem;">Sistem akan memicu peringatan jika stok mencapai angka ini.</small>
                        </div>
                    </div>

                    <!-- BAGIAN 3: BERKAS & DESKRIPSI -->
                    <div class="section-title d-flex align-items-center">
                        <div class="icon-box"><i class="fas fa-file-image"></i></div>
                        Visual & Rincian Deskripsi
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Deskripsi / Spesifikasi Lengkap<span class="text-danger-marker">*</span></label>
                            <textarea name="Deskripsi" class="form-control" rows="3" placeholder="Tuliskan spesifikasi produk, keunggulan, petunjuk penggunaan secara ringkas..." required></textarea>
                        </div>
                        <div class="col-md-12">
                            <div class="avatar-wrapper">
                                <div id="avatar-container" class="avatar-preview-circle">
                                    <span id="avatar-initials">?</span>
                                    <img id="avatar-image-preview" src="" alt="Pratinjau Foto">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">Pilih Foto Barang<span class="text-danger-marker">*</span></label>
                                    <input type="file" id="foto-input" name="Foto_Barang" class="form-control" accept="image/*" required>
                                    <div class="form-text text-muted mt-1" style="font-size:0.8rem;">Format didukung: <strong>JPG, JPEG, PNG, WEBP</strong>. Maksimal ukuran berkas: <strong>2 MB</strong>.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BUTTONS -->
                    <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary btn-batal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Batal
                        </button>
                        <button type="submit" name="simpan" class="btn btn-simpan">
                            <i class="fas fa-save me-2"></i>Simpan Produk
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

    // INISIALISASI SELECT2 DENGAN PORTING KE BODY DOKUMEN (TEMA EMERALD)
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
    $('#modalTambahBarang').on('shown.bs.modal', function() {
        $(document).off('focusin.bs.modal');
    });

    const namaInput = document.getElementById('brg_nama');
    const initialsSpan = document.getElementById('avatar-initials');
    const imagePreview = document.getElementById('avatar-image-preview');
    const fileInput = document.getElementById('foto-input');
    const form = document.getElementById('formTambahBarang');

    const hargaBeliInput = document.getElementById('brg_harga_beli');
    const hargaJualInput = document.getElementById('brg_harga_jual');

    // LOGIKA GENERATOR INISIAL NAMA BARANG
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
        namaInput.addEventListener('input', updateInitials);
    }

    // Menampilkan pratinjau foto produk secara real-time
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
                        confirmButtonColor: '#10b981'
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
                        confirmButtonColor: '#10b981'
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

            const hargaBeli = parseFloat(hargaBeliInput.value) || 0;
            const hargaJual = parseFloat(hargaJualInput.value) || 0;
            const stok = parseInt(form.elements['Stok'].value) || 0;
            const stokMin = parseInt(form.elements['Stok_Minimum'].value) || 0;

            // 1. Validasi Angka Negatif
            if (hargaBeli < 0 || hargaJual < 0 || stok < 0 || stokMin < 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Input Tidak Valid',
                    text: 'Nilai Harga Beli, Harga Jual, Stok, dan Stok Minimum tidak boleh bernilai negatif.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            // 2. Validasi Kerugian (Harga Beli > Harga Jual)
            if (hargaBeli > hargaJual) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Rugi! Input Tidak Valid',
                    text: 'Harga Beli (Modal) tidak boleh lebih besar dari Harga Jual! Silakan sesuaikan kembali harga jual produk.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            // 3. Validasi Keberadaan Foto Produk
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Foto Wajib Diunggah',
                    text: 'Silakan pilih foto produk terlebih dahulu sebelum menyimpan.',
                    confirmButtonColor: '#10b981'
                });
                return;
            }

            // PENGIRIMAN DATA FORM MENGGUNAKAN FETCH API (AJAX)
            const formData = new FormData(form);
            formData.append('simpan', '1'); // Memasukkan parameter 'simpan' secara eksplisit ke payload POST

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
            .then(response => response.text()) // Ambil raw text respon terlebih dahulu untuk menangkap error PHP jika ada
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        // Tutup modal asinkronus jika sukses
                        var modalEl = document.getElementById('modalTambahBarang');
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
                            confirmButtonColor: '#059669',
                            timer: 2000,
                            timerProgressBar: true,
                            willClose: () => {
                                window.location.href = 'barang_tampil.php';
                            }
                        }).then(() => {
                            window.location.href = 'barang_tampil.php';
                        });
                    } else {
                        // Validasi server gagal, pertahankan isi formulir tetap utuh
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menyimpan',
                            text: data.message,
                            confirmButtonColor: '#10b981'
                        });
                    }
                } catch (e) {
                    console.error("Kesalahan Parsing JSON. Respon mentah server:", text);
                    Swal.fire({
                        icon: 'error',
                        title: 'Kesalahan Sistem',
                        text: 'Terjadi kesalahan sistem internal saat memproses data produk.',
                        confirmButtonColor: '#10b981'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Kesalahan Sistem',
                    text: 'Terjadi kesalahan sistem atau database saat menghubungi server.',
                    confirmButtonColor: '#10b981'
                });
            });
        });
    }

    // Pembersihan ID atau parameter URL secara bersih saat modal ditutup
    $('#modalTambahBarang').on('hidden.bs.modal', function () {
        window.location.href = 'barang_tampil.php';
    });
});
</script>
