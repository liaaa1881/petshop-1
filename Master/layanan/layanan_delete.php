<?php
session_start();
include '../../config/koneksi.php';

if ($_SESSION['role'] != 'Admin') { header("Location: ../../dashboard/index.php"); exit; }

$id = $_GET['id'];
$sql = "DELETE FROM layanan WHERE id_layanan = ?";
$stmt = sqlsrv_query($conn, $sql, array($id));

if ($stmt) {
    header("Location: layanan_read.php");
}
?>