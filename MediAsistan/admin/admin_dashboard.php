<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// İstatistikleri getir
$stats = [];

// Toplam kullanıcı sayısı
$sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'";
$result = $conn->query($sql);
$stats['total_users'] = $result ? $result->fetch_assoc()['total'] : 0;

// Toplam ilaç sayısı - ilaclar tablosu yoksa varsayılan değer
$sql = "SHOW TABLES LIKE 'ilaclar'";
$table_exists = $conn->query($sql)->num_rows > 0;

if($table_exists) {
    $sql = "SELECT COUNT(*) as total FROM ilaclar";
    $result = $conn->query($sql);
    $stats['total_medications'] = $result->fetch_assoc()['total'];
} else {
    $stats['total_medications'] = 0;
}

// Toplam acil durum kişisi - tablo kontrolü
$sql = "SHOW TABLES LIKE 'acil_durum_kisileri'";
$table_exists = $conn->query($sql)->num_rows > 0;

if($table_exists) {
    $sql = "SELECT COUNT(*) as total FROM acil_durum_kisileri WHERE aktif_mi = 1";
    $result = $conn->query($sql);
    $stats['total_emergency_contacts'] = $result->fetch_assoc()['total'];
} else {
    $stats['total_emergency_contacts'] = 0;
}

// Kritik stoktaki ilaçlar
if($table_exists) {
    $sql = "SELECT COUNT(*) as total FROM ilaclar WHERE stok_adedi <= kritik_stok_seviyesi";
    $result = $conn->query($sql);
    $stats['critical_stock'] = $result->fetch_assoc()['total'];
} else {
    $stats['critical_stock'] = 0;
}

// Acil durum kayıtları
$sql = "SHOW TABLES LIKE 'acil_durum_kayitlar'";
$table_exists = $conn->query($sql)->num_rows > 0;

if($table_exists) {
    $sql = "SELECT COUNT(*) as total FROM acil_durum_kayitlar";
    $result = $conn->query($sql);
    $stats['emergency_records'] = $result->fetch_assoc()['total'];
} else {
    $stats['emergency_records'] = 0;
}

// Son kayıtlı kullanıcılar
$sql = "SELECT kullanici_id, ad, soyad, eposta, olusturulma_tarihi 
        FROM kullanicilar 
        ORDER BY olusturulma_tarihi DESC 
        LIMIT 5";
$recent_users = $conn->query($sql);

// Kritik stoktaki ilaçlar
$critical_medications = [];
if($table_exists) {
    $sql = "SELECT ilac_adi, stok_adedi, kritik_stok_seviyesi 
            FROM ilaclar 
            WHERE stok_adedi <= kritik_stok_seviyesi 
            ORDER BY stok_adedi ASC 
            LIMIT 5";
    $critical_medications = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - MediAsistan</title>
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
                            <a class="nav-link active" href="admin_dashboard.php">
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
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
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

                <!-- İstatistik Kartları -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['total_users']; ?></h5>
                                        <p class="card-text">Toplam Kullanıcı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['total_medications']; ?></h5>
                                        <p class="card-text">Toplam İlaç</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['total_emergency_contacts']; ?></h5>
                                        <p class="card-text">Acil Kişi</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-address-book"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $stats['critical_stock']; ?></h5>
                                        <p class="card-text">Kritik Stok</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Son Kayıtlar ve Kritik Stok -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-plus me-2"></i>Son Kayıtlı Kullanıcılar
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Ad Soyad</th>
                                                <th>E-posta</th>
                                                <th>Kayıt Tarihi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($recent_users && $recent_users->num_rows > 0): ?>
                                                <?php while($user = $recent_users->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['eposta']); ?></td>
                                                    <td><?php echo date('d.m.Y', strtotime($user['olusturulma_tarihi'])); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">Henüz kayıtlı kullanıcı bulunmuyor.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-circle me-2"></i>Kritik Stoktaki İlaçlar
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>İlaç Adı</th>
                                                <th>Stok</th>
                                                <th>Kritik Stok</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($critical_medications && $critical_medications->num_rows > 0): ?>
                                                <?php while($med = $critical_medications->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($med['ilac_adi']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $med['stok_adedi'] == 0 ? 'danger' : 'warning'; ?>">
                                                            <?php echo $med['stok_adedi']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $med['kritik_stok_seviyesi']; ?></td>
                                                    <td>
                                                        <?php if($med['stok_adedi'] == 0): ?>
                                                            <span class="badge bg-danger">Stokta Yok</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Kritik</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">Kritik stokta ilaç bulunmuyor.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="row g-4 mt-2">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Hızlı İşlemler
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <a href="admin_users.php" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-users me-2"></i>Kullanıcılar
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin_medications.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-pills me-2"></i>İlaçlar
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin_emergency.php" class="btn btn-outline-info w-100">
                                            <i class="fas fa-first-aid me-2"></i>Acil Durumlar
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="admin_reports.php" class="btn btn-outline-warning w-100">
                                            <i class="fas fa-chart-bar me-2"></i>Raporlar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>