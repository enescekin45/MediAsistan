<?php
session_start();

// Tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturumu yok et
session_destroy();

// Login sayfasına yönlendir
header("Location: login.php");
exit;
?>