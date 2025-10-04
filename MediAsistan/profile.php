<?php
session_start();
// Yolu düzelt: config/config.php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Kullanıcı giriş yapmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Sayfa başlığı
$page_title = "Profilim - MediAsistan";

// Kullanıcı bilgilerini güvenli şekilde getir
$kullanici = get_user_data($conn, $_SESSION['user_id']);
if (!$kullanici) {
    display_alert("Kullanıcı bilgileri alınamadı.", "danger");
}

// Sağlık bilgilerini getir
$saglik_bilgileri = null;
$saglik_sql = "SELECT * FROM kullanici_saglik_bilgileri WHERE kullanici_id = ?";
$stmt = execute_query($conn, $saglik_sql, [$_SESSION['user_id']], "i");
if ($stmt) {
    $saglik_bilgileri = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Profil güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['profil_guncelle'])) {
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $dogum_tarihi = $_POST['dogum_tarihi'];
    $kan_grubu = $_POST['kan_grubu'];
    $telefon = trim($_POST['telefon']);
    
    if (!empty($ad) && !empty($soyad)) {
        $sql = "UPDATE kullanicilar SET ad = ?, soyad = ?, dogum_tarihi = ?, kan_grubu = ?, telefon = ? 
                WHERE kullanici_id = ?";
        
        $stmt = execute_query($conn, $sql, [
            $ad, $soyad, $dogum_tarihi, $kan_grubu, $telefon, $_SESSION['user_id']
        ], "sssssi");
        
        if ($stmt) {
            // Oturum bilgilerini güncelle
            $_SESSION['user_name'] = $ad;
            $_SESSION['user_surname'] = $soyad;
            
            display_alert("Profil bilgileri başarıyla güncellendi!", "success");
            $stmt->close();
            
            // Sayfayı yenile
            header("Refresh:0");
            exit;
        } else {
            display_alert("Profil güncellenirken hata oluştu: " . $conn->error, "danger");
        }
    } else {
        display_alert("Ad ve soyad alanları zorunludur.", "warning");
    }
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sifre_degistir'])) {
    $mevcut_sifre = $_POST['mevcut_sifre'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    
    // Mevcut şifreyi doğrula
    if (password_verify($mevcut_sifre, $kullanici['sifre_hash'])) {
        if ($yeni_sifre === $sifre_tekrar) {
            if (strlen($yeni_sifre) >= 6) {
                $yeni_sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                
                $sql = "UPDATE kullanicilar SET sifre_hash = ? WHERE kullanici_id = ?";
                $stmt = execute_query($conn, $sql, [$yeni_sifre_hash, $_SESSION['user_id']], "si");
                
                if ($stmt) {
                    display_alert("Şifre başarıyla değiştirildi!", "success");
                    $stmt->close();
                } else {
                    display_alert("Şifre değiştirilirken hata oluştu: " . $conn->error, "danger");
                }
            } else {
                display_alert("Yeni şifre en az 6 karakter olmalıdır.", "warning");
            }
        } else {
            display_alert("Yeni şifreler eşleşmiyor.", "warning");
        }
    } else {
        display_alert("Mevcut şifre yanlış.", "danger");
    }
}

// Sağlık bilgileri güncelleme - DÜZELTİLDİ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saglik_guncelle'])) {
    $kronik_hastaliklar = trim($_POST['kronik_hastaliklar']);
    $ilac_alergileri = trim($_POST['ilac_alergileri']);
    $gida_alergileri = trim($_POST['gida_alergileri']);
    $diger_alergiler = trim($_POST['diger_alergiler']); // DÜZELTİLDİ: diger_alergileri -> diger_alergiler
    $ozel_medical_notlar = trim($_POST['ozel_medical_notlar']);
    $acil_durum_notu = trim($_POST['acil_durum_notu']);
    
    // Sağlık bilgisi var mı kontrol et
    if ($saglik_bilgileri) {
        // Güncelle - SÜTUN ADLARI DÜZELTİLDİ
        $sql = "UPDATE kullanici_saglik_bilgileri SET 
                kronik_hastaliklar = ?, ilac_alergileri = ?, gida_alergileri = ?, 
                diger_alergiler = ?, ozel_medical_notlar = ?, acil_durum_notu = ? 
                WHERE kullanici_id = ?";
        $types = "ssssssi";
        $params = [$kronik_hastaliklar, $ilac_alergileri, $gida_alergileri, 
                  $diger_alergiler, $ozel_medical_notlar, $acil_durum_notu, $_SESSION['user_id']];
    } else {
        // Ekle - SÜTUN ADLARI DÜZELTİLDİ
        $sql = "INSERT INTO kullanici_saglik_bilgileri 
                (kullanici_id, kronik_hastaliklar, ilac_alergileri, gida_alergileri, 
                 diger_alergiler, ozel_medical_notlar, acil_durum_notu) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $types = "issssss";
        $params = [$_SESSION['user_id'], $kronik_hastaliklar, $ilac_alergileri, 
                  $gida_alergileri, $diger_alergiler, $ozel_medical_notlar, $acil_durum_notu];
    }
    
    $stmt = execute_query($conn, $sql, $params, $types);
    
    if ($stmt) {
        display_alert("Sağlık bilgileri başarıyla kaydedildi!", "success");
        $stmt->close();
        
        // Sayfayı yenile
        header("Refresh:0");
        exit;
    } else {
        display_alert("Sağlık bilgileri kaydedilirken hata oluştu: " . $conn->error, "danger");
    }
}

include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fas fa-user text-primary me-2"></i>Profilim</h2>
            <p class="text-muted">Kişisel ve sağlık bilgilerinizi yönetin.</p>
        </div>
    </div>

    <?php if (!$kullanici): ?>
        <div class="alert alert-danger">
            Kullanıcı bilgileri yüklenemedi. Lütfen tekrar giriş yapın.
        </div>
    <?php else: ?>

    <div class="row">
        <!-- Profil Bilgileri -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profil Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" name="ad" 
                                           value="<?php echo htmlspecialchars($kullanici['ad']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" name="soyad" 
                                           value="<?php echo htmlspecialchars($kullanici['soyad']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($kullanici['eposta']); ?>" disabled>
                            <small class="text-muted">E-posta adresini değiştirmek için lütfen yönetici ile iletişime geçin.</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Doğum Tarihi</label>
                                    <input type="date" class="form-control" name="dogum_tarihi" 
                                           value="<?php echo $kullanici['dogum_tarihi']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Kan Grubu</label>
                                    <select class="form-control" name="kan_grubu">
                                        <option value="">Seçiniz</option>
                                        <?php 
                                        $kan_gruplari = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', '0+', '0-'];
                                        foreach ($kan_gruplari as $grup) {
                                            $selected = ($kullanici['kan_grubu'] == $grup) ? 'selected' : '';
                                            echo "<option value='$grup' $selected>$grup</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Telefon</label>
                            <input type="tel" class="form-control" name="telefon" 
                                   value="<?php echo htmlspecialchars($kullanici['telefon']); ?>">
                        </div>
                        
                        <button type="submit" name="profil_guncelle" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Profil Bilgilerini Güncelle
                        </button>
                    </form>
                </div>
            </div>

            <!-- Şifre Değiştirme -->
            <div class="card mt-3">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Şifre Değiştir</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Mevcut Şifre *</label>
                            <input type="password" class="form-control" name="mevcut_sifre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre *</label>
                            <input type="password" class="form-control" name="yeni_sifre" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre (Tekrar) *</label>
                            <input type="password" class="form-control" name="sifre_tekrar" required>
                        </div>
                        
                        <button type="submit" name="sifre_degistir" class="btn btn-warning w-100">
                            <i class="fas fa-key me-2"></i>Şifreyi Değiştir
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sağlık Bilgileri -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Sağlık Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Kronik Hastalıklar</label>
                            <textarea class="form-control" name="kronik_hastaliklar" rows="2" 
                                      placeholder="Diyabet, hipertansiyon, astım, vb."><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['kronik_hastaliklar']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">İlaç Alerjileri</label>
                            <textarea class="form-control" name="ilac_alergileri" rows="2" 
                                      placeholder="Penisilin, aspirin, vb."><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['ilac_alergileri']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gıda Alerjileri</label>
                            <textarea class="form-control" name="gida_alergileri" rows="2" 
                                      placeholder="Fındık, süt, yumurta, vb."><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['gida_alergileri']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Diğer Alerjiler</label>
                            <textarea class="form-control" name="diger_alergiler" rows="2" 
                                      placeholder="Polen, toz, hayvan tüyü, vb."><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['diger_alergiler']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Özel Tıbbi Notlar</label>
                            <textarea class="form-control" name="ozel_medical_notlar" rows="2" 
                                      placeholder="Özel durumlar, kullanılan cihazlar, vb."><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['ozel_medical_notlar']) : ''; ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Acil Durum Notu</label>
                            <textarea class="form-control" name="acil_durum_notu" rows="2" 
                                      placeholder="Acil durumda bilinmesi gereken özel notlar"><?php echo $saglik_bilgileri ? htmlspecialchars($saglik_bilgileri['acil_durum_notu']) : ''; ?></textarea>
                        </div>
                        
                        <button type="submit" name="saglik_guncelle" class="btn btn-success w-100">
                            <i class="fas fa-save me-2"></i>Sağlık Bilgilerini Kaydet
                        </button>
                    </form>
                </div>
            </div>

            <!-- İstatistikler -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>İstatistikler</h5>
                </div>
                <div class="card-body">
                    <?php
                    // İlaç sayısı
                    $ilac_sayisi = 0;
                    $ilac_sql = "SELECT COUNT(*) as sayi FROM ilaclar WHERE kullanici_id = ?";
                    $stmt = execute_query($conn, $ilac_sql, [$_SESSION['user_id']], "i");
                    if ($stmt) {
                        $ilac_sayisi = $stmt->get_result()->fetch_assoc()['sayi'];
                        $stmt->close();
                    }
                    
                    // Acil kişi sayısı
                    $kisi_sayisi = 0;
                    $kisi_sql = "SELECT COUNT(*) as sayi FROM acil_durum_kisileri WHERE kullanici_id = ? AND aktif_mi = 1";
                    $stmt = execute_query($conn, $kisi_sql, [$_SESSION['user_id']], "i");
                    if ($stmt) {
                        $kisi_sayisi = $stmt->get_result()->fetch_assoc()['sayi'];
                        $stmt->close();
                    }
                    ?>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-primary"><?php echo $ilac_sayisi; ?></h4>
                                <small>Kayıtlı İlaç</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 bg-light">
                                <h4 class="text-success"><?php echo $kisi_sayisi; ?></h4>
                                <small>Acil Kişi</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">Üyelik Tarihi: <?php echo format_date($kullanici['olusturulma_tarihi']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php include_once 'includes/footer.php'; ?>