// MediAsistan - Ana JavaScript Dosyası
// Tüm sayfalarda kullanılacak genel fonksiyonlar

class MediAsistan {
    constructor() {
        this.init();
    }

    init() {
        // Sayfa yüklendiğinde çalışacak fonksiyonlar
        document.addEventListener('DOMContentLoaded', () => {
            this.initEmergencyButtons();
            this.initNotifications();
            this.initAutoLogout();
            this.initFormValidations();
            this.initMobileMenu();
            this.initSmoothScroll();
        });
    }

    // Acil durum butonlarını yönetme
    initEmergencyButtons() {
        // 112 Arama butonu
        const emergencyCallBtn = document.getElementById('emergency-call');
        if (emergencyCallBtn) {
            emergencyCallBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.makeEmergencyCall();
            });
        }

        // Panik butonu
        const panicBtn = document.getElementById('panic-button');
        if (panicBtn) {
            panicBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.activatePanicMode();
            });
        }

        // Acil durum bildirimi butonu
        const emergencyAlertBtn = document.getElementById('emergency-alert');
        if (emergencyAlertBtn) {
            emergencyAlertBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.sendEmergencyAlert();
            });
        }
    }

    // 112 arama fonksiyonu
    makeEmergencyCall() {
        if (confirm('112 Acil Servis aransın mı?')) {
            // Telefon uygulamasını aç
            window.open('tel:112', '_self');
            
            // API'ye arama kaydı gönder
            this.logEmergencyAction('emergency_call', '112 arandı');
        }
    }

    // Panik modu aktivasyonu
    async activatePanicMode() {
        if (confirm('Panik modu aktif edilsin mi? Acil kişilerinize bildirim gidecektir.')) {
            try {
                const response = await fetch('backend/api/emergency_contacts.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'panic_mode',
                        timestamp: new Date().toISOString()
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showNotification('Panik modu aktif! Acil kişilerinize bildirim gönderildi.', 'success');
                    
                    // Konum paylaşımı iste
                    this.requestLocationShare();
                } else {
                    this.showNotification('Panik modu aktif edilemedi: ' + result.error, 'error');
                }
            } catch (error) {
                this.showNotification('Ağ hatası: ' + error.message, 'error');
                // Offline modda çalış
                this.offlinePanicMode();
            }
        }
    }

    // Acil durum bildirimi gönderme
    async sendEmergencyAlert() {
        const message = prompt('Acil durum mesajınızı yazın (opsiyonel):', 'Acil yardıma ihtiyacım var!');
        
        try {
            const response = await fetch('backend/api/emergency_contacts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'emergency_alert',
                    message: message,
                    timestamp: new Date().toISOString()
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Acil durum bildirimi gönderildi!', 'success');
            } else {
                this.showNotification('Bildirim gönderilemedi: ' + result.error, 'error');
            }
        } catch (error) {
            this.showNotification('Ağ hatası: ' + error.message, 'error');
        }
    }

    // Konum paylaşımı isteği
    requestLocationShare() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.shareLocation(position);
                },
                (error) => {
                    console.warn('Konum alınamadı:', error);
                    this.showNotification('Konum paylaşımı için izin vermeniz gerekiyor.', 'warning');
                },
                { timeout: 10000 }
            );
        }
    }

    // Konum paylaşımı
    async shareLocation(position) {
        const location = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy
        };

        try {
            const response = await fetch('backend/api/emergency_contacts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'share_location',
                    location: location,
                    timestamp: new Date().toISOString()
                })
            });

            // Google Maps linki oluştur
            const mapsLink = `https://maps.google.com/?q=${location.latitude},${location.longitude}`;
            console.log('Konum paylaşıldı:', mapsLink);
        } catch (error) {
            console.error('Konum paylaşım hatası:', error);
        }
    }

    // Çevrimdışı panik modu
    offlinePanicMode() {
        // LocalStorage'a kaydet
        const panicData = {
            activated: new Date().toISOString(),
            location: 'unknown',
            status: 'pending'
        };
        
        localStorage.setItem('panic_mode', JSON.stringify(panicData));
        this.showNotification('Panik modu aktif (çevrimdışı mod). İnternet bağlantısı sağlandığında bildirim gönderilecek.', 'warning');
    }

    // Bildirim yönetimi
    initNotifications() {
        // Bildirim izni iste
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // İlaç hatırlatıcılarını kontrol et
        this.checkMedicationReminders();
    }

    // İlaç hatırlatıcılarını kontrol et
    checkMedicationReminders() {
        const now = new Date();
        const currentTime = now.getHours() + ':' + now.getMinutes();
        
        // LocalStorage'dan hatırlatıcıları al
        const reminders = JSON.parse(localStorage.getItem('medication_reminders') || '[]');
        
        reminders.forEach(reminder => {
            if (reminder.time === currentTime && !reminder.notifiedToday) {
                this.showMedicationNotification(reminder);
            }
        });
    }

    // İlaç bildirimi göster
    showMedicationNotification(reminder) {
        const notificationMsg = `İlaç zamanı: ${reminder.medicationName} - ${reminder.dosage}`;
        
        // Browser bildirimi
        if (Notification.permission === 'granted') {
            new Notification('MediAsistan - İlaç Hatırlatıcı', {
                body: notificationMsg,
                icon: '/assets/images/icon.png'
            });
        }
        
        // Sayfa içi bildirim
        this.showNotification(notificationMsg, 'info');
        
        // Sesli bildirim (opsiyonel)
        this.playNotificationSound();
    }

    // Otomatik çıkış yönetimi
    initAutoLogout() {
        let inactivityTime = 0;
        
        const resetTimer = () => {
            inactivityTime = 0;
        };

        // Kullanıcı aktivitelerini dinle
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        events.forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        // Her dakika kontrol et
        setInterval(() => {
            inactivityTime++;
            
            // 30 dakika sonra uyarı göster
            if (inactivityTime === 30) {
                this.showNotification('Uzun süredir işlem yapılmadı. 5 dakika içinde otomatik çıkış yapılacak.', 'warning');
            }
            
            // 35 dakika sonra çıkış yap
            if (inactivityTime === 35) {
                this.logout();
            }
        }, 60000); // 1 dakika
    }

    // Form validasyonları
    initFormValidations() {
        const forms = document.querySelectorAll('form[needs-validation]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
        });

        // Telefon numarası formatlama
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                e.target.value = this.formatPhoneNumber(e.target.value);
            });
        });
    }

    // Telefon numarası formatlama
    formatPhoneNumber(phone) {
        // Sadece rakamları al
        const numbers = phone.replace(/\D/g, '');
        
        // Formatı uygula
        if (numbers.length <= 3) {
            return numbers;
        } else if (numbers.length <= 6) {
            return numbers.replace(/(\d{3})(\d{0,3})/, '$1 $2');
        } else if (numbers.length <= 10) {
            return numbers.replace(/(\d{3})(\d{3})(\d{0,4})/, '$1 $2 $3');
        } else {
            return numbers.replace(/(\d{1})(\d{3})(\d{3})(\d{0,4})/, '$1 $2 $3 $4');
        }
    }

    // Mobil menü yönetimi
    initMobileMenu() {
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', () => {
                navbarCollapse.classList.toggle('show');
            });
            
            // Menü dışına tıklanınca kapat
            document.addEventListener('click', (e) => {
                if (!navbarToggler.contains(e.target) && !navbarCollapse.contains(e.target)) {
                    navbarCollapse.classList.remove('show');
                }
            });
        }
    }

    // Smooth scroll
    initSmoothScroll() {
        const links = document.querySelectorAll('a[href^="#"]');
        
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                const targetId = link.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Bildirim göster
    showNotification(message, type = 'info') {
        // Bootstrap alert kullanarak bildirim göster
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        `;
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // 5 saniye sonra kaldır
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }

    // Bildirim sesi çal
    playNotificationSound() {
        const audio = new Audio('/assets/sounds/notification.mp3');
        audio.play().catch(e => console.log('Ses çalınamadı:', e));
    }

    // Acil durum aksiyonunu logla
    async logEmergencyAction(action, details) {
        try {
            await fetch('backend/api/emergency_contacts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'log_emergency',
                    type: action,
                    details: details,
                    timestamp: new Date().toISOString()
                })
            });
        } catch (error) {
            console.error('Log kaydı hatası:', error);
        }
    }

    // Çıkış yap
    logout() {
        if (confirm('Oturumunuz sonlandırılsın mı?')) {
            window.location.href = 'logout.php';
        }
    }
}

// Uygulamayı başlat
const mediAsistan = new MediAsistan();

// Global fonksiyonlar (diğer dosyalardan erişim için)
window.MediAsistan = {
    showNotification: (message, type) => mediAsistan.showNotification(message, type),
    formatPhone: (phone) => mediAsistan.formatPhoneNumber(phone),
    makeEmergencyCall: () => mediAsistan.makeEmergencyCall()
};