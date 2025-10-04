<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// İlaç ID kontrolü
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_medications.php');
    exit();
}

$ilac_id = intval($_GET['id']);

// İlaç bilgilerini getir
$sql = "SELECT i.*, k.ad as kullanici_adi, k.soyad as kullanici_soyadi 
        FROM ilaclar i 
        LEFT JOIN kullanicilar k ON i.kullanici_id = k.kullanici_id 
        WHERE i.ilac_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ilac_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    header('Location: admin_medications.php');
    exit();
}

$medication = $result->fetch_assoc();

// Aktif kullanıcıları getir (dropdown için)
$users_sql = "SELECT kullanici_id, ad, soyad FROM kullanicilar WHERE durum = 'aktif' ORDER BY ad, soyad";
$users = $conn->query($users_sql);

// İlaç güncelleme işlemi
if(isset($_POST['update_medication'])) {
    $kullanici_id = intval($_POST['kullanici_id']);
    $ilac_adi = trim($_POST['ilac_adi']);
    $dozaj = trim($_POST['dozaj']);
    $stok_adedi = intval($_POST['stok_adedi']);
    $kritik_stok_seviyesi = intval($_POST['kritik_stok_seviyesi']);
    $receteli_mi = isset($_POST['receteli_mi']) ? 1 : 0;
    $ilac_tipi = $_POST['ilac_tipi'];
    $barkod = trim($_POST['barkod']);
    $baslama_tarihi = $_POST['baslama_tarihi'];
    $bitis_tarihi = $_POST['bitis_tarihi'];
    $aciklama = trim($_POST['aciklama']);
    
    // Barkod kontrolü (başka bir ilaçta var mı?)
    if(!empty($barkod)) {
        $check_sql = "SELECT ilac_id FROM ilaclar WHERE barkod = ? AND ilac_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $barkod, $ilac_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error = "Bu barkod numarası başka bir ilaç tarafından kullanılıyor!";
        }
    }
    
    if(!isset($error)) {
        // İlaç güncelle
        $update_sql = "UPDATE ilaclar SET kullanici_id = ?, ilac_adi = ?, dozaj = ?, stok_adedi = ?, kritik_stok_seviyesi = ?, receteli_mi = ?, ilac_tipi = ?, barkod = ?, baslama_tarihi = ?, bitis_tarihi = ?, aciklama = ? WHERE ilac_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("issiiisssssi", $kullanici_id, $ilac_adi, $dozaj, $stok_adedi, $kritik_stok_seviyesi, $receteli_mi, $ilac_tipi, $barkod, $baslama_tarihi, $bitis_tarihi, $aciklama, $ilac_id);
        
        if($update_stmt->execute()) {
            $success = "İlaç başarıyla güncellendi!";
            // Güncel ilaç bilgilerini tekrar getir
            $stmt->execute();
            $result = $stmt->get_result();
            $medication = $result->fetch_assoc();
        } else {
            $error = "İlaç güncellenirken hata oluştu!";
        }
    }
}

// İlaç silme işlemi
if(isset($_GET['delete_medication'])) {
    $delete_sql = "DELETE FROM ilaclar WHERE ilac_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $ilac_id);
    
    if($delete_stmt->execute()) {
        $success = "İlaç başarıyla silindi!";
        header("Location: admin_medications.php");
        exit();
    } else {
        $error = "İlaç silinirken hata oluştu!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlaç Düzenle - MediAsistan</title>
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
        
        .medication-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        
        .medication-info-card {
            border-left: 4px solid var(--primary);
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stock-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .stock-normal { background-color: var(--success); }
        .stock-low { background-color: var(--warning); }
        .stock-critical { background-color: var(--secondary); }
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
                            <a class="nav-link active" href="admin_medications.php">
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
                            <i class="fas fa-pills me-2"></i>İlaç Düzenle
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
                        <!-- İlaç Bilgileri Kartı -->
                        <div class="card medication-info-card">
                            <div class="card-body text-center">
                                <div class="medication-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($medication['ilac_adi']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($medication['dozaj']); ?></p>
                                
                                <div class="row text-start mt-4">
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-id-card me-2"></i>İlaç ID:</strong>
                                        <span class="float-end">#<?php echo $medication['ilac_id']; ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-user me-2"></i>Kullanıcı:</strong>
                                        <span class="float-end">
                                            <?php if($medication['kullanici_adi']): ?>
                                                <?php echo htmlspecialchars($medication['kullanici_adi'] . ' ' . $medication['kullanici_soyadi']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Kullanıcı silinmiş</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-barcode me-2"></i>Barkod:</strong>
                                        <span class="float-end"><?php echo htmlspecialchars($medication['barkod'] ?? 'Belirtilmemiş'); ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-cube me-2"></i>İlaç Tipi:</strong>
                                        <span class="float-end"><?php echo htmlspecialchars($medication['ilac_tipi']); ?></span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-prescription me-2"></i>Reçete Durumu:</strong>
                                        <span class="float-end">
                                            <?php if($medication['receteli_mi']): ?>
                                                <span class="badge bg-primary">Reçeteli</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Reçetesiz</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-box me-2"></i>Stok Durumu:</strong>
                                        <span class="float-end">
                                            <?php
                                            $stock_class = '';
                                            $stock_text = '';
                                            if($medication['stok_adedi'] == 0) {
                                                $stock_class = 'stock-critical';
                                                $stock_text = 'Stokta Yok';
                                            } elseif($medication['stok_adedi'] <= $medication['kritik_stok_seviyesi']) {
                                                $stock_class = 'stock-low';
                                                $stock_text = 'Düşük Stok';
                                            } else {
                                                $stock_class = 'stock-normal';
                                                $stock_text = 'Normal Stok';
                                            }
                                            ?>
                                            <span class="badge bg-<?php 
                                                if($medication['stok_adedi'] == 0) echo 'danger';
                                                elseif($medication['stok_adedi'] <= $medication['kritik_stok_seviyesi']) echo 'warning';
                                                else echo 'success';
                                            ?>">
                                                <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                                <?php echo $stock_text; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <strong><i class="fas fa-calendar me-2"></i>Oluşturulma:</strong>
                                        <span class="float-end"><?php echo date('d.m.Y H:i', strtotime($medication['olusturulma_tarihi'])); ?></span>
                                    </div>
                                    <div class="col-12">
                                        <strong><i class="fas fa-sync me-2"></i>Son Güncelleme:</strong>
                                        <span class="float-end">
                                            <?php if($medication['guncelleme_tarihi']): ?>
                                                <?php echo date('d.m.Y H:i', strtotime($medication['guncelleme_tarihi'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Henüz güncellenmemiş</span>
                                            <?php endif; ?>
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
                                    <a href="admin_medications.php" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left me-2"></i>İlaç Listesine Dön
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal">
                                        <i class="fas fa-trash me-2"></i>İlacı Sil
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Stok Bilgileri -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>Stok Bilgileri
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Mevcut Stok:</span>
                                        <strong><?php echo $medication['stok_adedi']; ?> adet</strong>
                                    </div>
                                    <div class="progress mb-3" style="height: 10px;">
                                        <?php
                                        $max_stock = max($medication['stok_adedi'], $medication['kritik_stok_seviyesi'] * 3);
                                        $progress = ($medication['stok_adedi'] / $max_stock) * 100;
                                        $progress_class = 'bg-success';
                                        if($medication['stok_adedi'] == 0) {
                                            $progress_class = 'bg-danger';
                                        } elseif($medication['stok_adedi'] <= $medication['kritik_stok_seviyesi']) {
                                            $progress_class = 'bg-warning';
                                        }
                                        ?>
                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                             style="width: <?php echo $progress; ?>%"></div>
                                    </div>
                                </div>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="text-muted small">Kritik Stok</div>
                                            <div class="fw-bold"><?php echo $medication['kritik_stok_seviyesi']; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="text-muted small">Kalan</div>
                                            <div class="fw-bold text-<?php 
                                                if($medication['stok_adedi'] == 0) echo 'danger';
                                                elseif($medication['stok_adedi'] <= $medication['kritik_stok_seviyesi']) echo 'warning';
                                                else echo 'success';
                                            ?>">
                                                <?php echo $medication['stok_adedi']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <!-- Düzenleme Formu -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-edit me-2"></i>İlaç Bilgilerini Düzenle
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Temel Bilgiler</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="kullanici_id" class="form-label">Kullanıcı *</label>
                                                    <select class="form-select" id="kullanici_id" name="kullanici_id" required>
                                                        <option value="">Kullanıcı Seçin</option>
                                                        <?php 
                                                        $users->data_seek(0); // Reset pointer
                                                        while($user = $users->fetch_assoc()): ?>
                                                            <option value="<?php echo $user['kullanici_id']; ?>" 
                                                                <?php echo $medication['kullanici_id'] == $user['kullanici_id'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="ilac_adi" class="form-label">İlaç Adı *</label>
                                                    <input type="text" class="form-control" id="ilac_adi" name="ilac_adi" 
                                                           value="<?php echo htmlspecialchars($medication['ilac_adi']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="dozaj" class="form-label">Dozaj *</label>
                                                    <input type="text" class="form-control" id="dozaj" name="dozaj" 
                                                           value="<?php echo htmlspecialchars($medication['dozaj']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="ilac_tipi" class="form-label">İlaç Tipi</label>
                                                    <select class="form-select" id="ilac_tipi" name="ilac_tipi">
                                                        <option value="tablet" <?php echo $medication['ilac_tipi'] == 'tablet' ? 'selected' : ''; ?>>Tablet</option>
                                                        <option value="sıvı" <?php echo $medication['ilac_tipi'] == 'sıvı' ? 'selected' : ''; ?>>Sıvı</option>
                                                        <option value="kapsül" <?php echo $medication['ilac_tipi'] == 'kapsül' ? 'selected' : ''; ?>>Kapsül</option>
                                                        <option value="sprey" <?php echo $medication['ilac_tipi'] == 'sprey' ? 'selected' : ''; ?>>Sprey</option>
                                                        <option value="merhem" <?php echo $medication['ilac_tipi'] == 'merhem' ? 'selected' : ''; ?>>Merhem</option>
                                                        <option value="damla" <?php echo $medication['ilac_tipi'] == 'damla' ? 'selected' : ''; ?>>Damla</option>
                                                        <option value="injeksiyon" <?php echo $medication['ilac_tipi'] == 'injeksiyon' ? 'selected' : ''; ?>>Enjeksiyon</option>
                                                        <option value="diğer" <?php echo $medication['ilac_tipi'] == 'diğer' ? 'selected' : ''; ?>>Diğer</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-cube me-2"></i>Stok Bilgileri</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="stok_adedi" class="form-label">Stok Adedi</label>
                                                    <input type="number" class="form-control" id="stok_adedi" name="stok_adedi" 
                                                           value="<?php echo $medication['stok_adedi']; ?>" min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="kritik_stok_seviyesi" class="form-label">Kritik Stok Seviyesi</label>
                                                    <input type="number" class="form-control" id="kritik_stok_seviyesi" name="kritik_stok_seviyesi" 
                                                           value="<?php echo $medication['kritik_stok_seviyesi']; ?>" min="1" required>
                                                    <div class="form-text">Stok bu seviyenin altına düştüğünde uyarı verilecek</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="barkod" class="form-label">Barkod</label>
                                                    <input type="text" class="form-control" id="barkod" name="barkod" 
                                                           value="<?php echo htmlspecialchars($medication['barkod'] ?? ''); ?>" placeholder="Opsiyonel">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-calendar me-2"></i>Kullanım Periyodu</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="baslama_tarihi" class="form-label">Başlangıç Tarihi</label>
                                                    <input type="date" class="form-control" id="baslama_tarihi" name="baslama_tarihi" 
                                                           value="<?php echo $medication['baslama_tarihi']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                                    <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                                           value="<?php echo $medication['bitis_tarihi']; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-section">
                                        <h6 class="mb-3"><i class="fas fa-sticky-note me-2"></i>Ek Bilgiler</h6>
                                        <div class="mb-3">
                                            <label for="aciklama" class="form-label">Açıklama</label>
                                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" 
                                                      placeholder="İlaç hakkında notlar..."><?php echo htmlspecialchars($medication['aciklama'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="receteli_mi" name="receteli_mi" 
                                                   <?php echo $medication['receteli_mi'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="receteli_mi">
                                                Reçeteli İlaç
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="admin_medications.php" class="btn btn-secondary">
                                            <i class="fas fa-times me-2"></i>İptal
                                        </a>
                                        <button type="submit" name="update_medication" class="btn btn-primary">
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
                    <h5 class="modal-title" id="deleteModalLabel">İlaç Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong><?php echo htmlspecialchars($medication['ilac_adi']); ?></strong> adlı ilacı silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz! İlaca ait tüm veriler silinecektir.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <a href="?delete_medication=<?php echo $medication['ilac_id']; ?>" class="btn btn-danger">Sil</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tarih kontrolleri
            const baslamaTarihi = document.getElementById('baslama_tarihi');
            const bitisTarihi = document.getElementById('bitis_tarihi');
            
            // Bitiş tarihi başlangıç tarihinden önce olamaz
            if(baslamaTarihi.value) {
                bitisTarihi.min = baslamaTarihi.value;
            }
            
            baslamaTarihi.addEventListener('change', function() {
                bitisTarihi.min = this.value;
            });

            // Stok uyarı kontrolü
            const stokAdedi = document.getElementById('stok_adedi');
            const kritikStok = document.getElementById('kritik_stok_seviyesi');
            
            function checkStockWarning() {
                if(parseInt(stokAdedi.value) <= parseInt(kritikStok.value)) {
                    stokAdedi.classList.add('is-invalid');
                    stokAdedi.classList.remove('is-valid');
                } else {
                    stokAdedi.classList.remove('is-invalid');
                    stokAdedi.classList.add('is-valid');
                }
            }
            
            stokAdedi.addEventListener('input', checkStockWarning);
            kritikStok.addEventListener('input', checkStockWarning);
            
            // İlk yüklemede kontrol et
            checkStockWarning();
        });
    </script>
</body>
</html>