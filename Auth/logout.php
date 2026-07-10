<?php
session_start();
session_unset();
session_destroy();

// Karena login.php ada di folder Auth bersama logout.php
header("Location: login.php"); 
exit();
?>

