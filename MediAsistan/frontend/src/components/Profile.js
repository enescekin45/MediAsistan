import React, { useState } from 'react';
import './Profile.css';

function Profile({ user, setUser }) {
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: user.name || '',
    surname: user.surname || '',
    email: user.email || '',
    birthDate: user.birthDate || '',
    bloodType: user.bloodType || '',
    chronicDiseases: user.chronicDiseases || '',
    allergies: user.allergies || '',
    regularMedications: user.regularMedications || ''
  });
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData({
      ...formData,
      [name]: value
    });
    
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
      // Gerçek uygulamada backend API'sine güncelleme isteği gönderilecek
      // Şimdilik sadece frontend'de güncelliyoruz
      
      const updatedUser = {
        ...user,
        ...formData,
        updatedAt: new Date().toISOString()
      };
      
      setUser(updatedUser);
      localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
      
      setIsEditing(false);
      alert('Profil bilgileriniz başarıyla güncellendi.');
    } catch (error) {
      console.error('Profil güncelleme hatası:', error);
      setErrors({ general: 'Profil güncelleme sırasında bir hata oluştu' });
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancel = () => {
    setFormData({
      name: user.name || '',
      surname: user.surname || '',
      email: user.email || '',
      birthDate: user.birthDate || '',
      bloodType: user.bloodType || '',
      chronicDiseases: user.chronicDiseases || '',
      allergies: user.allergies || '',
      regularMedications: user.regularMedications || ''
    });
    setErrors({});
    setIsEditing(false);
  };

  return (
    <div className="profile-container">
      <div className="profile-header">
        <h2>Profil Bilgilerim</h2>
        {!isEditing && (
          <button 
            className="edit-btn"
            onClick={() => setIsEditing(true)}
          >
            Profili Düzenle
          </button>
        )}
      </div>
      
      {isEditing ? (
        <form onSubmit={handleSubmit} className="profile-form">
          <div className="form-row">
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
          
          <div className="form-row">
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
          </div>
          
          <div className="form-group">
            <label htmlFor="chronicDiseases">Kronik Hastalıklar</label>
            <textarea
              id="chronicDiseases"
              name="chronicDiseases"
              value={formData.chronicDiseases}
              onChange={handleChange}
              placeholder="Diyabet, kalp hastalığı, vs."
            />
          </div>
          
          <div className="form-group">
            <label htmlFor="allergies">Alerjiler</label>
            <textarea
              id="allergies"
              name="allergies"
              value={formData.allergies}
              onChange={handleChange}
              placeholder="İlaç, gıda, vs. alerjileri"
            />
          </div>
          
          <div className="form-group">
            <label htmlFor="regularMedications">Sürekli Kullanılan İlaçlar</label>
            <textarea
              id="regularMedications"
              name="regularMedications"
              value={formData.regularMedications}
              onChange={handleChange}
              placeholder="Düzenli kullandığınız ilaçlar"
            />
          </div>
          
          {errors.general && <div className="error-general">{errors.general}</div>}
          
          <div className="form-actions">
            <button 
              type="button" 
              onClick={handleCancel}
              className="cancel-btn"
            >
              İptal
            </button>
            <button 
              type="submit" 
              disabled={isLoading}
              className="save-btn"
            >
              {isLoading ? 'Kaydediliyor...' : 'Değişiklikleri Kaydet'}
            </button>
          </div>
        </form>
      ) : (
        <div className="profile-info">
          <div className="info-section">
            <h3>Kişisel Bilgiler</h3>
            <div className="info-grid">
              <div className="info-item">
                <span className="info-label">Ad Soyad:</span>
                <span className="info-value">{user.name} {user.surname}</span>
              </div>
              <div className="info-item">
                <span className="info-label">E-posta:</span>
                <span className="info-value">{user.email}</span>
              </div>
              <div className="info-item">
                <span className="info-label">Doğum Tarihi:</span>
                <span className="info-value">
                  {user.birthDate ? new Date(user.birthDate).toLocaleDateString('tr-TR') : 'Belirtilmemiş'}
                </span>
              </div>
              <div className="info-item">
                <span className="info-label">Kan Grubu:</span>
                <span className="info-value">{user.bloodType || 'Belirtilmemiş'}</span>
              </div>
            </div>
          </div>
          
          <div className="info-section">
            <h3>Sağlık Bilgileri</h3>
            <div className="info-grid">
              <div className="info-item full-width">
                <span className="info-label">Kronik Hastalıklar:</span>
                <span className="info-value">
                  {user.chronicDiseases || 'Belirtilmemiş'}
                </span>
              </div>
              <div className="info-item full-width">
                <span className="info-label">Alerjiler:</span>
                <span className="info-value">
                  {user.allergies || 'Belirtilmemiş'}
                </span>
              </div>
              <div className="info-item full-width">
                <span className="info-label">Sürekli Kullanılan İlaçlar:</span>
                <span className="info-value">
                  {user.regularMedications || 'Belirtilmemiş'}
                </span>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default Profile;