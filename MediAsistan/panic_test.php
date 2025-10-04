<?php
// panic_test.php - Profesyonel Acil Durum Paneli
session_start();
require_once './config/config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Header'ı dahil et
include 'includes/header.php';

// Kullanıcı bilgilerini al
$kullanici_id = $_SESSION['user_id'];
$sorgu = $conn->prepare("SELECT ad, soyad FROM kullanicilar WHERE kullanici_id = ?");
$sorgu->bind_param("i", $kullanici_id);
$sorgu->execute();
$result = $sorgu->get_result();
$kullanici = $result->fetch_assoc();
$sorgu->close();

// Acil iletişim kişi sayısını al
$sorgu = $conn->prepare("SELECT COUNT(*) as sayi FROM acil_durum_kisileri WHERE kullanici_id = ? AND aktif_mi = 1");
$sorgu->bind_param("i", $kullanici_id);
$sorgu->execute();
$result = $sorgu->get_result();
$kisi_sayisi = $result->fetch_assoc()['sayi'];
$sorgu->close();
?>

<div class="container-fluid emergency-container">
    <!-- Acil Durum Üst Bilgi -->
    <div class="emergency-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-first-aid me-3"></i>Acil Durum Paneli</h1>
                <p class="mb-0">Hoş geldiniz, <strong><?php echo htmlspecialchars($kullanici['ad'] . ' ' . $kullanici['soyad']); ?></strong></p>
                <small class="text-muted">Kullanıcı ID: <?php echo $kullanici_id; ?></small>
            </div>
            <div class="col-md-4 text-end">
                <div class="emergency-stats">
                    <span class="badge bg-warning"><i class="fas fa-users me-1"></i> <?php echo $kisi_sayisi; ?> Kişi</span>
                    <span class="badge bg-info"><i class="fas fa-bell me-1"></i> Aktif</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Ana İçerik -->
    <div class="row justify-content-center mt-4">
        <div class="col-lg-8 col-md-10">
            <!-- Durum Paneli -->
            <div class="status-panel">
                <div class="status-item">
                    <div class="status-icon">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="status-info">
                        <h4>Sistem Durumu</h4>
                        <p class="text-success"><i class="fas fa-check-circle me-2"></i>Tüm sistemler çalışıyor</p>
                    </div>
                </div>
                
                <div class="status-item">
                    <div class="status-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="status-info">
                        <h4>Konum Servisi</h4>
                        <p id="location-status" class="text-warning"><i class="fas fa-sync-alt me-2"></i>Konum kontrol ediliyor...</p>
                    </div>
                </div>

                <div class="status-item">
                    <div class="status-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="status-info">
                        <h4>Veritabanı</h4>
                        <p id="database-status" class="text-warning"><i class="fas fa-sync-alt me-2"></i>Bağlantı test ediliyor...</p>
                    </div>
                </div>
            </div>

            <!-- Acil Durum Butonu -->
            <div class="panic-section text-center">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Uyarı:</strong> Bu buton sadece gerçek acil durumlarda kullanılmalıdır.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <button class="panic-button" id="panicButton">
                    <div class="panic-button-inner">
                        <i class="fas fa-bell"></i>
                        <span class="panic-text">ACİL DURUM</span>
                        <small>Yardım Çağır</small>
                    </div>
                </button>

                <p class="text-muted mt-3">
                    <small><i class="fas fa-info-circle me-1"></i>Butona basıldığında acil iletişim kişilerinize bildirim gönderilecektir.</small>
                </p>
            </div>

            <!-- Hızlı Eylemler -->
            <div class="quick-actions">
                <h5><i class="fas fa-bolt me-2"></i>Hızlı Eylemler</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-primary w-100" onclick="testAPI()">
                            <i class="fas fa-vial me-2"></i>API Test
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-success w-100" onclick="testNotification()">
                            <i class="fas fa-bell me-2"></i>Test Bildirimi
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-outline-info w-100" onclick="getCurrentLocation()">
                            <i class="fas fa-map-marker-alt me-2"></i>Konum Al
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="emergency_contacts.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-address-book me-2"></i>Kişileri Yönet
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sonuçlar -->
            <div id="result" class="mt-4"></div>
        </div>
    </div>

    <!-- Acil Durum Overlay -->
    <div id="emergencyOverlay" class="emergency-overlay">
        <div class="overlay-content">
            <div class="emergency-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>ACİL DURUM</h2>
                <p id="overlayMessage">Yardım istediğiniz kişilere bildirim gönderiliyor...</p>
                <div class="progress mt-3">
                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                </div>
                <button id="cancelButton" class="btn btn-light mt-3" style="display: none;">İptal</button>
            </div>
        </div>
    </div>
</div>

<style>
.emergency-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}

.emergency-header {
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.status-panel {
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
}

.status-item {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.status-icon {
    font-size: 2em;
    color: #667eea;
    margin-right: 15px;
    width: 50px;
    text-align: center;
}

.panic-section {
    background: rgba(255, 255, 255, 0.95);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 20px;
}

.panic-button {
    background: linear-gradient(45deg, #ff6b6b, #ee5a24);
    border: none;
    border-radius: 50%;
    width: 200px;
    height: 200px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
    animation: pulse 2s infinite;
    display: inline-block;
}

.panic-button:hover:not(:disabled) {
    transform: scale(1.05);
    box-shadow: 0 15px 40px rgba(255, 107, 107, 0.6);
}

.panic-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    animation: none;
}

.panic-button-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.panic-button i {
    font-size: 3em;
    margin-bottom: 10px;
}

.panic-text {
    font-size: 1.5em;
    font-weight: bold;
}

.quick-actions {
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 15px;
}

.emergency-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 0, 0, 0.95);
    display: none;
    z-index: 9999;
}

.overlay-content {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: white;
    text-align: center;
}

.emergency-alert i {
    font-size: 4em;
    margin-bottom: 20px;
    animation: blink 1s infinite;
}

.emergency-alert h2 {
    font-size: 2.5em;
    margin-bottom: 20px;
    font-weight: bold;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

@media (max-width: 768px) {
    .panic-button {
        width: 150px;
        height: 150px;
    }
    
    .panic-button i {
        font-size: 2em;
    }
    
    .panic-text {
        font-size: 1.2em;
    }
    
    .emergency-alert h2 {
        font-size: 2em;
    }
}
</style>

<script>
// Değişkenler
let emergencyInProgress = false;

// Durum güncelleme fonksiyonları
function updateLocationStatus(status, isSuccess = true) {
    const statusElement = document.getElementById('location-status');
    statusElement.innerHTML = isSuccess ? 
        `<i class="fas fa-check-circle me-2"></i>${status}` :
        `<i class="fas fa-exclamation-circle me-2"></i>${status}`;
    statusElement.className = isSuccess ? 'text-success' : 'text-danger';
}

function updateDatabaseStatus(status, isSuccess = true) {
    const statusElement = document.getElementById('database-status');
    statusElement.innerHTML = isSuccess ? 
        `<i class="fas fa-check-circle me-2"></i>${status}` :
        `<i class="fas fa-exclamation-circle me-2"></i>${status}`;
    statusElement.className = isSuccess ? 'text-success' : 'text-danger';
}

// API testi
async function testAPI() {
    showLoading('API test ediliyor...');
    try {
        const response = await fetch('panic.php');
        const data = await response.json();
        
        if (data.durum === 'success') {
            updateDatabaseStatus('API çalışıyor', true);
            showResult({
                durum: 'success',
                mesaj: 'API başarıyla çalışıyor! Sistem hazır.',
                veri: data.veri
            });
        } else {
            throw new Error(data.mesaj);
        }
    } catch (error) {
        updateDatabaseStatus('API hatası', false);
        showResult({
            durum: 'error',
            mesaj: 'API testi başarısız: ' + error.message
        });
    } finally {
        hideLoading();
    }
}

// Konum alma
async function getCurrentLocation() {
    updateLocationStatus('Konum izni isteniyor...', false);
    
    try {
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            });
        });
        
        const lat = position.coords.latitude;
        const lon = position.coords.longitude;
        updateLocationStatus(`Konum alındı: ${lat.toFixed(4)}, ${lon.toFixed(4)}`, true);
        
        showResult({
            durum: 'success',
            mesaj: 'Konum başarıyla alındı!',
            veri: {
                konum_lat: lat,
                konum_lon: lon,
                konum_adresi: 'Gerçek zamanlı konum'
            }
        });
        
        return position;
    } catch (error) {
        let errorMessage = 'Konum alınamadı';
        if (error.code === 1) {
            errorMessage = 'Konum izni reddedildi. Lütfen tarayıcı ayarlarından izin verin.';
        } else if (error.code === 2) {
            errorMessage = 'Konum bilgisi alınamadı.';
        } else if (error.code === 3) {
            errorMessage = 'Konum alma işlemi zaman aşımına uğradı.';
        }
        updateLocationStatus(errorMessage, false);
        throw error;
    }
}

// Acil durum protokolü
async function triggerEmergencyProtocol() {
    if (emergencyInProgress) return;
    
    if (!confirm('🚨 ACİL DURUM BUTONU 🚨\n\nBu işlem acil iletişim kişilerinize bildirim gönderecektir. Devam etmek istiyor musunuz?')) {
        return;
    }
    
    emergencyInProgress = true;
    document.getElementById('panicButton').disabled = true;
    
    // Acil durum overlay'ini göster
    const overlay = document.getElementById('emergencyOverlay');
    const progressBar = document.getElementById('progressBar');
    const overlayMessage = document.getElementById('overlayMessage');
    const cancelButton = document.getElementById('cancelButton');
    
    overlay.style.display = 'block';
    overlayMessage.textContent = 'Sistem hazırlanıyor...';
    progressBar.style.width = '10%';
    
    try {
        // 1. Adım: Konum al
        overlayMessage.textContent = 'Konum bilgisi alınıyor...';
        progressBar.style.width = '30%';
        
        let position;
        try {
            position = await getCurrentLocation();
        } catch (error) {
            console.log('Konum alınamadı, varsayılan konum kullanılıyor:', error.message);
        }
        
        // 2. Adım: Bildirim gönder
        overlayMessage.textContent = 'Acil durum bildirimi gönderiliyor...';
        progressBar.style.width = '60%';
        
        const result = await sendEmergencyNotification(position);
        
        // 3. Adım: Başarılı
        overlayMessage.textContent = 'Bildirimler başarıyla gönderildi!';
        progressBar.style.width = '100%';
        
        // Ses efekti ve başarı mesajı
        playEmergencySound();
        
        setTimeout(() => {
            overlay.style.display = 'none';
            showResult(result);
            playNotificationSound();
            emergencyInProgress = false;
            document.getElementById('panicButton').disabled = false;
        }, 2000);
        
    } catch (error) {
        overlayMessage.textContent = 'Hata oluştu!';
        progressBar.style.width = '100%';
        progressBar.classList.remove('progress-bar-animated');
        progressBar.classList.add('bg-danger');
        
        cancelButton.style.display = 'block';
        cancelButton.onclick = () => {
            overlay.style.display = 'none';
            emergencyInProgress = false;
            document.getElementById('panicButton').disabled = false;
        };
        
        showResult({
            durum: 'error',
            mesaj: 'Acil durum bildirimi gönderilemedi: ' + error.message
        });
    }
}

// Test bildirimi
async function testNotification() {
    try {
        showLoading('Test bildirimi gönderiliyor...');
        
        const response = await fetch('panic.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                test: true, 
                konum_adresi: 'Test Konumu - İstanbul',
                konum_lat: 41.0082,
                konum_lon: 28.9784
            })
        });
        
        const responseText = await response.text();
        console.log('Test yanıtı:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            if (responseText.includes('<!DOCTYPE') || responseText.includes('<html>')) {
                throw new Error('Sunucu hata sayfası döndürdü. PHP dosyasında hata var.');
            } else {
                throw new Error('Geçersiz JSON yanıtı');
            }
        }
        
        playNotificationSound();
        showResult(data);
    } catch (error) {
        showResult({
            durum: 'error',
            mesaj: 'Test başarısız: ' + error.message
        });
    } finally {
        hideLoading();
    }
}

// Acil durum bildirimi gönder
async function sendEmergencyNotification(position = null) {
    const locationData = {};
    
    if (position) {
        locationData.konum_lat = position.coords.latitude;
        locationData.konum_lon = position.coords.longitude;
        locationData.konum_adresi = 'Gerçek zamanlı konum';
    } else {
        locationData.konum_lat = 41.0082;
        locationData.konum_lon = 28.9784;
        locationData.konum_adresi = 'İstanbul, Türkiye (varsayılan)';
    }
    
    const response = await fetch('panic.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(locationData)
    });
    
    const responseText = await response.text();
    console.log('Acil durum yanıtı:', responseText);
    
    let data;
    try {
        data = JSON.parse(responseText);
    } catch (e) {
        throw new Error('Geçersiz JSON yanıtı alındı');
    }
    
    return data;
}

// Ses efektleri
function playEmergencySound() {
    // Basit bip sesi simülasyonu
    try {
        const beep = new AudioContext();
        const oscillator = beep.createOscillator();
        const gainNode = beep.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(beep.destination);
        
        oscillator.frequency.value = 800;
        gainNode.gain.value = 0.3;
        
        oscillator.start();
        setTimeout(() => oscillator.stop(), 300);
    } catch (e) {
        console.log('Ses çalınamadı');
    }
}

function playNotificationSound() {
    // Kısa bip sesi
    try {
        const beep = new AudioContext();
        const oscillator = beep.createOscillator();
        const gainNode = beep.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(beep.destination);
        
        oscillator.frequency.value = 1000;
        gainNode.gain.value = 0.2;
        
        oscillator.start();
        setTimeout(() => oscillator.stop(), 100);
    } catch (e) {
        console.log('Bildirim sesi çalınamadı');
    }
}

// UI fonksiyonları
function showLoading(message = 'İşlem yapılıyor...') {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = `
        <div class="alert alert-info">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-3" role="status">
                    <span class="visually-hidden">Yükleniyor...</span>
                </div>
                <strong>${message}</strong>
            </div>
        </div>
    `;
}

function hideLoading() {
    // Yükleme gizlendiğinde özel bir işlem yapmaya gerek yok
}

function showResult(data) {
    const resultDiv = document.getElementById('result');
    
    if (data.durum === 'success') {
        resultDiv.innerHTML = `
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle me-2"></i>✅ İşlem Başarılı</h4>
                <p><strong>${data.mesaj}</strong></p>
                
                ${data.veri ? `
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><i class="fas fa-info-circle me-2"></i>İşlem Detayları</h5>
                                <p><strong>Kayıt No:</strong> #${data.veri.kayit_id || 'N/A'}</p>
                                <p><strong>Bildirim Gönderilen:</strong> ${data.veri.bildirim_gonderilen || 0} kişi</p>
                                <p><strong>Toplam Bildirim:</strong> ${data.veri.toplam_bildirim || 0}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5><i class="fas fa-map-marker-alt me-2"></i>Konum Bilgisi</h5>
                                <p><strong>Adres:</strong> ${data.veri.konum_adresi || 'N/A'}</p>
                                <p><strong>Zaman:</strong> ${data.veri.islem_zamani || 'N/A'}</p>
                                ${data.veri.konum_link ? `
                                <a href="${data.veri.konum_link}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i>Haritada Gör
                                </a>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                ${data.veri.talimat ? `
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i>${data.veri.talimat}</h6>
                </div>
                ` : ''}
                ` : ''}
            </div>
        `;
    } else {
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>❌ Hata</h4>
                <p class="mb-0"><strong>${data.mesaj}</strong></p>
            </div>
        `;
    }
    
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Sayfa yüklendiğinde
window.addEventListener('load', async function() {
    console.log('Acil durum paneli yüklendi');
    
    // Panik butonuna event listener ekle
    document.getElementById('panicButton').addEventListener('click', triggerEmergencyProtocol);
    
    // API testi yap
    await testAPI();
    
    // Konum izni kontrolü
    if (navigator.geolocation) {
        navigator.permissions.query({name: 'geolocation'}).then(function(result) {
            if (result.state === 'granted') {
                updateLocationStatus('Konum izni verilmiş', true);
            } else if (result.state === 'prompt') {
                updateLocationStatus('Konum izni bekleniyor...', false);
            } else {
                updateLocationStatus('Konum izni reddedilmiş', false);
            }
        });
    } else {
        updateLocationStatus('Konum servisi desteklenmiyor', false);
    }
});
</script>

<?php
// Footer'ı dahil et
include 'includes/footer.php';
?>