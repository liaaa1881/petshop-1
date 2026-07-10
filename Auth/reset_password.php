<?php 
// Tambahkan di paling atas file reset_password.php
$error_class = "";
$shake_class = "";
if(isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'){
    $error_class = "error-input";
    $shake_class = "shake-card";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Petshop Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SWEETALERT2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(44, 62, 80, 0.7), rgba(44, 62, 80, 0.7)), 
                        url('https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=1920&q=80');
            background-size: cover; 
            background-position: center; 
            height: 100vh;
            display: flex; 
            align-items: center; 
            justify-content: center;
            overflow: hidden;
        }

        /* Tata Letak & Animasi Masuk Kartu */
        .reset-card {
            width: 450px; 
            background: white; 
            border-radius: 25px; 
            padding: 50px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3); 
            text-align: center;
            animation: cardEntry 0.6s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
            transition: transform 0.3s ease;
        }

        @keyframes cardEntry {
            from { opacity: 0; transform: translateY(35px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Animasi Getar untuk Input Salah */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            15%, 45%, 75% { transform: translateX(-8px); }
            30%, 60%, 90% { transform: translateX(8px); }
        }

        .shake-field {
            animation: shake 0.4s ease-in-out;
        }

        .shake-card {
            animation: shake 0.5s ease-in-out;
        }

        .icon-circle {
            width: 80px; height: 80px; background: #eef2f7; color: #2c3e50;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 25px; font-size: 35px;
            transition: all 0.4s ease;
        }

        .reset-card:hover .icon-circle {
            background: #2c3e50;
            color: #0dcaf0;
            transform: scale(1.08) rotate(15deg);
        }

        /* Efek Input-Group yang Ditingkatkan */
        .input-group {
            border-radius: 12px;
            overflow: hidden;
            border: 1.5px solid #e1e8ed;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .input-group:focus-within {
            border-color: #2c3e50;
            box-shadow: 0 4px 15px rgba(44, 62, 80, 0.15) !important;
            transform: translateY(-2px);
        }

        .input-group-text {
            background: #f8f9fa;
            border: none;
            transition: color 0.3s ease;
        }

        .input-group:focus-within .input-group-text {
            color: #2c3e50 !important;
        }

        .form-control {
            border: none; 
            background: #f8f9fa;
            padding: 12px 18px;
            font-size: 14px;
        }

        .form-control:focus {
            box-shadow: none;
            background: white;
        }

        /* Efek Validasi Real-Time */
        .input-group.field-valid {
            border-color: #16a34a !important;
        }
        .input-group.field-valid .input-group-text {
            color: #16a34a !important;
        }

        .input-group.field-invalid {
            border-color: #dc2626 !important;
        }
        .input-group.field-invalid .input-group-text {
            color: #dc2626 !important;
        }

        /* Warna khusus Salmon saat dikirim kembali dari proses database */
        .error-input {
            background-color: #e67e5d !important; 
            color: white !important;
        }
        .error-input::placeholder { 
            color: rgba(255,255,255,0.8); 
        }
        .input-group:has(.error-input) {
            border-color: #c0392b !important;
        }

        /* Tombol & Transisi Link */
        .btn-verify {
            background: #2c3e50; color: white; border-radius: 12px; padding: 14px;
            font-weight: 700; border: none; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        .btn-verify:hover { 
            background: #1a252f; 
            transform: translateY(-3px); 
            color: #0dcaf0; 
            box-shadow: 0 5px 15px rgba(26, 37, 47, 0.3);
        }

        .btn-verify:active {
            transform: translateY(0);
        }

        .link-back {
            transition: all 0.3s ease;
            display: inline-block;
        }

        .link-back:hover {
            color: #2c3e50 !important;
            transform: translateX(-3px);
        }
    </style>
</head>
<body>
    <div class="reset-card <?php echo $shake_class; ?>">
        <div class="icon-circle shadow-sm">
            <i class="fas fa-key"></i>
        </div>
        <h3 class="fw-bold text-dark mb-1">Reset Kata Sandi?</h3>
        <p class="text-muted small mb-4">Jangan khawatir! Masukkan detail akun Anda untuk verifikasi identitas sebelum mengatur ulang sandi.</p>

        <form action="proses_lupa_password.php" method="POST" onsubmit="return verifikasiResetPassword(event)">
            <!-- Username -->
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold text-secondary">Nama Pengguna</label>
                <div class="input-group shadow-sm" id="username-group">
                    <span class="input-group-text border-0"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" id="username" name="username" class="form-control <?php echo $error_class; ?>" placeholder="Masukkan Nama Pengguna" required>
                </div>
            </div>

            <!-- Kontak / No HP -->
            <div class="mb-4 text-start">
                <label class="form-label small fw-bold text-secondary">Nomor HP Terdaftar</label>
                <div class="input-group shadow-sm" id="kontak-group">
                    <span class="input-group-text border-0"><i class="fas fa-phone text-muted"></i></span>
                    <input type="text" id="kontak" name="kontak" class="form-control <?php echo $error_class; ?>" placeholder="Contoh: 0812xxxxxxx" required>
                </div>
            </div>

            <button type="submit" name="verifikasi" class="btn btn-verify w-100 mb-3">VERIFIKASI SEKARANG</button>
            <a href="login.php" class="text-decoration-none text-muted small link-back"><i class="fas fa-arrow-left me-1"></i> Kembali ke Login</a>
        </form>
    </div>

    <!-- NOTIFIKASI SWEETALERT JIKA SERVER MENOLAK VERIFIKASI -->
    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Verifikasi Gagal',
                text: 'Nama Pengguna atau Nomor HP tidak cocok dengan data yang terdaftar pada sistem!',
                confirmButtonColor: '#2c3e50'
            });
        });
    </script>
    <?php endif; ?>

    <script>
        // ================== VALIDASI CLIENT-SIDE & EFEK SHAKE ==================
        const usernameRegex = /^[a-zA-Z0-9]{5,}$/;
        const telpRegex     = /^(\+628|\+638|08)\d{8,12}$/; // Format ID/PH & Lokal

        const usernameInput = document.getElementById('username');
        const usernameGroup = document.getElementById('username-group');
        const kontakInput   = document.getElementById('kontak');
        const kontakGroup   = document.getElementById('kontak-group');

        function setFieldStatus(groupEl, isValid) {
            // Bersihkan kelas error bawaan PHP jika pengguna mulai mengoreksi input
            const inputField = groupEl.querySelector('input');
            inputField.classList.remove('error-input');

            if (isValid) {
                groupEl.classList.remove('field-invalid');
                groupEl.classList.add('field-valid');
            } else {
                groupEl.classList.remove('field-valid');
                groupEl.classList.add('field-invalid');
            }
        }

        function clearFieldStatus(groupEl) {
            groupEl.classList.remove('field-invalid', 'field-valid');
            groupEl.querySelector('input').classList.remove('error-input');
        }

        // Real-Time Validation Username
        usernameInput.addEventListener('input', function() {
            const val = this.value.trim();
            if (val.length === 0) {
                clearFieldStatus(usernameGroup);
            } else {
                setFieldStatus(usernameGroup, usernameRegex.test(val));
            }
        });

        // Real-Time Validation Nomor HP (Hanya Angka dan Simbol + di awal)
        kontakInput.addEventListener('input', function() {
            // Hilangkan karakter selain angka dan "+"
            this.value = this.value.replace(/[^\d+]/g, '');
            const val = this.value.trim();
            if (val.length === 0) {
                clearFieldStatus(kontakGroup);
            } else {
                setFieldStatus(kontakGroup, telpRegex.test(val));
            }
        });

        // Trigger Efek Getar Visual pada Input Group
        function shakeInputGroup(groupEl) {
            groupEl.classList.add('shake-field');
            setTimeout(() => {
                groupEl.classList.remove('shake-field');
            }, 400);
        }

        // Validasi Akhir saat Submit Form
        function verifikasiResetPassword(event) {
            const username = usernameInput.value.trim();
            const kontak = kontakInput.value.trim();

            if (!usernameRegex.test(username)) {
                event.preventDefault();
                shakeInputGroup(usernameGroup);
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Nama Pengguna harus minimal 5 karakter tanpa spasi atau karakter spesial.',
                    confirmButtonColor: '#2c3e50'
                });
                return false;
            }

            if (!telpRegex.test(kontak)) {
                event.preventDefault();
                shakeInputGroup(kontakGroup);
                Swal.fire({
                    icon: 'error',
                    title: 'Validasi Gagal',
                    text: 'Nomor HP tidak valid. Gunakan format yang benar (contoh: 0812xxxxxxxx atau +62812xxxxxxxx).',
                    confirmButtonColor: '#2c3e50'
                });
                return false;
            }

            return true;
        }
    </script>
</body>
</html>