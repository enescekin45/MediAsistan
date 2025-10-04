
<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// Veritabanı bağlantı kontrolü
if (!$conn) {
    die("Veritabanı bağlantı hatası: " . mysqli_connect_error());
}

// Admin giriş kontrolü
if (!is_logged_in()) {
    header("Location: ../login.php");
    exit();
}

// Admin tablosu varlık kontrolü
$table_check = $conn->query("SHOW TABLES LIKE 'admin'");
if ($table_check->num_rows == 0) {
    // Admin tablosu yoksa oluşturalım
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `admin` (
        `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
        `eposta` VARCHAR(255) UNIQUE NOT NULL,
        `sifre_hash` VARCHAR(255) NOT NULL,
        `ad` VARCHAR(100) NOT NULL,
        `soyad` VARCHAR(100) NOT NULL,
        `yetkiler` LONGTEXT DEFAULT '[\"users\", \"medications\", \"reports\", \"settings\"]',
        `durum` ENUM('aktif', 'pasif') DEFAULT 'aktif',
        `son_giris` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_table_sql)) {
        // Varsayılan admin kullanıcısı oluştur (şifre: admin123)
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO `admin` (`eposta`, `sifre_hash`, `ad`, `soyad`) VALUES 
                        ('admin@mediasistan.com', '$default_password', 'Admin', 'User')";
        $conn->query($insert_admin);
    } else {
        die("Admin tablosu oluşturulamadı: " . $conn->error);
    }
}

// Admin yetkisi kontrolü
if (!is_admin()) {
    header("Location: ../unauthorized.php");
    exit();
}

// Hata ve başarı mesajları
$error = '';
$success = '';

// Admin bilgilerini getir
$admin_id = $_SESSION['user_id'];
$admin_query = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
if ($admin_query) {
    $admin_query->bind_param("i", $admin_id);
    $admin_query->execute();
    $admin_result = $admin_query->get_result();
    
    if ($admin_result && $admin_result->num_rows > 0) {
        $admin = $admin_result->fetch_assoc();
        // Session bilgilerini güncelle
        $_SESSION['admin_name'] = $admin['ad'] ?? '';
        $_SESSION['admin_surname'] = $admin['soyad'] ?? '';
        $_SESSION['admin_email'] = $admin['eposta'] ?? '';
        $_SESSION['user_name'] = $admin['ad'] ?? '';
        $_SESSION['user_surname'] = $admin['soyad'] ?? '';
    } else {
        $error = "Admin bulunamadı!";
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
    $admin_query->close();
} else {
    $error = "Veritabanı sorgusu hatası: " . $conn->error;
}

// Profil bilgilerini güncelleme
if(isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    
    // Validasyon
    if(empty($name) || empty($surname) || empty($email)) {
        $error = "Ad, soyad ve e-posta alanları zorunludur!";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçerli bir e-posta adresi giriniz!";
    } else {
        // E-posta başka bir admin tarafından kullanılıyor mu kontrol et
        $check_email = $conn->prepare("SELECT admin_id FROM admin WHERE eposta = ? AND admin_id != ?");
        if ($check_email) {
            $check_email->bind_param("si", $email, $admin_id);
            $check_email->execute();
            $check_result = $check_email->get_result();
            
            if($check_result && $check_result->num_rows > 0) {
                $error = "Bu e-posta adresi başka bir admin tarafından kullanılıyor!";
            } else {
                // Profili güncelle
                $update_stmt = $conn->prepare("UPDATE admin SET ad = ?, soyad = ?, eposta = ?, updated_at = NOW() WHERE admin_id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("sssi", $name, $surname, $email, $admin_id);
                    
                    if($update_stmt->execute()) {
                        $success = "Profil bilgileriniz başarıyla güncellendi!";
                        // Session bilgilerini güncelle
                        $_SESSION['admin_name'] = $name;
                        $_SESSION['admin_surname'] = $surname;
                        $_SESSION['admin_email'] = $email;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_surname'] = $surname;
                        
                        // Admin bilgilerini yeniden getir
                        $admin_query = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
                        if ($admin_query) {
                            $admin_query->bind_param("i", $admin_id);
                            $admin_query->execute();
                            $admin_result = $admin_query->get_result();
                            if ($admin_result && $admin_result->num_rows > 0) {
                                $admin = $admin_result->fetch_assoc();
                            }
                            $admin_query->close();
                        }
                    } else {
                        $error = "Profil güncellenirken bir hata oluştu: " . $conn->error;
                    }
                    $update_stmt->close();
                } else {
                    $error = "Güncelleme sorgusu hazırlanırken hata: " . $conn->error;
                }
            }
            $check_email->close();
        } else {
            $error = "E-posta kontrol sorgusu hazırlanırken hata: " . $conn->error;
        }
    }
}

// Yetki güncelleme
if(isset($_POST['update_permissions'])) {
    $selected_permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    // Yetkileri JSON formatına çevir
    $permissions_json = json_encode($selected_permissions);
    
    $update_stmt = $conn->prepare("UPDATE admin SET yetkiler = ?, updated_at = NOW() WHERE admin_id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param("si", $permissions_json, $admin_id);
        
        if($update_stmt->execute()) {
            $success = "Yetkiler başarıyla güncellendi!";
            // Admin bilgilerini yeniden getir
            $admin_query = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
            if ($admin_query) {
                $admin_query->bind_param("i", $admin_id);
                $admin_query->execute();
                $admin_result = $admin_query->get_result();
                if ($admin_result && $admin_result->num_rows > 0) {
                    $admin = $admin_result->fetch_assoc();
                }
                $admin_query->close();
            }
        } else {
            $error = "Yetkiler güncellenirken bir hata oluştu: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $error = "Yetki güncelleme sorgusu hazırlanırken hata: " . $conn->error;
    }
}

// Şifre değiştirme
if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasyon
    if(empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Tüm şifre alanlarını doldurunuz!";
    } elseif($new_password !== $confirm_password) {
        $error = "Yeni şifreler eşleşmiyor!";
    } elseif(strlen($new_password) < 6) {
        $error = "Yeni şifre en az 6 karakter olmalıdır!";
    } else {
        // Mevcut şifreyi kontrol et
        if(isset($admin['sifre_hash']) && password_verify($current_password, $admin['sifre_hash'])) {
            // Yeni şifreyi hash'le ve güncelle
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE admin SET sifre_hash = ?, updated_at = NOW() WHERE admin_id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_password_hash, $admin_id);
                
                if($update_stmt->execute()) {
                    $success = "Şifre başarıyla değiştirildi!";
                } else {
                    $error = "Şifre güncellenirken bir hata oluştu: " . $conn->error;
                }
                $update_stmt->close();
            } else {
                $error = "Şifre güncelleme sorgusu hazırlanırken hata: " . $conn->error;
            }
        } else {
            $error = "Mevcut şifre hatalı!";
        }
    }
}

// Site ayarları tablosu kontrolü ve oluşturma
$settings_table_check = $conn->query("SHOW TABLES LIKE 'site_ayarlari'");
if ($settings_table_check->num_rows == 0) {
    $create_settings_table = "CREATE TABLE IF NOT EXISTS `site_ayarlari` (
        `ayar_anahtari` VARCHAR(255) PRIMARY KEY,
        `ayar_degeri` TEXT,
        `olusturulma_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `guncelleme_tarihi` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if ($conn->query($create_settings_table)) {
        // Varsayılan ayarları ekle
        $default_settings = [
            'site_title' => 'MediAsistan',
            'site_description' => 'Sağlık Yönetim Sistemi'
        ];
        
        foreach ($default_settings as $key => $value) {
            $conn->query("INSERT INTO site_ayarlari (ayar_anahtari, ayar_degeri) VALUES ('$key', '$value')");
        }
    }
}

// Site ayarlarını getir
$site_settings = [];
$settings_query = $conn->query("SELECT * FROM site_ayarlari");
if($settings_query && $settings_query->num_rows > 0) {
    while($row = $settings_query->fetch_assoc()) {
        $site_settings[$row['ayar_anahtari']] = $row['ayar_degeri'];
    }
}

// İstatistikleri getir
$total_users = 0;
$total_medications = 0;
$total_emergencies = 0;
$recent_logins = 0;

// Tabloların varlığını kontrol et ve istatistikleri getir
$users_table = $conn->query("SHOW TABLES LIKE 'kullanicilar'");
if($users_table->num_rows > 0) {
    $users_result = $conn->query("SELECT COUNT(*) as count FROM kullanicilar");
    if($users_result && $users_result->num_rows > 0) {
        $total_users = $users_result->fetch_assoc()['count'];
    }
}

$medications_table = $conn->query("SHOW TABLES LIKE 'ilaclar'");
if($medications_table->num_rows > 0) {
    $medications_result = $conn->query("SELECT COUNT(*) as count FROM ilaclar");
    if($medications_result && $medications_result->num_rows > 0) {
        $total_medications = $medications_result->fetch_assoc()['count'];
    }
}

$emergencies_table = $conn->query("SHOW TABLES LIKE 'acil_durumlar'");
if($emergencies_table->num_rows > 0) {
    $emergencies_result = $conn->query("SELECT COUNT(*) as count FROM acil_durumlar");
    if($emergencies_result && $emergencies_result->num_rows > 0) {
        $total_emergencies = $emergencies_result->fetch_assoc()['count'];
    }
}

// Son 7 gün giriş istatistiği
if($users_table->num_rows > 0) {
    $logins_result = $conn->query("SELECT COUNT(*) as count FROM kullanicilar WHERE son_giris_tarihi >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if($logins_result && $logins_result->num_rows > 0) {
        $recent_logins = $logins_result->fetch_assoc()['count'];
    }
}

// Mevcut yetkileri decode et
$current_permissions = [];
if(isset($admin['yetkiler']) && !empty($admin['yetkiler'])) {
    $current_permissions = json_decode($admin['yetkiler'], true);
    if(!is_array($current_permissions)) {
        $current_permissions = [];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profili - <?php echo htmlspecialchars($site_settings['site_title'] ?? 'MediAsistan'); ?></title>
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
        
        .profile-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .profile-card .card-header {
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
        
        .btn-danger {
            background: linear-gradient(135deg, var(--secondary) 0%, #b91c1c 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            color: white;
            border: none;
            border-radius: 10px;
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
        
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        
        .activity-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
        }

        .admin-badge {
            background: linear-gradient(135deg, var(--purple) 0%, #6d28d9 100%);
            color: white;
        }
        
        .permission-checkbox {
            margin-bottom: 10px;
        }
        
        .permission-checkbox input[type="checkbox"] {
            margin-right: 8px;
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
                            <a class="nav-link" href="admin_settings.php">
                                <i class="fas fa-cogs"></i>Ayarlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="admin_profile.php">
                                <i class="fas fa-user-shield"></i>Profil
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
                            <i class="fas fa-user-shield me-2"></i>Admin Profili
                        </h2>
                        <div class="d-flex align-items-center">
                            <span class="me-3">Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['user_surname'] ?? ''); ?></span>
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
                <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Sol Kolon -->
                    <div class="col-lg-8">
                        <!-- Profil Bilgileri -->
                        <div class="card profile-card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                            <i class="fas fa-user-edit me-2"></i>Profil Bilgileri
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                            <i class="fas fa-key me-2"></i>Şifre Değiştir
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab">
                                            <i class="fas fa-user-lock me-2"></i>Yetkiler
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="profileTabsContent">
                                    <!-- Profil Bilgileri Tab -->
                                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                        <form method="POST" action="">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="name" class="form-label">Ad</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?php echo htmlspecialchars($admin['ad'] ?? ''); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="surname" class="form-label">Soyad</label>
                                                    <input type="text" class="form-control" id="surname" name="surname" 
                                                           value="<?php echo htmlspecialchars($admin['soyad'] ?? ''); ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <label for="email" class="form-label">E-posta</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($admin['eposta'] ?? ''); ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                Kayıt Tarihi: <?php echo isset($admin['created_at']) ? date('d.m.Y H:i', strtotime($admin['created_at'])) : 'Belirtilmemiş'; ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i>
                                                                Son Güncelleme: <?php echo isset($admin['updated_at']) ? date('d.m.Y H:i', strtotime($admin['updated_at'])) : 'Belirtilmemiş'; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Profili Güncelle
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Şifre Değiştir Tab -->
                                    <div class="tab-pane fade" id="password" role="tabpanel">
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

                                    <!-- Yetkiler Tab -->
                                    <div class="tab-pane fade" id="permissions" role="tabpanel">
                                        <form method="POST" action="">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <h6 class="mb-3">Sistem Yetkileri</h6>
                                                    <?php
                                                    $permission_options = [
                                                        'users' => 'Kullanıcı Yönetimi',
                                                        'medications' => 'İlaç Yönetimi',
                                                        'emergency' => 'Acil Durum Yönetimi', 
                                                        'reports' => 'Raporlar ve İstatistikler',
                                                        'settings' => 'Sistem Ayarları'
                                                    ];
                                                    
                                                    foreach($permission_options as $key => $label):
                                                    ?>
                                                    <div class="form-check permission-checkbox">
                                                        <input class="form-check-input" type="checkbox" name="permissions[]" 
                                                               value="<?php echo $key; ?>" 
                                                               id="perm_<?php echo $key; ?>"
                                                               <?php echo in_array($key, $current_permissions) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="perm_<?php echo $key; ?>">
                                                            <i class="fas fa-check-circle text-success me-2"></i>
                                                            <?php echo $label; ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="col-12">
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                        <strong>Uyarı:</strong> Yetkileri değiştirmek sistem erişiminizi etkileyebilir. 
                                                        Tüm yetkileri kaldırırsanız sistem yönetimine erişiminiz kısıtlanabilir.
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" name="update_permissions" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Yetkileri Güncelle
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Son Aktiviteler -->
                        <div class="card profile-card">
                            <div class="card-header">
                                <span><i class="fas fa-history me-2"></i>Son Aktiviteler</span>
                            </div>
                            <div class="card-body">
                                <div class="activity-timeline">
                                    <div class="activity-item">
                                        <h6 class="mb-1">Profil Güncellendi</h6>
                                        <p class="text-muted mb-1">Profil bilgileriniz başarıyla güncellendi.</p>
                                        <small class="text-muted"><?php echo date('d.m.Y H:i'); ?></small>
                                    </div>
                                    <div class="activity-item">
                                        <h6 class="mb-1">Sisteme Giriş Yapıldı</h6>
                                        <p class="text-muted mb-1">Admin paneline başarıyla giriş yapıldı.</p>
                                        <small class="text-muted"><?php echo isset($admin['son_giris']) ? date('d.m.Y H:i', strtotime($admin['son_giris'])) : date('d.m.Y H:i'); ?></small>
                                    </div>
                                    <div class="activity-item">
                                        <h6 class="mb-1">Kullanıcı Yönetimi</h6>
                                        <p class="text-muted mb-1">Son kullanıcı işlemleri gerçekleştirildi.</p>
                                        <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime('-1 hour')); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Kolon -->
                    <div class="col-lg-4">
                        <!-- Profil Özeti -->
                        <div class="card profile-card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <div class="profile-photo bg-light d-flex align-items-center justify-content-center mx-auto">
                                        <i class="fas fa-user-shield fa-3x text-primary"></i>
                                    </div>
                                </div>
                                <h4><?php echo htmlspecialchars($admin['ad'] ?? '') . ' ' . htmlspecialchars($admin['soyad'] ?? ''); ?></h4>
                                <p class="text-muted">Sistem Yöneticisi</p>
                                <div class="d-grid gap-2">
                                    <span class="badge admin-badge mb-2">Admin</span>
                                    <span class="badge bg-<?php echo ($admin['durum'] ?? 'aktif') == 'aktif' ? 'success' : 'danger'; ?>">
                                        <?php echo ($admin['durum'] ?? 'aktif') == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </div>
                                <hr>
                                <div class="row text-start">
                                    <div class="col-12">
                                        <small class="text-muted">E-posta:</small>
                                        <p class="mb-1"><?php echo htmlspecialchars($admin['eposta'] ?? ''); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Kayıt Tarihi:</small>
                                        <p class="mb-1"><?php echo isset($admin['created_at']) ? date('d.m.Y', strtotime($admin['created_at'])) : 'Belirtilmemiş'; ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Son Giriş:</small>
                                        <p class="mb-1"><?php echo isset($admin['son_giris']) ? date('d.m.Y H:i', strtotime($admin['son_giris'])) : 'Belirtilmemiş'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- İstatistikler -->
                        <div class="card stats-card mt-4">
                            <div class="card-body">
                                <h5 class="card-title text-white">
                                    <i class="fas fa-chart-bar me-2"></i>Sistem İstatistikleri
                                </h5>
                                <div class="stats-info">
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>Toplam Kullanıcı:</span>
                                        <span class="fw-bold"><?php echo $total_users; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>Toplam İlaç:</span>
                                        <span class="fw-bold"><?php echo $total_medications; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2 border-bottom border-white border-opacity-25">
                                        <span>Acil Durum:</span>
                                        <span class="fw-bold"><?php echo $total_emergencies; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between py-2">
                                        <span>Son 7 Gün Giriş:</span>
                                        <span class="fw-bold"><?php echo $recent_logins; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hızlı İşlemler -->
                        <div class="card profile-card mt-4">
                            <div class="card-header">
                                <span><i class="fas fa-bolt me-2"></i>Hızlı İşlemler</span>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard'a Git
                                    </a>
                                    <a href="admin_users.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-users me-1"></i>Kullanıcıları Yönet
                                    </a>
                                    <a href="admin_settings.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-cogs me-1"></i>Sistem Ayarları
                                    </a>
                                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-sign-out-alt me-1"></i>Çıkış Yap
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

        // Tab değişikliğinde URL hash'ini güncelle
        document.addEventListener('DOMContentLoaded', function() {
            const profileTabs = document.querySelectorAll('#profileTabs button[data-bs-toggle="tab"]');
            
            profileTabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    const target = e.target.getAttribute('data-bs-target');
                    window.location.hash = target;
                });
            });

            // URL hash'ine göre aktif tab'ı ayarla
            if (window.location.hash) {
                const triggerEl = document.querySelector(`#profileTabs button[data-bs-target="${window.location.hash}"]`);
                if (triggerEl) {
                    bootstrap.Tab.getOrCreateInstance(triggerEl).show();
                }
            }
        });
    </script>
</body>
</html>