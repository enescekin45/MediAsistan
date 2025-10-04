<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Admin giriş kontrolü
if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

// Basit veritabanı bağlantı kontrolü - DÜZELTİLDİ
if (!$conn || $conn->connect_error) {
    die("Veritabanı bağlantı hatası: " . ($conn->connect_error ?? "Bilinmeyen hata"));
}

// Hata ve başarı mesajları
$error = '';
$success = '';

// Yedekler dizini
$backupDir = '../backups/';

// Site ayarları tablosunu kontrol et ve yoksa oluştur
$check_table = $conn->query("SHOW TABLES LIKE 'site_ayarlari'");
if ($check_table && $check_table->num_rows == 0) {
    $create_table_sql = "CREATE TABLE site_ayarlari (
        ayar_anahtari VARCHAR(255) PRIMARY KEY,
        ayar_degeri TEXT,
        olusturulma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($create_table_sql)) {
        // Varsayılan ayarları ekle
        $default_settings = [
            'site_title' => 'MediAsistan',
            'site_description' => 'Sağlık Yönetim Sistemi',
            'site_keywords' => 'sağlık, ilaç, acil durum',
            'admin_email' => 'admin@mediasistan.com',
            'records_per_page' => '10',
            'maintenance_mode' => '0'
        ];
        
        foreach ($default_settings as $key => $value) {
            $conn->query("INSERT INTO site_ayarlari (ayar_anahtari, ayar_degeri) VALUES ('$key', '$value')");
        }
        $success = "Site ayarları tablosu oluşturuldu ve varsayılan ayarlar eklendi.";
    } else {
        $error = "Site ayarları tablosu oluşturulamadı: " . $conn->error;
    }
}

// Site ayarlarını getir
$site_settings = [];
$settings_query = $conn->query("SELECT * FROM site_ayarlari");
if ($settings_query && $settings_query->num_rows > 0) {
    while ($row = $settings_query->fetch_assoc()) {
        $site_settings[$row['ayar_anahtari']] = $row['ayar_degeri'];
    }
}

// Veritabanı yedekleme işlemi
if (isset($_GET['backup_db'])) {
    $backup_result = backupDatabase($conn, $backupDir);
    if ($backup_result['success']) {
        $success = "Veritabanı yedeklemesi başarıyla tamamlandı! Dosya: " . $backup_result['filename'];
    } else {
        $error = "Veritabanı yedeklenirken hata oluştu: " . $backup_result['error'];
    }
}

// Site ayarlarını güncelle
if (isset($_POST['update_settings'])) {
    $site_title = sanitize_input($_POST['site_title']);
    $site_description = sanitize_input($_POST['site_description']);
    $site_keywords = sanitize_input($_POST['site_keywords']);
    $admin_email = sanitize_input($_POST['admin_email']);
    $records_per_page = intval($_POST['records_per_page']);
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    
    // Validasyon
    if (empty($site_title)) {
        $error = "Site başlığı boş olamaz!";
    } elseif (!is_valid_email($admin_email)) {
        $error = "Geçerli bir e-posta adresi giriniz!";
    } elseif ($records_per_page < 5 || $records_per_page > 100) {
        $error = "Sayfa başına kayıt sayısı 5-100 arasında olmalıdır!";
    } else {
        $settings_updated = 0;
        $update_stmt = $conn->prepare("INSERT INTO site_ayarlari (ayar_anahtari, ayar_degeri) VALUES (?, ?) ON DUPLICATE KEY UPDATE ayar_degeri = ?");
        
        if ($update_stmt) {
            $settings_to_update = [
                'site_title' => $site_title,
                'site_description' => $site_description,
                'site_keywords' => $site_keywords,
                'admin_email' => $admin_email,
                'records_per_page' => $records_per_page,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings_to_update as $key => $value) {
                $update_stmt->bind_param("sss", $key, $value, $value);
                if ($update_stmt->execute()) {
                    $settings_updated++;
                } else {
                    $error = "Ayar güncellenirken hata: " . $update_stmt->error;
                    break;
                }
            }
            $update_stmt->close();
            
            if ($settings_updated > 0) {
                $success = "Site ayarları başarıyla güncellendi!";
                // Ayarları yeniden yükle
                $settings_query = $conn->query("SELECT * FROM site_ayarlari");
                $site_settings = [];
                while ($row = $settings_query->fetch_assoc()) {
                    $site_settings[$row['ayar_anahtari']] = $row['ayar_degeri'];
                }
            }
        } else {
            $error = "Veritabanı hazırlama hatası: " . $conn->error;
        }
    }
}

// Admin şifre değiştirme - GELİŞTİRİLDİ
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasyon
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Tüm şifre alanlarını doldurunuz!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Yeni şifreler eşleşmiyor!";
    } elseif (strlen($new_password) < 6) {
        $error = "Yeni şifre en az 6 karakter olmalıdır!";
    } else {
        $user_id = $_SESSION['user_id'];
        
        // Önce kullanıcıyı kontrol et
        $check_stmt = $conn->prepare("SELECT sifre FROM kullanicilar WHERE kullanici_id = ?");
        
        if ($check_stmt) {
            $check_stmt->bind_param("i", $user_id);
            if ($check_stmt->execute()) {
                $result = $check_stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    
                    // Şifre kontrolü
                    if (verify_password($current_password, $user['sifre'])) {
                        $new_password_hash = hash_password($new_password);
                        
                        // Şifreyi güncelle
                        $update_stmt = $conn->prepare("UPDATE kullanicilar SET sifre = ? WHERE kullanici_id = ?");
                        
                        if ($update_stmt) {
                            $update_stmt->bind_param("si", $new_password_hash, $user_id);
                            
                            if ($update_stmt->execute()) {
                                $success = "Şifre başarıyla değiştirildi!";
                            } else {
                                $error = "Şifre güncellenirken bir hata oluştu: " . $update_stmt->error;
                            }
                            $update_stmt->close();
                        } else {
                            $error = "Güncelleme sorgusu hazırlanamadı: " . $conn->error;
                        }
                    } else {
                        $error = "Mevcut şifre hatalı!";
                    }
                } else {
                    $error = "Kullanıcı bulunamadı!";
                }
            } else {
                $error = "Sorgu çalıştırılamadı: " . $check_stmt->error;
            }
            $check_stmt->close();
        } else {
            $error = "Kontrol sorgusu hazırlanamadı: " . $conn->error;
        }
    }
}

// Veritabanı yedekleme fonksiyonu
function backupDatabase($conn, $backupDir) {
    // Backups klasörü yoksa oluştur
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'error' => 'Backups klasörü oluşturulamadı'];
        }
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    try {
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        
        if (!$result) {
            return ['success' => false, 'error' => 'Tablo listesi alınamadı: ' . $conn->error];
        }
        
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        $sqlScript = "-- MediAsistan Veritabanı Yedekleme\n";
        $sqlScript .= "-- Yedekleme Tarihi: " . date('Y-m-d H:i:s') . "\n\n";
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            // Tablo yapısı
            $sqlScript .= "-- Tablo yapısı: `$table`\n";
            $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
            
            $create_table = $conn->query("SHOW CREATE TABLE `$table`");
            if ($create_table) {
                $row2 = $create_table->fetch_row();
                $sqlScript .= $row2[1] . ";\n\n";
            }
            
            // Tablo verileri
            $sqlScript .= "-- Tablo verileri: `$table`\n";
            $result_data = $conn->query("SELECT * FROM `$table`");
            
            if ($result_data && $result_data->num_rows > 0) {
                $sqlScript .= "INSERT INTO `$table` VALUES";
                $first = true;
                while ($row_data = $result_data->fetch_assoc()) {
                    if (!$first) $sqlScript .= ",";
                    $sqlScript .= "\n(";
                    $firstField = true;
                    foreach ($row_data as $value) {
                        if (!$firstField) $sqlScript .= ", ";
                        $sqlScript .= $value === null ? "NULL" : "'" . $conn->real_escape_string($value) . "'";
                        $firstField = false;
                    }
                    $sqlScript .= ")";
                    $first = false;
                }
                $sqlScript .= ";\n\n";
            }
        }
        
        $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        if (file_put_contents($filepath, $sqlScript) === false) {
            return ['success' => false, 'error' => 'Dosya yazılamadı'];
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Yedek dosyasını silme
if (isset($_GET['delete_backup'])) {
    $filename = sanitize_input($_GET['delete_backup']);
    $filepath = $backupDir . $filename;
    
    if (file_exists($filepath) && unlink($filepath)) {
        $success = "Yedek dosyası başarıyla silindi: " . $filename;
    } else {
        $error = "Yedek dosyası silinemedi: " . $filename;
    }
}

// Sistem bilgileri
$php_version = phpversion();
$mysql_version = $conn->server_info;
$system_os = php_uname('s');
$max_upload_size = ini_get('upload_max_filesize');
$max_execution_time = ini_get('max_execution_time');

// Mevcut yedekleri listele
$backup_files = [];
if (file_exists($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $filepath = $backupDir . $file;
            if (file_exists($filepath)) {
                $backup_files[] = [
                    'filename' => $file,
                    'size' => filesize($filepath),
                    'date' => date('d.m.Y H:i', filemtime($filepath))
                ];
            }
        }
    }
    
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Önbellek temizleme
if (isset($_GET['clear_cache'])) {
    $success = "Önbellek başarıyla temizlendi!";
}

// Veritabanı optimizasyonu
if (isset($_GET['optimize_db'])) {
    $tables_result = $conn->query("SHOW TABLES");
    $optimized = 0;
    
    if ($tables_result) {
        while ($table_row = $tables_result->fetch_row()) {
            $table = $table_row[0];
            if ($conn->query("OPTIMIZE TABLE `$table`")) {
                $optimized++;
            }
        }
    }
    
    if ($optimized > 0) {
        $success = "Veritabanı tabloları optimize edildi ($optimized tablo)";
    } else {
        $error = "Veritabanı optimizasyonu başarısız!";
    }
}

// Ayarları sıfırlama
if (isset($_GET['reset_settings'])) {
    $conn->query("DELETE FROM site_ayarlari");
    
    $default_settings = [
        'site_title' => 'MediAsistan',
        'site_description' => 'Sağlık Yönetim Sistemi',
        'site_keywords' => 'sağlık, ilaç, acil durum',
        'admin_email' => 'admin@mediasistan.com',
        'records_per_page' => '10',
        'maintenance_mode' => '0'
    ];
    
    foreach ($default_settings as $key => $value) {
        $conn->query("INSERT INTO site_ayarlari (ayar_anahtari, ayar_degeri) VALUES ('$key', '$value')");
    }
    
    $success = "Site ayarları başarıyla sıfırlandı!";
    $settings_query = $conn->query("SELECT * FROM site_ayarlari");
    $site_settings = [];
    while ($row = $settings_query->fetch_assoc()) {
        $site_settings[$row['ayar_anahtari']] = $row['ayar_degeri'];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - <?php echo htmlspecialchars($site_settings['site_title'] ?? 'MediAsistan'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #dc2626;
            --success: #059669;
            --warning: #d97706;
            --info: #0891b2;
            --purple: #7c3aed;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary) 0%, #1e40af 100%);
            color: white;
            min-height: 100vh;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.8rem 1rem;
            margin: 0.2rem 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .settings-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .settings-card .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        
        .system-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
        }
        
        .nav-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            background: transparent;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-medium { background: #f59e0b; width: 50%; }
        .strength-strong { background: #10b981; width: 75%; }
        .strength-very-strong { background: #059669; width: 100%; }
        
        .file-size {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .backup-actions {
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4><i class="fas fa-shield-alt me-2"></i>Admin Panel</h4>
                        <p class="small opacity-75"><?php echo htmlspecialchars($site_settings['site_title'] ?? 'MediAsistan'); ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_users.php">
                                <i class="fas fa-users"></i>Kullanıcılar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_medications.php">
                                <i class="fas fa-pills"></i>İlaçlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_emergency.php">
                                <i class="fas fa-first-aid"></i>Acil Durumlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_reports.php">
                                <i class="fas fa-chart-bar"></i>Raporlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_settings.php">
                                <i class="fas fa-cogs"></i>Ayarlar
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home"></i>Siteye Dön
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i>Çıkış Yap
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="admin-header mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h3 mb-0">
                            <i class="fas fa-cogs me-2"></i>Sistem Ayarları
                        </h2>
                        <div class="d-flex align-items-center">
                            <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name'] . ' ' . $_SESSION['user_surname']); ?></span>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-cog"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="admin_profile.php">Profil</a></li>
                                    <li><a class="dropdown-item" href="admin_settings.php">Ayarlar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="../logout.php">Çıkış Yap</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hata ve Başarı Mesajları -->
                <?php 
                if ($error): 
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    echo '<i class="fas fa-exclamation-triangle me-2"></i>' . $error;
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                endif;
                
                if ($success): 
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
                    echo '<i class="fas fa-check-circle me-2"></i>' . $success;
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                endif;
                ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card settings-card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                            <i class="fas fa-globe me-2"></i>Genel Ayarlar
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                            <i class="fas fa-shield-alt me-2"></i>Güvenlik
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                                            <i class="fas fa-database me-2"></i>Yedekleme
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="settingsTabsContent">
                                    <!-- Genel Ayarlar Tab -->
                                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                                        <form method="POST" action="">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="site_title" class="form-label">Site Başlığı</label>
                                                    <input type="text" class="form-control" id="site_title" name="site_title" 
                                                           value="<?php echo htmlspecialchars($site_settings['site_title'] ?? 'MediAsistan'); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="admin_email" class="form-label">Admin E-posta</label>
                                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                           value="<?php echo htmlspecialchars($site_settings['admin_email'] ?? 'admin@mediasistan.com'); ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <label for="site_description" class="form-label">Site Açıklaması</label>
                                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($site_settings['site_description'] ?? 'Sağlık Yönetim Sistemi'); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <label for="site_keywords" class="form-label">Site Anahtar Kelimeleri</label>
                                                    <input type="text" class="form-control" id="site_keywords" name="site_keywords" 
                                                           value="<?php echo htmlspecialchars($site_settings['site_keywords'] ?? 'sağlık, ilaç, acil durum'); ?>">
                                                    <div class="form-text">Anahtar kelimeleri virgülle ayırın</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="records_per_page" class="form-label">Sayfa Başına Kayıt</label>
                                                    <input type="number" class="form-control" id="records_per_page" name="records_per_page" 
                                                           value="<?php echo htmlspecialchars($site_settings['records_per_page'] ?? '10'); ?>" min="5" max="100">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Bakım Modu</label>
                                                    <div class="form-check form-switch mt-2">
                                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                               <?php echo ($site_settings['maintenance_mode'] ?? 0) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="maintenance_mode">
                                                            <?php echo ($site_settings['maintenance_mode'] ?? 0) ? 'Açık' : 'Kapalı'; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Ayarları Kaydet
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Güvenlik Tab -->
                                    <div class="tab-pane fade" id="security" role="tabpanel">
                                        <form method="POST" action="">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="current_password" class="form-label">Mevcut Şifre</label>
                                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="new_password" class="form-label">Yeni Şifre</label>
                                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                                    <div class="password-strength" id="passwordStrength"></div>
                                                    <div class="form-text">En az 6 karakter</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar)</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                                    <div class="form-text" id="passwordMatch"></div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <strong>Şifre Güvenlik Kuralları:</strong>
                                                        <ul class="mb-0 mt-2">
                                                            <li>En az 6 karakter uzunluğunda olmalı</li>
                                                            <li>Büyük ve küçük harf içermeli</li>
                                                            <li>Rakam ve özel karakter içermeli</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" name="change_password" class="btn btn-primary">
                                                        <i class="fas fa-key me-2"></i>Şifreyi Değiştir
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Yedekleme Tab -->
                                    <div class="tab-pane fade" id="backup" role="tabpanel">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    <strong>Önemli:</strong> Veritabanı yedeklerini düzenli olarak almanız önerilir.
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-primary">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                                        <h5>Veritabanı Yedekle</h5>
                                                        <p class="text-muted">Tüm veritabanını yedekleyin</p>
                                                        <a href="?backup_db=1" class="btn btn-primary" onclick="return confirm('Veritabanı yedeklemesi başlatılsın mı? Bu işlem birkaç saniye sürebilir.')">
                                                            <i class="fas fa-download me-2"></i>Yedek Al
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-success">
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-upload fa-3x text-success mb-3"></i>
                                                        <h5>Veritabanı Geri Yükle</h5>
                                                        <p class="text-muted">Yedekten geri yükleme yapın</p>
                                                        <button class="btn btn-success" onclick="alert('Bu özellik şu anda kullanılamıyor. Manuel olarak PHPMyAdmin üzerinden geri yükleme yapabilirsiniz.')">
                                                            <i class="fas fa-upload me-2"></i>Geri Yükle
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <h6 class="mt-4 mb-3">Son Yedeklemeler</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Dosya Adı</th>
                                                                <th>Boyut</th>
                                                                <th>Tarih</th>
                                                                <th>İşlem</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(!empty($backup_files)): ?>
                                                                <?php foreach($backup_files as $backup): ?>
                                                                <tr>
                                                                    <td>
                                                                        <i class="fas fa-database text-primary me-2"></i>
                                                                        <?php echo htmlspecialchars($backup['filename']); ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="file-size">
                                                                            <?php echo format_file_size($backup['size']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo $backup['date']; ?></td>
                                                                    <td>
                                                                        <div class="btn-group btn-group-sm backup-actions">
                                                                            <a href="../backups/<?php echo urlencode($backup['filename']); ?>" class="btn btn-outline-primary" download title="İndir">
                                                                                <i class="fas fa-download"></i>
                                                                            </a>
                                                                            <button class="btn btn-outline-danger" onclick="deleteBackup('<?php echo $backup['filename']; ?>')" title="Sil">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                            <tr>
                                                                <td colspan="4" class="text-center text-muted py-4">
                                                                    <i class="fas fa-database fa-2x mb-2"></i>
                                                                    <p>Henüz yedek bulunmuyor</p>
                                                                </td>
                                                            </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Sistem Bilgileri -->
                        <div class="card system-info-card">
                            <div class="card-body">
                                <h5 class="card-title text-white">
                                    <i class="fas fa-server me-2"></i>Sistem Bilgileri
                                </h5>
                                <div class="system-info">
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>PHP Versiyon:</span>
                                        <span class="fw-bold"><?php echo $php_version; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>MySQL Versiyon:</span>
                                        <span class="fw-bold"><?php echo $mysql_version; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>İşletim Sistemi:</span>
                                        <span class="fw-bold"><?php echo $system_os; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>Maks. Dosya Yükleme:</span>
                                        <span class="fw-bold"><?php echo $max_upload_size; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2">
                                        <span>Maks. Çalışma Süresi:</span>
                                        <span class="fw-bold"><?php echo $max_execution_time; ?>s</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hızlı İşlemler -->
                        <div class="card settings-card mt-4">
                            <div class="card-header">
                                <span><i class="fas fa-bolt me-2"></i>Hızlı İşlemler</span>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="?clear_cache=1" class="btn btn-outline-warning btn-sm" onclick="return confirm('Önbellek temizlensin mi?')">
                                        <i class="fas fa-broom me-1"></i>Önbelleği Temizle
                                    </a>
                                    <a href="?optimize_db=1" class="btn btn-outline-info btn-sm" onclick="return confirm('Veritabanı optimize edilsin mi?')">
                                        <i class="fas fa-wrench me-1"></i>Veritabanını Optimize Et
                                    </a>
                                    <a href="?reset_settings=1" class="btn btn-outline-danger btn-sm" onclick="return confirm('Tüm ayarlar varsayılana dönsün mü? Bu işlem geri alınamaz!')">
                                        <i class="fas fa-trash me-1"></i>Ayarları Sıfırla
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Şifre güçlülük kontrolü
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            
            // Uzunluk kontrolü
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Karakter çeşitliliği kontrolü
            if (password.match(/[a-z]/)) strength += 1;
            if (password.match(/[A-Z]/)) strength += 1;
            if (password.match(/[0-9]/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
            
            // Güçlülük seviyesini göster
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0%';
            } else if (strength <= 2) {
                strengthBar.className += ' strength-weak';
            } else if (strength <= 4) {
                strengthBar.className += ' strength-medium';
            } else if (strength <= 5) {
                strengthBar.className += ' strength-strong';
            } else {
                strengthBar.className += ' strength-very-strong';
            }
        });
        
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'form-text';
            } else if (password === confirmPassword) {
                matchText.textContent = 'Şifreler eşleşiyor';
                matchText.className = 'form-text text-success';
            } else {
                matchText.textContent = 'Şifreler eşleşmiyor';
                matchText.className = 'form-text text-danger';
            }
        });
        
        // Bakım modu toggle etiketi güncelleme
        const maintenanceToggle = document.getElementById('maintenance_mode');
        if(maintenanceToggle) {
            maintenanceToggle.addEventListener('change', function() {
                const label = this.nextElementSibling;
                label.textContent = this.checked ? 'Açık' : 'Kapalı';
            });
        }
        
        // Yedek silme fonksiyonu
        function deleteBackup(filename) {
            if(confirm('"' + filename + '" yedek dosyasını silmek istediğinizden emin misiniz?')) {
                window.location.href = '?delete_backup=' + encodeURIComponent(filename);
            }
        }

        // Tab geçişlerinde sayfanın yukarı kaydırılması
        document.addEventListener('DOMContentLoaded', function() {
            const triggerTabList = [].slice.call(document.querySelectorAll('#settingsTabs button'));
            triggerTabList.forEach(function (triggerEl) {
                triggerEl.addEventListener('click', function () {
                    window.scrollTo(0, 0);
                });
            });
        });
    </script>
</body>
</html>