<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Rapor tarih aralığı (varsayılan: son 30 gün)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// PDF indirme işlemi
if(isset($_GET['export_pdf'])) {
    $pdf_content = generatePDFContent($conn, $start_date, $end_date);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="mediasistan_rapor_'.date('Y-m-d').'.txt"');
    echo $pdf_content;
    exit();
}

// PDF içeriği oluşturma fonksiyonu
function generatePDFContent($conn, $start_date, $end_date) {
    $content = "========================================\n";
    $content .= "       MEDİASİSTAN SİSTEM RAPORU\n";
    $content .= "========================================\n\n";
    $content .= "Rapor Tarihi: " . date('d.m.Y H:i') . "\n";
    $content .= "Tarih Aralığı: " . date('d.m.Y', strtotime($start_date)) . " - " . date('d.m.Y', strtotime($end_date)) . "\n\n";
    
    $stats = [];
    $queries = [
        'total_users' => "SELECT COUNT(*) as total FROM kullanicilar",
        'active_users' => "SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'",
        'total_medications' => "SELECT COUNT(*) as total FROM ilaclar",
        'total_categories' => "SELECT COUNT(*) as total FROM ilk_yardim_kategorileri",
        'total_instructions' => "SELECT COUNT(*) as total FROM ilk_yardim_talimatlari"
    ];
    
    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        $stats[$key] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
    }
    
    $new_users = 0;
    $new_users_sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE olusturulma_tarihi BETWEEN ? AND ?";
    $new_users_stmt = $conn->prepare($new_users_sql);
    if ($new_users_stmt !== false) {
        $new_users_stmt->bind_param("ss", $start_date, $end_date);
        if($new_users_stmt->execute()) {
            $new_users_result = $new_users_stmt->get_result();
            $new_users = $new_users_result && $new_users_result->num_rows > 0 ? $new_users_result->fetch_assoc()['total'] : 0;
        }
        $new_users_stmt->close();
    }
    
    $content .= "GENEL İSTATİSTİKLER:\n";
    $content .= "────────────────────\n";
    $content .= "Toplam Kullanıcı: " . $stats['total_users'] . "\n";
    $content .= "Aktif Kullanıcı: " . $stats['active_users'] . "\n";
    $content .= "Yeni Kayıt: " . $new_users . "\n";
    $content .= "Toplam İlaç: " . $stats['total_medications'] . "\n";
    $content .= "Toplam Kategori: " . $stats['total_categories'] . "\n";
    $content .= "Toplam Talimat: " . $stats['total_instructions'] . "\n\n";
    
    return $content;
}

// Genel İstatistikleri al
function getStatistics($conn) {
    $stats = [];
    $queries = [
        'total_users' => "SELECT COUNT(*) as total FROM kullanicilar",
        'active_users' => "SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'",
        'total_medications' => "SELECT COUNT(*) as total FROM ilaclar",
        'total_categories' => "SELECT COUNT(*) as total FROM ilk_yardim_kategorileri",
        'total_instructions' => "SELECT COUNT(*) as total FROM ilk_yardim_talimatlari"
    ];
    
    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        $stats[$key] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
    }
    return $stats;
}

// İstatistikleri al
$stats = getStatistics($conn);
$total_users = $stats['total_users'];
$active_users = $stats['active_users'];
$total_medications = $stats['total_medications'];
$total_categories = $stats['total_categories'];
$total_instructions = $stats['total_instructions'];

// Yeni kayıtlar
$new_users = 0;
$new_users_sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE olusturulma_tarihi BETWEEN ? AND ?";
$new_users_stmt = $conn->prepare($new_users_sql);
if ($new_users_stmt !== false) {
    $new_users_stmt->bind_param("ss", $start_date, $end_date);
    if($new_users_stmt->execute()) {
        $new_users_result = $new_users_stmt->get_result();
        $new_users = $new_users_result && $new_users_result->num_rows > 0 ? $new_users_result->fetch_assoc()['total'] : 0;
    }
    $new_users_stmt->close();
}

// En çok kullanılan ilaçlar
$top_medications_sql = "SELECT ilac_adi, COUNT(*) as kullanım_sayisi 
                        FROM ilaclar 
                        GROUP BY ilac_adi 
                        ORDER BY kullanım_sayisi DESC 
                        LIMIT 10";
$top_medications_result = $conn->query($top_medications_sql);
$top_medications = $top_medications_result && $top_medications_result->num_rows > 0 ? $top_medications_result : false;

// Acil durum kategorilerine göre talimat dağılımı
$category_distribution_sql = "SELECT k.kategori_adi, COUNT(i.talimat_id) as talimat_sayisi
                             FROM ilk_yardim_kategorileri k
                             LEFT JOIN ilk_yardim_talimatlari i ON k.kategori_id = i.kategori_id
                             GROUP BY k.kategori_id, k.kategori_adi
                             ORDER BY talimat_sayisi DESC";
$category_distribution_result = $conn->query($category_distribution_sql);
$category_distribution = $category_distribution_result && $category_distribution_result->num_rows > 0 ? $category_distribution_result : false;

// Kullanıcı aktivite raporu
$user_activity_sql = "SELECT kullanici_adi, eposta, olusturulma_tarihi, guncelleme_tarihi, durum 
                      FROM kullanicilar 
                      ORDER BY guncelleme_tarihi DESC 
                      LIMIT 15";
$user_activity_result = $conn->query($user_activity_sql);
$user_activity = $user_activity_result && $user_activity_result->num_rows > 0 ? $user_activity_result : false;

// İlaç stok durumu
$medication_stock_sql = "SELECT ilac_adi, stok_adedi as stok_miktari, kritik_stok_seviyesi
                        FROM ilaclar 
                        WHERE stok_adedi <= kritik_stok_seviyesi 
                        ORDER BY stok_adedi ASC 
                        LIMIT 10";
$medication_stock_result = $conn->query($medication_stock_sql);
$medication_stock = $medication_stock_result && $medication_stock_result->num_rows > 0 ? $medication_stock_result : false;

// Son 7 günlük kullanıcı aktivite istatistikleri
$weekly_activity_sql = "SELECT 
                        COUNT(*) as toplam,
                        SUM(CASE WHEN DATE(guncelleme_tarihi) = CURDATE() THEN 1 ELSE 0 END) as bugun,
                        SUM(CASE WHEN DATE(guncelleme_tarihi) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as dun,
                        SUM(CASE WHEN DATE(guncelleme_tarihi) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as bu_hafta
                        FROM kullanicilar 
                        WHERE guncelleme_tarihi IS NOT NULL";
$weekly_activity_result = $conn->query($weekly_activity_sql);
$weekly_stats = $weekly_activity_result && $weekly_activity_result->num_rows > 0 ? $weekly_activity_result->fetch_assoc() : [];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - MediAsistan</title>
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
        
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            height: 100%;
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
        .bg-gradient-purple { background: linear-gradient(135deg, var(--purple) 0%, #6d28d9 100%); }
        
        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .report-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .report-card .card-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            padding: 1rem 1.5rem;
        }
        
        .date-filter {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }
        
        .table td {
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        .badge-sm {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .activity-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .export-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .export-buttons .btn {
                width: 100%;
                margin-right: 0;
            }
            
            .activity-stats {
                grid-template-columns: 1fr;
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
                            <a class="nav-link active" href="admin_reports.php">
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
                            <i class="fas fa-chart-bar me-2"></i>Sistem Raporları
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

                <!-- Tarih Filtresi -->
                <div class="date-filter">
                    <form method="GET" action="">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="export-buttons">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Filtrele
                                    </button>
                                    <a href="admin_reports.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-1"></i>Sıfırla
                                    </a>
                                    <a href="?export_pdf=1&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-success">
                                        <i class="fas fa-file-export me-1"></i>Raporu İndir
                                    </a>
                                    <button type="button" class="btn btn-info" onclick="window.print()">
                                        <i class="fas fa-print me-1"></i>Yazdır
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($start_date)) . ' - ' . date('d.m.Y', strtotime($end_date)); ?>
                                </span>
                            </div>
                        </div>
                    </form>
                </div>

                 <!-- Genel İstatistikler - DÜZENLENMİŞ -->
                <div class="row g-4 mb-4">
                    <!-- Toplam Kullanıcı -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $total_users; ?></h5>
                                        <p class="card-text">Toplam Kullanıcı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aktif Kullanıcı -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $active_users; ?></h5>
                                        <p class="card-text">Aktif Kullanıcı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Yeni Kayıt -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $new_users; ?></h5>
                                        <p class="card-text">Yeni Kayıt</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toplam İlaç -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $total_medications; ?></h5>
                                        <p class="card-text">Toplam İlaç</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toplam Kategori -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $total_categories; ?></h5>
                                        <p class="card-text">Toplam Kategori</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toplam Talimat -->
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-gradient-purple">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $total_instructions; ?></h5>
                                        <p class="card-text">Toplam Talimat</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-list-ol"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Sol Kolon -->
                    <div class="col-lg-8">
                        <!-- Son Kullanıcı Aktiviteleri -->
                        <div class="card report-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-clock me-2"></i>Son Kullanıcı Aktiviteleri</span>
                                <span class="badge bg-info">Son 15 Kullanıcı</span>
                            </div>
                            <div class="card-body">
                                <!-- Aktivite İstatistikleri -->
                                <?php if(!empty($weekly_stats)): ?>
                                <div class="activity-stats mb-4">
                                    <div class="stat-box">
                                        <div class="stat-number text-primary"><?php echo $weekly_stats['bugun'] ?? 0; ?></div>
                                        <div class="stat-label">Bugün Aktif</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-number text-success"><?php echo $weekly_stats['dun'] ?? 0; ?></div>
                                        <div class="stat-label">Dün Aktif</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-number text-warning"><?php echo $weekly_stats['bu_hafta'] ?? 0; ?></div>
                                        <div class="stat-label">Bu Hafta</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-number text-info"><?php echo $weekly_stats['toplam'] ?? 0; ?></div>
                                        <div class="stat-label">Toplam Aktivite</div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kullanıcı</th>
                                                <th>E-posta</th>
                                                <th>Kayıt Tarihi</th>
                                                <th>Son Aktivite</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($user_activity && $user_activity->num_rows > 0): ?>
                                                <?php while($user = $user_activity->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($user['kullanici_adi']); ?></div>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['eposta']); ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('d.m.Y', strtotime($user['olusturulma_tarihi'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <small class="<?php echo $user['guncelleme_tarihi'] ? 'text-success' : 'text-muted'; ?>">
                                                            <?php echo $user['guncelleme_tarihi'] ? date('d.m.Y H:i', strtotime($user['guncelleme_tarihi'])) : 'Henüz aktivite yok'; ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['durum'] == 'aktif' ? 'success' : 'secondary'; ?> badge-sm">
                                                            <?php echo $user['durum'] == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <i class="fas fa-user-clock fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Henüz kullanıcı aktivite verisi bulunmuyor.</p>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Kategori Dağılımı -->
                        <div class="card report-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-pie me-2"></i>Kategori Dağılımı</span>
                                <span class="badge bg-primary"><?php echo $category_distribution ? $category_distribution->num_rows : 0; ?> Kategori</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kategori</th>
                                                <th>Talimat Sayısı</th>
                                                <th>Yüzde</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if($category_distribution && $category_distribution->num_rows > 0): 
                                                $total_instructions_count = $total_instructions;
                                                while($category = $category_distribution->fetch_assoc()): 
                                                    $percentage = $total_instructions_count > 0 ? round(($category['talimat_sayisi'] / $total_instructions_count) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($category['kategori_adi']); ?></div>
                                                </td>
                                                <td><?php echo $category['talimat_sayisi']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                            <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                        <span class="small">%<?php echo $percentage; ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $category['talimat_sayisi'] > 0 ? 'success' : 'secondary'; ?> badge-sm">
                                                        <?php echo $category['talimat_sayisi'] > 0 ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <i class="fas fa-chart-pie fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Henüz kategori verisi bulunmuyor.</p>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Kolon -->
                    <div class="col-lg-4">
                        <!-- En Çok Kayıtlı İlaçlar -->
                        <div class="card report-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-pills me-2"></i>En Çok Kayıtlı İlaçlar</span>
                                <span class="badge bg-success">Top 10</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>İlaç Adı</th>
                                                <th>Kayıt Sayısı</th>
                                                <th>Trend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($top_medications && $top_medications->num_rows > 0): ?>
                                                <?php $rank = 1; ?>
                                                <?php while($medication = $top_medications->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $rank; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($medication['ilac_adi']); ?></div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-primary"><?php echo $medication['kullanım_sayisi']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if($rank <= 3): ?>
                                                            <span class="badge bg-danger badge-sm"><i class="fas fa-fire"></i> Popüler</span>
                                                        <?php elseif($rank <= 6): ?>
                                                            <span class="badge bg-warning badge-sm"><i class="fas fa-chart-line"></i> Orta</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-info badge-sm"><i class="fas fa-chart-bar"></i> Düşük</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php $rank++; ?>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4">
                                                    <i class="fas fa-pills fa-2x text-muted mb-2"></i>
                                                    <p class="text-muted">Henüz ilaç verisi bulunmuyor.</p>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Kritik Stok Uyarıları -->
                        <div class="card report-card border-warning">
                            <div class="card-header d-flex justify-content-between align-items-center bg-warning bg-opacity-10">
                                <span class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Kritik Stok Uyarıları</span>
                                <span class="badge bg-warning">Uyarı</span>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php if($medication_stock && $medication_stock->num_rows > 0): ?>
                                        <?php while($medication = $medication_stock->fetch_assoc()): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($medication['ilac_adi']); ?></h6>
                                                    <small class="text-muted">
                                                        Kritik Seviye: <?php echo $medication['kritik_stok_seviyesi']; ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-danger"><?php echo $medication['stok_miktari']; ?> Adet</span>
                                                </div>
                                            </div>
                                            <div class="progress mt-2" style="height: 6px;">
                                                <?php 
                                                $percentage = $medication['stok_miktari'] / max($medication['kritik_stok_seviyesi'] * 2, 1) * 100;
                                                $percentage = min($percentage, 100);
                                                ?>
                                                <div class="progress-bar bg-danger" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                            <p class="text-success">Tüm ilaç stokları yeterli seviyede.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sistem Özeti -->
                        <div class="card report-card">
                            <div class="card-header">
                                <span><i class="fas fa-info-circle me-2"></i>Sistem Özeti</span>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <div class="text-primary fw-bold"><?php echo $total_users; ?></div>
                                            <small class="text-muted">Toplam Kullanıcı</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="border rounded p-2">
                                            <div class="text-success fw-bold"><?php echo $active_users; ?></div>
                                            <small class="text-muted">Aktif Kullanıcı</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="text-warning fw-bold"><?php echo $total_medications; ?></div>
                                            <small class="text-muted">Toplam İlaç</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="text-info fw-bold"><?php echo $total_instructions; ?></div>
                                            <small class="text-muted">Toplam Talimat</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hızlı İşlemler -->
                        <div class="card report-card">
                            <div class="card-header">
                                <span><i class="fas fa-bolt me-2"></i>Hızlı İşlemler</span>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="admin_users.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-users me-1"></i>Kullanıcıları Yönet
                                    </a>
                                    <a href="admin_medications.php" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-pills me-1"></i>İlaçları Yönet
                                    </a>
                                    <a href="admin_emergency.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-first-aid me-1"></i>Acil Durumları Yönet
                                    </a>
                                    <a href="?export_pdf=1&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-file-export me-1"></i>Raporu İndir
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
        // Tarih kontrolleri
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            if(startDate && endDate) {
                // Maksimum tarihi bugün olarak ayarla
                const today = new Date().toISOString().split('T')[0];
                startDate.max = today;
                endDate.max = today;
                
                // Başlangıç tarihi değiştiğinde, bitiş tarihinin minimum değerini ayarla
                startDate.addEventListener('change', function() {
                    endDate.min = this.value;
                    if(endDate.value && endDate.value < this.value) {
                        endDate.value = this.value;
                    }
                });
                
                // Bitiş tarihi değiştiğinde, başlangıç tarihinin maksimum değerini ayarla
                endDate.addEventListener('change', function() {
                    startDate.max = this.value;
                    if(startDate.value && startDate.value > this.value) {
                        startDate.value = this.value;
                    }
                });
                
                // İlk yüklemede min/max değerlerini ayarla
                if(startDate.value) {
                    endDate.min = startDate.value;
                }
                if(endDate.value) {
                    startDate.max = endDate.value;
                }
            }
        });
    </script>
</body>
</html>