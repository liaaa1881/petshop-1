USE master;
GO
IF EXISTS (SELECT * FROM sys.databases WHERE name = 'petshop')
BEGIN
    ALTER DATABASE petshop SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE petshop;
END
GO

-- 2. BUAT DATABASE BARU
CREATE DATABASE petshop;
GO
USE petshop;
GO

-- ==========================================================
-- 3. TABEL MASTER (Prefix Audit: status, is_deleted, created, modified, deleted)
-- ==========================================================

-- 1. TABEL KATEGORI
CREATE TABLE Kategori (
    ID_Kategori INT PRIMARY KEY IDENTITY(1,1),
    Nama_Kategori VARCHAR(50) UNIQUE NOT NULL, -- Nama kategori tidak boleh kembar
    Deskripsi VARCHAR(255),
    Foto_Kategori VARCHAR(255),
    Tipe_Kategori VARCHAR(20) CHECK (Tipe_Kategori IN ('Barang', 'Layanan')),
    Foto_Barang VARCHAR(255),
    -- Audit Columns Master
    Kat_status VARCHAR(30),
    Kat_is_deleted BIT DEFAULT 0,
    Kat_created_by VARCHAR(50),
    Kat_created_date DATETIME DEFAULT GETDATE(),
    Kat_modified_by VARCHAR(50),
    Kat_modified_date DATETIME,
    Kat_deleted_by VARCHAR(50),
    Kat_deleted_date DATETIME
);

-- 2. TABEL KARYAWAN
CREATE TABLE Karyawan (
    ID_Karyawan INT PRIMARY KEY IDENTITY(1,1),
    NIK VARCHAR(16) UNIQUE NOT NULL, -- NIK wajib ada dan tidak boleh kembar
    Nama_Karyawan VARCHAR(100) NOT NULL,
    Jenis_Kelamin VARCHAR(15) CHECK (Jenis_Kelamin IN ('Laki-laki', 'Perempuan')),
    Tempat_Lahir VARCHAR(50),
    Tanggal_Lahir DATE,
    Agama VARCHAR(20) CHECK (Agama IN ('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu', 'Lainnya')),
    Status_Pernikahan VARCHAR(20) CHECK (Status_Pernikahan IN ('Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati')),
    Goldar VARCHAR(3) CHECK (Goldar IN ('A', 'B', 'AB', 'O', '-')),
    
    -- Kontak & Akun (Dibuat UNIQUE dan NOT NULL)
    No_Telepon VARCHAR(15) UNIQUE NOT NULL, -- No telepon tidak boleh kembar
    Email VARCHAR(100) UNIQUE NOT NULL,      -- Email tidak boleh kembar
    Username VARCHAR(50) UNIQUE NOT NULL,    -- Username tidak boleh kembar
    Password VARCHAR(255) NOT NULL,
    Role VARCHAR(20),
    Foto_Karyawan VARCHAR(255),
    
    -- Alamat Lengkap
    Alamat_KTP VARCHAR(255),
    Alamat_Domisili VARCHAR(255),
    Kelurahan VARCHAR(50),
    Kecamatan VARCHAR(50),
    Kota_Kabupaten VARCHAR(50),
    Provinsi VARCHAR(50),
    Kode_Pos VARCHAR(10),
    
    -- Kepegawaian & Keuangan
    Jabatan VARCHAR(50),
    Tanggal_Masuk DATE DEFAULT GETDATE(),
    Status_Karyawan VARCHAR(20) CHECK (Status_Karyawan IN ('Tetap', 'Kontrak', 'Magang')),
    
    
    -- Audit Columns Master
    Kar_status VARCHAR(30),
    Kar_is_deleted BIT DEFAULT 0,
    Kar_created_by VARCHAR(50),
    Kar_created_date DATETIME DEFAULT GETDATE(),
    Kar_modified_by VARCHAR(50),
    Kar_modified_date DATETIME,
    Kar_deleted_by VARCHAR(50),
    Kar_deleted_date DATETIME
);

-- 3. TABEL PELANGGAN
CREATE TABLE Pelanggan (
    ID_Pelanggan INT PRIMARY KEY IDENTITY(1,1),
    Nama_Pelanggan VARCHAR(100) NOT NULL,
    Jenis_Kelamin VARCHAR(15) CHECK (Jenis_Kelamin IN ('Laki-laki', 'Perempuan')),
    Tempat_Lahir VARCHAR(50),
    Tanggal_Lahir DATE,
    Pekerjaan VARCHAR(50),
    
    -- Kontak & Akun (Dibuat UNIQUE dan NOT NULL)
    No_Telepon VARCHAR(15) UNIQUE NOT NULL, -- No telepon pelanggan wajib unik untuk login/OTP
    Email VARCHAR(100) UNIQUE NOT NULL,      -- Email wajib unik
    Username VARCHAR(50) UNIQUE,             -- Username boleh kosong (jika beli langsung), tapi jika ada harus unik
    Password VARCHAR(255),
    Status_Member VARCHAR(20) CHECK (Status_Member IN ('Member', 'Non Member')),
    Poin_Member INT DEFAULT 0,
    Foto_Pelanggan VARCHAR(255),
    
    -- Alamat Lengkap
    Alamat VARCHAR(255),
    Kelurahan VARCHAR(50),
    Kecamatan VARCHAR(50),
    Kota_Kabupaten VARCHAR(50),
    Provinsi VARCHAR(50),
    Kode_Pos VARCHAR(10),
    
    -- Audit Columns Master
    Pel_status VARCHAR(30),
    Pel_is_deleted BIT DEFAULT 0,
    Pel_created_by VARCHAR(50),
    Pel_created_date DATETIME DEFAULT GETDATE(),
    Pel_modified_by VARCHAR(50),
    Pel_modified_date DATETIME,
    Pel_deleted_by VARCHAR(50),
    Pel_deleted_date DATETIME
);

-- 4. TABEL LAYANAN
CREATE TABLE Layanan (
    ID_Layanan INT PRIMARY KEY IDENTITY(1,1),
    ID_Kategori INT,
    Kode_Layanan VARCHAR(20) UNIQUE NOT NULL, -- Kode layanan harus unik
    Nama_Layanan VARCHAR(100) UNIQUE NOT NULL, -- Nama layanan tidak boleh kembar agar tidak membingungkan kasir
    Harga_Layanan DECIMAL(15,2) NOT NULL,
    Durasi DECIMAL(4,1),
    Deskripsi_Layanan VARCHAR(255),
    Foto_Layanan VARCHAR(255),
    
    -- Audit Columns Master
    Lay_status VARCHAR(30),
    Lay_is_deleted BIT DEFAULT 0,
    Lay_created_by VARCHAR(50),
    Lay_created_date DATETIME DEFAULT GETDATE(),
    Lay_modified_by VARCHAR(50),
    Lay_modified_date DATETIME,
    Lay_deleted_by VARCHAR(50),
    Lay_deleted_date DATETIME,
    FOREIGN KEY (ID_Kategori) REFERENCES Kategori(ID_Kategori)
);

-- 5. TABEL BARANG
CREATE TABLE Barang (
    ID_Barang INT PRIMARY KEY IDENTITY(1,1),
    ID_Kategori INT,
    Kode_Barang VARCHAR(50) UNIQUE NOT NULL, -- SKU / Barcode barang wajib unik
    Nama_Barang VARCHAR(100) UNIQUE NOT NULL, -- Nama barang dibuat unik untuk menghindari double input barang yang sama
    Harga_Beli DECIMAL(15,2) NOT NULL DEFAULT 0,
    Harga_Jual DECIMAL(15,2) NOT NULL DEFAULT 0,
    Stok INT DEFAULT 0,
    Stok_Minimum INT,
    Deskripsi VARCHAR(255),
    Satuan VARCHAR(20),
    Foto_Barang VARCHAR(255),
    
    -- Audit Columns Master
    Bar_status VARCHAR(30),
    Bar_is_deleted BIT DEFAULT 0,
    Bar_created_by VARCHAR(50),
    Bar_created_date DATETIME DEFAULT GETDATE(),
    Bar_modified_by VARCHAR(50),
    Bar_modified_date DATETIME,
    Bar_deleted_by VARCHAR(50),
    Bar_deleted_date DATETIME,
    FOREIGN KEY (ID_Kategori) REFERENCES Kategori(ID_Kategori)
);

-- 6. TABEL SUPPLIER
CREATE TABLE Supplier (
    ID_Supplier INT PRIMARY KEY IDENTITY(1,1),
    Nama_Supplier VARCHAR(100) UNIQUE NOT NULL, -- Nama perusahaan supplier tidak boleh sama

    -- Kontak Perusahaan
    No_Telepon VARCHAR(15) UNIQUE NOT NULL,     -- No telepon kantor tidak boleh sama
    Email VARCHAR(100) UNIQUE NOT NULL,         -- Email resmi kantor tidak boleh sama
    
    -- Alamat Perusahaan
    Alamat VARCHAR(255),
    Kelurahan VARCHAR(50),
    Kecamatan VARCHAR(50),
    Kota_Kabupaten VARCHAR(50),
    Provinsi VARCHAR(50),
    Kode_Pos VARCHAR(10),
    
    -- Detail Kontak Person (PIC)
    Nama_CP VARCHAR(100),
    Jabatan_CP VARCHAR(50),
    No_Telepon_CP VARCHAR(15),
    Email_CP VARCHAR(100),
    
    -- Informasi Rekening & Sistem
    Nama_Bank VARCHAR(50),
    No_Rekening VARCHAR(30) UNIQUE,             -- No rekening transfer ke supplier tidak boleh sama
    Atas_Nama_Rekening VARCHAR(100),
    Username VARCHAR(50) UNIQUE,
    Password VARCHAR(255),
    Foto_Supplier VARCHAR(255),
    
    -- Audit Columns Master
    Sup_status VARCHAR(30),
    Sup_is_deleted BIT DEFAULT 0,
    Sup_created_by VARCHAR(50),
    Sup_created_date DATETIME DEFAULT GETDATE(),
    Sup_modified_by VARCHAR(50),
    Sup_modified_date DATETIME,
    Sup_deleted_by VARCHAR(50),
    Sup_deleted_date DATETIME
);

-- ==========================================================
-- 4. TABEL TRANSAKSI (Prefix Audit: status, created, modified)
-- ==========================================================

-- 1. TABEL BOOKING (Pemesanan Layanan/Jasa)
CREATE TABLE Booking (
    ID_Booking INT PRIMARY KEY IDENTITY(1,1),
    Kode_Booking VARCHAR(50) UNIQUE NOT NULL, -- Kode unik transaksi booking (contoh: BK-202310-0001)
    ID_Pelanggan INT,
    ID_Layanan INT,
    ID_Karyawan INT,                         -- Karyawan/terapis/petugas yang ditugaskan
    Tanggal_Booking DATETIME DEFAULT GETDATE(),
    Jadwal_Booking DATETIME NOT NULL,         -- Waktu pelaksanaan layanan
    Harga_Layanan DECIMAL(15,2) NOT NULL,
    Diskon_Booking DECIMAL(15,2) DEFAULT 0,  -- Diskon khusus booking jika ada promo
    Total_Tarif DECIMAL(15,2) NOT NULL,       -- Harga_Layanan - Diskon_Booking
    Catatan_Booking VARCHAR(255),             -- Catatan tambahan dari pelanggan (misal: alergi tertentu)
    Status_Booking VARCHAR(20) CHECK (Status_Booking IN ('Pending', 'Diproses', 'Selesai', 'Dibatalkan')),
    
    -- Audit Columns Transaksi
    Book_status VARCHAR(30),
    Book_created_by VARCHAR(50),
    Book_created_date DATETIME DEFAULT GETDATE(),
    Book_modified_by VARCHAR(50),
    Book_modified_date DATETIME,
    
    FOREIGN KEY (ID_Pelanggan) REFERENCES Pelanggan(ID_Pelanggan),
    FOREIGN KEY (ID_Layanan) REFERENCES Layanan(ID_Layanan),
    FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan)
);

-- 2. TABEL PENJUALAN (Kasir / Nota Utama)
CREATE TABLE Penjualan (
    ID_Nota INT PRIMARY KEY IDENTITY(1,1),
    No_Nota VARCHAR(50) UNIQUE NOT NULL,     -- Kode nota pembayaran (contoh: INV-2023-0001)
    ID_Pelanggan INT,                        -- Bisa null jika pembeli umum / non-member
    ID_Karyawan INT,                         -- Kasir yang melayani
    ID_Booking INT NULL,                     -- Terhubung ke booking jika bayar jasa, NULL jika hanya beli barang
    Tanggal_Penjualan DATETIME DEFAULT GETDATE(),
    
    -- Rincian Pembayaran
    Subtotal_Penjualan DECIMAL(15,2) NOT NULL DEFAULT 0, -- Total sebelum diskon & pajak
    Total_Diskon DECIMAL(15,2) DEFAULT 0,
    Pajak_PPN DECIMAL(15,2) DEFAULT 0,                   -- PPN jika ada
    Grand_Total DECIMAL(15,2) NOT NULL DEFAULT 0,        -- Total akhir yang harus dibayar
    Jumlah_Bayar DECIMAL(15,2) NOT NULL DEFAULT 0,       -- Uang yang diserahkan pelanggan
    Kembalian DECIMAL(15,2) DEFAULT 0,                   -- Uang kembalian pelanggan
    
    Metode_Pembayaran VARCHAR(20) CHECK (Metode_Pembayaran IN ('Cash', 'Transfer', 'Qris')),
    Bukti_Pembayaran VARCHAR(255),                       -- Path file atau URL gambar bukti transfer/QRIS
    Status_Pembayaran VARCHAR(20) CHECK (Status_Pembayaran IN ('Lunas', 'Belum Lunas')),
    Catatan_Penjualan VARCHAR(255),
    
    -- Audit Columns Transaksi
    Pen_status VARCHAR(30),
    Pen_created_by VARCHAR(50),
    Pen_created_date DATETIME DEFAULT GETDATE(),
    Pen_modified_by VARCHAR(50),
    Pen_modified_date DATETIME,
    
    FOREIGN KEY (ID_Pelanggan) REFERENCES Pelanggan(ID_Pelanggan),
    FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan),
    FOREIGN KEY (ID_Booking) REFERENCES Booking(ID_Booking)
);

-- 3. TABEL DETAIL PENJUALAN (Rincian Produk yang Dibeli)
CREATE TABLE Detail_Penjualan (
    ID_Detail INT PRIMARY KEY IDENTITY(1,1),
    ID_Nota INT NOT NULL,
    ID_Barang INT NOT NULL,
    Jumlah INT NOT NULL CHECK (Jumlah > 0),
    Harga_Satuan DECIMAL(15,2) NOT NULL,
    Diskon_Item DECIMAL(15,2) DEFAULT 0,                 -- Diskon per baris barang
    Subtotal DECIMAL(15,2) NOT NULL,                      -- (Harga_Satuan * Jumlah) - Diskon_Item
    Catatan_Detail VARCHAR(100),                         -- Catatan opsional (misal: varian rasa/warna)
    
    -- Audit Columns Transaksi
    DetPen_status VARCHAR(30),
    DetPen_created_by VARCHAR(50),
    DetPen_created_date DATETIME DEFAULT GETDATE(),
    DetPen_modified_by VARCHAR(50),
    DetPen_modified_date DATETIME,
    
    FOREIGN KEY (ID_Nota) REFERENCES Penjualan(ID_Nota),
    FOREIGN KEY (ID_Barang) REFERENCES Barang(ID_Barang)
);

-- 4. TABEL STOK MASUK (Penerimaan Barang dari Supplier)
CREATE TABLE Stok_Masuk (
    ID_Stok INT PRIMARY KEY IDENTITY(1,1),
    No_Faktur VARCHAR(50) UNIQUE NOT NULL,   -- Nomor invoice/faktur dari supplier
    ID_Supplier INT,
    ID_Karyawan INT,                         -- Petugas gudang yang menerima
    Tanggal_Masuk DATETIME DEFAULT GETDATE(),
    Tanggal_Diterima DATETIME,                -- Waktu barang sampai fisik di gudang
    
    -- Rincian Biaya
    Subtotal_Stok DECIMAL(15,2) NOT NULL DEFAULT 0,
    Pajak_Stok DECIMAL(15,2) DEFAULT 0,      -- PPN masukan jika ada
    Total_Harga DECIMAL(15,2) NOT NULL DEFAULT 0, -- Total tagihan ke supplier
    
    Status VARCHAR(20) CHECK (Status IN ('Pending', 'Diterima')),
    Catatan_Masuk VARCHAR(255),              -- Keterangan kondisi pengiriman
    
    -- Audit Columns Transaksi
    SM_status VARCHAR(30),
    SM_created_by VARCHAR(50),
    SM_created_date DATETIME DEFAULT GETDATE(),
    SM_modified_by VARCHAR(50),
    SM_modified_date DATETIME,
    
    FOREIGN KEY (ID_Supplier) REFERENCES Supplier(ID_Supplier),
    FOREIGN KEY (ID_Karyawan) REFERENCES Karyawan(ID_Karyawan)
);

-- 5. TABEL DETAIL STOK MASUK (Rincian Barang yang Masuk)
CREATE TABLE Detail_Stok_Masuk (
    ID_Detail_Stok INT PRIMARY KEY IDENTITY(1,1),
    ID_Stok INT NOT NULL,
    ID_Barang INT NOT NULL,
    Jumlah_Masuk INT NOT NULL CHECK (Jumlah_Masuk > 0),
    Harga_Beli DECIMAL(15,2) NOT NULL,
    Subtotal DECIMAL(15,2) NOT NULL,          -- Jumlah_Masuk * Harga_Beli
    
    -- Tambahan Manajemen Gudang Realistis
    No_Batch VARCHAR(50),                    -- Nomor batch produksi (penting untuk quality control)
    Tanggal_Kadaluarsa DATE,                  -- Tanggal expired (sangat berguna untuk produk/barang konsumsi)
    
    -- Audit Columns Transaksi
    DetSM_status VARCHAR(30),
    DetSM_created_by VARCHAR(50),
    DetSM_created_date DATETIME DEFAULT GETDATE(),
    DetSM_modified_by VARCHAR(50),
    DetSM_modified_date DATETIME,
    
    FOREIGN KEY (ID_Stok) REFERENCES Stok_Masuk(ID_Stok),
    FOREIGN KEY (ID_Barang) REFERENCES Barang(ID_Barang)
);

USE petshop;
GO

-- ==========================================================
-- 1. INSERT TABEL KATEGORI (20 Data)
-- ID_Kategori: 1 s.d 10 (Barang), 11 s.d 20 (Layanan)
-- ==========================================================
INSERT INTO Kategori (Nama_Kategori, Deskripsi, Tipe_Kategori, Kat_status, Kat_created_by) VALUES
('Makanan Kucing', 'Makanan basah dan kering untuk kucing', 'Barang', 'Aktif', 'Sistem'),
('Makanan Anjing', 'Makanan basah, kering, dan camilan anjing', 'Barang', 'Aktif', 'Sistem'),
('Mainan Hewan', 'Mainan interaktif untuk kucing dan anjing', 'Barang', 'Aktif', 'Sistem'),
('Aksesoris', 'Kalung, tali tuntun, dan baju hewan', 'Barang', 'Aktif', 'Sistem'),
('Kandang & Kasur', 'Kandang besi, pet cargo, dan kasur empuk', 'Barang', 'Aktif', 'Sistem'),
('Obat & Vitamin', 'Obat cacing, vitamin bulu, dan obat kutu', 'Barang', 'Aktif', 'Sistem'),
('Pasir Kucing', 'Pasir gumpal wangi dan pasir zeolit', 'Barang', 'Aktif', 'Sistem'),
('Shampoo Hewan', 'Shampoo anti kutu, jamur, dan pelembut bulu', 'Barang', 'Aktif', 'Sistem'),
('Perlengkapan Mandi', 'Bak mandi, blower, gunting kuku, dan sisir', 'Barang', 'Aktif', 'Sistem'),
('Susu & Botol', 'Susu formula khusus anak kucing dan anjing', 'Barang', 'Aktif', 'Sistem'),
('Grooming Kucing', 'Jasa memandikan dan perawatan bulu kucing', 'Layanan', 'Aktif', 'Sistem'),
('Grooming Anjing', 'Jasa memandikan dan potong bulu anjing', 'Layanan', 'Aktif', 'Sistem'),
('Penitipan Kucing', 'Jasa titip sehat kucing harian', 'Layanan', 'Aktif', 'Sistem'),
('Penitipan Anjing', 'Jasa titip sehat anjing harian', 'Layanan', 'Aktif', 'Sistem'),
('Konsultasi Dokter', 'Pemeriksaan kesehatan hewan oleh dokter hewan', 'Layanan', 'Aktif', 'Sistem'),
('Vaksinasi', 'Pemberian vaksin wajib tahunan', 'Layanan', 'Aktif', 'Sistem'),
('Sterilisasi', 'Jasa operasi steril kucing dan anjing', 'Layanan', 'Aktif', 'Sistem'),
('Terapi & Spa', 'Terapi kutu/jamur intensif dan spa relaksasi', 'Layanan', 'Aktif', 'Sistem'),
('Potong Bulu', 'Jasa styling/cukur bulu estetik', 'Layanan', 'Aktif', 'Sistem'),
('Pembersihan Telinga', 'Jasa pembersihan telinga dan potong kuku', 'Layanan', 'Aktif', 'Sistem');


-- ==========================================================
-- 2. INSERT TABEL KARYAWAN (20 Data)
-- ==========================================================
INSERT INTO Karyawan (NIK, Nama_Karyawan, Jenis_Kelamin, Tempat_Lahir, Tanggal_Lahir, Agama, Status_Pernikahan, Goldar, No_Telepon, Email, Username, Password, Role, Jabatan, Status_Karyawan, Kar_status, Kar_created_by) VALUES
('3201011212950001', 'Andi Wijaya', 'Laki-laki', 'Jakarta', '1995-12-12', 'Islam', 'Kawin', 'O', '081234567801', 'andi@petshop.com', 'andi_w', 'pass123', 'Admin', 'Supervisor', 'Tetap', 'Aktif', 'Sistem'),
('3201011508960002', 'Budi Santoso', 'Laki-laki', 'Bandung', '1996-08-15', 'Islam', 'Belum Kawin', 'A', '081234567802', 'budi@petshop.com', 'budi_s', 'pass123', 'Kasir', 'Staf Kasir', 'Tetap', 'Aktif', 'Sistem'),
('3201012204970003', 'Citra Lestari', 'Perempuan', 'Surabaya', '1997-04-22', 'Kristen', 'Belum Kawin', 'B', '081234567803', 'citra@petshop.com', 'citra_l', 'pass123', 'Kasir', 'Staf Kasir', 'Kontrak', 'Aktif', 'Sistem'),
('3201011010900004', 'Dedi Kurniawan', 'Laki-laki', 'Medan', '1990-10-10', 'Islam', 'Kawin', 'AB', '081234567804', 'dedi@petshop.com', 'dedi_k', 'pass123', 'Groomer', 'Senior Groomer', 'Tetap', 'Aktif', 'Sistem'),
('3201010505980005', 'Eka Putri', 'Perempuan', 'Semarang', '1998-05-05', 'Islam', 'Belum Kawin', 'O', '081234567805', 'eka@petshop.com', 'eka_p', 'pass123', 'Groomer', 'Staf Groomer', 'Kontrak', 'Aktif', 'Sistem'),
('3201011402940006', 'Fahmi Idris', 'Laki-laki', 'Yogyakarta', '1994-02-14', 'Islam', 'Kawin', 'A', '081234567806', 'fahmi@petshop.com', 'fahmi_i', 'pass123', 'Dokter', 'Dokter Utama', 'Tetap', 'Aktif', 'Sistem'),
('3201012507950007', 'Gita Gutawa', 'Perempuan', 'Solo', '1995-07-25', 'Islam', 'Belum Kawin', 'B', '081234567807', 'gita@petshop.com', 'gita_g', 'pass123', 'Dokter', 'Dokter Mitra', 'Kontrak', 'Aktif', 'Sistem'),
('3201010309930008', 'Hadi Pranoto', 'Laki-laki', 'Malang', '1993-09-03', 'Islam', 'Kawin', 'O', '081234567808', 'hadi@petshop.com', 'hadi_p', 'pass123', 'Groomer', 'Staf Groomer', 'Tetap', 'Aktif', 'Sistem'),
('3201011911960009', 'Indah Permata', 'Perempuan', 'Bogor', '1996-11-19', 'Islam', 'Kawin', 'AB', '081234567809', 'indah@petshop.com', 'indah_p', 'pass123', 'Admin', 'Staf Admin', 'Tetap', 'Aktif', 'Sistem'),
('3201010808970010', 'Joko Susilo', 'Laki-laki', 'Cirebon', '1997-08-08', 'Islam', 'Belum Kawin', 'A', '081234567810', 'joko@petshop.com', 'joko_s', 'pass123', 'Groomer', 'Asisten Groomer', 'Magang', 'Aktif', 'Sistem'),
('3201011706910011', 'Kartika Sari', 'Perempuan', 'Palembang', '1991-06-17', 'Katolik', 'Kawin', 'O', '081234567811', 'kartika@petshop.com', 'kartika_s', 'pass123', 'Dokter', 'Dokter Mitra', 'Tetap', 'Aktif', 'Sistem'),
('3201012112920012', 'Lukman Hakim', 'Laki-laki', 'Padang', '1992-12-21', 'Islam', 'Kawin', 'B', '081234567812', 'lukman@petshop.com', 'lukman_h', 'pass123', 'Groomer', 'Senior Groomer', 'Tetap', 'Aktif', 'Sistem'),
('3201013003990013', 'Mega Utami', 'Perempuan', 'Denpasar', '1999-03-30', 'Hindu', 'Belum Kawin', 'O', '081234567813', 'mega@petshop.com', 'mega_u', 'pass123', 'Kasir', 'Staf Kasir', 'Kontrak', 'Aktif', 'Sistem'),
('3201011111950014', 'Naufal Rizqi', 'Laki-laki', 'Makassar', '1995-11-11', 'Islam', 'Belum Kawin', 'A', '081234567814', 'naufal@petshop.com', 'naufal_r', 'pass123', 'Groomer', 'Staf Groomer', 'Kontrak', 'Aktif', 'Sistem'),
('3201011802960015', 'Olivia Sandra', 'Perempuan', 'Manado', '1996-02-18', 'Kristen', 'Kawin', 'AB', '081234567815', 'olivia@petshop.com', 'olivia_s', 'pass123', 'Admin', 'Staf HRD', 'Tetap', 'Aktif', 'Sistem'),
('3201010210970016', 'Putra Aditya', 'Laki-laki', 'Pontianak', '1997-10-02', 'Islam', 'Belum Kawin', 'O', '081234567816', 'putra@petshop.com', 'putra_a', 'pass123', 'Groomer', 'Asisten Groomer', 'Magang', 'Aktif', 'Sistem'),
('3201010707980017', 'Rina Melati', 'Perempuan', 'Banjarmasin', '1998-07-07', 'Islam', 'Belum Kawin', 'B', '081234567817', 'rina@petshop.com', 'rina_m', 'pass123', 'Groomer', 'Staf Groomer', 'Kontrak', 'Aktif', 'Sistem'),
('3201012909940018', 'Suryo Utomo', 'Laki-laki', 'Surakarta', '1994-09-29', 'Islam', 'Kawin', 'A', '081234567818', 'suryo@petshop.com', 'suryo_u', 'pass123', 'Groomer', 'Staf Groomer', 'Tetap', 'Aktif', 'Sistem'),
('3201011603950019', 'Tari Lestari', 'Perempuan', 'Bekasi', '1995-03-16', 'Islam', 'Kawin', 'AB', '081234567819', 'tari@petshop.com', 'tari_l', 'pass123', 'Admin', 'Keuangan', 'Tetap', 'Aktif', 'Sistem'),
('3201012211930020', 'Wahyu Hidayat', 'Laki-laki', 'Depok', '1993-11-22', 'Islam', 'Kawin', 'O', '081234567820', 'wahyu@petshop.com', 'wahyu_h', 'pass123', 'Groomer', 'Senior Groomer', 'Tetap', 'Aktif', 'Sistem');


-- ==========================================================
-- 3. INSERT TABEL PELANGGAN (20 Data)
-- ==========================================================
INSERT INTO Pelanggan (Nama_Pelanggan, Jenis_Kelamin, Tempat_Lahir, Tanggal_Lahir, Pekerjaan, No_Telepon, Email, Username, Password, Status_Member, Poin_Member, Pel_status, Pel_created_by) VALUES
('Rizky Ramadhan', 'Laki-laki', 'Jakarta', '1990-01-15', 'Karyawan Swasta', '085612345001', 'rizky@gmail.com', 'rizky_r', 'pelanggan123', 'Member', 120, 'Aktif', 'Sistem'),
('Siti Aminah', 'Perempuan', 'Bogor', '1992-05-20', 'Ibu Rumah Tangga', '085612345002', 'siti@gmail.com', 'siti_a', 'pelanggan123', 'Member', 85, 'Aktif', 'Sistem'),
('Agus Setiawan', 'Laki-laki', 'Depok', '1988-08-10', 'PNS', '085612345003', 'agus@gmail.com', 'agus_s', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem'),
('Dewi Sartika', 'Perempuan', 'Tangerang', '1995-11-25', 'Mahasiswi', '085612345004', 'dewi@gmail.com', 'dewi_s', 'pelanggan123', 'Member', 230, 'Aktif', 'Sistem'),
('Fajar Nugraha', 'Laki-laki', 'Bekasi', '1991-03-05', 'Wirausaha', '085612345005', 'fajar@gmail.com', 'fajar_n', 'pelanggan123', 'Member', 50, 'Aktif', 'Sistem'),
('Hani Handayani', 'Perempuan', 'Bandung', '1994-07-12', 'Arsitek', '085612345006', 'hani@gmail.com', 'hani_h', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem'),
('Irfan Bachdim', 'Laki-laki', 'Surabaya', '1989-10-30', 'Atlet', '085612345007', 'irfan@gmail.com', 'irfan_b', 'pelanggan123', 'Member', 410, 'Aktif', 'Sistem'),
('Jeni Natalia', 'Perempuan', 'Semarang', '1996-12-05', 'Desainer', '085612345008', 'jeni@gmail.com', 'jeni_n', 'pelanggan123', 'Member', 15, 'Aktif', 'Sistem'),
('Kevin Sanjaya', 'Laki-laki', 'Medan', '1995-02-17', 'Karyawan Swasta', '085612345009', 'kevin@gmail.com', 'kevin_s', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem'),
('Larasati Putri', 'Perempuan', 'Yogyakarta', '1993-06-21', 'Guru', '085612345010', 'laras@gmail.com', 'laras_p', 'pelanggan123', 'Member', 90, 'Aktif', 'Sistem'),
('Miko Pratama', 'Laki-laki', 'Solo', '1997-09-14', 'Fotografer', '085612345011', 'miko@gmail.com', 'miko_p', 'pelanggan123', 'Member', 60, 'Aktif', 'Sistem'),
('Nadia Vega', 'Perempuan', 'Malang', '1992-04-03', 'Dokter', '085612345012', 'nadia@gmail.com', 'nadia_v', 'pelanggan123', 'Member', 300, 'Aktif', 'Sistem'),
('Oki Setiana', 'Laki-laki', 'Cirebon', '1990-08-28', 'Dosen', '085612345013', 'oki@gmail.com', 'oki_s', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem'),
('Putri Ariani', 'Perempuan', 'Palembang', '1999-01-09', 'Penyanyi', '085612345014', 'putria@gmail.com', 'putri_a', 'pelanggan123', 'Member', 180, 'Aktif', 'Sistem'),
('Ryan Hidayat', 'Laki-laki', 'Padang', '1994-05-18', 'Programmer', '085612345015', 'ryan@gmail.com', 'ryan_h', 'pelanggan123', 'Member', 70, 'Aktif', 'Sistem'),
('Sania Mirza', 'Perempuan', 'Makassar', '1996-03-24', 'Model', '085612345016', 'sania@gmail.com', 'sania_m', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem'),
('Tomi Suharto', 'Laki-laki', 'Denpasar', '1987-11-02', 'Wirausaha', '085612345017', 'tomi@gmail.com', 'tomi_s', 'pelanggan123', 'Member', 150, 'Aktif', 'Sistem'),
('Ulfa Dwiyanti', 'Perempuan', 'Banjarmasin', '1991-07-19', 'Presenter', '085612345018', 'ulfa@gmail.com', 'ulfa_d', 'pelanggan123', 'Member', 35, 'Aktif', 'Sistem'),
('Vino Bastian', 'Laki-laki', 'Manado', '1993-02-22', 'Aktor', '085612345019', 'vino@gmail.com', 'vino_b', 'pelanggan123', 'Member', 500, 'Aktif', 'Sistem'),
('Wulan Guritno', 'Perempuan', 'Pontianak', '1989-04-14', 'Wirausaha', '085612345020', 'wulan@gmail.com', 'wulan_g', 'pelanggan123', 'Non Member', 0, 'Aktif', 'Sistem');


-- ==========================================================
-- 4. INSERT TABEL LAYANAN (20 Data)
-- Menggunakan ID_Kategori 11 s.d 20
-- ==========================================================
INSERT INTO Layanan (ID_Kategori, Kode_Layanan, Nama_Layanan, Harga_Layanan, Durasi, Deskripsi_Layanan, Lay_status, Lay_created_by) VALUES
(11, 'LYN-CAT-01', 'Mandi Sehat Kucing', 75000.00, 1.0, 'Mandi reguler menggunakan shampo kondisioner berkualitas', 'Aktif', 'Sistem'),
(11, 'LYN-CAT-02', 'Mandi Kutu Jamur Kucing', 95000.00, 1.5, 'Mandi khusus dengan shampo obat anti kutu dan jamur', 'Aktif', 'Sistem'),
(11, 'LYN-CAT-03', 'Grooming Lengkap Kucing', 120000.00, 2.0, 'Mandi obat, potong kuku, cukur bulu telapak kaki dan telinga', 'Aktif', 'Sistem'),
(12, 'LYN-DOG-01', 'Mandi Sehat Anjing Kecil', 90000.00, 1.0, 'Mandi reguler untuk ras anjing kecil (Chihuahua, Pomeranian, dll.)', 'Aktif', 'Sistem'),
(12, 'LYN-DOG-02', 'Mandi Sehat Anjing Besar', 150000.00, 2.0, 'Mandi reguler untuk ras anjing besar (Golden Retriever, Husky, dll.)', 'Aktif', 'Sistem'),
(12, 'LYN-DOG-03', 'Grooming Kutu Jamur Anjing', 180000.00, 2.5, 'Mandi pengobatan untuk anjing dengan masalah kulit berat', 'Aktif', 'Sistem'),
(13, 'LYN-TP-CAT', 'Titip Kucing Harian', 50000.00, 24.0, 'Penitipan kucing harian di kandang AC, termasuk makan minum standar', 'Aktif', 'Sistem'),
(14, 'LYN-TP-DOG', 'Titip Anjing Harian', 80000.00, 24.0, 'Penitipan anjing harian di kandang nyaman, termasuk jadwal bermain', 'Aktif', 'Sistem'),
(15, 'LYN-DOC-01', 'Konsultasi Dokter Umum', 100000.00, 0.5, 'Pemeriksaan umum kondisi kesehatan fisik hewan kesayangan', 'Aktif', 'Sistem'),
(15, 'LYN-DOC-02', 'Konsultasi Dokter Spesialis Kulit', 150000.00, 0.5, 'Diagnosis khusus untuk infeksi parasit, jamur, atau alergi', 'Aktif', 'Sistem'),
(16, 'LYN-VAK-01', 'Vaksinasi Kucing F3', 200000.00, 0.5, 'Vaksin tricat mencegah Rhinotracheitis, Calicivirus, Panleukopenia', 'Aktif', 'Sistem'),
(16, 'LYN-VAK-02', 'Vaksinasi Anjing Eurican 4', 250000.00, 0.5, 'Vaksin pencegah Distemper, Hepatitis, Parvovirus, Laryngitis', 'Aktif', 'Sistem'),
(17, 'LYN-STR-01', 'Sterilisasi Kucing Jantan', 350000.00, 1.5, 'Tindakan operasi kastrasi steril kucing jantan sehat', 'Aktif', 'Sistem'),
(17, 'LYN-STR-02', 'Sterilisasi Kucing Betina', 600000.00, 2.0, 'Tindakan operasi steril (OH) kucing betina sehat', 'Aktif', 'Sistem'),
(18, 'LYN-SPA-01', 'Aroma Therapy Spa Hewan', 140000.00, 1.5, 'Pijat relaksasi menggunakan minyak esensial khusus hewan', 'Aktif', 'Sistem'),
(18, 'LYN-SPA-02', 'Whitening Spa Treatment', 160000.00, 1.5, 'Treatment khusus mencerahkan warna bulu putih kusam', 'Aktif', 'Sistem'),
(19, 'LYN-CUT-01', 'Cukur Model/Styling', 110000.00, 1.5, 'Cukur bulu anjing atau kucing model lion cut atau teddy bear cut', 'Aktif', 'Sistem'),
(19, 'LYN-CUT-02', 'Cukur Botak Medis', 80000.00, 1.0, 'Mencukur habis bulu demi mempermudah penyembuhan jamur parah', 'Aktif', 'Sistem'),
(20, 'LYN-EAR-01', 'Pembersihan Telinga Premium', 30000.00, 0.3, 'Membersihkan kotoran telinga bagian dalam dan ear mites', 'Aktif', 'Sistem'),
(20, 'LYN-EAR-02', 'Potong Kuku Hewan', 25000.00, 0.2, 'Pemotongan kuku rapih dan tumpul aman bagi owner', 'Aktif', 'Sistem');


-- ==========================================================
-- 5. INSERT TABEL BARANG (20 Data)
-- Menggunakan ID_Kategori 1 s.d 10
-- ==========================================================
INSERT INTO Barang (ID_Kategori, Kode_Barang, Nama_Barang, Harga_Beli, Harga_Jual, Stok, Stok_Minimum, Satuan, Bar_status, Bar_created_by) VALUES
(1, 'BRG-01-001', 'Whiskas Adult Tuna dry 1.2kg', 60000.00, 75000.00, 50, 5, 'Bungkus', 'Aktif', 'Sistem'),
(1, 'BRG-01-002', 'Royal Canin Fit 32 400g', 55000.00, 68000.00, 30, 5, 'Bungkus', 'Aktif', 'Sistem'),
(1, 'BRG-01-003', 'Me-O Wet Food Tuna 80g pouch', 6000.00, 8500.00, 150, 15, 'Sachet', 'Aktif', 'Sistem'),
(2, 'BRG-02-001', 'Pedigree Adult Beef dry 1.5kg', 50000.00, 65000.00, 40, 5, 'Bungkus', 'Aktif', 'Sistem'),
(2, 'BRG-02-002', 'JerHigh Milky Treat 70g', 18000.00, 24000.00, 80, 10, 'Bungkus', 'Aktif', 'Sistem'),
(3, 'BRG-03-001', 'Bola Karet Berbunyi', 8000.00, 15000.00, 100, 5, 'Pcs', 'Aktif', 'Sistem'),
(3, 'BRG-03-002', 'Stick Bulu Mainan Kucing', 12000.00, 22000.00, 60, 5, 'Pcs', 'Aktif', 'Sistem'),
(4, 'BRG-04-001', 'Kalung Lonceng Motif Kucing', 5000.00, 12000.00, 120, 10, 'Pcs', 'Aktif', 'Sistem'),
(4, 'BRG-04-002', 'Tali Tuntun Harness Anjing Medium', 35000.00, 55000.00, 25, 3, 'Pcs', 'Aktif', 'Sistem'),
(5, 'BRG-05-001', 'Pet Cargo Ukuran M', 110000.00, 145000.00, 12, 2, 'Pcs', 'Aktif', 'Sistem'),
(5, 'BRG-05-002', 'Kasur Hewan Empuk Bulat S', 45000.00, 68000.00, 18, 3, 'Pcs', 'Aktif', 'Sistem'),
(6, 'BRG-06-001', 'Detick Obat Kutu Tetes 1ml', 20000.00, 32000.00, 200, 10, 'Botol', 'Aktif', 'Sistem'),
(6, 'BRG-06-002', 'Nutriplus Gel Vitamin Bulu 120g', 125000.00, 155000.00, 15, 2, 'Tube', 'Aktif', 'Sistem'),
(7, 'BRG-07-001', 'Pasir Gumpal Bento 10L Wangi Apple', 55000.00, 78000.00, 45, 5, 'Sak', 'Aktif', 'Sistem'),
(7, 'BRG-07-002', 'Pasir Zeolit No 2 Sak 20kg', 35000.00, 55000.00, 25, 4, 'Sak', 'Aktif', 'Sistem'),
(8, 'BRG-08-001', 'Shampoo Flea & Tick 250ml', 40000.00, 60000.00, 35, 5, 'Botol', 'Aktif', 'Sistem'),
(8, 'BRG-08-002', 'Shampoo Sebazole Treatment 250ml', 180000.00, 215000.00, 10, 2, 'Botol', 'Aktif', 'Sistem'),
(9, 'BRG-09-001', 'Gunting Kuku Hewan Stainless', 18000.00, 30000.00, 50, 5, 'Pcs', 'Aktif', 'Sistem'),
(9, 'BRG-09-002', 'Sisir Slicker Brush Grooming', 25000.00, 42000.00, 40, 5, 'Pcs', 'Aktif', 'Sistem'),
(10, 'BRG-10-001', 'Susu Growssy Box Isi 10 Sachet', 42000.00, 58000.00, 30, 4, 'Box', 'Aktif', 'Sistem');


-- ==========================================================
-- 6. INSERT TABEL SUPPLIER (20 Data)
-- ==========================================================
INSERT INTO Supplier (Nama_Supplier, No_Telepon, Email, Alamat, Kelurahan, Kecamatan, Kota_Kabupaten, Provinsi, Kode_Pos, Nama_CP, Jabatan_CP, No_Telepon_CP, Email_CP, Nama_Bank, No_Rekening, Atas_Nama_Rekening, Username, Password, Sup_status, Sup_created_by) VALUES
('PT Distribusi Satwa Nusantara', '0215551201', 'sales@dsn.co.id', 'Kawasan Industri Pulogadung No 14', 'Jatinegara', 'Cakung', 'Jakarta Timur', 'DKI Jakarta', '13930', 'Ronaldo', 'Sales Manager', '08119001001', 'ronaldo@dsn.co.id', 'BCA', '1122334401', 'PT Distribusi Satwa Nusantara', 'sup_dsn', 'suppass123', 'Aktif', 'Sistem'),
('CV Petindo Jaya Abadi', '0226661202', 'info@petindo.co.id', 'Jl. Sukarno Hatta No 45', 'Batununggal', 'Batununggal', 'Bandung', 'Jawa Barat', '40266', 'Yanti', 'Admin Penjualan', '08119001002', 'yanti@petindo.co.id', 'Mandiri', '1122334402', 'CV Petindo Jaya Abadi', 'sup_petindo', 'suppass123', 'Aktif', 'Sistem'),
('PT Whiskas Indonesia Raya', '0215551203', 'wholesale@whiskas.co.id', 'Gedung Wisma Mulia Lt 18', 'Kuningan Barat', 'Mampang Prapatan', 'Jakarta Selatan', 'DKI Jakarta', '12710', 'Denny', 'Key Account Manager', '08119001003', 'denny@whiskas.co.id', 'BCA', '1122334403', 'PT Mars Symbioscience', 'sup_whiskas', 'suppass123', 'Aktif', 'Sistem'),
('UD Makmur Pakan Jaya', '0317771204', 'makmurpakan@gmail.com', 'Jl. Kenjeran No 180', 'Gading', 'Tambaksari', 'Surabaya', 'Jawa Timur', '60134', 'Sugeng', 'Owner', '08119001004', 'sugeng@makmur.com', 'BRI', '1122334404', 'Sugeng Pranoto', 'sup_makmur', 'suppass123', 'Aktif', 'Sistem'),
('PT Royal Canin Indonesia', '0215551205', 'order@royalcanin.co.id', 'Gedung Menara Standard Chartered', 'Karet Semanggi', 'Setiabudi', 'Jakarta Selatan', 'DKI Jakarta', '12930', 'Hendra', 'Sales Executive', '08119001005', 'hendra@royalcanin.co.id', 'BCA', '1122334405', 'PT Royal Canin Indonesia', 'sup_rc', 'suppass123', 'Aktif', 'Sistem'),
('CV Raya Aksesoris Hewan', '0248881206', 'raya.aksesoris@yahoo.com', 'Jl. Pamularsih No 12', 'Bongsari', 'Semarang Barat', 'Semarang', 'Jawa Tengah', '50148', 'Dewi', 'Sales Admin', '08119001006', 'dewi@rayaaksesoris.com', 'BNI', '1122334406', 'Dewi Lestari', 'sup_raya', 'suppass123', 'Aktif', 'Sistem'),
('PT Petworld Indonesia', '0215551207', 'sales@petworld.id', 'Kawasan MM2100 Blok C-2', 'Gandamekar', 'Cikarang Barat', 'Bekasi', 'Jawa Barat', '17530', 'Aditya', 'Marketing Manager', '08119001007', 'aditya@petworld.id', 'BCA', '1122334407', 'PT Petworld Indonesia', 'sup_petworld', 'suppass123', 'Aktif', 'Sistem'),
('CV Pasirindo Utama', '0251881208', 'pasirindo@gmail.com', 'Jl. Raya Tajur No 89', 'Tajur', 'Bogor Timur', 'Bogor', 'Jawa Barat', '16141', 'Asep', 'Operasional Gudang', '08119001008', 'asep@pasirindo.com', 'Mandiri', '1122334408', 'Asep Saepudin', 'sup_pasirindo', 'suppass123', 'Aktif', 'Sistem'),
('PT Medion Ardhika Bhakti', '0226661209', 'sales@medion.co.id', 'Jl. Babakan Ciparay No 282', 'Babakan Ciparay', 'Babakan Ciparay', 'Bandung', 'Jawa Barat', '40223', 'Bambang', 'Sales Representative', '08119001009', 'bambang@medion.co.id', 'BCA', '1122334409', 'PT Medion', 'sup_medion', 'suppass123', 'Aktif', 'Sistem'),
('Toko Grosir Pet Sembako', '0215551210', 'petsembako@gmail.com', 'Jl. Mangga Besar IX No 10', 'Tangki', 'Taman Sari', 'Jakarta Barat', 'DKI Jakarta', '11170', 'Ferry', 'Owner', '08119001010', 'ferry@petsembako.com', 'BCA', '1122334410', 'Ferry Wijaya', 'sup_sembako', 'suppass123', 'Aktif', 'Sistem'),
('PT Arto Veterinary Indonesia', '0215551211', 'info@artovet.co.id', 'Kawasan Bizpark Kalideres Blok B-5', 'Kalideres', 'Kalideres', 'Jakarta Barat', 'DKI Jakarta', '11840', 'Iwan', 'Distributor Lead', '08119001011', 'iwan@artovet.co.id', 'Danamon', '1122334411', 'PT Arto Veterinary', 'sup_artovet', 'suppass123', 'Aktif', 'Sistem'),
('CV Vet Medic Jaya', '0274991212', 'vetmedic@jogja.com', 'Jl. Kaliurang KM 7', 'Sinduharjo', 'Ngaglik', 'Sleman', 'DI Yogyakarta', '55581', 'Gatot', 'Gudang Utama', '08119001012', 'gatot@vetmedic.com', 'BCA', '1122334412', 'CV Vet Medic Jaya', 'sup_vetmedic', 'suppass123', 'Aktif', 'Sistem'),
('PT Pet Nutrition Pratama', '0215551213', 'sales@petnutrition.co.id', 'Kawasan Industri Tangerang Blok G-4', 'Jatake', 'Jatiuwung', 'Tangerang', 'Banten', '15136', 'Robert', 'National Sales', '08119001013', 'robert@petnutrition.co.id', 'HSBC', '1122334413', 'PT Pet Nutrition', 'sup_petnut', 'suppass123', 'Aktif', 'Sistem'),
('UD Indo Pasir Gumpal', '0215551214', 'indopasir@gmail.com', 'Jl. Kamal Raya No 8', 'Cengkareng Barat', 'Cengkareng', 'Jakarta Barat', 'DKI Jakarta', '11730', 'Hasan', 'Owner', '08119001014', 'hasan@indopasir.com', 'Mandiri', '1122334414', 'Hasan Basri', 'sup_indopasir', 'suppass123', 'Aktif', 'Sistem'),
('PT Beaphar Indonesia', '0215551215', 'order@beaphar.id', 'Sudirman Plaza, Plaza Marein Lt 10', 'Setiabudi', 'Setiabudi', 'Jakarta Selatan', 'DKI Jakarta', '12910', 'Nita', 'Sales Admin', '08119001015', 'nita@beaphar.id', 'BCA', '1122334415', 'PT Beaphar Indonesia', 'sup_beaphar', 'suppass123', 'Aktif', 'Sistem'),
('CV Kucing Lucu Petshop', '0317771216', 'kucinglucu@grosir.com', 'Jl. Dharmahusada No 34', 'Mojo', 'Gubeng', 'Surabaya', 'Jawa Timur', '60285', 'Lia', 'Marketing Staff', '08119001016', 'lia@kucinglucu.com', 'BRI', '1122334416', 'CV Kucing Lucu', 'sup_kucinglucu', 'suppass123', 'Aktif', 'Sistem'),
('PT Vet Indo Lestari', '0215551217', 'admin@vetindo.co.id', 'Jl. RC Veteran No 99', 'Bintaro', 'Pesanggrahan', 'Jakarta Selatan', 'DKI Jakarta', '12330', 'Syarif', 'Direktur Penjualan', '08119001017', 'syarif@vetindo.co.id', 'BCA', '1122334417', 'PT Vet Indo', 'sup_vetindo_l', 'suppass123', 'Aktif', 'Sistem'),
('CV Aneka Mainan Hewan', '0248881218', 'aneka.mainan@gmail.com', 'Jl. Gajahmada No 102', 'Kembangsari', 'Semarang Tengah', 'Semarang', 'Jawa Tengah', '50133', 'Rudi', 'Owner', '08119001018', 'rudi@anekamainan.com', 'BNI', '1122334418', 'Rudi Hermawan', 'sup_aneka', 'suppass123', 'Aktif', 'Sistem'),
('PT Susu Hewan Global', '0215551219', 'globalmilk@hewan.id', 'Ruko CBD Pluit Blok F No 12', 'Pluit', 'Penjaringan', 'Jakarta Utara', 'DKI Jakarta', '14450', 'Lisa', 'Customer Care', '08119001019', 'lisa@globalmilk.id', 'Mandiri', '1122334419', 'PT Susu Hewan Global', 'sup_globalmilk', 'suppass123', 'Aktif', 'Sistem'),
('PT Pet Cage Industry', '0215551220', 'cage@indopet.com', 'Jl. Kapuk Raya No 40', 'Kapuk Muara', 'Penjaringan', 'Jakarta Utara', 'DKI Jakarta', '14460', 'Toni', 'Production Lead', '08119001020', 'toni@indopet.com', 'BCA', '1122334420', 'PT Pet Cage Industry', 'sup_petcage', 'suppass123', 'Aktif', 'Sistem');


-- ==========================================================
-- 7. INSERT TABEL BOOKING (20 Data)
-- Pelanggan (ID 1-20), Layanan (ID 1-20), Karyawan (ID 1-20)
-- ==========================================================
INSERT INTO Booking (Kode_Booking, ID_Pelanggan, ID_Layanan, ID_Karyawan, Jadwal_Booking, Harga_Layanan, Diskon_Booking, Total_Tarif, Catatan_Booking, Status_Booking, Book_status, Book_created_by) VALUES
('BK-202310-0001', 1, 1, 4, '2023-10-25 09:00:00', 75000.00, 0.00, 75000.00, 'Kucing agak pemalu, minta groomer Dedi', 'Selesai', 'Selesai', 'Rizky_r'),
('BK-202310-0002', 2, 2, 5, '2023-10-25 10:30:00', 95000.00, 5000.00, 90000.00, 'Kucing ada jamur di telinga kiri', 'Selesai', 'Selesai', 'Siti_a'),
('BK-202310-0003', 4, 3, 8, '2023-10-25 13:00:00', 120000.00, 0.00, 120000.00, 'Minta cukur rapih telapak kaki', 'Selesai', 'Selesai', 'Dewi_s'),
('BK-202310-0004', 5, 4, 10, '2023-10-26 09:00:00', 90000.00, 0.00, 90000.00, 'Anjing pomeranian rewel jika dikeringkan', 'Selesai', 'Selesai', 'Fajar_n'),
('BK-202310-0005', 7, 5, 12, '2023-10-26 11:00:00', 150000.00, 10000.00, 140000.00, 'Golden Retriever, bulu sangat lebat', 'Selesai', 'Selesai', 'Irfan_b'),
('BK-202310-0006', 8, 6, 14, '2023-10-26 14:00:00', 180000.00, 0.00, 180000.00, 'Kutu lebat sekali, harap berhati-hati', 'Selesai', 'Selesai', 'Jeni_n'),
('BK-202310-0007', 10, 7, 4, '2023-10-27 08:00:00', 50000.00, 0.00, 50000.00, 'Titip sehat 3 hari mulai hari ini', 'Selesai', 'Selesai', 'Laras_p'),
('BK-202310-0008', 11, 8, 8, '2023-10-27 10:00:00', 80000.00, 0.00, 80000.00, 'Titip anjing pug, bawa pakan sendiri', 'Selesai', 'Selesai', 'Miko_p'),
('BK-202310-0009', 12, 9, 6, '2023-10-27 13:00:00', 100000.00, 0.00, 100000.00, 'Kucing muntah berbusa', 'Selesai', 'Selesai', 'Nadia_v'),
('BK-202310-0010', 14, 10, 7, '2023-10-28 09:00:00', 150000.00, 15000.00, 135000.00, 'Kulit anjing kemerahan gatal', 'Selesai', 'Selesai', 'Putri_a'),
('BK-202310-0011', 15, 11, 11, '2023-10-28 10:30:00', 200000.00, 0.00, 200000.00, 'Jadwal vaksin F3 tahunan', 'Selesai', 'Selesai', 'Ryan_h'),
('BK-202310-0012', 17, 12, 6, '2023-10-28 14:00:00', 250000.00, 0.00, 250000.00, 'Vaksinasi Eurican 4 anjing golden', 'Selesai', 'Selesai', 'Tomi_s'),
('BK-202310-0013', 18, 13, 11, '2023-10-29 09:00:00', 350000.00, 20000.00, 330000.00, 'Steril kucing persia jantan, puasa 8 jam', 'Selesai', 'Selesai', 'Ulfa_d'),
('BK-202310-0014', 19, 14, 6, '2023-10-29 11:30:00', 600000.00, 0.00, 600000.00, 'Steril kucing betina domestik', 'Selesai', 'Selesai', 'Vino_b'),
('BK-202310-0015', 1, 15, 12, '2023-10-29 15:00:00', 140000.00, 10000.00, 130000.00, 'Spa relaksasi wangi lavender', 'Selesai', 'Selesai', 'Rizky_r'),
('BK-202310-0016', 2, 16, 14, '2023-10-30 09:00:00', 160000.00, 0.00, 160000.00, 'Mencerahkan bulu kucing angora putih', 'Selesai', 'Selesai', 'Siti_a'),
('BK-202310-0017', 4, 17, 16, '2023-10-30 11:00:00', 110000.00, 0.00, 110000.00, 'Cukur model lion cut pada persia', 'Selesai', 'Selesai', 'Dewi_s'),
('BK-202310-0018', 5, 18, 18, '2023-10-30 14:00:00', 80000.00, 0.00, 80000.00, 'Cukur botak habis karena jamur parah', 'Selesai', 'Selesai', 'Fajar_n'),
('BK-202310-0019', 7, 19, 10, '2023-10-31 10:00:00', 30000.00, 0.00, 30000.00, 'Bersihkan telinga dalam ear mites kucing', 'Selesai', 'Selesai', 'Irfan_b'),
('BK-202310-0020', 8, 20, 20, '2023-10-31 13:00:00', 25000.00, 0.00, 25000.00, 'Potong kuku anjing poodle, galak harap rante', 'Selesai', 'Selesai', 'Jeni_n');


-- ==========================================================
-- 8. INSERT TABEL PENJUALAN (20 Data)
-- Pelanggan (ID 1-20), Karyawan (Kasir ID 2 / 3 / 13), Booking (ID 1-20)
-- ==========================================================
INSERT INTO Penjualan (No_Nota, ID_Pelanggan, ID_Karyawan, ID_Booking, Tanggal_Penjualan, Subtotal_Penjualan, Total_Diskon, Pajak_PPN, Grand_Total, Jumlah_Bayar, Kembalian, Metode_Pembayaran, Status_Pembayaran, Pen_status, Pen_created_by) VALUES
('INV-202310-0001', 1, 2, 1, '2023-10-25 10:15:00', 75000.00, 0.00, 7500.00, 82500.00, 100000.00, 17500.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0002', 2, 2, 2, '2023-10-25 12:10:00', 95000.00, 5000.00, 9000.00, 99000.00, 100000.00, 1000.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0003', 3, 3, NULL, '2023-10-25 13:30:00', 143000.00, 10000.00, 13300.00, 146300.00, 150000.00, 3700.00, 'Cash', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0004', 4, 3, 3, '2023-10-25 15:15:00', 120000.00, 0.00, 12000.00, 132000.00, 132000.00, 0.00, 'Qris', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0005', 5, 13, 4, '2023-10-26 10:30:00', 90000.00, 0.00, 9000.00, 99000.00, 100000.00, 1000.00, 'Cash', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0006', 6, 13, NULL, '2023-10-26 11:45:00', 215000.00, 0.00, 21500.00, 236500.00, 236500.00, 0.00, 'Qris', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0007', 7, 2, 5, '2023-10-26 13:10:00', 150000.00, 10000.00, 14000.00, 154000.00, 154000.00, 0.00, 'Transfer', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0008', 8, 2, 6, '2023-10-26 16:45:00', 180000.00, 0.00, 18000.00, 198000.00, 200000.00, 2000.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0009', 9, 3, NULL, '2023-10-27 09:15:00', 123000.00, 5000.00, 11800.00, 129800.00, 130000.00, 200.00, 'Cash', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0010', 10, 3, 7, '2023-10-27 10:00:00', 50000.00, 0.00, 5000.00, 55000.00, 55000.00, 0.00, 'Qris', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0011', 11, 13, 8, '2023-10-27 12:00:00', 80000.00, 0.00, 8000.00, 88000.00, 100000.00, 12000.00, 'Cash', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0012', 12, 13, 9, '2023-10-27 14:15:00', 100000.00, 0.00, 10000.00, 110000.00, 110000.00, 0.00, 'Qris', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0013', 13, 2, NULL, '2023-10-28 09:30:00', 68000.00, 0.00, 6800.00, 74800.00, 100000.00, 25200.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0014', 14, 2, 10, '2023-10-28 11:15:00', 150000.00, 15000.00, 13500.00, 148500.00, 150000.00, 1500.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0015', 15, 3, 11, '2023-10-28 11:30:00', 200000.00, 0.00, 20000.00, 220000.00, 220000.00, 0.00, 'Transfer', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0016', 16, 3, NULL, '2023-10-28 13:45:00', 58000.00, 0.00, 5800.00, 63800.00, 100000.00, 36200.00, 'Cash', 'Lunas', 'Lunas', 'Citra_l'),
('INV-202310-0017', 17, 13, 12, '2023-10-28 15:30:00', 25000.00, 0.00, 2500.00, 27500.00, 50000.00, 22500.00, 'Cash', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0018', 18, 13, 13, '2023-10-29 11:00:00', 350000.00, 20000.00, 33000.00, 363000.00, 363000.00, 0.00, 'Qris', 'Lunas', 'Lunas', 'Mega_u'),
('INV-202310-0019', 19, 2, 14, '2023-10-29 13:30:00', 600000.00, 0.00, 60000.00, 660000.00, 660000.00, 0.00, 'Transfer', 'Lunas', 'Lunas', 'Budi_s'),
('INV-202310-0020', 20, 2, 15, '2023-10-29 16:15:00', 140000.00, 10000.00, 13000.00, 143000.00, 150000.00, 7000.00, 'Cash', 'Lunas', 'Lunas', 'Budi_s');


-- ==========================================================
-- 9. INSERT TABEL DETAIL PENJUALAN (20 Data)
-- Nota (ID 1-20), Barang (ID 1-20)
-- ==========================================================
INSERT INTO Detail_Penjualan (ID_Nota, ID_Barang, Jumlah, Harga_Satuan, Diskon_Item, Subtotal, Catatan_Detail, DetPen_status, DetPen_created_by) VALUES
(1, 1, 1, 75000.00, 0.00, 75000.00, 'Untuk pakan kucing di rumah', 'Selesai', 'Sistem'),
(2, 3, 2, 8500.00, 0.00, 17000.00, 'Rasa tuna basah', 'Selesai', 'Sistem'),
(3, 2, 1, 68000.00, 0.00, 68000.00, 'Royal Canin Fit', 'Selesai', 'Sistem'),
(3, 14, 1, 75000.00, 10000.00, 65000.00, 'Diskon tebus pasir murah', 'Selesai', 'Sistem'),
(4, 5, 1, 24000.00, 0.00, 24000.00, 'Camilan JerHigh', 'Selesai', 'Sistem'),
(5, 4, 1, 65000.00, 0.00, 65000.00, 'Pedigree Anjing', 'Selesai', 'Sistem'),
(6, 17, 1, 215000.00, 0.00, 215000.00, 'Shampoo Sebazole anti jamur', 'Selesai', 'Sistem'),
(7, 6, 2, 15000.00, 0.00, 30000.00, 'Bola mainan kawat', 'Selesai', 'Sistem'),
(8, 7, 1, 22000.00, 0.00, 22000.00, 'Stick bulu', 'Selesai', 'Sistem'),
(9, 13, 1, 155000.00, 5000.00, 150000.00, 'Nutriplus gel vitamin', 'Selesai', 'Sistem'),
(10, 8, 1, 12000.00, 0.00, 12000.00, 'Kalung lonceng merah', 'Selesai', 'Sistem'),
(11, 9, 1, 55000.00, 0.00, 55000.00, 'Harness medium merah', 'Selesai', 'Sistem'),
(12, 12, 1, 32000.00, 0.00, 32000.00, 'Detick kutu tetes 1ml', 'Selesai', 'Sistem'),
(13, 11, 1, 68000.00, 0.00, 68000.00, 'Kasur empuk bulat abu-abu', 'Selesai', 'Sistem'),
(14, 10, 1, 145000.00, 0.00, 145000.00, 'Pet Cargo Biru', 'Selesai', 'Sistem'),
(15, 15, 1, 55000.00, 0.00, 55000.00, 'Pasir zeolit karung', 'Selesai', 'Sistem'),
(16, 20, 1, 58000.00, 0.00, 58000.00, 'Susu Growssy 1 Box', 'Selesai', 'Sistem'),
(17, 18, 1, 30000.00, 0.00, 30000.00, 'Gunting Kuku kecil', 'Selesai', 'Sistem'),
(18, 19, 1, 42000.00, 0.00, 42000.00, 'Sisir slicker brush kuning', 'Selesai', 'Sistem'),
(19, 16, 2, 60000.00, 0.00, 120000.00, 'Shampoo flea anti kutu', 'Selesai', 'Sistem'),
(20, 1, 2, 75000.00, 5000.00, 145000.00, 'Whiskas adult bungkus', 'Selesai', 'Sistem');


-- ==========================================================
-- 10. INSERT TABEL STOK MASUK (20 Data)
-- Supplier (ID 1-20), Karyawan (Gudang/Admin ID 1 / 9 / 15 / 19)
-- ==========================================================
INSERT INTO Stok_Masuk (No_Faktur, ID_Supplier, ID_Karyawan, Tanggal_Masuk, Tanggal_Diterima, Subtotal_Stok, Pajak_Stok, Total_Harga, Status, Catatan_Masuk, SM_status, SM_created_by) VALUES
('FKT-2023-0001', 1, 1, '2023-10-01 09:00:00', '2023-10-02 10:00:00', 3000000.00, 300000.00, 3300000.00, 'Diterima', 'Kardus utuh rapih', 'Selesai', 'Andi_w'),
('FKT-2023-0002', 2, 1, '2023-10-02 09:30:00', '2023-10-03 14:00:00', 1500000.00, 150000.00, 1650000.00, 'Diterima', 'Sesuai pesanan', 'Selesai', 'Andi_w'),
('FKT-2023-0003', 3, 9, '2023-10-03 10:00:00', '2023-10-04 11:30:00', 5000000.00, 500000.00, 5500000.00, 'Diterima', 'Whiskas dry & wet aman', 'Selesai', 'Indah_p'),
('FKT-2023-0004', 4, 9, '2023-10-05 11:00:00', '2023-10-06 13:00:00', 1200000.00, 0.00, 1200000.00, 'Diterima', 'Pembelian non PPN', 'Selesai', 'Indah_p'),
('FKT-2023-0005', 5, 15, '2023-10-08 14:00:00', '2023-10-09 10:15:00', 8000000.00, 800000.00, 8800000.00, 'Diterima', 'Royal canin kemasan baru', 'Selesai', 'Olivia_s'),
('FKT-2023-0006', 6, 15, '2023-10-10 10:00:00', '2023-10-11 11:00:00', 950000.00, 95000.00, 1045000.00, 'Diterima', 'Aksesoris lengkap', 'Selesai', 'Olivia_s'),
('FKT-2023-0007', 7, 19, '2023-10-12 13:00:00', '2023-10-13 14:30:00', 4500000.00, 450000.00, 4950000.00, 'Diterima', 'Barang aman', 'Selesai', 'Tari_l'),
('FKT-2023-0008', 8, 19, '2023-10-15 09:15:00', '2023-10-15 16:00:00', 2500000.00, 250000.00, 2750000.00, 'Diterima', 'Pasir bento gumpal wangi', 'Selesai', 'Tari_l'),
('FKT-2023-0009', 9, 1, '2023-10-16 11:00:00', '2023-10-17 10:00:00', 3100000.00, 310000.00, 3410000.00, 'Diterima', 'Vitamin & detick lengkap', 'Selesai', 'Andi_w'),
('FKT-2023-0010', 10, 1, '2023-10-18 14:30:00', '2023-10-19 13:00:00', 1800000.00, 0.00, 1800000.00, 'Diterima', 'Grosir pakan', 'Selesai', 'Andi_w'),
('FKT-2023-0011', 11, 9, '2023-10-20 09:00:00', '2023-10-21 11:00:00', 2200000.00, 220000.00, 2420000.00, 'Diterima', 'Obat luar veteriner', 'Selesai', 'Indah_p'),
('FKT-2023-0012', 12, 9, '2023-10-21 10:30:00', '2023-10-22 14:00:00', 1350000.00, 135000.00, 1485000.00, 'Diterima', 'Obat resep apotek', 'Selesai', 'Indah_p'),
('FKT-2023-0013', 13, 15, '2023-10-22 13:00:00', '2023-10-23 15:00:00', 6400000.00, 640000.00, 7040000.00, 'Diterima', 'Super premium wet food', 'Selesai', 'Olivia_s'),
('FKT-2023-0014', 14, 15, '2023-10-24 15:00:00', '2023-10-25 10:00:00', 1850000.00, 0.00, 1850000.00, 'Diterima', 'Pasir kemasan jumbo', 'Selesai', 'Olivia_s'),
('FKT-2023-0015', 15, 19, '2023-10-25 08:30:00', '2023-10-25 16:30:00', 4100000.00, 410000.00, 4510000.00, 'Diterima', 'Shampoo impor beaphar', 'Selesai', 'Tari_l'),
('FKT-2023-0016', 16, 19, '2023-10-25 10:00:00', '2023-10-26 11:30:00', 2800000.00, 0.00, 2800000.00, 'Diterima', 'Aksesoris kayu mainan', 'Selesai', 'Tari_l'),
('FKT-2023-0017', 17, 1, '2023-10-26 11:15:00', '2023-10-27 13:00:00', 5300000.00, 530000.00, 5830000.00, 'Diterima', 'Alat bedah steril lab', 'Selesai', 'Andi_w'),
('FKT-2023-0018', 18, 9, '2023-10-27 13:00:00', '2023-10-28 10:00:00', 1600000.00, 160000.00, 1760000.00, 'Diterima', 'Mainan tali anyam', 'Selesai', 'Indah_p'),
('FKT-2023-0019', 19, 15, '2023-10-28 14:00:00', '2023-10-29 09:00:00', 3700000.00, 370000.00, 4070000.00, 'Diterima', 'Susu botol hewan', 'Selesai', 'Olivia_s'),
('FKT-2023-0020', 20, 19, '2023-10-29 15:30:00', '2023-10-30 11:00:00', 6200000.00, 620000.00, 6820000.00, 'Diterima', 'Kandang besi susun', 'Selesai', 'Tari_l');


-- ==========================================================
-- 11. INSERT TABEL DETAIL STOK MASUK (20 Data)
-- Stok_Masuk (ID 1-20), Barang (ID 1-20)
-- ==========================================================
INSERT INTO Detail_Stok_Masuk (ID_Stok, ID_Barang, Jumlah_Masuk, Harga_Beli, Subtotal, No_Batch, Tanggal_Kadaluarsa, DetSM_status, DetSM_created_by) VALUES
(1, 1, 50, 60000.00, 3000000.00, 'BAT-WHI-01', '2025-10-01', 'Selesai', 'Sistem'),
(2, 3, 250, 6000.00, 1500000.00, 'BAT-MEO-03', '2025-08-15', 'Selesai', 'Sistem'),
(3, 2, 90, 55000.00, 4950000.00, 'BAT-RC-02', '2025-05-10', 'Selesai', 'Sistem'),
(4, 4, 24, 50000.00, 1200000.00, 'BAT-PED-04', '2025-09-20', 'Selesai', 'Sistem'),
(5, 13, 64, 125000.00, 8000000.00, 'BAT-NUT-05', '2026-02-14', 'Selesai', 'Sistem'),
(6, 8, 190, 5000.00, 950000.00, 'BAT-ACC-06', NULL, 'Selesai', 'Sistem'),
(7, 9, 128, 35000.00, 4480000.00, 'BAT-HAR-07', NULL, 'Selesai', 'Sistem'),
(8, 14, 45, 55000.00, 2475000.00, 'BAT-PAS-08', NULL, 'Selesai', 'Sistem'),
(9, 12, 155, 20000.00, 3100000.00, 'BAT-DET-09', '2026-11-30', 'Selesai', 'Sistem'),
(10, 5, 75, 18000.00, 1350000.00, 'BAT-JER-10', '2025-12-25', 'Selesai', 'Sistem'),
(11, 12, 110, 20000.00, 2200000.00, 'BAT-DET-11', '2026-08-12', 'Selesai', 'Sistem'),
(12, 17, 7, 180000.00, 1260000.00, 'BAT-SEB-12', '2026-01-20', 'Selesai', 'Sistem'),
(13, 2, 116, 55000.00, 6380000.00, 'BAT-RC-13', '2025-11-18', 'Selesai', 'Sistem'),
(14, 15, 52, 35000.00, 1820000.00, 'BAT-ZE-14', NULL, 'Selesai', 'Sistem'),
(15, 16, 102, 40000.00, 4080000.00, 'BAT-SHP-15', '2026-03-10', 'Selesai', 'Sistem'),
(16, 6, 350, 8000.00, 2800000.00, 'BAT-TOY-16', NULL, 'Selesai', 'Sistem'),
(17, 12, 265, 20000.00, 5300000.00, 'BAT-DET-17', '2026-05-15', 'Selesai', 'Sistem'),
(18, 7, 133, 12000.00, 1596000.00, 'BAT-STK-18', NULL, 'Selesai', 'Sistem'),
(19, 20, 88, 42000.00, 3696000.00, 'BAT-MILK-19', '2025-07-22', 'Selesai', 'Sistem'),
(20, 10, 56, 110000.00, 6160000.00, 'BAT-CAG-20', NULL, 'Selesai', 'Sistem');
GO