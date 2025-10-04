<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Kullanıcıları getir
$sql = "SELECT kullanici_id, eposta, ad, soyad, dogum_tarihi, kan_grubu, telefon, olusturulma_tarihi, durum 
        FROM kullanicilar 
        ORDER BY olusturulma_tarihi DESC";
$users = $conn->query($sql);

// Kullanıcı ekleme işlemi
if(isset($_POST['add_user'])) {
    $eposta = trim($_POST['eposta']);
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $sifre = $_POST['sifre'];
    $dogum_tarihi = $_POST['dogum_tarihi'];
    $kan_grubu = $_POST['kan_grubu'];
    $telefon = trim($_POST['telefon']);
    
    // E-posta kontrolü
    $check_sql = "SELECT kullanici_id FROM kullanicilar WHERE eposta = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $eposta);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        $error = "Bu e-posta adresi zaten kayıtlı!";
    } else {
        // Kullanıcı ekle
        $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO kullanicilar (eposta, sifre_hash, ad, soyad, dogum_tarihi, kan_grubu, telefon) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssssss", $eposta, $sifre_hash, $ad, $soyad, $dogum_tarihi, $kan_grubu, $telefon);
        
        if($insert_stmt->execute()) {
            $success = "Kullanıcı başarıyla eklendi!";
            header("Location: admin_users.php");
            exit();
        } else {
            $error = "Kullanıcı eklenirken hata oluştu!";
        }
    }
}

// Kullanıcı durumu değiştirme
if(isset($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);
    $current_status = $_GET['current_status'];
    $new_status = ($current_status == 'aktif') ? 'pasif' : 'aktif';
    
    $update_sql = "UPDATE kullanicilar SET durum = ? WHERE kullanici_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $user_id);
    
    if($update_stmt->execute()) {
        $success = "Kullanıcı durumu güncellendi!";
        header("Location: admin_users.php");
        exit();
    } else {
        $error = "Durum güncellenirken hata oluştu!";
    }
}

// Kullanıcı silme
if(isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);
    
    $delete_sql = "DELETE FROM kullanicilar WHERE kullanici_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    
    if($delete_stmt->execute()) {
        $success = "Kullanıcı başarıyla silindi!";
        header("Location: admin_users.php");
        exit();
    } else {
        $error = "Kullanıcı silinirken hata oluştu!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - MediAsistan</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .search-box {
            max-width: 300px;
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
                            <a class="nav-link active" href="admin_users.php">
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
                            <i class="fas fa-users me-2"></i>Kullanıcı Yönetimi
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
                                        $total_users_sql = "SELECT COUNT(*) as total FROM kullanicilar";
                                        $total_users_result = $conn->query($total_users_sql);
                                        $total_users = $total_users_result->fetch_assoc()['total'];
                                        ?>
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
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <?php
                                        $active_users_sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'";
                                        $active_users_result = $conn->query($active_users_sql);
                                        $active_users = $active_users_result->fetch_assoc()['total'];
                                        ?>
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
                    <div class="col-md-3">
                        <div class="card stat-card text-white bg-gradient-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <?php
                                        $inactive_users_sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'pasif'";
                                        $inactive_users_result = $conn->query($inactive_users_sql);
                                        $inactive_users = $inactive_users_result->fetch_assoc()['total'];
                                        ?>
                                        <h5 class="card-title"><?php echo $inactive_users; ?></h5>
                                        <p class="card-text">Pasif Kullanıcı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-times"></i>
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
                                        <?php
                                        $today = date('Y-m-d');
                                        $new_users_sql = "SELECT COUNT(*) as total FROM kullanicilar WHERE DATE(olusturulma_tarihi) = ?";
                                        $new_users_stmt = $conn->prepare($new_users_sql);
                                        $new_users_stmt->bind_param("s", $today);
                                        $new_users_stmt->execute();
                                        $new_users_result = $new_users_stmt->get_result();
                                        $new_users = $new_users_result->fetch_assoc()['total'];
                                        ?>
                                        <h5 class="card-title"><?php echo $new_users; ?></h5>
                                        <p class="card-text">Bugün Kayıtlı</p>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kullanıcı Yönetimi -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-list me-2"></i>Tüm Kullanıcılar
                                </h5>
                                <div class="d-flex">
                                    <div class="input-group search-box me-2">
                                        <input type="text" class="form-control" placeholder="Kullanıcı ara...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kullanıcı</th>
                                                <th>E-posta</th>
                                                <th>Telefon</th>
                                                <th>Kan Grubu</th>
                                                <th>Doğum Tarihi</th>
                                                <th>Kayıt Tarihi</th>
                                                <th>Durum</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if($users && $users->num_rows > 0): ?>
                                                <?php while($user = $users->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-2">
                                                                <?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                                                                <small class="text-muted">ID: <?php echo $user['kullanici_id']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['eposta']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['telefon'] ?? 'Belirtilmemiş'); ?></td>
                                                    <td>
                                                        <?php if($user['kan_grubu']): ?>
                                                            <span class="badge bg-light text-dark"><?php echo $user['kan_grubu']; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Belirtilmemiş</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if($user['dogum_tarihi']): ?>
                                                            <?php echo date('d.m.Y', strtotime($user['dogum_tarihi'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Belirtilmemiş</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($user['olusturulma_tarihi'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $user['durum'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                                            <?php echo $user['durum'] == 'aktif' ? 'Aktif' : 'Pasif'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="action-buttons">
                                                        <div class="btn-group" role="group">
                                                            <a href="?toggle_status=<?php echo $user['kullanici_id']; ?>&current_status=<?php echo $user['durum']; ?>" 
                                                               class="btn btn-sm btn-<?php echo $user['durum'] == 'aktif' ? 'warning' : 'success'; ?>"
                                                               title="<?php echo $user['durum'] == 'aktif' ? 'Pasif Yap' : 'Aktif Yap'; ?>">
                                                                <i class="fas fa-<?php echo $user['durum'] == 'aktif' ? 'pause' : 'play'; ?>"></i>
                                                            </a>
                                                            <a href="admin_user_edit.php?id=<?php echo $user['kullanici_id']; ?>" 
                                                               class="btn btn-sm btn-primary" title="Düzenle">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-danger" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#deleteModal"
                                                                    data-userid="<?php echo $user['kullanici_id']; ?>"
                                                                    data-username="<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>"
                                                                    title="Sil">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">Henüz kayıtlı kullanıcı bulunmuyor.</p>
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

    <!-- Yeni Kullanıcı Ekleme Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Yeni Kullanıcı Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="eposta" class="form-label">E-posta *</label>
                                    <input type="email" class="form-control" id="eposta" name="eposta" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sifre" class="form-label">Şifre *</label>
                                    <input type="password" class="form-control" id="sifre" name="sifre" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ad" class="form-label">Ad *</label>
                                    <input type="text" class="form-control" id="ad" name="ad" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soyad" class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                    <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="kan_grubu" class="form-label">Kan Grubu</label>
                                    <select class="form-select" id="kan_grubu" name="kan_grubu">
                                        <option value="">Seçiniz</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="0+">0+</option>
                                        <option value="0-">0-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="telefon" name="telefon">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Kullanıcı Ekle</button>
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
                    <h5 class="modal-title" id="deleteModalLabel">Kullanıcı Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><span id="userName"></span> adlı kullanıcıyı silmek istediğinizden emin misiniz?</p>
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
            var userId = button.getAttribute('data-userid');
            var userName = button.getAttribute('data-username');
            
            var modalBody = deleteModal.querySelector('.modal-body #userName');
            modalBody.textContent = userName;
            
            var confirmDelete = deleteModal.querySelector('#confirmDelete');
            confirmDelete.href = '?delete_user=' + userId;
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
    </script>
</body>
</html>