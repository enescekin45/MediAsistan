-- MediAsistan Veritabanı Yedekleme
-- Yedekleme Tarihi: 2025-10-02 19:54:11

SET FOREIGN_KEY_CHECKS=0;

-- Tablo yapısı: `acil_durum_kayitlari`
DROP TABLE IF EXISTS `acil_durum_kayitlari`;
CREATE TABLE `acil_durum_kayitlari` (
  `kayit_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `tetikleme_tipi` enum('panik_butonu','ilk_yardim_rehberi','otomatik') DEFAULT 'panik_butonu',
  `konum_lat` decimal(10,8) DEFAULT NULL,
  `konum_lon` decimal(11,8) DEFAULT NULL,
  `konum_adresi` text DEFAULT NULL,
  `durum` enum('baslatildi','devam_ediyor','tamamlandi','iptal_edildi') DEFAULT 'baslatildi',
  `baslama_zamani` datetime DEFAULT current_timestamp(),
  `bitis_zamani` datetime DEFAULT NULL,
  PRIMARY KEY (`kayit_id`),
  KEY `kullanici_id` (`kullanici_id`),
  KEY `kategori_id` (`kategori_id`),
  KEY `idx_tarih` (`baslama_zamani`),
  CONSTRAINT `acil_durum_kayitlari_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE,
  CONSTRAINT `acil_durum_kayitlari_ibfk_2` FOREIGN KEY (`kategori_id`) REFERENCES `ilk_yardim_kategorileri` (`kategori_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `acil_durum_kayitlari`
INSERT INTO `acil_durum_kayitlari` VALUES
('1', '1', NULL, 'panik_butonu', '41.00820000', '28.97840000', 'İstanbul, Türkiye', 'tamamlandi', '2025-09-26 19:24:41', '2025-09-26 19:24:42'),
('59', '3', NULL, 'panik_butonu', '41.00820000', '28.97840000', 'Test Konumu - İstanbul', 'tamamlandi', '2025-09-27 21:28:05', '2025-09-27 21:28:05'),
('60', '3', NULL, 'panik_butonu', '39.02873370', '31.78153890', 'Gerçek zamanlı konum', 'tamamlandi', '2025-09-27 21:28:17', '2025-09-27 21:28:18'),
('61', '3', NULL, 'panik_butonu', '41.00820000', '28.97840000', 'Test Konumu - İstanbul', 'tamamlandi', '2025-09-27 21:30:50', '2025-09-27 21:30:50'),
('62', '3', NULL, 'panik_butonu', '39.02873370', '31.78153890', 'Gerçek zamanlı konum', 'tamamlandi', '2025-09-27 21:31:33', '2025-09-27 21:31:33'),
('63', '3', NULL, 'panik_butonu', '39.02873370', '31.78153890', 'Gerçek zamanlı konum', 'tamamlandi', '2025-09-27 22:04:58', '2025-09-27 22:04:59'),
('64', '3', NULL, 'panik_butonu', '41.00820000', '28.97840000', 'Test Konumu - İstanbul', 'tamamlandi', '2025-09-27 22:05:31', '2025-09-27 22:05:31'),
('65', '3', NULL, 'panik_butonu', '38.65968640', '32.93184000', 'Gerçek zamanlı konum', 'tamamlandi', '2025-09-27 23:11:59', '2025-09-27 23:11:59'),
('66', '1', NULL, 'panik_butonu', '38.65968640', '32.93184000', 'Gerçek zamanlı konum', 'tamamlandi', '2025-09-30 22:25:27', '2025-09-30 22:25:28'),
('67', '1', NULL, 'panik_butonu', '41.00820000', '28.97840000', 'Test Konumu - İstanbul', 'tamamlandi', '2025-09-30 22:26:07', '2025-09-30 22:26:08'),
('68', '3', NULL, 'panik_butonu', '39.02874390', '31.78154010', 'Gerçek zamanlı konum', 'tamamlandi', '2025-10-01 23:08:43', '2025-10-01 23:08:44'),
('69', '1', NULL, 'panik_butonu', '37.89619200', '32.48619520', 'Gerçek zamanlı konum', 'tamamlandi', '2025-10-02 19:52:23', '2025-10-02 19:52:24');

-- Tablo yapısı: `acil_durum_kisileri`
DROP TABLE IF EXISTS `acil_durum_kisileri`;
CREATE TABLE `acil_durum_kisileri` (
  `kisi_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `ad_soyad` varchar(100) NOT NULL,
  `telefon` varchar(20) NOT NULL,
  `eposta` varchar(255) DEFAULT NULL,
  `iliski` enum('aile','arkadas','doktor','komsu','diger') DEFAULT 'aile',
  `sira_no` tinyint(4) DEFAULT 1,
  `aktif_mi` tinyint(1) DEFAULT 1,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`kisi_id`),
  UNIQUE KEY `unique_kullanici_kisi` (`kullanici_id`,`telefon`),
  CONSTRAINT `acil_durum_kisileri_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `acil_durum_kisileri`
INSERT INTO `acil_durum_kisileri` VALUES
('1', '1', 'Ayşe Yılmaz', '+905553334455', NULL, 'aile', '1', '1', '2025-09-24 22:41:50'),
('2', '1', 'Mehmet Demir', '+905556667788', NULL, 'doktor', '2', '1', '2025-09-24 22:41:50'),
('3', '3', 'Mustafa Yıldırım', '05314896253', 'musatafa@gmail.com', 'aile', '1', '1', '2025-09-27 22:01:39');

-- Tablo yapısı: `adminler`
DROP TABLE IF EXISTS `adminler`;
CREATE TABLE `adminler` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `eposta` varchar(255) NOT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `ad` varchar(100) NOT NULL,
  `soyad` varchar(100) NOT NULL,
  `yetkiler` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '["users", "medications", "reports", "settings"]' CHECK (json_valid(`yetkiler`)),
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  `son_giris` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `eposta` (`eposta`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `adminler`
INSERT INTO `adminler` VALUES
('1', 'admin@mediasistan.com', '$2y$10$M2hYFCwaKDMZIfLJBpDwBuOskuTF8Fib1DMq0kWvVzBo9Lq8/8e2y', 'Sistem', 'Yöneticisi', '[\"users\", \"medications\", \"reports\", \"settings\"]', 'aktif', NULL, '2025-09-29 18:51:10', '2025-09-30 17:32:59');

-- Tablo yapısı: `bildirim_gecmisi`
DROP TABLE IF EXISTS `bildirim_gecmisi`;
CREATE TABLE `bildirim_gecmisi` (
  `bildirim_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `bildirim_tipi` enum('ilac_hatirlatma','stok_uyarisi','acil_durum','sistem') NOT NULL,
  `baslik` varchar(200) NOT NULL,
  `mesaj` text NOT NULL,
  `hedef_id` int(11) DEFAULT NULL,
  `gonderim_durumu` enum('bekliyor','gonderildi','hata') DEFAULT 'bekliyor',
  `gonderim_zamani` datetime DEFAULT NULL,
  `okundu_mi` tinyint(1) DEFAULT 0,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`bildirim_id`),
  KEY `kullanici_id` (`kullanici_id`),
  KEY `idx_durum_tarih` (`gonderim_durumu`,`olusturulma_tarihi`),
  CONSTRAINT `bildirim_gecmisi_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=127 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `bildirim_gecmisi`
INSERT INTO `bildirim_gecmisi` VALUES
('112', '1', 'acil_durum', 'Acil Durum - Mehmet Demir', '🆘 ACİL DURUM! Ahmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\n📞 İletişim: +905551234567\n📍 Konum: https://maps.google.com/?q=41.0082,28.9784\n🕒 Zaman: 26.09.2025 22:32:24', '2', 'gonderildi', '2025-09-26 22:32:24', '0', '2025-09-26 22:32:24'),
('113', '1', 'acil_durum', 'Acil Durum - Ayşe Yılmaz', '🆘 ACİL DURUM! Ahmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\n📞 İletişim: +905551234567\n📍 Konum: https://maps.google.com/?q=41.0082,28.9784\n🕒 Zaman: 26.09.2025 22:33:50', '1', 'gonderildi', '2025-09-26 22:33:50', '0', '2025-09-26 22:33:50'),
('117', '3', 'acil_durum', 'Acil Durum - Mustafa Yıldırım', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mustafa Yıldırım,\n\nEnes Cekin acil yardıma ihtiyaç duyuyor!\n\nİletişim: 05888875\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=39.0287337,31.7815389\nZaman: 27.09.2025 22:04:59\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '3', 'gonderildi', NULL, '0', '2025-09-27 22:04:59'),
('118', '3', 'acil_durum', 'Acil Durum - Mustafa Yıldırım', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mustafa Yıldırım,\n\nEnes Cekin acil yardıma ihtiyaç duyuyor!\n\nİletişim: 05888875\nKonum: Test Konumu - İstanbul\nHarita: https://maps.google.com/?q=41.0082,28.9784\nZaman: 27.09.2025 22:05:31\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '3', 'gonderildi', NULL, '0', '2025-09-27 22:05:31'),
('119', '3', 'acil_durum', 'Acil Durum - Mustafa Yıldırım', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mustafa Yıldırım,\n\nEnes Cekin acil yardıma ihtiyaç duyuyor!\n\nİletişim: 05888875\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=38.6596864,32.93184\nZaman: 27.09.2025 23:11:59\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '3', 'gonderildi', NULL, '0', '2025-09-27 23:11:59'),
('120', '1', 'acil_durum', 'Acil Durum - Ayşe Yılmaz', 'ACİL DURUM BİLDİRİMİ\n\nSayın Ayşe Yılmaz,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=38.6596864,32.93184\nZaman: 30.09.2025 22:25:28\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '1', 'gonderildi', NULL, '0', '2025-09-30 22:25:28'),
('121', '1', 'acil_durum', 'Acil Durum - Mehmet Demir', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mehmet Demir,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=38.6596864,32.93184\nZaman: 30.09.2025 22:25:28\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '2', 'gonderildi', NULL, '0', '2025-09-30 22:25:28'),
('122', '1', 'acil_durum', 'Acil Durum - Ayşe Yılmaz', 'ACİL DURUM BİLDİRİMİ\n\nSayın Ayşe Yılmaz,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Test Konumu - İstanbul\nHarita: https://maps.google.com/?q=41.0082,28.9784\nZaman: 30.09.2025 22:26:08\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '1', 'gonderildi', NULL, '0', '2025-09-30 22:26:08'),
('123', '1', 'acil_durum', 'Acil Durum - Mehmet Demir', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mehmet Demir,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Test Konumu - İstanbul\nHarita: https://maps.google.com/?q=41.0082,28.9784\nZaman: 30.09.2025 22:26:08\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '2', 'gonderildi', NULL, '0', '2025-09-30 22:26:08'),
('124', '3', 'acil_durum', 'Acil Durum - Mustafa Yıldırım', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mustafa Yıldırım,\n\nEnes Çekin acil yardıma ihtiyaç duyuyor!\n\nİletişim: 05888875\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=39.0287439,31.7815401\nZaman: 01.10.2025 23:08:44\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '3', 'gonderildi', NULL, '0', '2025-10-01 23:08:44'),
('125', '1', 'acil_durum', 'Acil Durum - Ayşe Yılmaz', 'ACİL DURUM BİLDİRİMİ\n\nSayın Ayşe Yılmaz,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=37.896192,32.4861952\nZaman: 02.10.2025 19:52:23\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '1', 'gonderildi', NULL, '0', '2025-10-02 19:52:23'),
('126', '1', 'acil_durum', 'Acil Durum - Mehmet Demir', 'ACİL DURUM BİLDİRİMİ\n\nSayın Mehmet Demir,\n\nAhmet Yılmaz acil yardıma ihtiyaç duyuyor!\n\nİletişim: +905551234567\nKonum: Gerçek zamanlı konum\nHarita: https://maps.google.com/?q=37.896192,32.4861952\nZaman: 02.10.2025 19:52:24\n\nLütfen derhal müdahale edin veya 112\'yi arayın.', '2', 'gonderildi', NULL, '0', '2025-10-02 19:52:24');

-- Tablo yapısı: `bugunun_ilac_hatirlatmalari`
DROP TABLE IF EXISTS `bugunun_ilac_hatirlatmalari`;
-- Tablo verileri: `bugunun_ilac_hatirlatmalari`
-- Tablo yapısı: `fcm_tokenlar`
DROP TABLE IF EXISTS `fcm_tokenlar`;
CREATE TABLE `fcm_tokenlar` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `device_token` text NOT NULL,
  `cihaz_tipi` enum('android','ios','web') NOT NULL,
  `son_aktivite` datetime DEFAULT current_timestamp(),
  `aktif_mi` tinyint(1) DEFAULT 1,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  KEY `idx_token` (`device_token`(255)),
  KEY `idx_kullanici_cihaz` (`kullanici_id`,`cihaz_tipi`),
  CONSTRAINT `fcm_tokenlar_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `fcm_tokenlar`
-- Tablo yapısı: `ilac_alim_gecmisi`
DROP TABLE IF EXISTS `ilac_alim_gecmisi`;
CREATE TABLE `ilac_alim_gecmisi` (
  `alim_id` int(11) NOT NULL AUTO_INCREMENT,
  `ilac_id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `alim_tarihi` date NOT NULL,
  `alim_saati` time NOT NULL,
  `planlanan_saat` time NOT NULL,
  `durum` enum('alındı','atlandı','ertelendi') DEFAULT 'alındı',
  `alinan_dozaj` varchar(50) DEFAULT NULL,
  `notlar` text DEFAULT NULL,
  `kayit_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`alim_id`),
  KEY `ilac_id` (`ilac_id`),
  KEY `idx_tarih_saat` (`alim_tarihi`,`alim_saati`),
  KEY `idx_kullanici_tarih` (`kullanici_id`,`alim_tarihi`),
  CONSTRAINT `ilac_alim_gecmisi_ibfk_1` FOREIGN KEY (`ilac_id`) REFERENCES `ilaclar` (`ilac_id`) ON DELETE CASCADE,
  CONSTRAINT `ilac_alim_gecmisi_ibfk_2` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `ilac_alim_gecmisi`
-- Tablo yapısı: `ilac_hatirlatma_zamanlari`
DROP TABLE IF EXISTS `ilac_hatirlatma_zamanlari`;
CREATE TABLE `ilac_hatirlatma_zamanlari` (
  `hatirlatma_id` int(11) NOT NULL AUTO_INCREMENT,
  `ilac_id` int(11) NOT NULL,
  `saat` time NOT NULL,
  `pazartesi` tinyint(1) DEFAULT 1,
  `sali` tinyint(1) DEFAULT 1,
  `carsamba` tinyint(1) DEFAULT 1,
  `persembe` tinyint(1) DEFAULT 1,
  `cuma` tinyint(1) DEFAULT 1,
  `cumartesi` tinyint(1) DEFAULT 1,
  `pazar` tinyint(1) DEFAULT 1,
  `aktif_mi` tinyint(1) DEFAULT 1,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`hatirlatma_id`),
  UNIQUE KEY `unique_ilac_saat` (`ilac_id`,`saat`),
  CONSTRAINT `ilac_hatirlatma_zamanlari_ibfk_1` FOREIGN KEY (`ilac_id`) REFERENCES `ilaclar` (`ilac_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `ilac_hatirlatma_zamanlari`
INSERT INTO `ilac_hatirlatma_zamanlari` VALUES
('3', '2', '09:00:00', '1', '1', '1', '1', '1', '1', '1', '1', '2025-09-24 22:41:50');

-- Tablo yapısı: `ilaclar`
DROP TABLE IF EXISTS `ilaclar`;
CREATE TABLE `ilaclar` (
  `ilac_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `ilac_adi` varchar(100) NOT NULL,
  `dozaj` varchar(50) NOT NULL,
  `stok_adedi` int(11) DEFAULT 0,
  `kritik_stok_seviyesi` int(11) DEFAULT 3,
  `receteli_mi` tinyint(1) DEFAULT 0,
  `ilac_tipi` enum('tablet','sıvı','kapsül','sprey','merhem','diğer') DEFAULT 'tablet',
  `barkod` varchar(100) DEFAULT NULL,
  `baslama_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ilac_id`),
  KEY `idx_kullanici_ilac` (`kullanici_id`,`ilac_adi`),
  CONSTRAINT `ilaclar_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `ilaclar`
INSERT INTO `ilaclar` VALUES
('2', '1', 'Lisinopril', '10 mg', '15', '3', '0', 'tablet', NULL, NULL, NULL, NULL, '2025-09-24 22:41:50', '2025-09-24 22:41:50'),
('3', '3', 'Kan Tansiyonu İlacı', '1 Tablet / Gün', '10', '3', '0', 'tablet', NULL, NULL, NULL, 'Sabah kahvaltısından sonra alınmalıdır.', '2025-09-25 16:52:02', '2025-09-29 18:25:08'),
('4', '1', 'parol', '40', '4', '3', '1', 'tablet', '', '2025-09-29', '2025-09-30', 'sds', '2025-09-30 19:08:13', '2025-09-30 19:17:09');

-- Tablo yapısı: `ilk_yardim_kategorileri`
DROP TABLE IF EXISTS `ilk_yardim_kategorileri`;
CREATE TABLE `ilk_yardim_kategorileri` (
  `kategori_id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_adi` varchar(100) NOT NULL,
  `kategori_ikon` varchar(50) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `onem_derecesi` tinyint(4) DEFAULT 1,
  `sira_no` tinyint(4) DEFAULT 1,
  `aktif_mi` tinyint(1) DEFAULT 1,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`kategori_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `ilk_yardim_kategorileri`
INSERT INTO `ilk_yardim_kategorileri` VALUES
('1', 'Kalp Krizi', 'heart', NULL, '5', '1', '1', '2025-09-24 22:41:50'),
('2', 'Yanık', 'fire', NULL, '3', '2', '1', '2025-09-24 22:41:50'),
('3', 'Boğulma', 'water', NULL, '4', '3', '1', '2025-09-24 22:41:50'),
('4', 'Şiddetli Kanama', 'droplet', NULL, '4', '4', '1', '2025-09-24 22:41:50'),
('5', 'Bayılma', 'user-x', NULL, '3', '5', '1', '2025-09-24 22:41:50'),
('6', 'Kırık ve Çıkık', 'bone', NULL, '3', '6', '1', '2025-09-24 22:41:50'),
('7', 'Zehirlenme', 'skull', NULL, '4', '7', '1', '2025-09-24 22:41:50');

-- Tablo yapısı: `ilk_yardim_talimatlari`
DROP TABLE IF EXISTS `ilk_yardim_talimatlari`;
CREATE TABLE `ilk_yardim_talimatlari` (
  `talimat_id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `adim_numarasi` tinyint(4) NOT NULL,
  `baslik` varchar(200) NOT NULL,
  `aciklama` text NOT NULL,
  `goruntuleme_suresi` smallint(6) DEFAULT NULL,
  `resim_url` varchar(255) DEFAULT NULL,
  `ses_dosyasi_url` varchar(255) DEFAULT NULL,
  `onemli_uyari` tinyint(1) DEFAULT 0,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`talimat_id`),
  UNIQUE KEY `unique_kategori_adim` (`kategori_id`,`adim_numarasi`),
  CONSTRAINT `ilk_yardim_talimatlari_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `ilk_yardim_kategorileri` (`kategori_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `ilk_yardim_talimatlari`
INSERT INTO `ilk_yardim_talimatlari` VALUES
('1', '1', '1', 'Sakin Olun ve Hastayı Yatırın', 'Hastayı sırtüstü yatırın, ayaklarını 30 cm yukarı kaldırın. Sıkı giysileri gevşetin.', NULL, NULL, NULL, '1', '2025-09-24 22:41:50'),
('2', '1', '2', '112\'yi Hemen Arayın', 'Acil servisi arayın ve durumu bildirin. Hastanın bilincini kontrol edin.', NULL, '', '', '1', '2025-09-24 22:41:50'),
('3', '1', '3', 'Aspirin Verin (Alerjisi Yoksa)', 'Hasta bilinçliyse ve aspirin alerjisi yoksa, 300 mg aspirin çiğnetin.', NULL, NULL, NULL, '0', '2025-09-24 22:41:50'),
('4', '2', '1', 'Yanığı Soğutun', 'Yanık bölgesini 15-20 dakika soğuk su altında tutun.', NULL, NULL, NULL, '1', '2025-09-24 22:41:50'),
('5', '2', '2', 'Yanık Üzerini Temizleyin', 'Yanık üzerindeki takıları çıkarın, temiz bir bezle örtün.', NULL, NULL, NULL, '0', '2025-09-24 22:41:50'),
('6', '2', '3', 'Tıbbi Yardım Çağırın', 'Ciddi yanıklarda derhal 112\'yi arayın.', NULL, NULL, NULL, '1', '2025-09-24 22:41:50'),
('7', '3', '1', 'Sakin olun ve güvenliği sağlayın', 'Öncelikle sakin olun. Kişiyi kurtarmak için kendi güvenliğinizi tehlikeye atmayın. Eğer su güvenli değilse, profesyonel yardım bekleyin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('8', '3', '2', 'Kişiyi sudan çıkarın', 'Kişiyi sudan çıkarırken dikkatli olun. Mümkünse can yeleği veya can simidi kullanın. Sudaki kişiye ulaşamıyorsanız, uzun bir cisim uzatarak yardım edin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('9', '3', '3', 'Bilinç kontrolü', 'Kişiyi sudan çıkardıktan sonra bilincini kontrol edin. Omuzlarına hafifçe vurarak \"İyi misiniz?\" diye sorun. Yanıt yoksa bilinç kaybı var demektir.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('10', '3', '4', '112\'yi arayın', 'Eğer kişi bilincini kaybetmişse veya nefes almıyorsa, derhal 112 acil servisi arayın. Durumu kısaca anlatın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('11', '3', '5', 'Suni solunum ve kalp masajı', 'Eğer kişi nefes almıyorsa, suni solunum ve kalp masajına başlayın. 30 kalp masajı ve 2 suni solunum şeklinde devam edin. Yardım gelene kadar sürdürün.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('12', '3', '6', 'Kişiyi sıcak tutun', 'Eğer kişi bilinci yerindeyse, onu sıcak tutun ve yardım gelene kadar yanında kalın. Islak giysileri çıkarıp battaniye ile örtün.', NULL, NULL, NULL, '0', '2025-09-27 22:30:34'),
('13', '4', '1', 'Sakin olun', 'Sakin olun ve hasta için derhal yardım çağırın. Panik yapmadan hızlı hareket edin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('14', '4', '2', 'Kanayan bölgeye baskı uygulayın', 'Temiz bir bez, gazlı bez veya eldiven ile kanayan bölgeye direkt baskı uygulayın. Baskıyı 5-10 dakika sürekli tutun.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('15', '4', '3', 'Bölgeyi yüksekte tutun', 'Kanayan uzvu (kol veya bacak) kalp seviyesinin üzerine yükseltin. Bu, kan akışını yavaşlatır.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('16', '4', '4', 'Turnike uygulayın (gerekirse)', 'Eğer kanama durmuyorsa ve şiddetliyse, turnike uygulayın. Turnikeyi kanamanın olduğu uzvun üst kısmına sıkıca bağlayın. Turnike zamanını not alın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('17', '4', '5', '112\'yi arayın', 'Derhal 112 ambulansını arayın. Kanamanın şiddeti ve uygulanan müdahaleler hakkında bilgi verin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('18', '5', '1', 'Kişiyi yatırın', 'Kişiyi sırt üstü yatırın ve ayaklarını yaklaşık 30 cm yüksekte tutun. Bu, kanın beyne dönüşünü kolaylaştırır.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('19', '5', '2', 'Sıkı giysileri gevşetin', 'Boyun, göğüs ve bel çevresindeki sıkı giysileri, kemer veya kravatı gevşetin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('20', '5', '3', 'Havalandırma sağlayın', 'Ortamın havalandırıldığından emin olun. Kalabalığı uzaklaştırın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('21', '5', '4', 'Bilinci kontrol edin', 'Kişinin bilincinin geri gelip gelmediğini kontrol edin. Yanıt vermiyorsa 112\'yi arayın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('22', '5', '5', '112\'yi arayın', 'Eğer kişi kısa sürede kendine gelmezse, nöbet geçiriyorsa veya tekrar bayılırsa, derhal 112\'yi arayın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('23', '6', '1', 'Yaralı bölgeyi hareket ettirmeyin', 'Kırık veya çıkık olan bölgeyi sabit tutun ve hareket ettirmeyin. Ekstra yaralanmayı önleyin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('24', '6', '2', 'Şişliği azaltın', 'Buz veya soğuk kompres uygulayarak şişliği ve ağrıyı azaltın. Buzu direkt cilde değdirmeyin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('25', '6', '3', 'Atel uygulayın', 'Mümkünse atel (tahta, karton gibi sert malzeme) ile yaralı bölgeyi sabitleyin. Ateli, eklemin üst ve altından bağlayın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('26', '6', '4', '112\'yi arayın', 'Tıbbi yardım isteyin. Yaralıyı hareket ettirmeden ambulans bekleyin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:36'),
('27', '7', '1', 'Zehirin kaynağını belirleyin', 'Zehirlenmeye neden olan maddeyi (ilaç, kimyasal, bitki vb.) belirlemeye çalışın. Maddeyi hasta ile birlikte saklayın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:37'),
('28', '7', '2', '112\'yi arayın', 'Derhal 112 veya Zehir Danışma Merkezi\'ni (114) arayın. Zehirli madde hakkında bilgi verin.', NULL, NULL, NULL, '0', '2025-09-27 22:30:37'),
('29', '7', '3', 'Kişiyi rahatlatın', 'Kişiyi sakinleştirin ve hareket ettirmeyin. Eğer bilinçsizse, yan yatırın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:37'),
('30', '7', '4', 'Kusmaya zorlamayın', 'Eğer zehir yutulmuşsa, kusturmaya çalışmayın. Bu, daha fazla hasara neden olabilir.', NULL, NULL, NULL, '0', '2025-09-27 22:30:37'),
('31', '7', '5', 'Zehirli maddeyi uzaklaştırın', 'Eğer zehir ciltte ise, bol su ve sabunla yıkayın. Gözde ise, bol su ile yıkayın.', NULL, NULL, NULL, '0', '2025-09-27 22:30:37');

-- Tablo yapısı: `kritik_stok_uyarilari`
DROP TABLE IF EXISTS `kritik_stok_uyarilari`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `kritik_stok_uyarilari` AS select `k`.`kullanici_id` AS `kullanici_id`,`k`.`eposta` AS `eposta`,`k`.`ad` AS `ad`,`k`.`soyad` AS `soyad`,`i`.`ilac_id` AS `ilac_id`,`i`.`ilac_adi` AS `ilac_adi`,`i`.`stok_adedi` AS `stok_adedi`,`i`.`kritik_stok_seviyesi` AS `kritik_stok_seviyesi` from (`kullanicilar` `k` join `ilaclar` `i` on(`k`.`kullanici_id` = `i`.`kullanici_id`)) where `i`.`stok_adedi` <= `i`.`kritik_stok_seviyesi` and `i`.`stok_adedi` > 0;

-- Tablo verileri: `kritik_stok_uyarilari`
-- Tablo yapısı: `kullanici_saglik_bilgileri`
DROP TABLE IF EXISTS `kullanici_saglik_bilgileri`;
CREATE TABLE `kullanici_saglik_bilgileri` (
  `saglik_id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `kronik_hastaliklar` text DEFAULT NULL,
  `ilac_alergileri` text DEFAULT NULL,
  `gida_alergileri` text DEFAULT NULL,
  `diger_alergiler` text DEFAULT NULL,
  `ozel_medical_notlar` text DEFAULT NULL,
  `acil_durum_notu` text DEFAULT NULL,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`saglik_id`),
  UNIQUE KEY `kullanici_id` (`kullanici_id`),
  CONSTRAINT `kullanici_saglik_bilgileri_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`kullanici_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `kullanici_saglik_bilgileri`
INSERT INTO `kullanici_saglik_bilgileri` VALUES
('1', '1', 'Hipertansiyon, Tip 2 Diyabet', 'Penisilin, Aspirin', NULL, NULL, NULL, NULL, '2025-09-24 22:41:50', '2025-09-24 22:41:50'),
('2', '3', 'Hipertansiyon Diyabet (Tip 2)', 'Penisilin Aspirin', 'Yer fıstığı Deniz ürünleri', 'Toz alerjisi Polen alerjisi', 'Düzenli insülin kullanıyor  Tansiyon ilacı sabah-akşam alınmalı', 'Acil durumda 112 aranmalı\r\n Yakını: Ahmet Yılmaz (0500 000 00 00)', '2025-09-25 20:14:02', '2025-09-25 20:14:02');

-- Tablo yapısı: `kullanicilar`
DROP TABLE IF EXISTS `kullanicilar`;
CREATE TABLE `kullanicilar` (
  `kullanici_id` int(11) NOT NULL AUTO_INCREMENT,
  `eposta` varchar(255) NOT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `ad` varchar(50) NOT NULL,
  `soyad` varchar(50) NOT NULL,
  `dogum_tarihi` date DEFAULT NULL,
  `kan_grubu` enum('A+','A-','B+','B-','AB+','AB-','0+','0-') DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `profil_foto` varchar(255) DEFAULT NULL,
  `olusturulma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `durum` enum('aktif','pasif') DEFAULT 'aktif',
  PRIMARY KEY (`kullanici_id`),
  UNIQUE KEY `eposta` (`eposta`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `kullanicilar`
INSERT INTO `kullanicilar` VALUES
('1', 'ahmet@mail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ahmet', 'Yılmaz', '1985-05-15', 'A+', '+905551234567', NULL, '2025-09-24 22:41:50', '2025-09-24 22:41:50', 'aktif'),
('3', 'horoxir659@euleina.com', '$2y$10$jEJFsXvV8pgfi68VIyBBMOEXLzoGOAPFpLfe.xYPRIdxwQYnNg0qS', 'Enes', 'Çekin', '2005-06-10', 'B+', '05888875', NULL, '2025-09-24 23:01:33', '2025-09-30 18:59:14', 'aktif');

-- Tablo yapısı: `site_ayarlari`
DROP TABLE IF EXISTS `site_ayarlari`;
CREATE TABLE `site_ayarlari` (
  `ayar_anahtari` varchar(255) NOT NULL,
  `ayar_degeri` text DEFAULT NULL,
  `olusturulma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ayar_anahtari`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablo verileri: `site_ayarlari`
INSERT INTO `site_ayarlari` VALUES
('admin_email', 'admin@mediasistan.com', '2025-09-30 21:58:44', '2025-09-30 21:58:44'),
('maintenance_mode', '0', '2025-09-30 21:58:44', '2025-09-30 21:58:44'),
('records_per_page', '10', '2025-09-30 21:58:44', '2025-09-30 21:58:44'),
('site_description', 'Sağlık Yönetim Sistemi', '2025-09-30 21:58:44', '2025-09-30 21:58:44'),
('site_keywords', 'sağlık, ilaç, acil durum', '2025-09-30 21:58:44', '2025-09-30 21:58:44'),
('site_title', 'MediAsistanaa', '2025-09-30 21:58:44', '2025-10-02 19:50:10');

SET FOREIGN_KEY_CHECKS=1;
