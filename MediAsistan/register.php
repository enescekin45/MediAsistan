<?php
session_start();
require_once './config/config.php';

// Kullanıcı zaten giriş yapmışsa yönlendir
if(isset($_SESSION['kullanici_id'])) {
    header('Location: index.php');
    exit();
}

// Kayıt formu işleme
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ad = trim($_POST['ad']);
    $soyad = trim($_POST['soyad']);
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $sifre_tekrar = $_POST['sifre_tekrar'];
    $dogum_tarihi = $_POST['dogum_tarihi'];
    $kan_grubu = $_POST['kan_grubu'];
    $telefon = trim($_POST['telefon']);
    
    // Validasyon
    $hata = "";
    
    if(empty($ad) || empty($soyad) || empty($eposta) || empty($sifre)) {
        $hata = "Lütfen zorunlu alanları doldurun.";
    } elseif($sifre !== $sifre_tekrar) {
        $hata = "Şifreler eşleşmiyor.";
    } elseif(strlen($sifre) < 6) {
        $hata = "Şifre en az 6 karakter olmalıdır.";
    } else {
        // E-posta kontrolü
        $sql = "SELECT kullanici_id FROM kullanicilar WHERE eposta = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $eposta);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $hata = "Bu e-posta adresi zaten kullanımda.";
        } else {
            // Kullanıcıyı kaydet
            $sifre_hash = password_hash($sifre, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO kullanicilar (ad, soyad, eposta, sifre_hash, dogum_tarihi, kan_grubu, telefon) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $ad, $soyad, $eposta, $sifre_hash, $dogum_tarihi, $kan_grubu, $telefon);
            
            if($stmt->execute()) {
                $basari = "Kayıt başarılı! Giriş yapabilirsiniz.";
                // Formu temizle
                $ad = $soyad = $eposta = $dogum_tarihi = $kan_grubu = $telefon = "";
            } else {
                $hata = "Kayıt sırasında bir hata oluştu: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - MediAsistan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assests/css/style.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center">
                        <h4>MediAsistan - Kayıt Ol</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($hata)): ?>
                            <div class="alert alert-danger"><?php echo $hata; ?></div>
                        <?php endif; ?>
                        
                        <?php if(isset($basari)): ?>
                            <div class="alert alert-success"><?php echo $basari; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ad" class="form-label">Ad *</label>
                                        <input type="text" class="form-control" id="ad" name="ad" 
                                               value="<?php echo isset($ad) ? $ad : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="soyad" class="form-label">Soyad *</label>
                                        <input type="text" class="form-control" id="soyad" name="soyad"
                                               value="<?php echo isset($soyad) ? $soyad : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="eposta" class="form-label">E-posta Adresi *</label>
                                <input type="email" class="form-control" id="eposta" name="eposta"
                                       value="<?php echo isset($eposta) ? $eposta : ''; ?>" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sifre" class="form-label">Şifre *</label>
                                        <input type="password" class="form-control" id="sifre" name="sifre" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sifre_tekrar" class="form-label">Şifre Tekrar *</label>
                                        <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="dogum_tarihi" class="form-label">Doğum Tarihi</label>
                                        <input type="date" class="form-control" id="dogum_tarihi" name="dogum_tarihi"
                                               value="<?php echo isset($dogum_tarihi) ? $dogum_tarihi : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="kan_grubu" class="form-label">Kan Grubu</label>
                                        <select class="form-control" id="kan_grubu" name="kan_grubu">
                                            <option value="">Seçiniz</option>
                                            <option value="A+" <?php echo (isset($kan_grubu) && $kan_grubu == 'A+') ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo (isset($kan_grubu) && $kan_grubu == 'A-') ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo (isset($kan_grubu) && $kan_grubu == 'B+') ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo (isset($kan_grubu) && $kan_grubu == 'B-') ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo (isset($kan_grubu) && $kan_grubu == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo (isset($kan_grubu) && $kan_grubu == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                            <option value="0+" <?php echo (isset($kan_grubu) && $kan_grubu == '0+') ? 'selected' : ''; ?>>0+</option>
                                            <option value="0-" <?php echo (isset($kan_grubu) && $kan_grubu == '0-') ? 'selected' : ''; ?>>0-</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon"
                                       value="<?php echo isset($telefon) ? $telefon : ''; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">Kayıt Ol</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p>Zaten hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>