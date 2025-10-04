<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// İlaçları getir (tüm kullanıcıların ilaçlarını)
$sql = "SELECT i.*, k.ad as kullanici_adi, k.soyad as kullanici_soyadi 
        FROM ilaclar i 
        LEFT JOIN kullanicilar k ON i.kullanici_id = k.kullanici_id 
        ORDER BY i.olusturulma_tarihi DESC";
$medications = $conn->query($sql);

// İlaç ekleme işlemi
if(isset($_POST['add_medication'])) {
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
    
    // Barkod kontrolü (isteğe bağlı)
    if(!empty($barkod)) {
        $check_sql = "SELECT ilac_id FROM ilaclar WHERE barkod = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $barkod);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error = "Bu barkod numarası zaten kayıtlı!";
        }
    }
    
    if(!isset($error)) {
        // İlaç ekle
        $insert_sql = "INSERT INTO ilaclar (kullanici_id, ilac_adi, dozaj, stok_adedi, kritik_stok_seviyesi, receteli_mi, ilac_tipi, barkod, baslama_tarihi, bitis_tarihi, aciklama) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("issiiisssss", $kullanici_id, $ilac_adi, $dozaj, $stok_adedi, $kritik_stok_seviyesi, $receteli_mi, $ilac_tipi, $barkod, $baslama_tarihi, $bitis_tarihi, $aciklama);
        
        if($insert_stmt->execute()) {
            $success = "İlaç başarıyla eklendi!";
            header("Location: admin_medications.php");
            exit();
        } else {
            $error = "İlaç eklenirken hata oluştu!";
        }
    }
}

// İlaç silme
if(isset($_GET['delete_medication'])) {
    $ilac_id = intval($_GET['delete_medication']);
    
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

// Aktif kullanıcıları getir (dropdown için)
$users_sql = "SELECT kullanici_id, ad, soyad FROM kullanicilar WHERE durum = 'aktif' ORDER BY ad, soyad";
$users = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlaç Yönetimi - MediAsistan</title>
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
        
        .stock-critical {
            background-color: #fee2e2 !important;
        }
        
        .stock-low {
            background-color: #fef3c7 !important;
        }
        
        .medication-icon {
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
        
        .badge-prescription {
            background-color: #7c3aed;
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
                            <i class="fas fa-pills me-2"></i>İlaç Yönetimi
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
                                        <?php
                                        $total_medications_sql = "SELECT COUNT(*) as total FROM ilaclar";
                                        $total_medications_result = $conn->query($total_medications_sql);
                                        $total_medications = $total_medications_result->fetch_assoc()['total'];
                                        ?>
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
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <?php
                                        $normal_stock_sql = "SELECT COUNT(*) as total FROM ilaclar WHERE stok_adedi > kritik_stok_seviyesi";
                                        $normal_stock_result = $conn->query($normal_stock_sql);
                                        $normal_stock = $normal_stock_result->fetch_assoc()['total'];
                                        ?>
                                        <h5 class="card-title"><?php echo $normal_stock; ?></h5>
                                        <p class="card-text">Normal Stok</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-check-circle"></i>
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
                                        <?php
                                        $low_stock_sql = "SELECT COUNT(*) as total FROM ilaclar WHERE stok_adedi <= kritik_stok_seviyesi AND stok_adedi > 0";
                                        $low_stock_result = $conn->query($low_stock_sql);
                                        $low_stock = $low_stock_result->fetch_assoc()['total'];
                                        ?>
                                        <h5 class="card-title"><?php echo $low_stock; ?></h5>
                                        <p class="card-text">Düşük Stok</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-exclamation-triangle"></i>
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
                                        <?php
                                        $out_of_stock_sql = "SELECT COUNT(*) as total FROM ilaclar WHERE stok_adedi = 0";
                                        $out_of_stock_result = $conn->query($out_of_stock_sql);
                                        $out_of_stock = $out_of_stock_result->fetch_assoc()['total'];
                                        ?>
                                        <h5 class="card-title"><?php echo $out_of_stock; ?></h5>
                                        <p class="card-text">Stokta Yok</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İlaç Yönetimi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Tüm İlaçlar
                                </h5>
                                <div class="d-flex">
                                    <div class="input-group search-box me-2">
                                        <input type="text" class="form-control" placeholder="İlaç ara...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicationModal">
                                        <i class="fas fa-plus me-1"></i>Yeni İlaç
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>İlaç</th>
                                                <th>Kullanıcı</th>
                                                <th>Dozaj</th>
                                                <th>Stok</th>
                                                <th>Kritik Stok</th>
                                                <th>İlaç Tipi</th>
                                                <th>Reçete</th>
                                                <th>Başlangıç</th>
                                                <th>Bitiş</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($medications && $medications->num_rows > 0): ?>
                                                <?php while($med = $medications->fetch_assoc()): 
                                                    $stock_class = '';
                                                    if($med['stok_adedi'] == 0) {
                                                        $stock_class = 'stock-critical';
                                                    } elseif($med['stok_adedi'] <= $med['kritik_stok_seviyesi']) {
                                                        $stock_class = 'stock-low';
                                                    }
                                                ?>
                                                <tr class="<?php echo $stock_class; ?>">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="medication-icon me-2">
                                                                <i class="fas fa-pills"></i>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($med['ilac_adi']); ?></div>
                                                                <?php if($med['barkod']): ?>
                                                                    <small class="text-muted">Barkod: <?php echo htmlspecialchars($med['barkod']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if($med['kullanici_adi']): ?>
                                                            <?php echo htmlspecialchars($med['kullanici_adi'] . ' ' . $med['kullanici_soyadi']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Kullanıcı silinmiş</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($med['dozaj']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            if($med['stok_adedi'] == 0) echo 'danger';
                                                            elseif($med['stok_adedi'] <= $med['kritik_stok_seviyesi']) echo 'warning';
                                                            else echo 'success';
                                                        ?>">
                                                            <?php echo $med['stok_adedi']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $med['kritik_stok_seviyesi']; ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($med['ilac_tipi']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if($med['receteli_mi']): ?>
                                                            <span class="badge badge-prescription">Reçeteli</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Reçetesiz</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($med['baslama_tarihi']): ?>
                                                            <?php echo date('d.m.Y', strtotime($med['baslama_tarihi'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($med['bitis_tarihi']): ?>
                                                            <?php echo date('d.m.Y', strtotime($med['bitis_tarihi'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="action-buttons">
                                                        <div class="btn-group" role="group">
                                                            <a href="admin_medication_edit.php?id=<?php echo $med['ilac_id']; ?>" 
                                                               class="btn btn-sm btn-primary" title="Düzenle">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteModal"
                                                                    data-medid="<?php echo $med['ilac_id']; ?>"
                                                                    data-medname="<?php echo htmlspecialchars($med['ilac_adi']); ?>"
                                                                    title="Sil">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">Henüz kayıtlı ilaç bulunmuyor.</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Sayfalama -->
                                <nav aria-label="Sayfalama">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1">Önceki</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Sonraki</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Yeni İlaç Ekleme Modal -->
    <div class="modal fade" id="addMedicationModal" tabindex="-1" aria-labelledby="addMedicationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMedicationModalLabel">
                        <i class="fas fa-pills me-2"></i>Yeni İlaç Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kullanici_id" class="form-label">Kullanıcı *</label>
                                    <select class="form-select" id="kullanici_id" name="kullanici_id" required>
                                        <option value="">Kullanıcı Seçin</option>
                                        <?php while($user = $users->fetch_assoc()): ?>
                                            <option value="<?php echo $user['kullanici_id']; ?>">
                                                <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ilac_adi" class="form-label">İlaç Adı *</label>
                                    <input type="text" class="form-control" id="ilac_adi" name="ilac_adi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dozaj" class="form-label">Dozaj *</label>
                                    <input type="text" class="form-control" id="dozaj" name="dozaj" placeholder="Örn: 500mg, 10ml" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ilac_tipi" class="form-label">İlaç Tipi</label>
                                    <select class="form-select" id="ilac_tipi" name="ilac_tipi">
                                        <option value="tablet">Tablet</option>
                                        <option value="sıvı">Sıvı</option>
                                        <option value="kapsül">Kapsül</option>
                                        <option value="sprey">Sprey</option>
                                        <option value="merhem">Merhem</option>
                                        <option value="damla">Damla</option>
                                        <option value="injeksiyon">Enjeksiyon</option>
                                        <option value="diğer">Diğer</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="stok_adedi" class="form-label">Stok Adedi</label>
                                    <input type="number" class="form-control" id="stok_adedi" name="stok_adedi" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="kritik_stok_seviyesi" class="form-label">Kritik Stok</label>
                                    <input type="number" class="form-control" id="kritik_stok_seviyesi" name="kritik_stok_seviyesi" value="3" min="1">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="barkod" class="form-label">Barkod</label>
                                    <input type="text" class="form-control" id="barkod" name="barkod" placeholder="Opsiyonel">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="baslama_tarihi" class="form-label">Başlangıç Tarihi</label>
                                    <input type="date" class="form-control" id="baslama_tarihi" name="baslama_tarihi">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                    <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3" placeholder="İlaç hakkında notlar..."></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="receteli_mi" name="receteli_mi">
                                    <label class="form-check-label" for="receteli_mi">
                                        Reçeteli İlaç
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_medication" class="btn btn-primary">İlaç Ekle</button>
                    </div>
                </form>
            </div>
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
                    <p><span id="medName"></span> adlı ilacı silmek istediğinizden emin misiniz?</p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz!</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Sil</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Silme modalını ayarla
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var medId = button.getAttribute('data-medid');
            var medName = button.getAttribute('data-medname');
            
            var modalBody = deleteModal.querySelector('.modal-body #medName');
            modalBody.textContent = medName;
            
            var confirmDelete = deleteModal.querySelector('#confirmDelete');
            confirmDelete.href = '?delete_medication=' + medId;
        });
        
        // Arama fonksiyonu
        document.querySelector('.search-box input').addEventListener('keyup', function() {
            var filter = this.value.toLowerCase();
            var rows = document.querySelectorAll('table tbody tr');
            
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Tarih kontrolleri
        document.addEventListener('DOMContentLoaded', function() {
            const baslamaTarihi = document.getElementById('baslama_tarihi');
            const bitisTarihi = document.getElementById('bitis_tarihi');
            
            // Bugünün tarihini varsayılan olarak ayarla
            const today = new Date().toISOString().split('T')[0];
            baslamaTarihi.value = today;
            
            // Bitiş tarihi başlangıç tarihinden önce olamaz
            baslamaTarihi.addEventListener('change', function() {
                bitisTarihi.min = this.value;
            });
        });
    </script>
</body>
</html>