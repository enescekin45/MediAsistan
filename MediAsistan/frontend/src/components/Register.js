import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import './Register.css';

function Register({ onLogin }) {
  const [formData, setFormData] = useState({
    name: '',
    surname: '',
    email: '',
    password: '',
    birthDate: '',
    bloodType: '',
    chronicDiseases: '',
    allergies: '',
    regularMedications: ''
  });
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });
    
    // Hata mesajını temizle
    if (errors[name]) {
      setErrors({
        ...errors,
        [name]: ''
      });
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) newErrors.name = 'Ad alanı zorunludur';
    if (!formData.surname.trim()) newErrors.surname = 'Soyad alanı zorunludur';
    if (!formData.email.trim()) {
      newErrors.email = 'E-posta alanı zorunludur';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'Geçerli bir e-posta adresi girin';
    }
    if (!formData.password) {
      newErrors.password = 'Şifre alanı zorunludur';
    } else if (formData.password.length < 6) {
      newErrors.password = 'Şifre en az 6 karakter olmalıdır';
    }
    if (!formData.birthDate) newErrors.birthDate = 'Doğum tarihi zorunludur';
    if (!formData.bloodType) newErrors.bloodType = 'Kan grubu zorunludur';
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    setIsLoading(true);
    
    try {
      // Burada gerçek uygulamada backend API'sine kayıt isteği gönderilecek
      // Şimdilik simüle ediyoruz
      
      // Kullanıcı verilerini hazırla
      const userData = {
        id: Date.now(), // Geçici ID
        ...formData,
        emergencyContacts: [],
        medications: [],
        createdAt: new Date().toISOString()
      };
      
      // Kullanıcıyı "giriş yapmış" say ve ana sayfaya yönlendir
      onLogin(userData);
      navigate('/dashboard');
    } catch (error) {
      console.error('Kayıt hatası:', error);
      setErrors({ general: 'Kayıt işlemi sırasında bir hata oluştu' });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="register-container">
      <div className="register-form">
        <h2>MediAsistan'a Kayıt Ol</h2>
        
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="name">Ad *</label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className={errors.name ? 'error' : ''}
            />
            {errors.name && <span className="error-text">{errors.name}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="surname">Soyad *</label>
            <input
              type="text"
              id="surname"
              name="surname"
              value={formData.surname}
              onChange={handleChange}
              className={errors.surname ? 'error' : ''}
            />
            {errors.surname && <span className="error-text">{errors.surname}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="email">E-posta *</label>
            <input
              type="email"
              id="email"
              name="email"
              value={formData.email}
              onChange={handleChange}
              className={errors.email ? 'error' : ''}
            />
            {errors.email && <span className="error-text">{errors.email}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="password">Şifre *</label>
            <input
              type="password"
              id="password"
              name="password"
              value={formData.password}
              onChange={handleChange}
              className={errors.password ? 'error' : ''}
            />
            {errors.password && <span className="error-text">{errors.password}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="birthDate">Doğum Tarihi *</label>
            <input
              type="date"
              id="birthDate"
              name="birthDate"
              value={formData.birthDate}
              onChange={handleChange}
              className={errors.birthDate ? 'error' : ''}
            />
            {errors.birthDate && <span className="error-text">{errors.birthDate}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="bloodType">Kan Grubu *</label>
            <select
              id="bloodType"
              name="bloodType"
              value={formData.bloodType}
              onChange={handleChange}
              className={errors.bloodType ? 'error' : ''}
            >
              <option value="">Seçiniz</option>
              <option value="A+">A Rh+</option>
              <option value="A-">A Rh-</option>
              <option value="B+">B Rh+</option>
              <option value="B-">B Rh-</option>
              <option value="AB+">AB Rh+</option>
              <option value="AB-">AB Rh-</option>
              <option value="0+">0 Rh+</option>
              <option value="0-">0 Rh-</option>
            </select>
            {errors.bloodType && <span className="error-text">{errors.bloodType}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="chronicDiseases">Kronik Hastalıklar (varsa)</label>
            <textarea
              id="chronicDiseases"
              name="chronicDiseases"
              value={formData.chronicDiseases}
              onChange={handleChange}
              placeholder="Diyabet, kalp hastalığı, vs."
            />
          </div>
          
          <div className="form-group">
            <label htmlFor="allergies">Alerjiler (varsa)</label>
            <textarea
              id="allergies"
              name="allergies"
              value={formData.allergies}
              onChange={handleChange}
              placeholder="İlaç, gıda, vs. alerjileri"
            />
          </div>
          
          <div className="form-group">
            <label htmlFor="regularMedications">Sürekli Kullanılan İlaçlar (varsa)</label>
            <textarea
              id="regularMedications"
              name="regularMedications"
              value={formData.regularMedications}
              onChange={handleChange}
              placeholder="Düzenli kullandığınız ilaçlar"
            />
          </div>
          
          {errors.general && <div className="error-general">{errors.general}</div>}
          
          <button type="submit" disabled={isLoading} className="submit-btn">
            {isLoading ? 'Kayıt Yapılıyor...' : 'Kayıt Ol'}
          </button>
        </form>
        
        <p className="login-link">
          Zaten hesabınız var mı? <Link to="/login">Giriş yapın</Link>
        </p>
      </div>
    </div>
  );
}

export default Register;