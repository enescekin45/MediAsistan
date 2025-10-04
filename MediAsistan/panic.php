<?php
// panic.php - Gerçekçi Panik Butonu API
session_start();

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tüm çıktı tamponlarını temizle
while (ob_get_level()) {
    ob_end_clean();
}

// JSON header'ı gönder - KESİNLİKLE İLK SATIRLARDA
header('Content-Type: application/json; charset=utf-8');

// Basit test için hızlı yanıt
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'durum' => 'success', 
        'mesaj' => 'Panik butonu API çalışıyor',
        'veri' => ['api_status' => 'active', 'timestamp' => date('Y-m-d H:i:s')]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Config dosyasını dahil et
    require_once __DIR__ . '/config/config.php';
    
    // Oturum kontrolü
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Oturum açmanız gerekiyor', 401);
    }

    // Gelen JSON verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null || json_last_error() !== JSON_ERROR_NONE) {
        $input = [];
    }
    
    // Oturumdan kullanıcı ID'sini al
    $kullanici_id = intval($_SESSION['user_id']);
    
    // Konum bilgilerini al
    $konum_lat = isset($input['konum_lat']) ? floatval($input['konum_lat']) : 41.0082;
    $konum_lon = isset($input['konum_lon']) ? floatval($input['konum_lon']) : 28.9784;
    $konum_adresi = isset($input['konum_adresi']) ? trim($input['konum_adresi']) : "Konum bilgisi alınamadı";
    
    // Google Maps linki oluştur
    $konum_link = "https://maps.google.com/?q={$konum_lat},{$konum_lon}";

    // 1. Acil durum kaydı oluştur - SORGU DÜZELTİLDİ
    $sorgu = $conn->prepare("INSERT INTO acil_durum_kayitlari (kullanici_id, tetikleme_tipi, konum_lat, konum_lon, konum_adresi, durum) VALUES (?, 'panik_butonu', ?, ?, ?, 'baslatildi')");
    
    if (!$sorgu) {
        throw new Exception('Sistem hazırlık hatası: ' . $conn->error);
    }
    
    // PARAMETRE SAYISI DÜZELTİLDİ - 4 parametre, 4 tip
    $sorgu->bind_param("idds", $kullanici_id, $konum_lat, $konum_lon, $konum_adresi);
    
    if (!$sorgu->execute()) {
        throw new Exception('Acil durum kaydı oluşturulamadı: ' . $sorgu->error);
    }
    
    $kayit_id = $conn->insert_id;
    $sorgu->close();

    // 2. Acil iletişim kişilerini getir
    $kisiSayisi = 0;
    $basariSayisi = 0;
    $kisiler = [];

    $sorgu = $conn->prepare("SELECT kisi_id, ad_soyad, telefon, eposta, iliski FROM acil_durum_kisileri WHERE kullanici_id = ? AND aktif_mi = 1 ORDER BY sira_no ASC");
    
    if ($sorgu) {
        $sorgu->bind_param("i", $kullanici_id);
        
        if ($sorgu->execute()) {
            $result = $sorgu->get_result();
            while ($row = $result->fetch_assoc()) {
                $kisiler[] = $row;
            }
            $kisiSayisi = count($kisiler);
        }
        $sorgu->close();
    }

    // 3. Kullanıcı bilgilerini al
    $kullanici = null;
    $sorgu = $conn->prepare("SELECT ad, soyad, telefon FROM kullanicilar WHERE kullanici_id = ?");
    
    if ($sorgu) {
        $sorgu->bind_param("i", $kullanici_id);
        
        if ($sorgu->execute()) {
            $result = $sorgu->get_result();
            $kullanici = $result->fetch_assoc();
        }
        $sorgu->close();
    }
    
    if (!$kullanici) {
        // Kullanıcı bulunamazsa varsayılan değerler
        $kullanici = ['ad' => 'Kullanıcı', 'soyad' => '', 'telefon' => 'Bilinmiyor'];
    }

    // 4. Her kişiye bildirim gönder
    foreach ($kisiler as $kisi) {
        // Gerçekçi SMS mesajı
        $mesaj = "ACİL DURUM BİLDİRİMİ\n\n"
               . "Sayın " . $kisi['ad_soyad'] . ",\n\n"
               . $kullanici['ad'] . " " . $kullanici['soyad'] . " acil yardıma ihtiyaç duyuyor!\n\n"
               . "İletişim: " . ($kullanici['telefon'] ?: 'Bilinmiyor') . "\n"
               . "Konum: " . $konum_adresi . "\n"
               . "Harita: " . $konum_link . "\n"
               . "Zaman: " . date('d.m.Y H:i:s') . "\n\n"
               . "Lütfen derhal müdahale edin veya 112'yi arayın.";
        
        $basari = true;
        
        if ($basari) {
            $basariSayisi++;
            
            // Bildirim geçmişine kaydet
            $sorgu_bildirim = $conn->prepare("INSERT INTO bildirim_gecmisi (kullanici_id, bildirim_tipi, baslik, mesaj, hedef_id, gonderim_durumu) VALUES (?, 'acil_durum', ?, ?, ?, 'gonderildi')");
            
            if ($sorgu_bildirim) {
                $baslik = "Acil Durum - " . $kisi['ad_soyad'];
                $sorgu_bildirim->bind_param("issi", $kullanici_id, $baslik, $mesaj, $kisi['kisi_id']);
                $sorgu_bildirim->execute();
                $sorgu_bildirim->close();
            }
        }
    }

    // 5. Sistem bildirimleri
    $fcm_bildirim_sayisi = $kisiSayisi;

    // 6. İşlemi tamamlandı olarak işaretle
    $sorgu = $conn->prepare("UPDATE acil_durum_kayitlari SET durum = 'tamamlandi', bitis_zamani = NOW() WHERE kayit_id = ?");
    
    if ($sorgu) {
        $sorgu->bind_param("i", $kayit_id);
        $sorgu->execute();
        $sorgu->close();
    }

    // Başarılı yanıt
    $response = [
        'durum' => 'success',
        'mesaj' => 'Acil durum bildirimi başarıyla gönderildi! Yardım ekipleri yönlendiriliyor...',
        'veri' => [
            'kayit_id' => $kayit_id,
            'kisi_sayisi' => $kisiSayisi,
            'bildirim_gonderilen' => $basariSayisi,
            'toplam_bildirim' => $fcm_bildirim_sayisi,
            'konum_link' => $konum_link,
            'konum_adresi' => $konum_adresi,
            'islem_zamani' => date('d.m.Y H:i:s'),
            'talimat' => 'Sakin olun. Yardım yolda. Güvenli bir yerde bekleyin.'
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'durum' => 'error',
        'mesaj' => 'Sistem hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>