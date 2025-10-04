<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Kullanıcı ID kontrolü
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Kullanıcı bilgilerini getir
$sql = "SELECT kullanici_id, eposta, ad, soyad, dogum_tarihi, kan_grubu, telefon, olusturulma_tarihi, durum 
        FROM kullanicilar 
        WHERE kullanici_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    header('Location: admin_users.php');
    exit();
}

$user = $result->fetch_assoc();

// Kullanıcı güncelleme işlemi
if(isset($_POST['update_user'])) {
    $eposta = trim($_POST['eposta']);
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $sifre = $_POST['sifre'];
    $dogum_tarihi = $_POST['dogum_tarihi'];
    $kan_grubu = $_POST['kan_grubu'];
    $telefon = trim($_POST['telefon']);
    $durum = $_POST['durum'];
    
    // E-posta kontrolü (başka bir kullanıcıda var mı?)
    $check_sql = "SELECT kullanici_id FROM kullanicilar WHERE eposta = ? AND kullanici_id != ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $eposta, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        $error = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!";
    } else {
        // Şifre güncelleme kontrolü
        if(!empty($sifre)) {
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            $update_sql = "UPDATE kullanicilar SET eposta = ?, sifre_hash = ?, ad = ?, soyad = ?, dogum_tarihi = ?, kan_grubu = ?, telefon = ?, durum = ? WHERE kullanici_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssssi", $eposta, $sifre_hash, $ad, $soyad, $dogum_tarihi, $kan_grubu, $telefon, $durum, $user_id);
        } else {
            $update_sql = "UPDATE kullanicilar SET eposta = ?, ad = ?, soyad = ?, dogum_tarihi = ?, kan_grubu = ?, telefon = ?, durum = ? WHERE kullanici_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssssi", $eposta, $ad, $soyad, $dogum_tarihi, $kan_grubu, $telefon, $durum, $user_id);
        }
        
        if($update_stmt->execute()) {
            $success = "Kullanıcı başarıyla güncellendi!";
            // Güncel kullanıcı bilgilerini tekrar getir
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error = "Kullanıcı güncellenirken hata oluştu!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Düzenle - MediAsistan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #dc2626;
            --success: #059669;
            --warning: #d97706;
            --info: #0891b2;
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
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        
        .bg-gradient-primary { background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, var(--success) 0%, #047857 100%); }
        .bg-gradient-warning { background: linear-gradient(135deg, var(--warning) 0%, #b45309 100%); }
        .bg-gradient-danger { background: linear-gradient(135deg, var(--secondary) 0%, #b91c1c 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, var(--info) 0%, #0e7490 100%); }
        
        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #374151;
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .user-info-card {
            border-left: 4px solid var(--primary);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
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
                        <p class="small opacity-75">MediAsistan Yönetim</p>
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
                            <i class="fas fa-user-edit me-2"></i>Kullanıcı Düzenle
                        </h2>
                        <div class="d-flex align-items-center">
                            <span class="me-3">Hoş geldiniz, <?php echo $_SESSION['user_name'] . ' ' . $_SESSION['user_surname']; ?></span>
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
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <!-- Kullanıcı Bilgileri Kartı -->
                        <div class="card user-info-card">
                            <div class="card-body text-center">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?>
                                </div>
                                <h4><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($user['eposta']); ?></p>
                                
                                <div class="row text-start mt-4">
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-id-card me-2"></i>Kullanıcı ID:</strong>
                                        <span class="float-end">#<?php echo $user['kullanici_id']; ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-phone me-2"></i>Telefon:</strong>
                                        <span class="float-end"><?php echo htmlspecialchars($user['telefon'] ?? 'Belirtilmemiş'); ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-tint me-2"></i>Kan Grubu:</strong>
                                        <span class="float-end"><?php echo $user['kan_grubu'] ?? 'Belirtilmemiş'; ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-birthday-cake me-2"></i>Doğum Tarihi:</strong>
                                        <span class="float-end">
                                            <?php echo $user['dogum_tarihi'] ? date('d.m.Y', strtotime($user['dogum_tarihi'])) : 'Belirtilmemiş'; ?>
                                        </span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-calendar me-2"></i>Kayıt Tarihi:</strong>
                                        <span class="float-end"><?php echo date('d.m.Y H:i', strtotime($user['olusturulma_tarihi'])); ?></span>
                                    </div>
                                    <div class="col-12">
                                        <strong><i class="fas fa-circle me-2"></i>Durum:</strong>
                                        <span class="float-end badge bg-<?php echo $user['durum'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                            <?php echo $user['durum'] == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hızlı İşlemler -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Hızlı İşlemler
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin_users.php" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left me-2"></i>Kullanıcı Listesine Dön
                                    </a>
                                    <a href="?toggle_status=<?php echo $user['kullanici_id']; ?>&current_status=<?php echo $user['durum']; ?>" 
                                       class="btn btn-<?php echo $user['durum'] == 'aktif' ? 'warning' : 'success'; ?>">
                                        <i class="fas fa-<?php echo $user['durum'] == 'aktif' ? 'pause' : 'play'; ?> me-2"></i>
                                        <?php echo $user['durum'] == 'aktif' ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal">
                                        <i class="fas fa-trash me-2"></i>Kullanıcıyı Sil
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <!-- Düzenleme Formu -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit me-2"></i>Kullanıcı Bilgilerini Düzenle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-user me-2"></i>Kişisel Bilgiler</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="ad" class="form-label">Ad *</label>
                                                    <input type="text" class="form-control" id="ad" name="ad" 
                                                           value="<?php echo htmlspecialchars($user['ad']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="soyad" class="form-label">Soyad *</label>
                                                    <input type="text" class="form-control" id="soyad" name="soyad" 
                                                           value="<?php echo htmlspecialchars($user['soyad']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                                    <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi" 
                                                           value="<?php echo $user['dogum_tarihi']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="kan_grubu" class="form-label">Kan Grubu</label>
                                                    <select class="form-select" id="kan_grubu" name="kan_grubu">
                                                        <option value="">Seçiniz</option>
                                                        <option value="A+" <?php echo $user['kan_grubu'] == 'A+' ? 'selected' : ''; ?>>A+</option>
                                                        <option value="A-" <?php echo $user['kan_grubu'] == 'A-' ? 'selected' : ''; ?>>A-</option>
                                                        <option value="B+" <?php echo $user['kan_grubu'] == 'B+' ? 'selected' : ''; ?>>B+</option>
                                                        <option value="B-" <?php echo $user['kan_grubu'] == 'B-' ? 'selected' : ''; ?>>B-</option>
                                                        <option value="AB+" <?php echo $user['kan_grubu'] == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                                        <option value="AB-" <?php echo $user['kan_grubu'] == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                                        <option value="0+" <?php echo $user['kan_grubu'] == '0+' ? 'selected' : ''; ?>>0+</option>
                                                        <option value="0-" <?php echo $user['kan_grubu'] == '0-' ? 'selected' : ''; ?>>0-</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-address-card me-2"></i>İletişim Bilgileri</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="eposta" class="form-label">E-posta *</label>
                                                    <input type="email" class="form-control" id="eposta" name="eposta" 
                                                           value="<?php echo htmlspecialchars($user['eposta']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="telefon" class="form-label">Telefon</label>
                                                    <input type="tel" class="form-control" id="telefon" name="telefon" 
                                                           value="<?php echo htmlspecialchars($user['telefon'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Güvenlik Ayarları</h6>
                                        <div class="mb-3">
                                            <label for="sifre" class="form-label">Yeni Şifre</label>
                                            <input type="password" class="form-control" id="sifre" name="sifre" 
                                                   placeholder="Şifreyi değiştirmek istemiyorsanız boş bırakın">
                                            <div class="form-text">Şifre en az 6 karakter olmalıdır.</div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-cog me-2"></i>Hesap Ayarları</h6>
                                        <div class="mb-3">
                                            <label for="durum" class="form-label">Hesap Durumu</label>
                                            <select class="form-select" id="durum" name="durum" required>
                                                <option value="aktif" <?php echo $user['durum'] == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                                <option value="pasif" <?php echo $user['durum'] == 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="admin_users.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>İptal
                                        </a>
                                        <button type="submit" name="update_user" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Kullanıcı Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></strong> adlı kullanıcıyı silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz! Kullanıcının tüm verileri silinecektir.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <a href="admin_users.php?delete_user=<?php echo $user['kullanici_id']; ?>" class="btn btn-danger">Sil</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Doğum tarihi formatını kontrol et
        document.addEventListener('DOMContentLoaded', function() {
            const dogumTarihi = document.getElementById('dogum_tarihi');
            if(dogumTarihi.value) {
                // Tarih formatını input type="date" için uygun formata çevir
                const tarih = new Date(dogumTarihi.value);
                const formattedDate = tarih.toISOString().split('T')[0];
                dogumTarihi.value = formattedDate;
            }
        });
    </script>
</body>
</html>