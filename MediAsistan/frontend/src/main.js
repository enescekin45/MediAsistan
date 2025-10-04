import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import './App.css';

// Bileşenleri import ediyoruz
import Register from './components/Register';
import Profile from './components/Profile';
import FirstAid from './components/FirstAid';
import EmergencyContacts from './components/EmergencyContacts';
import Medications from './components/Medications';


function Main() {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);

  // Uygulama başladığında kullanıcı oturumunu kontrol et
  useEffect(() => {
    const checkAuthStatus = () => {
      const savedUser = localStorage.getItem('mediAsistanUser');
      if (savedUser) {
        setUser(JSON.parse(savedUser));
      }
      setIsLoading(false);
    };

    checkAuthStatus();
  }, []);

  // Kullanıcı giriş yaptığında çalışacak fonksiyon
  const handleLogin = (userData) => {
    setUser(userData);
    localStorage.setItem('mediAsistanUser', JSON.stringify(userData));
  };

  // Kullanıcı çıkış yaptığında çalışacak fonksiyon
  const handleLogout = () => {
    setUser(null);
    localStorage.removeItem('mediAsistanUser');
  };

  // 112'yi aramak için fonksiyon
  const callEmergency = () => {
    window.location.href = 'tel:112';
  };

  // Panik butonu fonksiyonu
  const handlePanicButton = () => {
    if (user && user.emergencyContacts && user.emergencyContacts.length > 0) {
      // Burada gerçek uygulamada backend API'sine istek atılacak
      alert(`Acil durum mesajı ${user.emergencyContacts.length} kişiye gönderiliyor...`);
      
      // Konum bilgisini al
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
          const { latitude, longitude } = position.coords;
          const locationLink = `https://maps.google.com/?q=${latitude},${longitude}`;
          
          // Her bir acil iletişim kişisine mesaj gönder (simülasyon)
          user.emergencyContacts.forEach(contact => {
            console.log(`${contact.name} (${contact.phone}) adlı kişiye mesaj gönderildi: 
              ${user.name} acil yardıma ihtiyaç duyuyor! Mevcut konumu: ${locationLink}`);
          });
        });
      }
    } else {
      alert('Lütfen önce acil durum kişilerinizi ekleyin.');
    }
  };

  if (isLoading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
        <p>MediAsistan yükleniyor...</p>
      </div>
    );
  }

  return (
    <Router>
      <div className="App">
        {/* Üst navigasyon çubuğu */}
        {user && (
          <nav className="navbar">
            <div className="nav-brand">
              <h1>MediAsistan 🏥</h1>
            </div>
            <div className="nav-links">
              <span>Hoş geldiniz, {user.name}</span>
              <button onClick={handleLogout} className="logout-btn">Çıkış Yap</button>
            </div>
          </nav>
        )}
        
        {/* Ana içerik alanı */}
        <div className="main-content">
          <Routes>
            {/* Kullanıcı giriş yapmamışsa login sayfasına yönlendir */}
            <Route 
              path="/" 
              element={user ? <Navigate to="/dashboard" /> : <Navigate to="/login" />} 
            />
            <Route 
              path="/login" 
              element={user ? <Navigate to="/dashboard" /> : <Login onLogin={handleLogin} />} 
            />
            <Route 
              path="/register" 
              element={user ? <Navigate to="/dashboard" /> : <Register onLogin={handleLogin} />} 
            />
            
            {/* Kullanıcı giriş yapmışsa diğer sayfalara erişebilir */}
            {user && (
              <>
                <Route path="/dashboard" element={<Dashboard user={user} />} />
                <Route path="/profile" element={<Profile user={user} setUser={setUser} />} />
                <Route path="/firstaid" element={<FirstAid />} />
                <Route path="/emergency-contacts" element={<EmergencyContacts user={user} setUser={setUser} />} />
                <Route path="/medication" element={<MedicationTracker user={user} setUser={setUser} />} />
              </>
            )}
            
            {/* Tanımlanmamış yollar için dashboard'a yönlendir */}
            <Route path="*" element={<Navigate to={user ? "/dashboard" : "/login"} />} />
          </Routes>
        </div>
        
        {/* Acil durum butonları (sadece giriş yapılmışsa görünür) */}
        {user && (
          <div className="emergency-buttons">
            <button className="emergency-call-btn" onClick={callEmergency}>
              📞 112'yi Ara
            </button>
            <button className="panic-btn" onClick={handlePanicButton}>
              🚨 Panik Butonu
            </button>
          </div>
        )}
      </div>
    </Router>
  );
}

export default Main;