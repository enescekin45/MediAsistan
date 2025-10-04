<?php
session_start();
require_once './config/config.php';

// Kullanıcı giriş yapmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Seçilen kategoriyi al
$kategori_id = isset($_GET['kategori']) ? intval($_GET['kategori']) : 1;

// İlk yardım kategorilerini getir
$kategoriler_sql = "SELECT * FROM ilk_yardim_kategorileri WHERE aktif_mi = 1 ORDER BY sira_no";
$kategoriler = $conn->query($kategoriler_sql);

// Seçili kategorinin talimatlarını getir
$talimatlar_sql = "SELECT * FROM ilk_yardim_talimatlari WHERE kategori_id = ? ORDER BY adim_numarasi";
$stmt = $conn->prepare($talimatlar_sql);
$stmt->bind_param("i", $kategori_id);
$stmt->execute();
$talimatlar = $stmt->get_result();

// Kategori bilgisini al
$kategori_bilgi_sql = "SELECT * FROM ilk_yardim_kategorileri WHERE kategori_id = ?";
$stmt2 = $conn->prepare($kategori_bilgi_sql);
$stmt2->bind_param("i", $kategori_id);
$stmt2->execute();
$kategori_bilgi = $stmt2->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlk Yardım - MediAsistan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .category-card { 
            cursor: pointer; 
            transition: transform 0.3s; 
            border: none;
            border-radius: 15px;
        }
        .category-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .step-card { 
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
        }
        .important-step { 
            border-left-color: #dc3545;
            background-color: #f8f9fa;
        }
        .emergency-buttons {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <!-- Navigasyon -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-heartbeat me-2"></i>MediAsistan
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a>
                <a class="nav-link" href="medications.php"><i class="fas fa-pills me-1"></i>İlaçlarım</a>
                <a class="nav-link active" href="first_aid.php"><i class="fas fa-first-aid me-1"></i>İlk Yardım</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i>Profilim</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Çıkış</a>
            </div>
        </div>
    </nav>

    <!-- Acil Durum Butonları -->
    <div class="emergency-buttons">
        <a href="tel:112" class="btn btn-danger btn-lg rounded-circle shadow" title="112'yi Ara">
            <i class="fas fa-phone-alt"></i>
        </a>
        <a href="panic.php" class="btn btn-warning btn-lg rounded-circle shadow mt-2" title="Panik Butonu">
            <i class="fas fa-exclamation-triangle"></i>
        </a>
    </div>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-danger">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Önemli Uyarı!</h4>
                    <p class="mb-0">
                        <strong>Bu bilgiler profesyonel tıbbi yardımın yerine geçmez.</strong> 
                        Acil durumlarda lütfen derhal 112'yi arayınız veya en yakın sağlık kuruluşuna başvurunuz.
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kategoriler -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>İlk Yardım Konuları</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php while($kategori = $kategoriler->fetch_assoc()): 
                                $active = $kategori['kategori_id'] == $kategori_id ? 'active' : '';
                            ?>
                            <a href="first_aid.php?kategori=<?php echo $kategori['kategori_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $active; ?>">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-<?php echo $kategori['kategori_ikon'] ?? 'first-aid'; ?> me-3 fa-lg"></i>
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($kategori['kategori_adi']); ?></h6>
                                        <?php if ($kategori['onem_derecesi'] >= 4): ?>
                                            <small class="text-danger"><i class="fas fa-exclamation-circle"></i> Yüksek Öncelikli</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Talimatlar -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $kategori_bilgi['kategori_ikon'] ?? 'first-aid'; ?> me-2"></i>
                            <?php echo htmlspecialchars($kategori_bilgi['kategori_adi']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($talimatlar->num_rows > 0): ?>
                            <div class="steps-container">
                                <?php while($talimat = $talimatlar->fetch_assoc()): 
                                    $important_class = $talimat['onemli_uyari'] ? 'important-step' : '';
                                ?>
                                <div class="card step-card <?php echo $important_class; ?>">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start">
                                            <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; min-width: 40px;">
                                                <strong><?php echo $talimat['adim_numarasi']; ?></strong>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title">
                                                    <?php if ($talimat['onemli_uyari']): ?>
                                                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($talimat['baslik']); ?>
                                                </h6>
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($talimat['aciklama'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Bu kategori için henüz talimat eklenmemiş.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Acil İletişim Kartı -->
                        <div class="card mt-4 border-danger">
                            <div class="card-body text-center">
                                <h6 class="card-title text-danger">
                                    <i class="fas fa-phone-alt me-2"></i>Acil Durumda Ne Yapmalı?
                                </h6>
                                <p class="card-text">
                                    <strong>1. Sakin olun</strong><br>
                                    <strong>2. 112'yi arayın</strong><br>
                                    <strong>3. Yukarıdaki talimatları uygulayın</strong>
                                </p>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                    <a href="tel:112" class="btn btn-danger">
                                        <i class="fas fa-phone-alt me-2"></i>112'yi Ara
                                    </a>
                                    <a href="panic_test.php" class="btn btn-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Panik Butonu
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Otomatik kaydırma için
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('kategori')) {
                window.scrollTo(0, document.querySelector('.steps-container').offsetTop - 20);
            }
        });
    </script>
</body>
</html>