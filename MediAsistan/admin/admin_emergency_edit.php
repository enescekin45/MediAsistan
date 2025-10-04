<?php
session_start();
require_once '../config/config.php';

// Admin giriş kontrolü
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Düzenleme türü ve ID kontrolü
if(!isset($_GET['type']) || !isset($_GET['id'])) {
    header('Location: admin_emergency.php');
    exit();
}

$type = $_GET['type'];
$id = intval($_GET['id']);

if($type == 'category') {
    // Kategori bilgilerini getir
    $sql = "SELECT * FROM ilk_yardim_kategorileri WHERE kategori_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if(!$item) {
        header('Location: admin_emergency.php');
        exit();
    }
    
    // Kategori güncelleme işlemi
    if(isset($_POST['update_category'])) {
        $kategori_adi = trim($_POST['kategori_adi']);
        $kategori_ikon = trim($_POST['kategori_ikon']);
        $aciklama = trim($_POST['aciklama']);
        $onem_derecesi = intval($_POST['onem_derecesi']);
        $sira_no = intval($_POST['sira_no']);
        $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
        
        // Kategori adı kontrolü (kendisi hariç)
        $check_sql = "SELECT kategori_id FROM ilk_yardim_kategorileri WHERE kategori_adi = ? AND kategori_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $kategori_adi, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error = "Bu kategori adı zaten mevcut!";
        } else {
            // Kategori güncelle
            $update_sql = "UPDATE ilk_yardim_kategorileri SET kategori_adi = ?, kategori_ikon = ?, aciklama = ?, onem_derecesi = ?, sira_no = ?, aktif_mi = ? WHERE kategori_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssiiii", $kategori_adi, $kategori_ikon, $aciklama, $onem_derecesi, $sira_no, $aktif_mi, $id);
            
            if($update_stmt->execute()) {
                $success = "Kategori başarıyla güncellendi!";
                header("Location: admin_emergency.php");
                exit();
            } else {
                $error = "Kategori güncellenirken hata oluştu!";
            }
        }
    }
} elseif($type == 'instruction') {
    // Talimat bilgilerini getir
    $sql = "SELECT i.*, k.kategori_adi FROM ilk_yardim_talimatlari i 
            LEFT JOIN ilk_yardim_kategorileri k ON i.kategori_id = k.kategori_id 
            WHERE i.talimat_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if(!$item) {
        header('Location: admin_emergency.php');
        exit();
    }
    
    // Talimat güncelleme işlemi
    if(isset($_POST['update_instruction'])) {
        $kategori_id = intval($_POST['kategori_id']);
        $adim_numarasi = intval($_POST['adim_numarasi']);
        $baslik = trim($_POST['baslik']);
        $aciklama = trim($_POST['aciklama']);
        $goruntuleme_suresi = !empty($_POST['goruntuleme_suresi']) ? intval($_POST['goruntuleme_suresi']) : NULL;
        $resim_url = trim($_POST['resim_url']);
        $ses_dosyasi_url = trim($_POST['ses_dosyasi_url']);
        $onemli_uyari = isset($_POST['onemli_uyari']) ? 1 : 0;
        
        // Aynı kategori ve adım numarası kontrolü (kendisi hariç)
        $check_sql = "SELECT talimat_id FROM ilk_yardim_talimatlari WHERE kategori_id = ? AND adim_numarasi = ? AND talimat_id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iii", $kategori_id, $adim_numarasi, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if($check_result->num_rows > 0) {
            $error = "Bu kategoride aynı adım numarasına sahip talimat zaten mevcut!";
        } else {
            // Talimat güncelle
            $update_sql = "UPDATE ilk_yardim_talimatlari SET kategori_id = ?, adim_numarasi = ?, baslik = ?, aciklama = ?, goruntuleme_suresi = ?, resim_url = ?, ses_dosyasi_url = ?, onemli_uyari = ? WHERE talimat_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iississii", $kategori_id, $adim_numarasi, $baslik, $aciklama, $goruntuleme_suresi, $resim_url, $ses_dosyasi_url, $onemli_uyari, $id);
            
            if($update_stmt->execute()) {
                $success = "Talimat başarıyla güncellendi!";
                header("Location: admin_emergency.php");
                exit();
            } else {
                $error = "Talimat güncellenirken hata oluştu!";
            }
        }
    }
} else {
    header('Location: admin_emergency.php');
    exit();
}

// Kategorileri getir (talimat düzenleme için)
$categories_sql = "SELECT * FROM ilk_yardim_kategorileri ORDER BY sira_no, kategori_adi";
$categories = $conn->query($categories_sql);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $type == 'category' ? 'Kategori Düzenle' : 'Talimat Düzenle'; ?> - MediAsistan</title>
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
        
        .admin-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .category-icon-preview {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .step-number-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
            margin-bottom: 1rem;
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
                            <i class="fas fa-edit me-2"></i>
                            <?php echo $type == 'category' ? 'Kategori Düzenle' : 'Talimat Düzenle'; ?>
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
                    <div class="col-12">
                        <div class="form-container">
                            <?php if($type == 'category'): ?>
                                <!-- Kategori Düzenleme Formu -->
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="kategori_adi" class="form-label">Kategori Adı *</label>
                                                <input type="text" class="form-control" id="kategori_adi" name="kategori_adi" value="<?php echo htmlspecialchars($item['kategori_adi']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="aciklama" class="form-label">Açıklama</label>
                                                <textarea class="form-control" id="aciklama" name="aciklama" rows="4"><?php echo htmlspecialchars($item['aciklama'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="onem_derecesi" class="form-label">Önem Derecesi</label>
                                                        <select class="form-select" id="onem_derecesi" name="onem_derecesi">
                                                            <option value="1" <?php echo $item['onem_derecesi'] == 1 ? 'selected' : ''; ?>>Düşük</option>
                                                            <option value="2" <?php echo $item['onem_derecesi'] == 2 ? 'selected' : ''; ?>>Orta</option>
                                                            <option value="3" <?php echo $item['onem_derecesi'] == 3 ? 'selected' : ''; ?>>Yüksek</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="sira_no" class="form-label">Sıra No</label>
                                                        <input type="number" class="form-control" id="sira_no" name="sira_no" value="<?php echo $item['sira_no']; ?>" min="1">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="kategori_ikon" class="form-label">Kategori İkonu</label>
                                                        <input type="text" class="form-control" id="kategori_ikon" name="kategori_ikon" value="<?php echo htmlspecialchars($item['kategori_ikon'] ?? ''); ?>" placeholder="Örn: heart, first-aid, ambulance">
                                                        <div class="form-text">Font Awesome ikon adı (fa- ön eki olmadan)</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Durum</label>
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" id="aktif_mi" name="aktif_mi" <?php echo $item['aktif_mi'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="aktif_mi">
                                                                Aktif
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Önizleme</h6>
                                                    <?php if($item['kategori_ikon']): ?>
                                                        <div class="category-icon-preview mx-auto">
                                                            <i class="fas fa-<?php echo $item['kategori_ikon']; ?>" id="iconPreview"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="category-icon-preview mx-auto bg-secondary">
                                                            <i class="fas fa-folder" id="iconPreview"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <h5 id="titlePreview"><?php echo htmlspecialchars($item['kategori_adi']); ?></h5>
                                                    <p class="text-muted small">Kategori Önizlemesi</p>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="update_category" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i>Değişiklikleri Kaydet
                                                    </button>
                                                    <a href="admin_emergency.php" class="btn btn-secondary">
                                                        <i class="fas fa-arrow-left me-1"></i>Geri Dön
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                            <?php else: ?>
                                <!-- Talimat Düzenleme Formu -->
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="kategori_id" class="form-label">Kategori *</label>
                                                        <select class="form-select" id="kategori_id" name="kategori_id" required>
                                                            <option value="">Kategori Seçin</option>
                                                            <?php if($categories && $categories->num_rows > 0): ?>
                                                                <?php while($category = $categories->fetch_assoc()): ?>
                                                                    <option value="<?php echo $category['kategori_id']; ?>" <?php echo $item['kategori_id'] == $category['kategori_id'] ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($category['kategori_adi']); ?>
                                                                    </option>
                                                                <?php endwhile; ?>
                                                            <?php endif; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="adim_numarasi" class="form-label">Adım Numarası *</label>
                                                        <input type="number" class="form-control" id="adim_numarasi" name="adim_numarasi" value="<?php echo $item['adim_numarasi']; ?>" min="1" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="baslik" class="form-label">Başlık *</label>
                                                <input type="text" class="form-control" id="baslik" name="baslik" value="<?php echo htmlspecialchars($item['baslik']); ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="aciklama" class="form-label">Açıklama *</label>
                                                <textarea class="form-control" id="aciklama" name="aciklama" rows="6" required><?php echo htmlspecialchars($item['aciklama']); ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="goruntuleme_suresi" class="form-label">Görüntüleme Süresi (saniye)</label>
                                                        <input type="number" class="form-control" id="goruntuleme_suresi" name="goruntuleme_suresi" value="<?php echo $item['goruntuleme_suresi'] ?? ''; ?>" min="1" placeholder="Opsiyonel">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="onemli_uyari" class="form-label">Önemli Uyarı</label>
                                                        <div class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" id="onemli_uyari" name="onemli_uyari" <?php echo $item['onemli_uyari'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="onemli_uyari">
                                                                Önemli uyarı olarak işaretle
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="resim_url" class="form-label">Resim URL</label>
                                                        <input type="url" class="form-control" id="resim_url" name="resim_url" value="<?php echo htmlspecialchars($item['resim_url'] ?? ''); ?>" placeholder="https://...">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="ses_dosyasi_url" class="form-label">Ses Dosyası URL</label>
                                                        <input type="url" class="form-control" id="ses_dosyasi_url" name="ses_dosyasi_url" value="<?php echo htmlspecialchars($item['ses_dosyasi_url'] ?? ''); ?>" placeholder="https://...">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6 class="card-title">Önizleme</h6>
                                                    <div class="step-number-preview mx-auto">
                                                        <?php echo $item['adim_numarasi']; ?>
                                                    </div>
                                                    <h5 id="instructionTitlePreview"><?php echo htmlspecialchars($item['baslik']); ?></h5>
                                                    <p class="text-muted small" id="instructionCategoryPreview">
                                                        Kategori: <?php echo htmlspecialchars($item['kategori_adi']); ?>
                                                    </p>
                                                    <div class="mt-2">
                                                        <?php if($item['resim_url']): ?>
                                                            <span class="badge bg-info me-1"><i class="fas fa-image"></i> Resim</span>
                                                        <?php endif; ?>
                                                        <?php if($item['ses_dosyasi_url']): ?>
                                                            <span class="badge bg-success"><i class="fas fa-volume-up"></i> Ses</span>
                                                        <?php endif; ?>
                                                        <?php if($item['onemli_uyari']): ?>
                                                            <span class="badge bg-warning mt-1"><i class="fas fa-exclamation-triangle"></i> Önemli</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3">
                                                <div class="d-grid gap-2">
                                                    <button type="submit" name="update_instruction" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i>Değişiklikleri Kaydet
                                                    </button>
                                                    <a href="admin_emergency.php" class="btn btn-secondary">
                                                        <i class="fas fa-arrow-left me-1"></i>Geri Dön
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Kategori ikon önizleme
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($type == 'category'): ?>
            const kategoriIkonInput = document.getElementById('kategori_ikon');
            const kategoriAdiInput = document.getElementById('kategori_adi');
            const iconPreview = document.getElementById('iconPreview');
            const titlePreview = document.getElementById('titlePreview');
            
            if(kategoriIkonInput) {
                kategoriIkonInput.addEventListener('input', function() {
                    if(this.value) {
                        iconPreview.className = 'fas fa-' + this.value;
                    } else {
                        iconPreview.className = 'fas fa-folder';
                    }
                });
            }
            
            if(kategoriAdiInput) {
                kategoriAdiInput.addEventListener('input', function() {
                    titlePreview.textContent = this.value;
                });
            }
            <?php else: ?>
            // Talimat önizleme
            const baslikInput = document.getElementById('baslik');
            const kategoriSelect = document.getElementById('kategori_id');
            const adimNumarasiInput = document.getElementById('adim_numarasi');
            const instructionTitlePreview = document.getElementById('instructionTitlePreview');
            const instructionCategoryPreview = document.getElementById('instructionCategoryPreview');
            const stepNumberPreview = document.querySelector('.step-number-preview');
            
            if(baslikInput) {
                baslikInput.addEventListener('input', function() {
                    instructionTitlePreview.textContent = this.value;
                });
            }
            
            if(adimNumarasiInput) {
                adimNumarasiInput.addEventListener('input', function() {
                    stepNumberPreview.textContent = this.value;
                });
            }
            
            if(kategoriSelect) {
                kategoriSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if(selectedOption.text !== 'Kategori Seçin') {
                        instructionCategoryPreview.textContent = 'Kategori: ' + selectedOption.text;
                    }
                });
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>