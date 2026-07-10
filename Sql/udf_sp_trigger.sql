USE petshop;
GO

-- ==========================================================
-- 1. BAGIAN USER DEFINED FUNCTIONS (UDF)
-- Untuk membantu tampilan (Read) dan perhitungan otomatis
-- ==========================================================

-- A. Fungsi Inisial Nama (Menggantikan getInitials di PHP)
CREATE OR ALTER FUNCTION dbo.fn_GetInitials (@Nama VARCHAR(100))
RETURNS VARCHAR(10) AS
BEGIN
    DECLARE @Initials VARCHAR(10) = '';
    SET @Nama = LTRIM(RTRIM(@Nama));
    IF CHARINDEX(' ', @Nama) > 0
        SET @Initials = UPPER(LEFT(@Nama, 1) + SUBSTRING(@Nama, CHARINDEX(' ', @Nama) + 1, 1));
    ELSE
        SET @Initials = UPPER(LEFT(@Nama, 1));
    RETURN @Initials;
END;
GO

-- B. Fungsi Label Status Stok (Aman/Kritis)
CREATE OR ALTER FUNCTION dbo.fn_LabelStok (@Stok INT, @Min INT)
RETURNS VARCHAR(20) AS
BEGIN
    RETURN CASE WHEN @Stok <= @Min THEN '⚠️ KRITIS' ELSE '✅ AMAN' END;
END;
GO

-- C. Fungsi Hitung Margin Keuntungan
CREATE OR ALTER FUNCTION dbo.fn_HitungMargin (@Beli DECIMAL(15,2), @Jual DECIMAL(15,2))
RETURNS DECIMAL(15,2) AS
BEGIN
    RETURN @Jual - @Beli;
END;
GO


-- ==========================================================
-- 2. BAGIAN STORED PROCEDURES (SP) MASTER (6 MASTER)
-- Mengcover: CREATE, READ, UPDATE, DELETE, TOGGLE STATUS
-- ==========================================================

-- A. SP MASTER BARANG
CREATE OR ALTER PROCEDURE sp_ManageBarang
    @Mode VARCHAR(10), @ID INT = NULL, @Kat INT = NULL, @Kode VARCHAR(50) = NULL, @Nama VARCHAR(100) = NULL,
    @Beli DECIMAL(15,2) = 0, @Jual DECIMAL(15,2) = 0, @Stok INT = 0, @Min INT = 0, @Ket VARCHAR(255) = NULL, 
    @Satuan VARCHAR(20) = NULL, @Foto VARCHAR(255) = NULL, @User VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode IN ('ADD', 'EDIT') AND (@Beli < 0 OR @Jual < 0 OR @Stok < 0)
    BEGIN RAISERROR ('Nilai finansial/stok tidak boleh negatif!', 16, 1); RETURN; END

    IF @Mode = 'ADD'
        INSERT INTO Barang (ID_Kategori, Kode_Barang, Nama_Barang, Harga_Beli, Harga_Jual, Stok, Stok_Minimum, Deskripsi, Satuan, Foto_Barang, Bar_status, Bar_is_deleted, Bar_created_by)
        VALUES (@Kat, @Kode, @Nama, @Beli, @Jual, @Stok, @Min, @Ket, @Satuan, @Foto, 'Aktif', 0, @User);
    ELSE IF @Mode = 'EDIT'
        UPDATE Barang SET ID_Kategori=@Kat, Kode_Barang=@Kode, Nama_Barang=@Nama, Harga_Beli=@Beli, Harga_Jual=@Jual, Stok=@Stok, Stok_Minimum=@Min, Deskripsi=@Ket, Satuan=@Satuan, Foto_Barang=ISNULL(@Foto, Foto_Barang), Bar_modified_by=@User, Bar_modified_date=GETDATE() WHERE ID_Barang = @ID;
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Barang SET Bar_status = CASE WHEN Bar_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Barang = @ID;
    ELSE IF @Mode = 'DELETE'
        UPDATE Barang SET Bar_is_deleted = 1, Bar_deleted_by = @User, Bar_deleted_date = GETDATE() WHERE ID_Barang = @ID;
    ELSE IF @Mode = 'SELECT'
        SELECT *, dbo.fn_GetInitials(Nama_Barang) as Inisial, dbo.fn_LabelStok(Stok, Stok_Minimum) as StatusStok FROM Barang WHERE Bar_is_deleted = 0;
END;
GO

-- B. SP MASTER KARYAWAN
CREATE OR ALTER PROCEDURE sp_ManageKaryawan
    @Mode VARCHAR(10), @ID INT = NULL, @NIK VARCHAR(16) = NULL, @Nama VARCHAR(100) = NULL, @UserAcc VARCHAR(50) = NULL, 
    @Pass VARCHAR(255) = NULL, @Role VARCHAR(20) = NULL, @UserAdmin VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode = 'ADD' AND LEN(@NIK) <> 16 BEGIN RAISERROR ('NIK harus 16 digit!', 16, 1); RETURN; END
    
    IF @Mode = 'ADD'
        INSERT INTO Karyawan (NIK, Nama_Karyawan, Username, Password, Role, Kar_status, Kar_is_deleted, Kar_created_by)
        VALUES (@NIK, @Nama, @UserAcc, @Pass, @Role, 'Aktif', 0, @UserAdmin);
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Karyawan SET Kar_status = CASE WHEN Kar_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Karyawan = @ID;
    ELSE IF @Mode = 'SELECT'
        SELECT *, dbo.fn_GetInitials(Nama_Karyawan) as Inisial FROM Karyawan WHERE Kar_is_deleted = 0;
END;
GO

-- C. SP MASTER PELANGGAN
CREATE OR ALTER PROCEDURE sp_ManagePelanggan
    @Mode VARCHAR(10), @ID INT = NULL, @Nama VARCHAR(100) = NULL, @Telp VARCHAR(15) = NULL, @StatusMember VARCHAR(20) = NULL, @UserAdmin VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode = 'ADD'
        INSERT INTO Pelanggan (Nama_Pelanggan, No_Telepon, Status_Member, Pel_status, Pel_is_deleted, Pel_created_by)
        VALUES (@Nama, @Telp, @StatusMember, 'Aktif', 0, @UserAdmin);
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Pelanggan SET Pel_status = CASE WHEN Pel_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Pelanggan = @ID;
    ELSE IF @Mode = 'SELECT'
        SELECT *, dbo.fn_GetInitials(Nama_Pelanggan) as Inisial FROM Pelanggan WHERE Pel_is_deleted = 0;
END;
GO

-- D. SP MASTER KATEGORI
CREATE OR ALTER PROCEDURE sp_ManageKategori
    @Mode VARCHAR(10), @ID INT = NULL, @Nama VARCHAR(50) = NULL, @Tipe VARCHAR(20) = NULL, @User VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode = 'ADD'
        INSERT INTO Kategori (Nama_Kategori, Tipe_Kategori, Kat_status, Kat_is_deleted, Kat_created_by)
        VALUES (@Nama, @Tipe, 'Aktif', 0, @User);
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Kategori SET Kat_status = CASE WHEN Kat_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Kategori = @ID;
END;
GO

-- E. SP MASTER LAYANAN
CREATE OR ALTER PROCEDURE sp_ManageLayanan
    @Mode VARCHAR(10), @ID INT = NULL, @Kat INT = NULL, @Nama VARCHAR(100) = NULL, @Harga DECIMAL(15,2) = 0, @User VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode = 'ADD'
        INSERT INTO Layanan (ID_Kategori, Kode_Layanan, Nama_Layanan, Harga_Layanan, Lay_status, Lay_created_by)
        VALUES (@Kat, 'LYN-'+LEFT(CAST(NEWID() AS VARCHAR(36)),5), @Nama, @Harga, 'Aktif', @User);
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Layanan SET Lay_status = CASE WHEN Lay_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Layanan = @ID;
END;
GO

-- F. SP MASTER SUPPLIER
CREATE OR ALTER PROCEDURE sp_ManageSupplier
    @Mode VARCHAR(10), @ID INT = NULL, @Nama VARCHAR(100) = NULL, @Telp VARCHAR(15) = NULL, @UserAdmin VARCHAR(50) = NULL
AS
BEGIN
    IF @Mode = 'ADD'
        INSERT INTO Supplier (Nama_Supplier, No_Telepon, Sup_status, Sup_is_deleted, Sup_created_by)
        VALUES (@Nama, @Telp, 'Aktif', 0, @UserAdmin);
    ELSE IF @Mode = 'TOGGLE'
        UPDATE Supplier SET Sup_status = CASE WHEN Sup_status = 'Aktif' THEN 'Non-Aktif' ELSE 'Aktif' END WHERE ID_Supplier = @ID;
END;
GO


-- ==========================================================
-- 3. BAGIAN TRIGGER TRANSAKSI (OTOMATISASI PROBIS)
-- Mengcover: Stok, Poin, Status Booking, & Validasi Jadwal
-- ==========================================================

-- A. Trigger Potong Stok Saat Penjualan Barang
CREATE OR ALTER TRIGGER trg_AutoPotongStok
ON Detail_Penjualan
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE B SET B.Stok = B.Stok - I.Jumlah
    FROM Barang B INNER JOIN inserted I ON B.ID_Barang = I.ID_Barang;
END;
GO

-- B. Trigger Tambah Stok Saat Pengadaan Logistik Diterima
CREATE OR ALTER TRIGGER trg_AutoTambahStokGudang
ON Stok_Masuk
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE(Status)
    BEGIN
        UPDATE B SET B.Stok = B.Stok + DSM.Jumlah_Masuk
        FROM Barang B
        INNER JOIN Detail_Stok_Masuk DSM ON B.ID_Barang = DSM.ID_Barang
        INNER JOIN inserted I ON DSM.ID_Stok = I.ID_Stok
        INNER JOIN deleted D ON I.ID_Stok = D.ID_Stok
        WHERE I.Status = 'Diterima' AND D.Status = 'Pending';
    END
END;
GO

-- C. Trigger Ubah Status Booking Jasa Jauh Otomatis Jauh Nota Dibuat
CREATE OR ALTER TRIGGER trg_LinkBookingKeNota
ON Penjualan
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE BK SET BK.Status_Booking = 'Selesai'
    FROM Booking BK INNER JOIN inserted I ON BK.ID_Booking = I.ID_Booking
    WHERE I.ID_Booking IS NOT NULL;
END;
GO

-- D. Trigger Validasi Bentrok Jadwal Booking (Pencegahan Ganda)
CREATE OR ALTER TRIGGER trg_CekJadwalBentrok
ON Booking
AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (
        SELECT 1 FROM Booking BK INNER JOIN inserted I ON BK.ID_Karyawan = I.ID_Karyawan 
        AND BK.Jadwal_Booking = I.Jadwal_Booking AND BK.ID_Booking <> I.ID_Booking
        WHERE BK.Status_Booking IN ('Pending', 'Diproses')
    )
    BEGIN
        RAISERROR ('Petugas sudah memiliki jadwal aktif di jam tersebut!', 16, 1);
        ROLLBACK TRANSACTION;
    END
END;
GO

-- E. Trigger Reward Poin Member (Setiap Lunas)
CREATE OR ALTER TRIGGER trg_HadiahPoinMember
ON Penjualan
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    IF UPDATE(Status_Pembayaran)
    BEGIN
        UPDATE PL SET PL.Poin_Member = PL.Poin_Member + (I.Grand_Total / 10000)
        FROM Pelanggan PL INNER JOIN inserted I ON PL.ID_Pelanggan = I.ID_Pelanggan
        WHERE I.Status_Pembayaran = 'Lunas' AND PL.Status_Member = 'Member';
    END
END;
GO