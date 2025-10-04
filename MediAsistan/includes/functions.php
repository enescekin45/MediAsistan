<?php
/**
 * MediAsistan - Fonksiyonlar
 */

// Yönlendirme fonksiyonu
function redirect($url) {
    header("Location: $url");
    exit;
}

// Güvenli input temizleme
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Alert mesajı gösterme
function display_alert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Alert mesajını render etme
function show_alert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        $html = '<div class="alert alert-'.$alert['type'].' alert-dismissible fade show" role="alert">';
        $html .= $alert['message'];
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
        unset($_SESSION['alert']);
        return $html;
    }
    return '';
}

// Tarih formatı
function format_date($date, $format = 'd.m.Y') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return '-';
    return date($format, strtotime($date));
}

// Telefon formatı
function format_phone($phone) {
    if (empty($phone)) return '-';
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($clean_phone) == 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{2})(\d{2})/', '$1 $2 $3 $4', $clean_phone);
    } elseif (strlen($clean_phone) == 11) {
        return preg_replace('/(\d{1})(\d{3})(\d{3})(\d{2})(\d{2})/', '$1 $2 $3 $4 $5', $clean_phone);
    } else {
        return $phone;
    }
}

// SQL sorgusu çalıştırma (güvenli versiyon)
function execute_query($conn, $sql, $params = [], $types = "") {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Prepare Hatası: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("SQL Execute Hatası: " . $stmt->error);
        return false;
    }
    
    return $stmt;
}

// Kullanıcı bilgilerini güvenli şekilde getir
function get_user_data($conn, $user_id) {
    $sql = "SELECT * FROM kullanicilar WHERE kullanici_id = ?";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    
    if ($stmt) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    return null;
}

// Şifre kontrolü
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Şifre hash'leme
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Dosya boyutunu formatla
function format_file_size($bytes) {
    if ($bytes == 0) return "0 B";
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = 1024;
    $class = min((int)log($bytes, $base), count($units) - 1);
    
    return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $units[$class];
}

// E-posta validasyonu
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// CSRF token oluşturma
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token kontrolü
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Hata ayıklama için SQL hatalarını göster
function debug_sql_error($conn, $sql, $params = []) {
    error_log("SQL Hatası: " . $conn->error);
    error_log("SQL Sorgusu: " . $sql);
    error_log("Parametreler: " . print_r($params, true));
    return "Bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
}

// Kullanıcı rolünü kontrol et
function check_user_role($required_role) {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role) {
        return false;
    }
    return true;
}

// Admin mi kontrol et
function is_admin() {
    return check_user_role('admin');
}

// Kullanıcı giriş yapmış mı kontrol et
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Güvenli çıkış
function safe_logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}
?>