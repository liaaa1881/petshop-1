<?php
// Ambil seluruh kategori untuk tombol filter
$query_kat = sqlsrv_query($conn, "SELECT * FROM Kategori ORDER BY Nama_Kategori ASC");
$kategori_list = [];
if ($query_kat) {
    while ($k = sqlsrv_fetch_array($query_kat, SQLSRV_FETCH_ASSOC)) {
        $kategori_list[] = $k;
    }
}

// Ambil data layanan untuk pilihan form booking grooming
$query_layanan = sqlsrv_query($conn, "SELECT ID_Layanan, Nama_Layanan, Harga_Layanan FROM Layanan ORDER BY Nama_Layanan ASC");

// PERBAIKAN QUERY: Hanya mengambil produk yang berstatus 'Aktif' dan belum dihapus (Bar_is_deleted = 0)
$sql_produk = "SELECT B.ID_Barang, B.Nama_Barang, B.Harga_Jual, B.Stok, B.Satuan, B.Foto_Barang AS Foto_Barang, K.Nama_Kategori 
               FROM Barang B 
               LEFT JOIN Kategori K ON B.ID_Kategori = K.ID_Kategori 
               WHERE B.Bar_status = 'Aktif' AND B.Bar_is_deleted = 0
               ORDER BY B.ID_Barang DESC";
$query_produk = sqlsrv_query($conn, $sql_produk);

// Proses Simpan Form Booking Mandiri dari Customer
if (isset($_POST['booking_mandiri'])) {
    $id_pelanggan   = $_SESSION['id_pelanggan'] ?? null; 
    $id_layanan     = $_POST['id_layanan'];
    $jadwal_raw     = $_POST['jadwal_booking'];
    $jadwal_booking = date('Y-m-d H:i:s', strtotime($jadwal_raw));
    
    // Ambil harga layanan secara otomatis
    $q_harga = sqlsrv_query($conn, "SELECT Harga_Layanan FROM Layanan WHERE ID_Layanan = ?", array($id_layanan));
    $d_harga = sqlsrv_fetch_array($q_harga, SQLSRV_FETCH_ASSOC);
    $harga_layanan = $d_harga['Harga_Layanan'] ?? 0;

    if ($id_pelanggan) {
        $kode_booking = "BK-" . date('Ymd') . "-" . rand(1000, 9999);
        $total_tarif  = $harga_layanan; 

        $sql_book = "INSERT INTO Booking (Kode_Booking, ID_Pelanggan, ID_Layanan, ID_Karyawan, Tanggal_Booking, Jadwal_Booking, Harga_Layanan, Diskon_Booking, Total_Tarif, Status_Booking, Book_created_by, Book_created_date) 
                     VALUES (?, ?, ?, NULL, GETDATE(), ?, ?, 0, ?, 'Pending', ?, GETDATE())";
                     
        $params_book = array($kode_booking, $id_pelanggan, $id_layanan, $jadwal_booking, $harga_layanan, $total_tarif, ($_SESSION['username'] ?? 'Customer'));
        $stmt_book = sqlsrv_query($conn, $sql_book, $params_book);

        if ($stmt_book) {
            echo "
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Reservasi Berhasil!',
                        text: 'Jadwal perawatan anabul Anda telah berhasil terdaftar.',
                        icon: 'success',
                        confirmButtonColor: '#ffc107',
                        confirmButtonText: 'Lihat Jadwal Saya'
                    }).then((result) => {
                        window.location.href = '../customer/booking_saya.php';
                    });
                });
            </script>";
            exit();
        } else {
            die(print_r(sqlsrv_errors(), true));
        }
    } else {
        echo "
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Sesi Berakhir',
                    text: 'Sesi masuk telah berakhir. Silakan masuk kembali.',
                    icon: 'warning',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Login'
                }).then((result) => {
                    window.location.href = '../Auth/login.php';
                });
            });
        </script>";
        exit();
    }
}
?>

<div class="row mb-4 g-3 align-items-center">
    <div class="col-md-6 text-center text-md-start">
        <h4 class="fw-bold text-dark mb-1"><i class="fas fa-shopping-bag me-2 text-info"></i>Katalog Perlengkapan Anabul</h4>
        <p class="text-muted mb-0">Temukan pakan nutrisi, vitamin, dan aksesoris terbaik untuk hewan kesayangan Anda.</p>
    </div>
    <div class="col-md-6">
        <div class="input-group shadow-sm" style="border-radius: 50px; overflow: hidden;">
            <span class="input-group-text bg-white border-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="cariProduk" class="form-control border-0 py-2.5" placeholder="Cari produk impian hewan Anda...">
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4 justify-content-center justify-content-md-start">
    <button class="btn btn-info rounded-pill px-4 btn-filter-kat active text-white" data-kategori="semua">All Products</button>
    <?php foreach ($kategori_list as $kat): ?>
        <button class="btn btn-outline-secondary btn-white bg-white text-dark rounded-pill px-4 btn-filter-kat border shadow-sm" data-kategori="<?= htmlspecialchars($kat['Nama_Kategori']) ?>">
            <?= htmlspecialchars($kat['Nama_Kategori']) ?>
        </button>
    <?php endforeach; ?>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4" id="containerProduk">
    <?php 
    if ($query_produk) {
        while($row = sqlsrv_fetch_array($query_produk, SQLSRV_FETCH_ASSOC)) { 
            $stok = $row['Stok'];
            $foto = trim($row['Foto_Barang'] ?? '');
            
            // Penguraian nama file murni
            $parts = preg_split('/[\\\\\/]/', $foto);
            $clean_filename = end($parts);
            
            // Perbaikan Rute Utama: Langsung diarahkan ke folder uploads/barang/
            $base_root = isset($root) ? $root : "http://localhost:3000/";
            $primary_src = !empty($clean_filename) ? $base_root . "uploads/barang/" . $clean_filename : "";
    ?>
    <div class="col item-produk-card" data-nama="<?= strtolower($row['Nama_Barang']); ?>" data-kategori="<?= htmlspecialchars($row['Nama_Kategori']); ?>">
        <div class="card h-100 border-0 shadow-sm product-card" style="background: rgba(255,255,255,0.9); backdrop-filter: blur(5px);">
            
            <!-- Frame Gambar Produk (Metode Layering) -->
            <div class="position-relative d-flex align-items-center justify-content-center bg-light overflow-hidden" style="height: 200px; border-top-left-radius: 20px; border-top-right-radius: 20px; background: rgba(0, 206, 201, 0.04) !important;">
                
                <!-- LAPISAN 1: Ikon box default -->
                <div class="text-center py-4 position-absolute" style="z-index: 1;">
                    <i class="fas fa-box-open fa-4x text-info opacity-25"></i>
                </div>

                <!-- LAPISAN 2: Gambar asli produk -->
                <?php if (!empty($foto)): ?>
                    <img src="<?= $primary_src; ?>" 
                         data-filename="<?= htmlspecialchars($clean_filename); ?>"
                         data-attempt="0"
                         class="w-100 h-100 position-absolute" 
                         style="object-fit: cover; z-index: 2;" 
                         alt="<?= htmlspecialchars($row['Nama_Barang']); ?>"
                         onerror="tryNextPath(this)">
                <?php endif; ?>
                
                <span class="badge bg-white text-dark border rounded-pill position-absolute top-0 end-0 m-3 small shadow-sm" style="z-index: 3;">
                    <?= htmlspecialchars($row['Nama_Kategori']); ?>
                </span>
            </div>
            
            <div class="card-body p-3 d-flex flex-column">
                <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($row['Nama_Barang']); ?>"><?= $row['Nama_Barang']; ?></h6>
                <h5 class="text-info fw-bold mb-3">Rp <?= number_format($row['Harga_Jual'], 0, ',', '.'); ?></h5>
                
                <div class="d-flex justify-content-between align-items-center small text-muted mb-4 mt-auto">
                    <span>Sisa Stok:</span>
                    <span class="fw-bold <?= $stok <= 5 ? 'text-danger' : 'text-success' ?>">
                        <?= $stok; ?> <?= $row['Satuan']; ?>
                    </span>
                </div>

                <div class="row g-2 mt-auto">
                    <div class="col-6">
                        <a href="produk_detail.php?id=<?= $row['ID_Barang'] ?>" class="btn btn-light border w-100 py-2 text-dark font-medium rounded-3" style="font-size: 0.8rem;">
                            Detail
                        </a>
                    </div>
                    <div class="col-6">
                        <?php if ($stok > 0): ?>
                            <a href="keranjang_proses.php?aksi=tambah&id=<?= $row['ID_Barang'] ?>" class="btn btn-info text-white w-100 py-2 rounded-3 d-flex align-items-center justify-content-center" style="font-size: 0.8rem;">
                                <i class="fas fa-shopping-cart me-1"></i> Beli
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary w-100 py-2 rounded-3 text-white" disabled style="font-size: 0.8rem;">Habis</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
        } 
    } else {
        echo "<div class='col-12 text-center my-4'><p class='text-muted'>Gagal memuat katalog barang.</p></div>";
    }
    ?>
</div>

<div class="row mt-5">
    <div class="col-lg-5 mb-4 mb-lg-0">
        <div class="card border-0 p-4 shadow-sm text-white rounded-4 h-100 d-flex flex-column justify-content-center" style="background: linear-gradient(135deg, #e67e22, #d35400);">
            <h4 class="fw-bold mb-2">Butuh Perawatan Anabul? 🐾</h4>
            <p class="opacity-90 small">Petshop Pro menyediakan penanganan mandi sehat, mandi obat kutu, hingga pembersihan premium secara berkala dan profesional.</p>
            <hr class="border-white opacity-25 my-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fab fa-whatsapp fa-2x"></i>
                <div>
                    <small class="d-block opacity-75">Konsultasi via Whatsapp</small>
                    <a href="https://wa.me/085778240372" target="_blank" class="text-white fw-bold text-decoration-none">0857-7824-0372</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-7">
        <div class="card border-0 p-4 shadow-sm rounded-4 bg-white">
            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-calendar-plus text-warning me-2"></i>Booking Jadwal Perawatan Salon</h5>
            <p class="text-muted small mb-4">Pilih jenis pelayanan favorit tanpa perlu mengantre lama di lokasi toko.</p>
            
            <form action="" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-medium text-secondary">Pilih Jenis Layanan</label>
                        <select name="id_layanan" class="form-select px-3 py-2" required>
                            <option value="">-- Pilih Layanan --</option>
                            <?php if($query_layanan): ?>
                                <?php while($l = sqlsrv_fetch_array($query_layanan, SQLSRV_FETCH_ASSOC)): ?>
                                    <option value="<?= $l['ID_Layanan'] ?>"><?= htmlspecialchars($l['Nama_Layanan']) ?> (Rp <?= number_format($l['Harga_Layanan'],0,',','.') ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium text-secondary">Rencana Jadwal Kedatangan</label>
                        <input type="datetime-local" name="jadwal_booking" class="form-control px-3 py-2" required>
                    </div>
                    <div class="col-12 text-end mt-4">
                        <button type="submit" name="booking_mandiri" class="btn btn-warning text-dark fw-bold px-4 py-2 rounded-pill shadow-sm">
                            <i class="fas fa-paper-plane me-1"></i> Daftarkan Sekarang
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Fungsi rute dinamis berlapis dengan prioritas uploads/barang/
function tryNextPath(img) {
    const filename = img.getAttribute('data-filename');
    const base_root = "<?= isset($root) ? $root : 'http://localhost:3000/'; ?>";
    
    const paths = [
        base_root + 'uploads/barang/' + filename,
        '../uploads/barang/' + filename,
        '/uploads/barang/' + filename,
        base_root + 'Master/barang/uploads/' + filename,
        '../Master/barang/uploads/' + filename
    ];
    
    let currentAttempt = parseInt(img.getAttribute('data-attempt') || '0');
    
    if (currentAttempt < paths.length) {
        img.setAttribute('data-attempt', currentAttempt + 1);
        img.src = paths[currentAttempt];
    } else {
        img.style.display = 'none';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const pencarian = document.getElementById('cariProduk');
    const tombolFilter = document.querySelectorAll('.btn-filter-kat');
    const itemProduk = document.querySelectorAll('.item-produk-card');

    function jalankanFilter() {
        const kataKunci = pencarian.value.toLowerCase();
        const kategoriAktif = document.querySelector('.btn-filter-kat.active').getAttribute('data-kategori');

        itemProduk.forEach(item => {
            const namaItem = item.getAttribute('data-nama');
            const kategoriItem = item.getAttribute('data-kategori');

            const cocokNama = namaItem.includes(kataKunci);
            const cocokKategori = (kategoriAktif === 'semua' || kategoriItem === kategoriAktif);

            if (cocokNama && cocokKategori) {
                item.style.display = "block";
            } else {
                item.style.display = "none";
            }
        });
    }

    pencarian.addEventListener('keyup', jalankanFilter);

    tombolFilter.forEach(tombol => {
        tombol.addEventListener('click', function() {
            tombolFilter.forEach(b => {
                b.classList.remove('active', 'btn-info', 'text-white');
                b.classList.add('btn-outline-secondary', 'bg-white', 'text-dark');
            });
            this.classList.add('active', 'btn-info', 'text-white');
            this.classList.remove('btn-outline-secondary', 'bg-white', 'text-dark');
            jalankanFilter();
        });
    });
});
</script>