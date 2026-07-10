<?php
session_start();
include '../Config/koneksi.php';

// Proteksi
if (
    !isset($_SESSION['reset_user']) ||
    !isset($_SESSION['reset_table'])
) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['update'])) {

    $p1 = trim($_POST['pass1']);
    $p2 = trim($_POST['pass2']);

    $user  = $_SESSION['reset_user'];
    $tabel = $_SESSION['reset_table'];

    // Cek password sama atau tidak
    if ($p1 != $p2) {
        header("Location: reset_password.php?pesan=tidak_cocok");
        exit();
    }

    // Validasi tabel yang boleh diupdate
    $allowed_tables = array(
        'Karyawan',
        'Pelanggan',
        'Supplier'
    );

    if (!in_array($tabel, $allowed_tables)) {
        header("Location: login.php");
        exit();
    }

    // Update password
    $sql = "UPDATE $tabel SET Password = ? WHERE Username = ?";
    $params = array($p1, $user);

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {

        // Hapus session reset
        unset($_SESSION['reset_user']);
        unset($_SESSION['reset_table']);

        echo "
        <script>
            alert('Password berhasil diubah!');
            window.location='login.php';
        </script>";
        exit();

    } else {

        die(print_r(sqlsrv_errors(), true));

    }

} else {

    header('Location: login.php');
    exit();

}
?>