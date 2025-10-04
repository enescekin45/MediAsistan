<?php
// config/config.php

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Istanbul');

// Veritabanı bağlantı bilgileri
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'medi_asistan_db';

// MySQLi bağlantısı oluştur
try {
    $conn = new mysqli($db_host, $db_user, $db_password, $db_name);
    
    // Bağlantı hatası kontrolü
    if ($conn->connect_error) {
        throw new Exception("Veritabanı bağlantı hatası: " . $conn->connect_error);
    }
    
    // Karakter seti
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Oturum başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Güvenlik ayarları
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Sabitler
define('SITE_URL', 'http://localhost/mediasistan');
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/mediasistan/uploads/');
?>