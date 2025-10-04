<?php
session_start();
require_once 'config/config.php';

// Kullanıcı giriş yapmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// İlaç ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ilac_ekle'])) {
    $ilac_adi = trim($_POST['ilac_adi']);
    $dozaj = trim($_POST['dozaj']);
    $stok_adedi = intval($_POST['stok_adedi']);
    $kritik_stok = intval($_POST['kritik_stok']);
    $ilac_tipi = $_POST['ilac_tipi'];
    $aciklama = trim($_POST['aciklama']);
    
    if (!empty($ilac_adi) && !empty($dozaj)) {
        $sql = "INSERT INTO ilaclar (kullanici_id, ilac_adi, dozaj, stok_adedi, kritik_stok_seviyesi, ilac_tipi, aciklama) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issiiss", $_SESSION['user_id'], $ilac_adi, $dozaj, $stok_adedi, $kritik_stok, $ilac_tipi, $aciklama);
        
        if ($stmt->execute()) {
            $basari_mesaji = "İlaç başarıyla eklendi!";
        } else {
            $hata_mesaji = "İlaç eklenirken hata oluştu: " . $conn->error;
        }
        $stmt->close();
    }
}

// İlaç düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ilac_guncelle'])) {
    $ilac_id = intval($_POST['ilac_id']);
    $ilac_adi = trim($_POST['ilac_adi']);
    $dozaj = trim($_POST['dozaj']);
    $stok_adedi = intval($_POST['stok_adedi']);
    $kritik_stok = intval($_POST['kritik_stok']);
    $ilac_tipi = $_POST['ilac_tipi'];
    $aciklama = trim($_POST['aciklama']);
    
    if (!empty($ilac_adi) && !empty($dozaj)) {
        $sql = "UPDATE ilaclar SET ilac_adi = ?, dozaj = ?, stok_adedi = ?, kritik_stok_seviyesi = ?, ilac_tipi = ?, aciklama = ? 
                WHERE ilac_id = ? AND kullanici_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiissii", $ilac_adi, $dozaj, $stok_adedi, $kritik_stok, $ilac_tipi, $aciklama, $ilac_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $basari_mesaji = "İlaç başarıyla güncellendi!";
            // Düzenleme modundan çık
            unset($_GET['duzenle']);
            header("Location: medications.php");
            exit;
        } else {
            $hata_mesaji = "İlaç güncellenirken hata oluştu: " . $conn->error;
        }
        $stmt->close();
    }
}

// İlaç silme işlemi
if (isset($_GET['sil'])) {
    $ilac_id = intval($_GET['sil']);
    
    // İlacın kullanıcıya ait olduğunu kontrol et
    $sql = "DELETE FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ilac_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $basari_mesaji = "İlaç başarıyla silindi!";
    } else {
        $hata_mesaji = "İlaç silinirken hata oluştu.";
    }
    $stmt->close();
}

// Düzenlenecek ilacı getir
$duzenlenecek_ilac = null;
if (isset($_GET['duzenle'])) {
    $ilac_id = intval($_GET['duzenle']);
    $sql = "SELECT * FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ilac_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $duzenlenecek_ilac = $result->fetch_assoc();
    $stmt->close();
}

// Kullanıcının ilaçlarını getir
$sql = "SELECT * FROM ilaclar WHERE kullanici_id = ? ORDER BY ilac_adi";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$ilaclar = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlaçlarım - MediAsistan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stok-dusuk { background-color: #fff3cd; }
        .stok-kritik { background-color: #f8d7da; }
        .ilac-listesi { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <!-- Navigasyon -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat me-2"></i>MediAsistan
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a>
                <a class="nav-link active" href="medications.php"><i class="fas fa-pills me-1"></i>İlaçlarım</a>
                <a class="nav-link" href="first_aid.php"><i class="fas fa-first-aid me-1"></i>İlk Yardım</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i>Profilim</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-pills text-primary me-2"></i>İlaç Yönetimi</h2>
                <p class="text-muted">İlaçlarınızı ekleyin, düzenleyin ve stok durumunu takip edin.</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php if(isset($basari_mesaji)): ?>
            <div class="alert alert-success"><?php echo $basari_mesaji; ?></div>
        <?php endif; ?>
        
        <?php if(isset($hata_mesaji)): ?>
            <div class="alert alert-danger"><?php echo $hata_mesaji; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- İlaç Ekleme/Düzenleme Formu -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header <?php echo $duzenlenecek_ilac ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $duzenlenecek_ilac ? 'edit' : 'plus-circle'; ?> me-2"></i>
                            <?php echo $duzenlenecek_ilac ? 'İlaç Düzenle' : 'Yeni İlaç Ekle'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($duzenlenecek_ilac): ?>
                            <!-- Düzenleme Formu -->
                            <form method="POST" action="">
                                <input type="hidden" name="ilac_id" value="<?php echo $duzenlenecek_ilac['ilac_id']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">İlaç Adı *</label>
                                    <input type="text" class="form-control" name="ilac_adi" 
                                           value="<?php echo htmlspecialchars($duzenlenecek_ilac['ilac_adi']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dozaj *</label>
                                    <input type="text" class="form-control" name="dozaj" 
                                           value="<?php echo htmlspecialchars($duzenlenecek_ilac['dozaj']); ?>" 
                                           placeholder="Örn: 1 tablet, 500mg" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Stok Adedi</label>
                                            <input type="number" class="form-control" name="stok_adedi" 
                                                   value="<?php echo $duzenlenecek_ilac['stok_adedi']; ?>" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Kritik Stok</label>
                                            <input type="number" class="form-control" name="kritik_stok" 
                                                   value="<?php echo $duzenlenecek_ilac['kritik_stok_seviyesi']; ?>" min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">İlaç Tipi</label>
                                    <select class="form-control" name="ilac_tipi">
                                        <option value="tablet" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'tablet') ? 'selected' : ''; ?>>Tablet</option>
                                        <option value="kapsül" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'kapsül') ? 'selected' : ''; ?>>Kapsül</option>
                                        <option value="sıvı" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'sıvı') ? 'selected' : ''; ?>>Sıvı</option>
                                        <option value="sprey" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'sprey') ? 'selected' : ''; ?>>Sprey</option>
                                        <option value="merhem" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'merhem') ? 'selected' : ''; ?>>Merhem</option>
                                        <option value="diğer" <?php echo ($duzenlenecek_ilac['ilac_tipi'] == 'diğer') ? 'selected' : ''; ?>>Diğer</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <textarea class="form-control" name="aciklama" rows="2"><?php echo htmlspecialchars($duzenlenecek_ilac['aciklama']); ?></textarea>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="ilac_guncelle" class="btn btn-warning">
                                        <i class="fas fa-save me-2"></i>Güncelle
                                    </button>
                                    <a href="medications.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>İptal
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Ekleme Formu -->
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">İlaç Adı *</label>
                                    <input type="text" class="form-control" name="ilac_adi" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dozaj *</label>
                                    <input type="text" class="form-control" name="dozaj" placeholder="Örn: 1 tablet, 500mg" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Stok Adedi</label>
                                            <input type="number" class="form-control" name="stok_adedi" value="0" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Kritik Stok</label>
                                            <input type="number" class="form-control" name="kritik_stok" value="3" min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">İlaç Tipi</label>
                                    <select class="form-control" name="ilac_tipi">
                                        <option value="tablet">Tablet</option>
                                        <option value="kapsül">Kapsül</option>
                                        <option value="sıvı">Sıvı</option>
                                        <option value="sprey">Sprey</option>
                                        <option value="merhem">Merhem</option>
                                        <option value="diğer">Diğer</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <textarea class="form-control" name="aciklama" rows="2"></textarea>
                                </div>
                                <button type="submit" name="ilac_ekle" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>İlaç Ekle
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- İlaç Listesi -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>İlaç Listem</h5>
                    </div>
                    <div class="card-body ilac-listesi">
                        <?php if ($ilaclar->num_rows > 0): ?>
                            <?php while($ilac = $ilaclar->fetch_assoc()): 
                                $stok_durumu = "";
                                if ($ilac['stok_adedi'] == 0) {
                                    $stok_durumu = "stok-kritik";
                                } elseif ($ilac['stok_adedi'] <= $ilac['kritik_stok_seviyesi']) {
                                    $stok_durumu = "stok-dusuk";
                                }
                            ?>
                            <div class="card mb-3 <?php echo $stok_durumu; ?>">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="card-title"><?php echo htmlspecialchars($ilac['ilac_adi']); ?></h6>
                                            <p class="card-text mb-1">
                                                <small class="text-muted">
                                                    <i class="fas fa-capsules me-1"></i><?php echo htmlspecialchars($ilac['dozaj']); ?> • 
                                                    <?php echo ucfirst($ilac['ilac_tipi']); ?>
                                                </small>
                                            </p>
                                            <?php if (!empty($ilac['aciklama'])): ?>
                                                <p class="card-text"><small><?php echo htmlspecialchars($ilac['aciklama']); ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <div class="stok-bilgisi">
                                                <strong>Stok: <?php echo $ilac['stok_adedi']; ?></strong>
                                                <?php if ($ilac['stok_adedi'] <= $ilac['kritik_stok_seviyesi']): ?>
                                                    <br><span class="badge bg-danger">Kritik Stok</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <a href="medications.php?duzenle=<?php echo $ilac['ilac_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="medications.php?sil=<?php echo $ilac['ilac_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Bu ilacı silmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz hiç ilaç eklememişsiniz.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stok Uyarıları -->
                <?php
                $kritik_stok_sql = "SELECT * FROM ilaclar WHERE kullanici_id = ? AND stok_adedi <= kritik_stok_seviyesi";
                $stmt = $conn->prepare($kritik_stok_sql);
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $kritik_ilaclar = $stmt->get_result();
                
                if ($kritik_ilaclar->num_rows > 0): ?>
                <div class="card mt-3 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Stok Uyarıları</h6>
                    </div>
                    <div class="card-body">
                        <?php while($ilac = $kritik_ilaclar->fetch_assoc()): ?>
                            <div class="alert alert-warning mb-2 py-2">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong><?php echo htmlspecialchars($ilac['ilac_adi']); ?></strong> 
                                - Kalan stok: <?php echo $ilac['stok_adedi']; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php $stmt->close(); ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>