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
GO -- Memisahkan pembuatan tabel dengan pembuatan Stored Procedure pertama


-- =============================================
-- SP: KATEGORI CREATE
-- Fungsi: Menambah data kategori baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Kategori_Create
    @Nama_Kategori VARCHAR(50),      -- Wajib diisi, unik
    @Deskripsi VARCHAR(255) = NULL,  -- Opsional
    @Foto_Kategori VARCHAR(255) = NULL,
    @Tipe_Kategori VARCHAR(20),      -- Wajib: 'Barang' atau 'Layanan'
    @Foto_Barang VARCHAR(255) = NULL,
    @Kat_status VARCHAR(30) = 'Aktif',
    @Kat_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;  -- Suppress "rows affected" message
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Cek apakah nama kategori sudah ada (soft delete = 0)
        IF EXISTS (
            SELECT 1 FROM Kategori 
            WHERE Nama_Kategori = @Nama_Kategori 
              AND Kat_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama kategori sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Cek constraint CHECK untuk Tipe_Kategori
        IF @Tipe_Kategori NOT IN ('Barang', 'Layanan')
        BEGIN
            RAISERROR('Tipe kategori hanya boleh Barang atau Layanan!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Kategori (
            Nama_Kategori, Deskripsi, Foto_Kategori, Tipe_Kategori, Foto_Barang,
            Kat_status, Kat_created_by, Kat_created_date
        )
        VALUES (
            @Nama_Kategori, @Deskripsi, @Foto_Kategori, @Tipe_Kategori, @Foto_Barang,
            @Kat_status, @Kat_created_by, GETDATE()
        );
        
        -- RETURN ID yang baru dibuat + pesan sukses
        SELECT 
            SCOPE_IDENTITY() AS ID_Kategori, 
            'Kategori berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;  -- Lempar error ke aplikasi
    END CATCH
END;
GO

-- =============================================
-- SP: KATEGORI READ
-- Fungsi: Menampilkan data kategori (semua / by ID)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Kategori_Read
    @ID_Kategori INT = NULL  -- NULL = tampil semua
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Kategori,
        Nama_Kategori,
        Deskripsi,
        Foto_Kategori,
        Tipe_Kategori,
        Foto_Barang,
        Kat_status,
        Kat_is_deleted,
        Kat_created_by,
        Kat_created_date,
        Kat_modified_by,
        Kat_modified_date,
        Kat_deleted_by,
        Kat_deleted_date
    FROM Kategori
    WHERE 
        (@ID_Kategori IS NULL OR ID_Kategori = @ID_Kategori)
        AND Kat_is_deleted = 0  -- Hanya tampilkan yang aktif
    ORDER BY ID_Kategori;
END;
GO

-- =============================================
-- SP: KATEGORI UPDATE
-- Fungsi: Mengubah data kategori
-- =============================================
CREATE OR ALTER PROCEDURE sp_Kategori_Update
    @ID_Kategori INT,                -- Wajib: ID yang mau diupdate
    @Nama_Kategori VARCHAR(50) = NULL,
    @Deskripsi VARCHAR(255) = NULL,
    @Foto_Kategori VARCHAR(255) = NULL,
    @Tipe_Kategori VARCHAR(20) = NULL,
    @Foto_Barang VARCHAR(255) = NULL,
    @Kat_status VARCHAR(30) = NULL,
    @Kat_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Cek apakah data ada
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Kat_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Cek duplikat nama (kecuali dirinya sendiri)
        IF @Nama_Kategori IS NOT NULL 
           AND EXISTS (
               SELECT 1 FROM Kategori 
               WHERE Nama_Kategori = @Nama_Kategori 
                 AND ID_Kategori <> @ID_Kategori  -- <> artinya "bukan dirinya"
                 AND Kat_is_deleted = 0
           )
        BEGIN
            RAISERROR('Nama kategori sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: Cek tipe kategori valid
        IF @Tipe_Kategori IS NOT NULL 
           AND @Tipe_Kategori NOT IN ('Barang', 'Layanan')
        BEGIN
            RAISERROR('Tipe kategori hanya boleh Barang atau Layanan!', 16, 1);
            RETURN;
        END
        
        -- UPDATE dengan ISNULL (jika parameter NULL, pakai nilai lama)
        UPDATE Kategori
        SET 
            Nama_Kategori = ISNULL(@Nama_Kategori, Nama_Kategori),
            Deskripsi = ISNULL(@Deskripsi, Deskripsi),
            Foto_Kategori = ISNULL(@Foto_Kategori, Foto_Kategori),
            Tipe_Kategori = ISNULL(@Tipe_Kategori, Tipe_Kategori),
            Foto_Barang = ISNULL(@Foto_Barang, Foto_Barang),
            Kat_status = ISNULL(@Kat_status, Kat_status),
            Kat_modified_by = @Kat_modified_by,
            Kat_modified_date = GETDATE()
        WHERE ID_Kategori = @ID_Kategori;
        
        SELECT 'Kategori berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: KATEGORI DELETE (SOFT DELETE)
-- Fungsi: Menonaktifkan kategori (tidak hapus permanen)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Kategori_Delete
    @ID_Kategori INT,
    @Kat_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Cek apakah data ada
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Kat_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Cek apakah kategori masih digunakan di Layanan
        IF EXISTS (
            SELECT 1 FROM Layanan 
            WHERE ID_Kategori = @ID_Kategori 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori masih digunakan oleh data Layanan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: Cek apakah kategori masih digunakan di Barang
        IF EXISTS (
            SELECT 1 FROM Barang 
            WHERE ID_Kategori = @ID_Kategori 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori masih digunakan oleh data Barang!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE: Update flag, bukan DELETE
        UPDATE Kategori
        SET 
            Kat_is_deleted = 1,
            Kat_deleted_by = @Kat_deleted_by,
            Kat_deleted_date = GETDATE(),
            Kat_status = 'Nonaktif'
        WHERE ID_Kategori = @ID_Kategori;
        
        SELECT 'Kategori berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO


-- =============================================
-- SP: KARYAWAN CREATE
-- Fungsi: Menambah data karyawan baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Karyawan_Create
    @NIK VARCHAR(16),                 -- Wajib, unik
    @Nama_Karyawan VARCHAR(100),       -- Wajib
    @Jenis_Kelamin VARCHAR(15),        -- Wajib: 'Laki-laki' atau 'Perempuan'
    @Tempat_Lahir VARCHAR(50) = NULL,
    @Tanggal_Lahir DATE = NULL,
    @Agama VARCHAR(20) = NULL,        -- CHECK constraint
    @Status_Pernikahan VARCHAR(20) = NULL,
    @Goldar VARCHAR(3) = '-',
    @No_Telepon VARCHAR(15),          -- Wajib, unik
    @Email VARCHAR(100),               -- Wajib, unik
    @Username VARCHAR(50),             -- Wajib, unik
    @Password VARCHAR(255),            -- Wajib
    @Role VARCHAR(20) = NULL,
    @Foto_Karyawan VARCHAR(255) = NULL,
    @Alamat_KTP VARCHAR(255) = NULL,
    @Alamat_Domisili VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Jabatan VARCHAR(50) = NULL,
    @Status_Karyawan VARCHAR(20) = 'Kontrak',
    @Kar_status VARCHAR(30) = 'Aktif',
    @Kar_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI: NIK sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Karyawan WHERE NIK = @NIK AND Kar_is_deleted = 0)
        BEGIN
            RAISERROR('NIK sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: No Telepon sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Karyawan WHERE No_Telepon = @No_Telepon AND Kar_is_deleted = 0)
        BEGIN
            RAISERROR('No Telepon sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Email sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Karyawan WHERE Email = @Email AND Kar_is_deleted = 0)
        BEGIN
            RAISERROR('Email sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Username sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Karyawan WHERE Username = @Username AND Kar_is_deleted = 0)
        BEGIN
            RAISERROR('Username sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Karyawan (
            NIK, Nama_Karyawan, Jenis_Kelamin, Tempat_Lahir, Tanggal_Lahir, Agama,
            Status_Pernikahan, Goldar, No_Telepon, Email, Username, Password, Role,
            Foto_Karyawan, Alamat_KTP, Alamat_Domisili, Kelurahan, Kecamatan,
            Kota_Kabupaten, Provinsi, Kode_Pos, Jabatan, Status_Karyawan,
            Kar_status, Kar_created_by, Kar_created_date
        )
        VALUES (
            @NIK, @Nama_Karyawan, @Jenis_Kelamin, @Tempat_Lahir, @Tanggal_Lahir, @Agama,
            @Status_Pernikahan, @Goldar, @No_Telepon, @Email, @Username, @Password, @Role,
            @Foto_Karyawan, @Alamat_KTP, @Alamat_Domisili, @Kelurahan, @Kecamatan,
            @Kota_Kabupaten, @Provinsi, @Kode_Pos, @Jabatan, @Status_Karyawan,
            @Kar_status, @Kar_created_by, GETDATE()
        );
        
        SELECT 
            SCOPE_IDENTITY() AS ID_Karyawan,
            'Karyawan berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: KARYAWAN READ
-- Fungsi: Menampilkan data karyawan (semua / by ID / by Username untuk login)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Karyawan_Read
    @ID_Karyawan INT = NULL,
    @Username VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jenis_Kelamin,
        Tempat_Lahir,
        Tanggal_Lahir,
        Agama,
        Status_Pernikahan,
        Goldar,
        No_Telepon,
        Email,
        Username,
        Role,
        Foto_Karyawan,
        Alamat_KTP,
        Alamat_Domisili,
        Kelurahan,
        Kecamatan,
        Kota_Kabupaten,
        Provinsi,
        Kode_Pos,
        Jabatan,
        Tanggal_Masuk,
        Status_Karyawan,
        Kar_status,
        Kar_is_deleted,
        Kar_created_by,
        Kar_created_date,
        Kar_modified_by,
        Kar_modified_date
    FROM Karyawan
    WHERE 
        (@ID_Karyawan IS NULL OR ID_Karyawan = @ID_Karyawan)
        AND (@Username IS NULL OR Username = @Username)
        AND Kar_is_deleted = 0
    ORDER BY ID_Karyawan;
END;
GO

-- =============================================
-- SP: KARYAWAN UPDATE
-- Fungsi: Mengubah data karyawan
-- =============================================
CREATE OR ALTER PROCEDURE sp_Karyawan_Update
    @ID_Karyawan INT,
    @NIK VARCHAR(16) = NULL,
    @Nama_Karyawan VARCHAR(100) = NULL,
    @Jenis_Kelamin VARCHAR(15) = NULL,
    @Tempat_Lahir VARCHAR(50) = NULL,
    @Tanggal_Lahir DATE = NULL,
    @Agama VARCHAR(20) = NULL,
    @Status_Pernikahan VARCHAR(20) = NULL,
    @Goldar VARCHAR(3) = NULL,
    @No_Telepon VARCHAR(15) = NULL,
    @Email VARCHAR(100) = NULL,
    @Username VARCHAR(50) = NULL,
    @Password VARCHAR(255) = NULL,
    @Role VARCHAR(20) = NULL,
    @Foto_Karyawan VARCHAR(255) = NULL,
    @Alamat_KTP VARCHAR(255) = NULL,
    @Alamat_Domisili VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Jabatan VARCHAR(50) = NULL,
    @Status_Karyawan VARCHAR(20) = NULL,
    @Kar_status VARCHAR(30) = NULL,
    @Kar_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI: Data ada?
        IF NOT EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE ID_Karyawan = @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: NIK duplikat (bukan dirinya sendiri)?
        IF @NIK IS NOT NULL AND EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE NIK = @NIK 
              AND ID_Karyawan <> @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('NIK sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: No Telepon duplikat?
        IF @No_Telepon IS NOT NULL AND EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE No_Telepon = @No_Telepon 
              AND ID_Karyawan <> @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('No Telepon sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Email duplikat?
        IF @Email IS NOT NULL AND EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE Email = @Email 
              AND ID_Karyawan <> @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Email sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Username duplikat?
        IF @Username IS NOT NULL AND EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE Username = @Username 
              AND ID_Karyawan <> @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Username sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- UPDATE
        UPDATE Karyawan
        SET 
            NIK = ISNULL(@NIK, NIK),
            Nama_Karyawan = ISNULL(@Nama_Karyawan, Nama_Karyawan),
            Jenis_Kelamin = ISNULL(@Jenis_Kelamin, Jenis_Kelamin),
            Tempat_Lahir = ISNULL(@Tempat_Lahir, Tempat_Lahir),
            Tanggal_Lahir = ISNULL(@Tanggal_Lahir, Tanggal_Lahir),
            Agama = ISNULL(@Agama, Agama),
            Status_Pernikahan = ISNULL(@Status_Pernikahan, Status_Pernikahan),
            Goldar = ISNULL(@Goldar, Goldar),
            No_Telepon = ISNULL(@No_Telepon, No_Telepon),
            Email = ISNULL(@Email, Email),
            Username = ISNULL(@Username, Username),
            Password = ISNULL(@Password, Password),
            Role = ISNULL(@Role, Role),
            Foto_Karyawan = ISNULL(@Foto_Karyawan, Foto_Karyawan),
            Alamat_KTP = ISNULL(@Alamat_KTP, Alamat_KTP),
            Alamat_Domisili = ISNULL(@Alamat_Domisili, Alamat_Domisili),
            Kelurahan = ISNULL(@Kelurahan, Kelurahan),
            Kecamatan = ISNULL(@Kecamatan, Kecamatan),
            Kota_Kabupaten = ISNULL(@Kota_Kabupaten, Kota_Kabupaten),
            Provinsi = ISNULL(@Provinsi, Provinsi),
            Kode_Pos = ISNULL(@Kode_Pos, Kode_Pos),
            Jabatan = ISNULL(@Jabatan, Jabatan),
            Status_Karyawan = ISNULL(@Status_Karyawan, Status_Karyawan),
            Kar_status = ISNULL(@Kar_status, Kar_status),
            Kar_modified_by = @Kar_modified_by,
            Kar_modified_date = GETDATE()
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 'Karyawan berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: KARYAWAN DELETE (SOFT DELETE)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Karyawan_Delete
    @ID_Karyawan INT,
    @Kar_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        IF NOT EXISTS (
            SELECT 1 FROM Karyawan 
            WHERE ID_Karyawan = @ID_Karyawan 
              AND Kar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Karyawan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE
        UPDATE Karyawan
        SET 
            Kar_is_deleted = 1,
            Kar_deleted_by = @Kar_deleted_by,
            Kar_deleted_date = GETDATE(),
            Kar_status = 'Nonaktif'
        WHERE ID_Karyawan = @ID_Karyawan;
        
        SELECT 'Karyawan berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: KARYAWAN LOGIN
-- Fungsi: Autentikasi karyawan untuk aplikasi web
-- =============================================
CREATE OR ALTER PROCEDURE sp_Karyawan_Login
    @Username VARCHAR(50),
    @Password VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Karyawan,
        NIK,
        Nama_Karyawan,
        Jenis_Kelamin,
        No_Telepon,
        Email,
        Username,
        Role,
        Foto_Karyawan,
        Jabatan,
        Status_Karyawan,
        Kar_status
    FROM Karyawan
    WHERE Username = @Username 
      AND Password = @Password 
      AND Kar_is_deleted = 0;
END;
GO

-- =============================================
-- SP: PELANGGAN CREATE
-- Fungsi: Menambah data pelanggan baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Pelanggan_Create
    @Nama_Pelanggan VARCHAR(100),       -- Wajib
    @Jenis_Kelamin VARCHAR(15) = NULL,  -- 'Laki-laki' atau 'Perempuan'
    @Tempat_Lahir VARCHAR(50) = NULL,
    @Tanggal_Lahir DATE = NULL,
    @Pekerjaan VARCHAR(50) = NULL,
    @No_Telepon VARCHAR(15),            -- Wajib, unik
    @Email VARCHAR(100),                 -- Wajib, unik
    @Username VARCHAR(50) = NULL,       -- Boleh kosong (jika beli langsung)
    @Password VARCHAR(255) = NULL,
    @Status_Member VARCHAR(20) = 'Non Member',
    @Poin_Member INT = 0,
    @Foto_Pelanggan VARCHAR(255) = NULL,
    @Alamat VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Pel_status VARCHAR(30) = 'Aktif',
    @Pel_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI: No Telepon sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Pelanggan WHERE No_Telepon = @No_Telepon AND Pel_is_deleted = 0)
        BEGIN
            RAISERROR('No Telepon sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Email sudah terdaftar?
        IF EXISTS (SELECT 1 FROM Pelanggan WHERE Email = @Email AND Pel_is_deleted = 0)
        BEGIN
            RAISERROR('Email sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Username jika diisi, harus unik
        IF @Username IS NOT NULL AND EXISTS (
            SELECT 1 FROM Pelanggan WHERE Username = @Username AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('Username sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Pelanggan (
            Nama_Pelanggan, Jenis_Kelamin, Tempat_Lahir, Tanggal_Lahir, Pekerjaan,
            No_Telepon, Email, Username, Password, Status_Member, Poin_Member, Foto_Pelanggan,
            Alamat, Kelurahan, Kecamatan, Kota_Kabupaten, Provinsi, Kode_Pos,
            Pel_status, Pel_created_by, Pel_created_date
        )
        VALUES (
            @Nama_Pelanggan, @Jenis_Kelamin, @Tempat_Lahir, @Tanggal_Lahir, @Pekerjaan,
            @No_Telepon, @Email, @Username, @Password, @Status_Member, @Poin_Member, @Foto_Pelanggan,
            @Alamat, @Kelurahan, @Kecamatan, @Kota_Kabupaten, @Provinsi, @Kode_Pos,
            @Pel_status, @Pel_created_by, GETDATE()
        );
        
        SELECT 
            SCOPE_IDENTITY() AS ID_Pelanggan,
            'Pelanggan berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: PELANGGAN READ
-- Fungsi: Menampilkan data pelanggan (semua / by ID / by Username / by No Telepon)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Pelanggan_Read
    @ID_Pelanggan INT = NULL,
    @Username VARCHAR(50) = NULL,
    @No_Telepon VARCHAR(15) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Pelanggan,
        Nama_Pelanggan,
        Jenis_Kelamin,
        Tempat_Lahir,
        Tanggal_Lahir,
        Pekerjaan,
        No_Telepon,
        Email,
        Username,
        Status_Member,
        Poin_Member,
        Foto_Pelanggan,
        Alamat,
        Kelurahan,
        Kecamatan,
        Kota_Kabupaten,
        Provinsi,
        Kode_Pos,
        Pel_status,
        Pel_is_deleted,
        Pel_created_by,
        Pel_created_date,
        Pel_modified_by,
        Pel_modified_date
    FROM Pelanggan
    WHERE 
        (@ID_Pelanggan IS NULL OR ID_Pelanggan = @ID_Pelanggan)
        AND (@Username IS NULL OR Username = @Username)
        AND (@No_Telepon IS NULL OR No_Telepon = @No_Telepon)
        AND Pel_is_deleted = 0
    ORDER BY ID_Pelanggan;
END;
GO

-- =============================================
-- SP: PELANGGAN UPDATE
-- Fungsi: Mengubah data pelanggan
-- =============================================
CREATE OR ALTER PROCEDURE sp_Pelanggan_Update
    @ID_Pelanggan INT,
    @Nama_Pelanggan VARCHAR(100) = NULL,
    @Jenis_Kelamin VARCHAR(15) = NULL,
    @Tempat_Lahir VARCHAR(50) = NULL,
    @Tanggal_Lahir DATE = NULL,
    @Pekerjaan VARCHAR(50) = NULL,
    @No_Telepon VARCHAR(15) = NULL,
    @Email VARCHAR(100) = NULL,
    @Username VARCHAR(50) = NULL,
    @Password VARCHAR(255) = NULL,
    @Status_Member VARCHAR(20) = NULL,
    @Poin_Member INT = NULL,
    @Foto_Pelanggan VARCHAR(255) = NULL,
    @Alamat VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Pel_status VARCHAR(30) = NULL,
    @Pel_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI: Data ada?
        IF NOT EXISTS (
            SELECT 1 FROM Pelanggan 
            WHERE ID_Pelanggan = @ID_Pelanggan 
              AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('Pelanggan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: No Telepon duplikat?
        IF @No_Telepon IS NOT NULL AND EXISTS (
            SELECT 1 FROM Pelanggan 
            WHERE No_Telepon = @No_Telepon 
              AND ID_Pelanggan <> @ID_Pelanggan 
              AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('No Telepon sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Email duplikat?
        IF @Email IS NOT NULL AND EXISTS (
            SELECT 1 FROM Pelanggan 
            WHERE Email = @Email 
              AND ID_Pelanggan <> @ID_Pelanggan 
              AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('Email sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI: Username duplikat?
        IF @Username IS NOT NULL AND EXISTS (
            SELECT 1 FROM Pelanggan 
            WHERE Username = @Username 
              AND ID_Pelanggan <> @ID_Pelanggan 
              AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('Username sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- UPDATE
        UPDATE Pelanggan
        SET 
            Nama_Pelanggan = ISNULL(@Nama_Pelanggan, Nama_Pelanggan),
            Jenis_Kelamin = ISNULL(@Jenis_Kelamin, Jenis_Kelamin),
            Tempat_Lahir = ISNULL(@Tempat_Lahir, Tempat_Lahir),
            Tanggal_Lahir = ISNULL(@Tanggal_Lahir, Tanggal_Lahir),
            Pekerjaan = ISNULL(@Pekerjaan, Pekerjaan),
            No_Telepon = ISNULL(@No_Telepon, No_Telepon),
            Email = ISNULL(@Email, Email),
            Username = ISNULL(@Username, Username),
            Password = ISNULL(@Password, Password),
            Status_Member = ISNULL(@Status_Member, Status_Member),
            Poin_Member = ISNULL(@Poin_Member, Poin_Member),
            Foto_Pelanggan = ISNULL(@Foto_Pelanggan, Foto_Pelanggan),
            Alamat = ISNULL(@Alamat, Alamat),
            Kelurahan = ISNULL(@Kelurahan, Kelurahan),
            Kecamatan = ISNULL(@Kecamatan, Kecamatan),
            Kota_Kabupaten = ISNULL(@Kota_Kabupaten, Kota_Kabupaten),
            Provinsi = ISNULL(@Provinsi, Provinsi),
            Kode_Pos = ISNULL(@Kode_Pos, Kode_Pos),
            Pel_status = ISNULL(@Pel_status, Pel_status),
            Pel_modified_by = @Pel_modified_by,
            Pel_modified_date = GETDATE()
        WHERE ID_Pelanggan = @ID_Pelanggan;
        
        SELECT 'Pelanggan berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: PELANGGAN DELETE (SOFT DELETE)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Pelanggan_Delete
    @ID_Pelanggan INT,
    @Pel_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        IF NOT EXISTS (
            SELECT 1 FROM Pelanggan 
            WHERE ID_Pelanggan = @ID_Pelanggan 
              AND Pel_is_deleted = 0
        )
        BEGIN
            RAISERROR('Pelanggan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE
        UPDATE Pelanggan
        SET 
            Pel_is_deleted = 1,
            Pel_deleted_by = @Pel_deleted_by,
            Pel_deleted_date = GETDATE(),
            Pel_status = 'Nonaktif'
        WHERE ID_Pelanggan = @ID_Pelanggan;
        
        SELECT 'Pelanggan berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: PELANGGAN LOGIN
-- Fungsi: Autentikasi pelanggan untuk aplikasi web
-- =============================================
CREATE OR ALTER PROCEDURE sp_Pelanggan_Login
    @Username VARCHAR(50),
    @Password VARCHAR(255)
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Pelanggan,
        Nama_Pelanggan,
        No_Telepon,
        Email,
        Username,
        Status_Member,
        Poin_Member,
        Foto_Pelanggan
    FROM Pelanggan
    WHERE Username = @Username 
      AND Password = @Password 
      AND Pel_is_deleted = 0;
END;
GO


-- =============================================
-- SP: LAYANAN CREATE
-- Fungsi: Menambah data layanan/jasa baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Layanan_Create
    @ID_Kategori INT,                   -- Wajib, FK ke Kategori (Tipe='Layanan')
    @Kode_Layanan VARCHAR(20),          -- Wajib, unik
    @Nama_Layanan VARCHAR(100),         -- Wajib, unik
    @Harga_Layanan DECIMAL(15,2),       -- Wajib
    @Durasi DECIMAL(4,1) = NULL,        -- Durasi dalam jam (misal: 1.5 = 1 jam 30 menit)
    @Deskripsi_Layanan VARCHAR(255) = NULL,
    @Foto_Layanan VARCHAR(255) = NULL,
    @Lay_status VARCHAR(30) = 'Aktif',
    @Lay_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Kategori harus ada dan aktif
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Kat_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Kategori harus bertipe 'Layanan'
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Tipe_Kategori = 'Layanan'
        )
        BEGIN
            RAISERROR('Kategori harus bertipe Layanan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: Kode layanan unik
        IF EXISTS (
            SELECT 1 FROM Layanan 
            WHERE Kode_Layanan = @Kode_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kode layanan sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: Nama layanan unik
        IF EXISTS (
            SELECT 1 FROM Layanan 
            WHERE Nama_Layanan = @Nama_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama layanan sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: Harga harus > 0
        IF @Harga_Layanan <= 0
        BEGIN
            RAISERROR('Harga layanan harus lebih dari 0!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Layanan (
            ID_Kategori, Kode_Layanan, Nama_Layanan, Harga_Layanan, Durasi,
            Deskripsi_Layanan, Foto_Layanan, Lay_status, Lay_created_by, Lay_created_date
        )
        VALUES (
            @ID_Kategori, @Kode_Layanan, @Nama_Layanan, @Harga_Layanan, @Durasi,
            @Deskripsi_Layanan, @Foto_Layanan, @Lay_status, @Lay_created_by, GETDATE()
        );
        
        SELECT 
            SCOPE_IDENTITY() AS ID_Layanan,
            'Layanan berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: LAYANAN READ
-- Fungsi: Menampilkan data layanan dengan JOIN ke Kategori
-- =============================================
CREATE OR ALTER PROCEDURE sp_Layanan_Read
    @ID_Layanan INT = NULL,
    @ID_Kategori INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        l.ID_Layanan,
        l.ID_Kategori,
        k.Nama_Kategori,
        k.Tipe_Kategori,
        l.Kode_Layanan,
        l.Nama_Layanan,
        l.Harga_Layanan,
        l.Durasi,
        CASE 
            WHEN l.Durasi IS NULL THEN '-'
            WHEN l.Durasi < 1 THEN CAST(CAST(l.Durasi * 60 AS INT) AS VARCHAR) + ' menit'
            WHEN l.Durasi = FLOOR(l.Durasi) THEN CAST(CAST(l.Durasi AS INT) AS VARCHAR) + ' jam'
            ELSE CAST(CAST(FLOOR(l.Durasi) AS INT) AS VARCHAR) + ' jam ' + 
                 CAST(CAST((l.Durasi - FLOOR(l.Durasi)) * 60 AS INT) AS VARCHAR) + ' menit'
        END AS Durasi_Format,
        l.Deskripsi_Layanan,
        l.Foto_Layanan,
        l.Lay_status,
        l.Lay_is_deleted,
        l.Lay_created_by,
        l.Lay_created_date,
        l.Lay_modified_by,
        l.Lay_modified_date
    FROM Layanan l
    LEFT JOIN Kategori k ON l.ID_Kategori = k.ID_Kategori
    WHERE 
        (@ID_Layanan IS NULL OR l.ID_Layanan = @ID_Layanan)
        AND (@ID_Kategori IS NULL OR l.ID_Kategori = @ID_Kategori)
        AND l.Lay_is_deleted = 0
    ORDER BY l.ID_Layanan;
END;
GO

-- =============================================
-- SP: LAYANAN UPDATE
-- Fungsi: Mengubah data layanan
-- =============================================
CREATE OR ALTER PROCEDURE sp_Layanan_Update
    @ID_Layanan INT,
    @ID_Kategori INT = NULL,
    @Kode_Layanan VARCHAR(20) = NULL,
    @Nama_Layanan VARCHAR(100) = NULL,
    @Harga_Layanan DECIMAL(15,2) = NULL,
    @Durasi DECIMAL(4,1) = NULL,
    @Deskripsi_Layanan VARCHAR(255) = NULL,
    @Foto_Layanan VARCHAR(255) = NULL,
    @Lay_status VARCHAR(30) = NULL,
    @Lay_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Data ada?
        IF NOT EXISTS (
            SELECT 1 FROM Layanan 
            WHERE ID_Layanan = @ID_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Layanan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Kategori baru valid?
        IF @ID_Kategori IS NOT NULL
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM Kategori 
                WHERE ID_Kategori = @ID_Kategori 
                  AND Kat_is_deleted = 0
            )
            BEGIN
                RAISERROR('Kategori tidak ditemukan!', 16, 1);
                RETURN;
            END
            
            IF NOT EXISTS (
                SELECT 1 FROM Kategori 
                WHERE ID_Kategori = @ID_Kategori 
                  AND Tipe_Kategori = 'Layanan'
            )
            BEGIN
                RAISERROR('Kategori harus bertipe Layanan!', 16, 1);
                RETURN;
            END
        END
        
        -- VALIDASI 3: Kode duplikat?
        IF @Kode_Layanan IS NOT NULL AND EXISTS (
            SELECT 1 FROM Layanan 
            WHERE Kode_Layanan = @Kode_Layanan 
              AND ID_Layanan <> @ID_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kode layanan sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: Nama duplikat?
        IF @Nama_Layanan IS NOT NULL AND EXISTS (
            SELECT 1 FROM Layanan 
            WHERE Nama_Layanan = @Nama_Layanan 
              AND ID_Layanan <> @ID_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama layanan sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: Harga valid?
        IF @Harga_Layanan IS NOT NULL AND @Harga_Layanan <= 0
        BEGIN
            RAISERROR('Harga layanan harus lebih dari 0!', 16, 1);
            RETURN;
        END
        
        -- UPDATE
        UPDATE Layanan
        SET 
            ID_Kategori = ISNULL(@ID_Kategori, ID_Kategori),
            Kode_Layanan = ISNULL(@Kode_Layanan, Kode_Layanan),
            Nama_Layanan = ISNULL(@Nama_Layanan, Nama_Layanan),
            Harga_Layanan = ISNULL(@Harga_Layanan, Harga_Layanan),
            Durasi = ISNULL(@Durasi, Durasi),
            Deskripsi_Layanan = ISNULL(@Deskripsi_Layanan, Deskripsi_Layanan),
            Foto_Layanan = ISNULL(@Foto_Layanan, Foto_Layanan),
            Lay_status = ISNULL(@Lay_status, Lay_status),
            Lay_modified_by = @Lay_modified_by,
            Lay_modified_date = GETDATE()
        WHERE ID_Layanan = @ID_Layanan;
        
        SELECT 'Layanan berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: LAYANAN DELETE (SOFT DELETE)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Layanan_Delete
    @ID_Layanan INT,
    @Lay_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        IF NOT EXISTS (
            SELECT 1 FROM Layanan 
            WHERE ID_Layanan = @ID_Layanan 
              AND Lay_is_deleted = 0
        )
        BEGIN
            RAISERROR('Layanan tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE
        UPDATE Layanan
        SET 
            Lay_is_deleted = 1,
            Lay_deleted_by = @Lay_deleted_by,
            Lay_deleted_date = GETDATE(),
            Lay_status = 'Nonaktif'
        WHERE ID_Layanan = @ID_Layanan;
        
        SELECT 'Layanan berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO


-- =============================================
-- SP: BARANG CREATE
-- Fungsi: Menambah data barang/produk baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Barang_Create
    @ID_Kategori INT,                   -- Wajib, FK ke Kategori (Tipe='Barang')
    @Kode_Barang VARCHAR(50),           -- Wajib, unik (SKU/Barcode)
    @Nama_Barang VARCHAR(100),          -- Wajib, unik
    @Harga_Beli DECIMAL(15,2) = 0,      -- Default 0
    @Harga_Jual DECIMAL(15,2) = 0,      -- Default 0
    @Stok INT = 0,                      -- Default 0
    @Stok_Minimum INT = NULL,           -- Alert stok rendah
    @Deskripsi VARCHAR(255) = NULL,
    @Satuan VARCHAR(20) = NULL,         -- pcs, kg, liter, dll
    @Foto_Barang VARCHAR(255) = NULL,
    @Bar_status VARCHAR(30) = 'Aktif',
    @Bar_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Kategori harus ada dan aktif
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Kat_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kategori tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Kategori harus bertipe 'Barang'
        IF NOT EXISTS (
            SELECT 1 FROM Kategori 
            WHERE ID_Kategori = @ID_Kategori 
              AND Tipe_Kategori = 'Barang'
        )
        BEGIN
            RAISERROR('Kategori harus bertipe Barang!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: Kode barang unik
        IF EXISTS (
            SELECT 1 FROM Barang 
            WHERE Kode_Barang = @Kode_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kode barang sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: Nama barang unik
        IF EXISTS (
            SELECT 1 FROM Barang 
            WHERE Nama_Barang = @Nama_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama barang sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: Harga jual >= harga beli
        IF @Harga_Jual < @Harga_Beli
        BEGIN
            RAISERROR('Harga jual tidak boleh lebih rendah dari harga beli!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Barang (
            ID_Kategori, Kode_Barang, Nama_Barang, Harga_Beli, Harga_Jual, Stok,
            Stok_Minimum, Deskripsi, Satuan, Foto_Barang, Bar_status, Bar_created_by, Bar_created_date
        )
        VALUES (
            @ID_Kategori, @Kode_Barang, @Nama_Barang, @Harga_Beli, @Harga_Jual, @Stok,
            @Stok_Minimum, @Deskripsi, @Satuan, @Foto_Barang, @Bar_status, @Bar_created_by, GETDATE()
        );
        
        SELECT 
            SCOPE_IDENTITY() AS ID_Barang,
            'Barang berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: BARANG READ
-- Fungsi: Menampilkan data barang dengan status stok
-- =============================================
CREATE OR ALTER PROCEDURE sp_Barang_Read
    @ID_Barang INT = NULL,
    @ID_Kategori INT = NULL,
    @Search VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        b.ID_Barang,
        b.ID_Kategori,
        k.Nama_Kategori,
        b.Kode_Barang,
        b.Nama_Barang,
        b.Harga_Beli,
        b.Harga_Jual,
        (b.Harga_Jual - b.Harga_Beli) AS Keuntungan,
        b.Stok,
        b.Stok_Minimum,
        -- Status stok otomatis
        CASE 
            WHEN b.Stok_Minimum IS NULL THEN 'Tidak Ada Minimum'
            WHEN b.Stok <= 0 THEN 'Habis'
            WHEN b.Stok <= b.Stok_Minimum THEN 'Stok Rendah'
            ELSE 'Aman'
        END AS Status_Stok,
        b.Deskripsi,
        b.Satuan,
        b.Foto_Barang,
        b.Bar_status,
        b.Bar_is_deleted,
        b.Bar_created_by,
        b.Bar_created_date,
        b.Bar_modified_by,
        b.Bar_modified_date
    FROM Barang b
    LEFT JOIN Kategori k ON b.ID_Kategori = k.ID_Kategori
    WHERE 
        (@ID_Barang IS NULL OR b.ID_Barang = @ID_Barang)
        AND (@ID_Kategori IS NULL OR b.ID_Kategori = @ID_Kategori)
        AND (@Search IS NULL OR b.Nama_Barang LIKE '%' + @Search + '%' OR b.Kode_Barang LIKE '%' + @Search + '%')
        AND b.Bar_is_deleted = 0
    ORDER BY b.ID_Barang;
END;
GO

-- =============================================
-- SP: BARANG UPDATE
-- Fungsi: Mengubah data barang
-- =============================================
CREATE OR ALTER PROCEDURE sp_Barang_Update
    @ID_Barang INT,
    @ID_Kategori INT = NULL,
    @Kode_Barang VARCHAR(50) = NULL,
    @Nama_Barang VARCHAR(100) = NULL,
    @Harga_Beli DECIMAL(15,2) = NULL,
    @Harga_Jual DECIMAL(15,2) = NULL,
    @Stok_Minimum INT = NULL,
    @Deskripsi VARCHAR(255) = NULL,
    @Satuan VARCHAR(20) = NULL,
    @Foto_Barang VARCHAR(255) = NULL,
    @Bar_status VARCHAR(30) = NULL,
    @Bar_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Data ada?
        IF NOT EXISTS (
            SELECT 1 FROM Barang 
            WHERE ID_Barang = @ID_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Barang tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Kategori baru valid?
        IF @ID_Kategori IS NOT NULL
        BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM Kategori 
                WHERE ID_Kategori = @ID_Kategori 
                  AND Kat_is_deleted = 0
            )
            BEGIN
                RAISERROR('Kategori tidak ditemukan!', 16, 1);
                RETURN;
            END
            
            IF NOT EXISTS (
                SELECT 1 FROM Kategori 
                WHERE ID_Kategori = @ID_Kategori 
                  AND Tipe_Kategori = 'Barang'
            )
            BEGIN
                RAISERROR('Kategori harus bertipe Barang!', 16, 1);
                RETURN;
            END
        END
        
        -- VALIDASI 3: Kode duplikat?
        IF @Kode_Barang IS NOT NULL AND EXISTS (
            SELECT 1 FROM Barang 
            WHERE Kode_Barang = @Kode_Barang 
              AND ID_Barang <> @ID_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Kode barang sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: Nama duplikat?
        IF @Nama_Barang IS NOT NULL AND EXISTS (
            SELECT 1 FROM Barang 
            WHERE Nama_Barang = @Nama_Barang 
              AND ID_Barang <> @ID_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama barang sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: Harga valid?
        DECLARE @CurrentHargaBeli DECIMAL(15,2);
        DECLARE @NewHargaBeli DECIMAL(15,2);
        DECLARE @NewHargaJual DECIMAL(15,2);
        
        SELECT @CurrentHargaBeli = Harga_Beli FROM Barang WHERE ID_Barang = @ID_Barang;
        SET @NewHargaBeli = ISNULL(@Harga_Beli, @CurrentHargaBeli);
        SET @NewHargaJual = ISNULL(@Harga_Jual, (SELECT Harga_Jual FROM Barang WHERE ID_Barang = @ID_Barang));
        
        IF @NewHargaJual < @NewHargaBeli
        BEGIN
            RAISERROR('Harga jual tidak boleh lebih rendah dari harga beli!', 16, 1);
            RETURN;
        END
        
        -- UPDATE
        UPDATE Barang
        SET 
            ID_Kategori = ISNULL(@ID_Kategori, ID_Kategori),
            Kode_Barang = ISNULL(@Kode_Barang, Kode_Barang),
            Nama_Barang = ISNULL(@Nama_Barang, Nama_Barang),
            Harga_Beli = ISNULL(@Harga_Beli, Harga_Beli),
            Harga_Jual = ISNULL(@Harga_Jual, Harga_Jual),
            Stok_Minimum = ISNULL(@Stok_Minimum, Stok_Minimum),
            Deskripsi = ISNULL(@Deskripsi, Deskripsi),
            Satuan = ISNULL(@Satuan, Satuan),
            Foto_Barang = ISNULL(@Foto_Barang, Foto_Barang),
            Bar_status = ISNULL(@Bar_status, Bar_status),
            Bar_modified_by = @Bar_modified_by,
            Bar_modified_date = GETDATE()
        WHERE ID_Barang = @ID_Barang;
        
        SELECT 'Barang berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: BARANG DELETE (SOFT DELETE)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Barang_Delete
    @ID_Barang INT,
    @Bar_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        IF NOT EXISTS (
            SELECT 1 FROM Barang 
            WHERE ID_Barang = @ID_Barang 
              AND Bar_is_deleted = 0
        )
        BEGIN
            RAISERROR('Barang tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE
        UPDATE Barang
        SET 
            Bar_is_deleted = 1,
            Bar_deleted_by = @Bar_deleted_by,
            Bar_deleted_date = GETDATE(),
            Bar_status = 'Nonaktif'
        WHERE ID_Barang = @ID_Barang;
        
        SELECT 'Barang berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO


-- =============================================
-- SP: SUPPLIER CREATE
-- Fungsi: Menambah data supplier baru
-- =============================================
CREATE OR ALTER PROCEDURE sp_Supplier_Create
    @Nama_Supplier VARCHAR(100),        -- Wajib, unik
    @No_Telepon VARCHAR(15),            -- Wajib, unik (telepon kantor)
    @Email VARCHAR(100),                 -- Wajib, unik (email resmi)
    @Alamat VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Nama_CP VARCHAR(100) = NULL,     -- Contact Person
    @Jabatan_CP VARCHAR(50) = NULL,
    @No_Telepon_CP VARCHAR(15) = NULL,
    @Email_CP VARCHAR(100) = NULL,
    @Nama_Bank VARCHAR(50) = NULL,
    @No_Rekening VARCHAR(30) = NULL,    -- Unik jika diisi
    @Atas_Nama_Rekening VARCHAR(100) = NULL,
    @Username VARCHAR(50) = NULL,       -- Unik jika diisi
    @Password VARCHAR(255) = NULL,
    @Foto_Supplier VARCHAR(255) = NULL,
    @Sup_status VARCHAR(30) = 'Aktif',
    @Sup_created_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Nama supplier unik
        IF EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Nama_Supplier = @Nama_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama supplier sudah ada!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: No telepon kantor unik
        IF EXISTS (
            SELECT 1 FROM Supplier 
            WHERE No_Telepon = @No_Telepon 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('No telepon kantor sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: Email kantor sudah terdaftar?
        IF EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Email = @Email 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Email kantor sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: No rekening unik (jika diisi)
        IF @No_Rekening IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE No_Rekening = @No_Rekening 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('No rekening sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: Username unik (jika diisi)
        IF @Username IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Username = @Username 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Username sudah terdaftar!', 16, 1);
            RETURN;
        END
        
        -- INSERT DATA
        INSERT INTO Supplier (
            Nama_Supplier, No_Telepon, Email, Alamat, Kelurahan, Kecamatan, Kota_Kabupaten,
            Provinsi, Kode_Pos, Nama_CP, Jabatan_CP, No_Telepon_CP, Email_CP, Nama_Bank, No_Rekening,
            Atas_Nama_Rekening, Username, Password, Foto_Supplier, Sup_status, Sup_created_by, Sup_created_date
        )
        VALUES (
            @Nama_Supplier, @No_Telepon, @Email, @Alamat, @Kelurahan, @Kecamatan, @Kota_Kabupaten,
            @Provinsi, @Kode_Pos, @Nama_CP, @Jabatan_CP, @No_Telepon_CP, @Email_CP, @Nama_Bank, @No_Rekening,
            @Atas_Nama_Rekening, @Username, @Password, @Foto_Supplier, @Sup_status, @Sup_created_by, GETDATE()
        );
        
        SELECT 
            SCOPE_IDENTITY() AS ID_Supplier,
            'Supplier berhasil ditambahkan' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: SUPPLIER READ
-- Fungsi: Menampilkan data supplier
-- =============================================
CREATE OR ALTER PROCEDURE sp_Supplier_Read
    @ID_Supplier INT = NULL,
    @Search VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    SELECT 
        ID_Supplier,
        Nama_Supplier,
        No_Telepon,
        Email,
        Alamat,
        Kelurahan,
        Kecamatan,
        Kota_Kabupaten,
        Provinsi,
        Kode_Pos,
        Nama_CP,
        Jabatan_CP,
        No_Telepon_CP,
        Email_CP,
        Nama_Bank,
        No_Rekening,
        Atas_Nama_Rekening,
        Username,
        Foto_Supplier,
        Sup_status,
        Sup_is_deleted,
        Sup_created_by,
        Sup_created_date,
        Sup_modified_by,
        Sup_modified_date
    FROM Supplier
    WHERE 
        (@ID_Supplier IS NULL OR ID_Supplier = @ID_Supplier)
        AND (@Search IS NULL OR Nama_Supplier LIKE '%' + @Search + '%')
        AND Sup_is_deleted = 0
    ORDER BY ID_Supplier;
END;
GO

-- =============================================
-- SP: SUPPLIER UPDATE
-- Fungsi: Mengubah data supplier
-- =============================================
CREATE OR ALTER PROCEDURE sp_Supplier_Update
    @ID_Supplier INT,
    @Nama_Supplier VARCHAR(100) = NULL,
    @No_Telepon VARCHAR(15) = NULL,
    @Email VARCHAR(100) = NULL,
    @Alamat VARCHAR(255) = NULL,
    @Kelurahan VARCHAR(50) = NULL,
    @Kecamatan VARCHAR(50) = NULL,
    @Kota_Kabupaten VARCHAR(50) = NULL,
    @Provinsi VARCHAR(50) = NULL,
    @Kode_Pos VARCHAR(10) = NULL,
    @Nama_CP VARCHAR(100) = NULL,
    @Jabatan_CP VARCHAR(50) = NULL,
    @No_Telepon_CP VARCHAR(15) = NULL,
    @Email_CP VARCHAR(100) = NULL,
    @Nama_Bank VARCHAR(50) = NULL,
    @No_Rekening VARCHAR(30) = NULL,
    @Atas_Nama_Rekening VARCHAR(100) = NULL,
    @Username VARCHAR(50) = NULL,
    @Password VARCHAR(255) = NULL,
    @Foto_Supplier VARCHAR(255) = NULL,
    @Sup_status VARCHAR(30) = NULL,
    @Sup_modified_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        -- VALIDASI 1: Data ada?
        IF NOT EXISTS (
            SELECT 1 FROM Supplier 
            WHERE ID_Supplier = @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Supplier tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 2: Nama duplikat?
        IF @Nama_Supplier IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Nama_Supplier = @Nama_Supplier 
              AND ID_Supplier <> @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Nama supplier sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 3: No telepon duplikat?
        IF @No_Telepon IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE No_Telepon = @No_Telepon 
              AND ID_Supplier <> @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('No telepon sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 4: Email duplikat?
        IF @Email IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Email = @Email 
              AND ID_Supplier <> @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Email sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 5: No rekening duplikat?
        IF @No_Rekening IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE No_Rekening = @No_Rekening 
              AND ID_Supplier <> @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('No rekening sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- VALIDASI 6: Username duplikat?
        IF @Username IS NOT NULL AND EXISTS (
            SELECT 1 FROM Supplier 
            WHERE Username = @Username 
              AND ID_Supplier <> @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Username sudah digunakan!', 16, 1);
            RETURN;
        END
        
        -- UPDATE
        UPDATE Supplier
        SET 
            Nama_Supplier = ISNULL(@Nama_Supplier, Nama_Supplier),
            No_Telepon = ISNULL(@No_Telepon, No_Telepon),
            Email = ISNULL(@Email, Email),
            Alamat = ISNULL(@Alamat, Alamat),
            Kelurahan = ISNULL(@Kelurahan, Kelurahan),
            Kecamatan = ISNULL(@Kecamatan, Kecamatan),
            Kota_Kabupaten = ISNULL(@Kota_Kabupaten, Kota_Kabupaten),
            Provinsi = ISNULL(@Provinsi, Provinsi),
            Kode_Pos = ISNULL(@Kode_Pos, Kode_Pos),
            Nama_CP = ISNULL(@Nama_CP, Nama_CP),
            Jabatan_CP = ISNULL(@Jabatan_CP, Jabatan_CP),
            No_Telepon_CP = ISNULL(@No_Telepon_CP, No_Telepon_CP),
            Email_CP = ISNULL(@Email_CP, Email_CP),
            Nama_Bank = ISNULL(@Nama_Bank, Nama_Bank),
            No_Rekening = ISNULL(@No_Rekening, No_Rekening),
            Atas_Nama_Rekening = ISNULL(@Atas_Nama_Rekening, Atas_Nama_Rekening),
            Username = ISNULL(@Username, Username),
            Password = ISNULL(@Password, Password),
            Foto_Supplier = ISNULL(@Foto_Supplier, Foto_Supplier),
            Sup_status = ISNULL(@Sup_status, Sup_status),
            Sup_modified_by = @Sup_modified_by,
            Sup_modified_date = GETDATE()
        WHERE ID_Supplier = @ID_Supplier;
        
        SELECT 'Supplier berhasil diupdate' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- SP: SUPPLIER DELETE (SOFT DELETE)
-- =============================================
CREATE OR ALTER PROCEDURE sp_Supplier_Delete
    @ID_Supplier INT,
    @Sup_deleted_by VARCHAR(50) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        BEGIN TRANSACTION;
        
        IF NOT EXISTS (
            SELECT 1 FROM Supplier 
            WHERE ID_Supplier = @ID_Supplier 
              AND Sup_is_deleted = 0
        )
        BEGIN
            RAISERROR('Supplier tidak ditemukan!', 16, 1);
            RETURN;
        END
        
        -- SOFT DELETE
        UPDATE Supplier
        SET 
            Sup_is_deleted = 1,
            Sup_deleted_by = @Sup_deleted_by,
            Sup_deleted_date = GETDATE(),
            Sup_status = 'Nonaktif'
        WHERE ID_Supplier = @ID_Supplier;
        
        SELECT 'Supplier berhasil dihapus' AS Message;
        
        COMMIT TRANSACTION;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO


-- =============================================
-- UDF: fn_TotalPenjualan
-- Fungsi: Menghitung total penjualan (grand total) dalam periode tertentu
-- Return: DECIMAL(15,2)
-- =============================================
CREATE OR ALTER FUNCTION fn_TotalPenjualan
(
    @Tanggal_Dari DATE,
    @Tanggal_Sampai DATE
)
RETURNS DECIMAL(15,2)
AS
BEGIN
    DECLARE @Total DECIMAL(15,2);
    
    SELECT @Total = ISNULL(SUM(Grand_Total), 0)
    FROM Penjualan
    WHERE CAST(Tanggal_Penjualan AS DATE) BETWEEN @Tanggal_Dari AND @Tanggal_Sampai
      AND Status_Pembayaran = 'Lunas';
    
    RETURN @Total;
END;
GO

-- =============================================
-- UDF: fn_JumlahTransaksi
-- Fungsi: Menghitung jumlah nota/penjualan dalam periode
-- Return: INT
-- =============================================
CREATE OR ALTER FUNCTION fn_JumlahTransaksi
(
    @Tanggal_Dari DATE,
    @Tanggal_Sampai DATE
)
RETURNS INT
AS
BEGIN
    DECLARE @Jumlah INT;
    
    SELECT @Jumlah = COUNT(*)
    FROM Penjualan
    WHERE CAST(Tanggal_Penjualan AS DATE) BETWEEN @Tanggal_Dari AND @Tanggal_Sampai
      AND Status_Pembayaran = 'Lunas';
    
    RETURN @Jumlah;
END;
GO

-- =============================================
-- UDF: fn_TotalBooking
-- Fungsi: Menghitung total nilai booking dalam periode
-- Return: DECIMAL(15,2)
-- =============================================
CREATE OR ALTER FUNCTION fn_TotalBooking
(
    @Tanggal_Dari DATE,
    @Tanggal_Sampai DATE
)
RETURNS DECIMAL(15,2)
AS
BEGIN
    DECLARE @Total DECIMAL(15,2);
    
    SELECT @Total = ISNULL(SUM(Total_Tarif), 0)
    FROM Booking
    WHERE CAST(Tanggal_Booking AS DATE) BETWEEN @Tanggal_Dari AND @Tanggal_Sampai
      AND Status_Booking <> 'Dibatalkan';
    
    RETURN @Total;
END;
GO

-- =============================================
-- UDF: fn_JumlahBookingByStatus
-- Fungsi: Menghitung jumlah booking berdasarkan status
-- Return: INT
-- =============================================
CREATE OR ALTER FUNCTION fn_JumlahBookingByStatus
(
    @Status_Booking VARCHAR(20),
    @Tanggal_Dari DATE = NULL,
    @Tanggal_Sampai DATE = NULL
)
RETURNS INT
AS
BEGIN
    DECLARE @Jumlah INT;
    
    SELECT @Jumlah = COUNT(*)
    FROM Booking
    WHERE Status_Booking = @Status_Booking
      AND (@Tanggal_Dari IS NULL OR CAST(Tanggal_Booking AS DATE) >= @Tanggal_Dari)
      AND (@Tanggal_Sampai IS NULL OR CAST(Tanggal_Booking AS DATE) <= @Tanggal_Sampai);
    
    RETURN @Jumlah;
END;
GO

-- =============================================
-- UDF: fn_StokBarang
-- Fungsi: Menampilkan stok barang saat ini
-- Return: TABLE (inline table-valued function)
-- =============================================
CREATE OR ALTER FUNCTION fn_StokBarang
(
    @ID_Barang INT = NULL
)
RETURNS TABLE
AS
RETURN
(
    SELECT 
        b.ID_Barang,
        b.Kode_Barang,
        b.Nama_Barang,
        k.Nama_Kategori,
        b.Stok,
        b.Stok_Minimum,
        CASE 
            WHEN b.Stok <= 0 THEN 'Habis'
            WHEN b.Stok_Minimum IS NOT NULL AND b.Stok <= b.Stok_Minimum THEN 'Stok Rendah'
            ELSE 'Aman'
        END AS Status_Stok,
        b.Harga_Beli,
        b.Harga_Jual,
        (b.Harga_Jual - b.Harga_Beli) AS Margin,
        b.Satuan
    FROM Barang b
    LEFT JOIN Kategori k ON b.ID_Kategori = k.ID_Kategori
    WHERE b.Bar_is_deleted = 0
      AND (@ID_Barang IS NULL OR b.ID_Barang = @ID_Barang)
);
GO

-- =============================================
-- UDF: fn_BarangTerlaris
-- Fungsi: Menampilkan barang paling laris dalam periode
-- Return: TABLE
-- =============================================
CREATE OR ALTER FUNCTION fn_BarangTerlaris
(
    @Tanggal_Dari DATE,
    @Tanggal_Sampai DATE,
    @TopN INT = 10
)
RETURNS TABLE
AS
RETURN
(
    SELECT TOP (@TopN)
        b.ID_Barang,
        b.Kode_Barang,
        b.Nama_Barang,
        k.Nama_Kategori,
        SUM(dp.Jumlah) AS Total_Terjual,
        SUM(dp.Subtotal) AS Total_Pendapatan,
        AVG(dp.Harga_Satuan) AS Harga_Rata_Rata
    FROM Detail_Penjualan dp
    INNER JOIN Barang b ON dp.ID_Barang = b.ID_Barang
    LEFT JOIN Kategori k ON b.ID_Kategori = k.ID_Kategori
    INNER JOIN Penjualan p ON dp.ID_Nota = p.ID_Nota
    WHERE CAST(p.Tanggal_Penjualan AS DATE) BETWEEN @Tanggal_Dari AND @Tanggal_Sampai
      AND p.Status_Pembayaran = 'Lunas'
    GROUP BY b.ID_Barang, b.Kode_Barang, b.Nama_Barang, k.Nama_Kategori
    ORDER BY Total_Terjual DESC
);
GO


-- =============================================
-- TABEL: Log_History
-- Fungsi: Menyimpan catatan perubahan data (audit trail)
-- =============================================
CREATE TABLE Log_History (
    ID_Log INT PRIMARY KEY IDENTITY(1,1),
    Nama_Tabel VARCHAR(50) NOT NULL,
    ID_Record INT,
    Aksi VARCHAR(20) NOT NULL,      -- INSERT, UPDATE, DELETE
    Data_Lama NVARCHAR(MAX),        -- JSON/XML string data sebelumnya
    Data_Baru NVARCHAR(MAX),        -- JSON/XML string data sesudahnya
    User_Action VARCHAR(50),
    Waktu_Action DATETIME DEFAULT GETDATE(),
    IP_Address VARCHAR(50) NULL     -- Perbaikan: diubah dari '= NULL' ke 'NULL'
);
GO


-- =============================================
-- TRIGGER: trg_Kategori_Log
-- Fungsi: Log history setiap INSERT/UPDATE/DELETE pada Kategori
-- =============================================
CREATE OR ALTER TRIGGER trg_Kategori_Log
ON Kategori
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;
    
    DECLARE @Aksi VARCHAR(20);
    DECLARE @Data_Lama NVARCHAR(MAX) = NULL;
    DECLARE @Data_Baru NVARCHAR(MAX) = NULL;
    
    -- Tentukan aksi
    IF EXISTS (SELECT 1 FROM inserted) AND EXISTS (SELECT 1 FROM deleted)
        SET @Aksi = 'UPDATE';
    ELSE IF EXISTS (SELECT 1 FROM inserted)
        SET @Aksi = 'INSERT';
    ELSE
        SET @Aksi = 'DELETE';
    
    -- Ambil data lama (untuk UPDATE/DELETE)
    IF @Aksi IN ('UPDATE', 'DELETE')
    BEGIN
        SELECT @Data_Lama = (
            SELECT ID_Kategori, Nama_Kategori, Deskripsi, Foto_Kategori, Tipe_Kategori,
                   Kat_status, Kat_is_deleted, Kat_created_by, Kat_modified_by
            FROM deleted
            FOR JSON PATH
        );
    END
    
    -- Ambil data baru (untuk INSERT/UPDATE)
    IF @Aksi IN ('INSERT', 'UPDATE')
    BEGIN
        SELECT @Data_Baru = (
            SELECT ID_Kategori, Nama_Kategori, Deskripsi, Foto_Kategori, Tipe_Kategori,
                   Kat_status, Kat_is_deleted, Kat_created_by, Kat_modified_by
            FROM inserted
            FOR JSON PATH
        );
    END
    
    -- Insert ke Log_History
    INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Lama, Data_Baru, User_Action)
    SELECT 
        'Kategori',
        COALESCE(i.ID_Kategori, d.ID_Kategori),
        @Aksi,
        @Data_Lama,
        @Data_Baru,
        COALESCE(i.Kat_modified_by, i.Kat_created_by, d.Kat_modified_by, d.Kat_created_by, SYSTEM_USER)
    FROM inserted i
    FULL OUTER JOIN deleted d ON i.ID_Kategori = d.ID_Kategori;
END;
GO

-- =============================================
-- TRIGGER: trg_DetailPenjualan_UpdateStok
-- Fungsi: Kurangi stok barang saat penjualan dibuat
-- =============================================
CREATE OR ALTER TRIGGER trg_DetailPenjualan_UpdateStok
ON Detail_Penjualan
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    
    BEGIN TRY
        -- Kurangi stok barang sesuai jumlah yang dibeli
        UPDATE b
        SET b.Stok = b.Stok - i.Jumlah
        FROM Barang b
        INNER JOIN inserted i ON b.ID_Barang = i.ID_Barang;
        
        -- Cek stok negatif (rollback jika terjadi)
        IF EXISTS (
            SELECT 1 FROM Barang b
            INNER JOIN inserted i ON b.ID_Barang = i.ID_Barang
            WHERE b.Stok < 0
        )
        BEGIN
            RAISERROR('Stok barang tidak mencukupi!', 16, 1);
            ROLLBACK TRANSACTION;
            RETURN;
        END
        
        -- Log history
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Action)
        SELECT 
            'Barang_Stok_Update',
            i.ID_Barang,
            'UPDATE_STOK',
            (SELECT ID_Barang, Jumlah FROM inserted i2 WHERE i2.ID_Detail = i.ID_Detail FOR JSON PATH),
            SYSTEM_USER
        FROM inserted i;
    END TRY
    BEGIN CATCH
        ROLLBACK TRANSACTION;
        THROW;
    END CATCH
END;
GO

-- =============================================
-- TRIGGER: trg_DetailStokMasuk_TambahStok
-- Fungsi: Tambah stok barang saat barang masuk diterima
-- =============================================
-- 1. Pastikan trigger pada tabel Detail_Stok_Masuk tidak langsung menambah stok jika statusnya masih 'Pending'
ALTER TRIGGER trg_DetailStokMasuk_TambahStok
ON Detail_Stok_Masuk
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    -- Hanya jalankan penambahan stok jika data master (Stok_Masuk) berstatus 'Selesai'
    -- (Saat pengadaan baru dibuat, statusnya 'Pending', sehingga bagian ini akan dilewati)
    IF EXISTS (
        SELECT 1 FROM inserted i 
        JOIN Stok_Masuk sm ON i.ID_Stok = sm.ID_Stok 
        WHERE sm.Status = 'Selesai'
    )
    BEGIN
        UPDATE b
        SET b.Stok = b.Stok + i.Jumlah_Masuk
        FROM Barang b
        JOIN inserted i ON b.ID_Barang = i.ID_Barang;
    END
END;
GO



-- 2. Buat trigger baru yang kebal terhadap perbedaan huruf besar/kecil (UPPER) dan spasi (TRIM)
CREATE TRIGGER trg_StokMasuk_Diterima_TambahStok
ON Stok_Masuk
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- Memastikan status berubah dari 'PENDING' menjadi 'DITERIMA' atau 'SELESAI'
    IF EXISTS (
        SELECT 1 
        FROM inserted i
        JOIN deleted d ON i.ID_Stok = d.ID_Stok
        WHERE UPPER(TRIM(d.Status)) = 'PENDING' 
          AND (UPPER(TRIM(i.Status)) = 'DITERIMA' OR UPPER(TRIM(i.Status)) = 'SELESAI')
    )
    BEGIN
        -- Lakukan penambahan stok ke tabel Barang
        UPDATE b
        SET b.Stok = b.Stok + det.Jumlah_Masuk
        FROM Barang b
        JOIN Detail_Stok_Masuk det ON b.ID_Barang = det.ID_Barang
        JOIN inserted i ON det.ID_Stok = i.ID_Stok
        JOIN deleted d ON i.ID_Stok = d.ID_Stok
        WHERE UPPER(TRIM(d.Status)) = 'PENDING' 
          AND (UPPER(TRIM(i.Status)) = 'DITERIMA' OR UPPER(TRIM(i.Status)) = 'SELESAI');
    END
END;
GO

-- =============================================
-- TRIGGER: trg_Penjualan_PoinMember
-- Fungsi: Tambah poin member saat pembayaran lunas
-- =============================================
CREATE OR ALTER TRIGGER trg_Penjualan_PoinMember
ON Penjualan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Hanya jika status berubah menjadi Lunas
    IF UPDATE(Status_Pembayaran)
    BEGIN
        UPDATE p
        SET p.Poin_Member = p.Poin_Member + FLOOR(i.Grand_Total / 10000)  -- 1 poin per 10.000
        FROM Pelanggan p
        INNER JOIN inserted i ON p.ID_Pelanggan = i.ID_Pelanggan
        INNER JOIN deleted d ON i.ID_Nota = d.ID_Nota
        WHERE i.Status_Pembayaran = 'Lunas'
          AND d.Status_Pembayaran <> 'Lunas'
          AND p.Status_Member = 'Member'
          AND p.Pel_is_deleted = 0;
        
        -- Log history
        INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Action)
        SELECT 
            'Pelanggan_Poin',
            i.ID_Pelanggan,
            'TAMBAH_POIN',
            (SELECT i2.ID_Pelanggan, i2.Grand_Total, FLOOR(i2.Grand_Total / 10000) AS Poin_Ditambah FROM inserted i2 WHERE i2.ID_Nota = i.ID_Nota FOR JSON PATH),
            SYSTEM_USER
        FROM inserted i
        INNER JOIN deleted d ON i.ID_Nota = d.ID_Nota
        WHERE i.Status_Pembayaran = 'Lunas'
          AND d.Status_Pembayaran <> 'Lunas';
    END
END;
GO

-- =============================================
-- TRIGGER: trg_Booking_ValidasiJadwal
-- Fungsi: Cek bentrok jadwal karyawan (terapis)
-- =============================================
CREATE OR ALTER TRIGGER trg_Booking_ValidasiJadwal
ON Booking
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    
    -- Cek apakah karyawan sudah punya booking di waktu yang sama
    IF EXISTS (
        SELECT 1
        FROM inserted i
        INNER JOIN Booking b ON i.ID_Karyawan = b.ID_Karyawan
            AND i.ID_Booking <> b.ID_Booking
            AND b.Status_Booking <> 'Dibatalkan'
        WHERE 
            -- Bentrok jika jadwal dalam rentang 2 jam
            ABS(DATEDIFF(MINUTE, i.Jadwal_Booking, b.Jadwal_Booking)) < 120
    )
    BEGIN
        RAISERROR('Jadwal karyawan bentrok dengan booking lain!', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
    
    -- Log history
    INSERT INTO Log_History (Nama_Tabel, ID_Record, Aksi, Data_Baru, User_Action)
    SELECT 
        'Booking_Validasi',
        i.ID_Booking,
        'VALIDASI_JADWAL',
        (SELECT ID_Booking, ID_Karyawan, Jadwal_Booking FROM inserted i2 WHERE i2.ID_Booking = i.ID_Booking FOR JSON PATH),
        SYSTEM_USER
    FROM inserted i;
END;
GO

