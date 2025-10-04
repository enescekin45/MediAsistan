<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Kategorileri getir
$categories_sql = "SELECT * FROM ilk_yardim_kategorileri ORDER BY sira_no, kategori_adi";
$categories = $conn->query($categories_sql);

// Talimatları getir (kategori bilgileriyle birlikte)
$instructions_sql = "SELECT i.*, k.kategori_adi 
                     FROM ilk_yardim_talimatlari i 
                     LEFT JOIN ilk_yardim_kategorileri k ON i.kategori_id = k.kategori_id 
                     ORDER BY k.sira_no, i.adim_numarasi";
$instructions = $conn->query($instructions_sql);

// Kategori ekleme işlemi
if(isset($_POST['add_category'])) {
    $kategori_adi = trim($_POST['kategori_adi']);
    $kategori_ikon = trim($_POST['kategori_ikon']);
    $aciklama = trim($_POST['aciklama']);
    $onem_derecesi = intval($_POST['onem_derecesi']);
    $sira_no = intval($_POST['sira_no']);
    $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
    
    // Kategori adı kontrolü
    $check_sql = "SELECT kategori_id FROM ilk_yardim_kategorileri WHERE kategori_adi = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $kategori_adi);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        $error = "Bu kategori adı zaten mevcut!";
    } else {
        // Kategori ekle
        $insert_sql = "INSERT INTO ilk_yardim_kategorileri (kategori_adi, kategori_ikon, aciklama, onem_derecesi, sira_no, aktif_mi) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssiii", $kategori_adi, $kategori_ikon, $aciklama, $onem_derecesi, $sira_no, $aktif_mi);
        
        if($insert_stmt->execute()) {
            $success = "Kategori başarıyla eklendi!";
            header("Location: admin_emergency.php");
            exit();
        } else {
            $error = "Kategori eklenirken hata oluştu!";
        }
    }
}

// Talimat ekleme işlemi
if(isset($_POST['add_instruction'])) {
    $kategori_id = intval($_POST['kategori_id']);
    $adim_numarasi = intval($_POST['adim_numarasi']);
    $baslik = trim($_POST['baslik']);
    $aciklama = trim($_POST['aciklama']);
    $goruntuleme_suresi = !empty($_POST['goruntuleme_suresi']) ? intval($_POST['goruntuleme_suresi']) : NULL;
    $resim_url = trim($_POST['resim_url']);
    $ses_dosyasi_url = trim($_POST['ses_dosyasi_url']);
    $onemli_uyari = isset($_POST['onemli_uyari']) ? 1 : 0;
    
    // Aynı kategori ve adım numarası kontrolü
    $check_sql = "SELECT talimat_id FROM ilk_yardim_talimatlari WHERE kategori_id = ? AND adim_numarasi = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $kategori_id, $adim_numarasi);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        $error = "Bu kategoride aynı adım numarasına sahip talimat zaten mevcut!";
    } else {
        // Talimat ekle
        $insert_sql = "INSERT INTO ilk_yardim_talimatlari (kategori_id, adim_numarasi, baslik, aciklama, goruntuleme_suresi, resim_url, ses_dosyasi_url, onemli_uyari) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iississi", $kategori_id, $adim_numarasi, $baslik, $aciklama, $goruntuleme_suresi, $resim_url, $ses_dosyasi_url, $onemli_uyari);
        
        if($insert_stmt->execute()) {
            $success = "Talimat başarıyla eklendi!";
            header("Location: admin_emergency.php");
            exit();
        } else {
            $error = "Talimat eklenirken hata oluştu!";
        }
    }
}

// Kategori silme
if(isset($_GET['delete_category'])) {
    $kategori_id = intval($_GET['delete_category']);
    
    // Önce bu kategoriye ait talimatları kontrol et
    $check_sql = "SELECT COUNT(*) as total FROM ilk_yardim_talimatlari WHERE kategori_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $kategori_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $talimat_sayisi = $check_result->fetch_assoc()['total'];
    
    if($talimat_sayisi > 0) {
        $error = "Bu kategoriye ait talimatlar bulunuyor. Önce talimatları silmelisiniz!";
    } else {
        $delete_sql = "DELETE FROM ilk_yardim_kategorileri WHERE kategori_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $kategori_id);
        
        if($delete_stmt->execute()) {
            $success = "Kategori başarıyla silindi!";
            header("Location: admin_emergency.php");
            exit();
        } else {
            $error = "Kategori silinirken hata oluştu!";
        }
    }
}

// Talimat silme
if(isset($_GET['delete_instruction'])) {
    $talimat_id = intval($_GET['delete_instruction']);
    
    $delete_sql = "DELETE FROM ilk_yardim_talimatlari WHERE talimat_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $talimat_id);
    
    if($delete_stmt->execute()) {
        $success = "Talimat başarıyla silindi!";
        header("Location: admin_emergency.php");
        exit();
    } else {
        $error = "Talimat silinirken hata oluştu!";
    }
}

// İstatistikler için sorgular
$total_categories_sql = "SELECT COUNT(*) as total FROM ilk_yardim_kategorileri";
$total_categories_result = $conn->query($total_categories_sql);
$total_categories = $total_categories_result ? $total_categories_result->fetch_assoc()['total'] : 0;

$active_categories_sql = "SELECT COUNT(*) as total FROM ilk_yardim_kategorileri WHERE aktif_mi = 1";
$active_categories_result = $conn->query($active_categories_sql);
$active_categories = $active_categories_result ? $active_categories_result->fetch_assoc()['total'] : 0;

$total_instructions_sql = "SELECT COUNT(*) as total FROM ilk_yardim_talimatlari";
$total_instructions_result = $conn->query($total_instructions_sql);
$total_instructions = $total_instructions_result ? $total_instructions_result->fetch_assoc()['total'] : 0;

$important_instructions_sql = "SELECT COUNT(*) as total FROM ilk_yardim_talimatlari WHERE onemli_uyari = 1";
$important_instructions_result = $conn->query($important_instructions_sql);
$important_instructions = $important_instructions_result ? $important_instructions_result->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acil Durum Yönetimi - MediAsistan</title>
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
        
        .search-box {
            max-width: 300px;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .importance-badge {
            font-size: 0.75rem;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        .warning-step {
            border-left: 4px solid var(--warning);
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 8px 8px;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--primary);
            font-weight: 600;
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
                            <a class="nav-link active" href="admin_emergency.php">
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
                            <i class="fas fa-first-aid me-2"></i>Acil Durum Yönetimi
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

                <!-- İstatistik Kartları -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-primary">
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
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $active_categories; ?></h5>
                                        <p class="card-text">Aktif Kategori</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
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
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title"><?php echo $important_instructions; ?></h5>
                                        <p class="card-text">Önemli Uyarı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Yapısı -->
                <ul class="nav nav-tabs" id="emergencyTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
                            <i class="fas fa-folder me-1"></i>Kategoriler
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="instructions-tab" data-bs-toggle="tab" data-bs-target="#instructions" type="button" role="tab">
                            <i class="fas fa-list-ol me-1"></i>Talimatlar
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="emergencyTabsContent">
                    <!-- Kategoriler Tab -->
                    <div class="tab-pane fade show active" id="categories" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Acil Durum Kategorileri</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus me-1"></i>Yeni Kategori
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kategori</th>
                                        <th>Açıklama</th>
                                        <th>Önem Derecesi</th>
                                        <th>Sıra No</th>
                                        <th>Durum</th>
                                        <th>Oluşturulma</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($categories && $categories->num_rows > 0): ?>
                                        <?php while($category = $categories->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if($category['kategori_ikon']): ?>
                                                        <div class="category-icon me-2">
                                                            <i class="fas fa-<?php echo $category['kategori_ikon']; ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($category['kategori_adi']); ?></div>
                                                        <small class="text-muted">ID: <?php echo $category['kategori_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($category['aciklama'] ?? '-'); ?></td>
                                            <td>
                                                <?php
                                                $importance_class = 'bg-secondary';
                                                $importance_text = 'Düşük';
                                                if($category['onem_derecesi'] == 2) {
                                                    $importance_class = 'bg-warning';
                                                    $importance_text = 'Orta';
                                                } elseif($category['onem_derecesi'] == 3) {
                                                    $importance_class = 'bg-danger';
                                                    $importance_text = 'Yüksek';
                                                }
                                                ?>
                                                <span class="badge <?php echo $importance_class; ?> importance-badge">
                                                    <?php echo $importance_text; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $category['sira_no']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $category['aktif_mi'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $category['aktif_mi'] ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($category['olusturulma_tarihi'])); ?></td>
                                            <td class="action-buttons">
                                                <div class="btn-group" role="group">
                                                    <a href="admin_emergency_edit.php?type=category&id=<?php echo $category['kategori_id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteCategoryModal"
                                                            data-categoryid="<?php echo $category['kategori_id']; ?>"
                                                            data-categoryname="<?php echo htmlspecialchars($category['kategori_adi']); ?>"
                                                            title="Sil">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-folder fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Henüz kategori bulunmuyor.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Talimatlar Tab -->
                    <div class="tab-pane fade" id="instructions" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Acil Durum Talimatları</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstructionModal">
                                <i class="fas fa-plus me-1"></i>Yeni Talimat
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Adım</th>
                                        <th>Kategori</th>
                                        <th>Başlık</th>
                                        <th>Açıklama</th>
                                        <th>Görüntüleme Süresi</th>
                                        <th>Önemli Uyarı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($instructions && $instructions->num_rows > 0): ?>
                                        <?php while($instruction = $instructions->fetch_assoc()): ?>
                                        <tr class="<?php echo $instruction['onemli_uyari'] ? 'warning-step' : ''; ?>">
                                            <td>
                                                <div class="step-number"><?php echo $instruction['adim_numarasi']; ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($instruction['kategori_adi']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($instruction['baslik']); ?></div>
                                                <?php if($instruction['resim_url'] || $instruction['ses_dosyasi_url']): ?>
                                                    <div class="mt-1">
                                                        <?php if($instruction['resim_url']): ?>
                                                            <span class="badge bg-info me-1"><i class="fas fa-image"></i></span>
                                                        <?php endif; ?>
                                                        <?php if($instruction['ses_dosyasi_url']): ?>
                                                            <span class="badge bg-success"><i class="fas fa-volume-up"></i></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $aciklama = $instruction['aciklama'];
                                                if(strlen($aciklama) > 100) {
                                                    echo htmlspecialchars(substr($aciklama, 0, 100)) . '...';
                                                } else {
                                                    echo htmlspecialchars($aciklama);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if($instruction['goruntuleme_suresi']): ?>
                                                    <span class="badge bg-secondary"><?php echo $instruction['goruntuleme_suresi']; ?> sn</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($instruction['onemli_uyari']): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <div class="btn-group" role="group">
                                                    <a href="admin_emergency_edit.php?type=instruction&id=<?php echo $instruction['talimat_id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteInstructionModal"
                                                            data-instructionid="<?php echo $instruction['talimat_id']; ?>"
                                                            data-instructiontitle="<?php echo htmlspecialchars($instruction['baslik']); ?>"
                                                            title="Sil">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-list-ol fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Henüz talimat bulunmuyor.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni Kategori Ekleme Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">
                        <i class="fas fa-folder-plus me-2"></i>Yeni Kategori Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kategori_adi" class="form-label">Kategori Adı *</label>
                            <input type="text" class="form-control" id="kategori_adi" name="kategori_adi" required>
                        </div>
                        <div class="mb-3">
                            <label for="kategori_ikon" class="form-label">Kategori İkonu</label>
                            <input type="text" class="form-control" id="kategori_ikon" name="kategori_ikon" placeholder="Örn: heart, first-aid, ambulance">
                            <div class="form-text">Font Awesome ikon adı (fa- ön eki olmadan)</div>
                        </div>
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="onem_derecesi" class="form-label">Önem Derecesi</label>
                                    <select class="form-select" id="onem_derecesi" name="onem_derecesi">
                                        <option value="1">Düşük</option>
                                        <option value="2">Orta</option>
                                        <option value="3">Yüksek</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sira_no" class="form-label">Sıra No</label>
                                    <input type="number" class="form-control" id="sira_no" name="sira_no" value="1" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="aktif_mi" name="aktif_mi" checked>
                            <label class="form-check-label" for="aktif_mi">
                                Aktif
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_category" class="btn btn-primary">Kategori Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Yeni Talimat Ekleme Modal -->
    <div class="modal fade" id="addInstructionModal" tabindex="-1" aria-labelledby="addInstructionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addInstructionModalLabel">
                        <i class="fas fa-list-plus me-2"></i>Yeni Talimat Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kategori_id" class="form-label">Kategori *</label>
                                    <select class="form-select" id="kategori_id" name="kategori_id" required>
                                        <option value="">Kategori Seçin</option>
                                        <?php 
                                        if($categories && $categories->num_rows > 0) {
                                            $categories->data_seek(0);
                                            while($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['kategori_id']; ?>">
                                                    <?php echo htmlspecialchars($category['kategori_adi']); ?>
                                                </option>
                                            <?php endwhile;
                                        } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="adim_numarasi" class="form-label">Adım Numarası *</label>
                                    <input type="number" class="form-control" id="adim_numarasi" name="adim_numarasi" value="1" min="1" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="baslik" class="form-label">Başlık *</label>
                                    <input type="text" class="form-control" id="baslik" name="baslik" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama *</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="4" required></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="goruntuleme_suresi" class="form-label">Görüntüleme Süresi (saniye)</label>
                                    <input type="number" class="form-control" id="goruntuleme_suresi" name="goruntuleme_suresi" min="1" placeholder="Opsiyonel">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="onemli_uyari" class="form-label">Önemli Uyarı</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="onemli_uyari" name="onemli_uyari">
                                        <label class="form-check-label" for="onemli_uyari">
                                            Önemli uyarı olarak işaretle
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="resim_url" class="form-label">Resim URL</label>
                                    <input type="url" class="form-control" id="resim_url" name="resim_url" placeholder="https://...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ses_dosyasi_url" class="form-label">Ses Dosyası URL</label>
                                    <input type="url" class="form-control" id="ses_dosyasi_url" name="ses_dosyasi_url" placeholder="https://...">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_instruction" class="btn btn-primary">Talimat Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kategori Silme Onay Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteCategoryModalLabel">Kategori Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><span id="categoryName"></span> adlı kategoriyi silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz! Kategoriye ait tüm talimatlar da silinecektir.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <a href="#" id="confirmDeleteCategory" class="btn btn-danger">Sil</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Talimat Silme Onay Modal -->
    <div class="modal fade" id="deleteInstructionModal" tabindex="-1" aria-labelledby="deleteInstructionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteInstructionModalLabel">Talimat Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><span id="instructionTitle"></span> adlı talimatı silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz!</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <a href="#" id="confirmDeleteInstruction" class="btn btn-danger">Sil</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Kategori silme modalını ayarla
        var deleteCategoryModal = document.getElementById('deleteCategoryModal');
        deleteCategoryModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var categoryId = button.getAttribute('data-categoryid');
            var categoryName = button.getAttribute('data-categoryname');
            
            var modalBody = deleteCategoryModal.querySelector('.modal-body #categoryName');
            modalBody.textContent = categoryName;
            
            var confirmDelete = deleteCategoryModal.querySelector('#confirmDeleteCategory');
            confirmDelete.href = '?delete_category=' + categoryId;
        });
        
        // Talimat silme modalını ayarla
        var deleteInstructionModal = document.getElementById('deleteInstructionModal');
        deleteInstructionModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var instructionId = button.getAttribute('data-instructionid');
            var instructionTitle = button.getAttribute('data-instructiontitle');
            
            var modalBody = deleteInstructionModal.querySelector('.modal-body #instructionTitle');
            modalBody.textContent = instructionTitle;
            
            var confirmDelete = deleteInstructionModal.querySelector('#confirmDeleteInstruction');
            confirmDelete.href = '?delete_instruction=' + instructionId;
        });

        // Tab değişikliğinde URL hash'ini güncelle
        document.addEventListener('DOMContentLoaded', function() {
            var triggerTabList = [].slice.call(document.querySelectorAll('#emergencyTabs button'));
            triggerTabList.forEach(function (triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', function (event) {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });
            
            // URL'de hash varsa ilgili tab'ı aç
            if(window.location.hash) {
                var hash = window.location.hash.substring(1);
                var triggerEl = document.querySelector('#emergencyTabs button[data-bs-target="#' + hash + '"]');
                if(triggerEl) {
                    bootstrap.Tab.getInstance(triggerEl).show();
                }
            }
            
            // Tab değiştiğinde URL hash'ini güncelle
            var tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabEls.forEach(function(tabEl) {
                tabEl.addEventListener('shown.bs.tab', function (event) {
                    var target = event.target.getAttribute('data-bs-target');
                    if(target) {
                        window.location.hash = target.substring(1);
                    }
                });
            });
        });
    </script>
</body>
</html>