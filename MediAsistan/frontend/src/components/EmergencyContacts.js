import React, { useState } from 'react';
import './EmergencyContacts.css';

function EmergencyContacts({ user, setUser }) {
  const [isAdding, setIsAdding] = useState(false);
  const [editingIndex, setEditingIndex] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    phone: '',
    relationship: ''
  });
  const [errors, setErrors] = useState({});

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
    
    if (!formData.name.trim()) newErrors.name = 'İsim alanı zorunludur';
    if (!formData.phone.trim()) {
      newErrors.phone = 'Telefon numarası zorunludur';
    } else if (!/^[0-9+\-\s()]{10,}$/.test(formData.phone)) {
      newErrors.phone = 'Geçerli bir telefon numarası girin';
    }
    if (!formData.relationship.trim()) newErrors.relationship = 'İlişki türü zorunludur';
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    const updatedContacts = user.emergencyContacts ? [...user.emergencyContacts] : [];
    
    if (editingIndex !== null) {
      // Mevcut kişiyi düzenle
      updatedContacts[editingIndex] = formData;
    } else {
      // Yeni kişi ekle
      updatedContacts.push(formData);
    }
    
    const updatedUser = {
      ...user,
      emergencyContacts: updatedContacts
    };
    
    setUser(updatedUser);
    localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
    
    resetForm();
    alert(editingIndex !== null ? 'Kişi başarıyla güncellendi' : 'Kişi başarıyla eklendi');
  };

  const handleEdit = (index) => {
    const contact = user.emergencyContacts[index];
    setFormData(contact);
    setEditingIndex(index);
    setIsAdding(true);
  };

  const handleDelete = (index) => {
    if (window.confirm('Bu kişiyi silmek istediğinizden emin misiniz?')) {
      const updatedContacts = user.emergencyContacts.filter((_, i) => i !== index);
      
      const updatedUser = {
        ...user,
        emergencyContacts: updatedContacts
      };
      
      setUser(updatedUser);
      localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
      alert('Kişi başarıyla silindi');
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      phone: '',
      relationship: ''
    });
    setErrors({});
    setIsAdding(false);
    setEditingIndex(null);
  };

  const handleCancel = () => {
    resetForm();
  };

  return (
    <div className="emergency-contacts-container">
      <div className="contacts-header">
        <h2>Acil Durum Kişileri</h2>
        <p>Panik butonuna basıldığında bu kişilere otomatik olarak mesaj gönderilecektir.</p>
      </div>

      {!isAdding ? (
        <div className="contacts-list">
          {user.emergencyContacts && user.emergencyContacts.length > 0 ? (
            <div className="contacts-grid">
              {user.emergencyContacts.map((contact, index) => (
                <div key={index} className="contact-card">
                  <div className="contact-info">
                    <h3>{contact.name}</h3>
                    <p><strong>Telefon:</strong> {contact.phone}</p>
                    <p><strong>İlişki:</strong> {contact.relationship}</p>
                  </div>
                  <div className="contact-actions">
                    <button 
                      className="edit-btn"
                      onClick={() => handleEdit(index)}
                    >
                      Düzenle
                    </button>
                    <button 
                      className="delete-btn"
                      onClick={() => handleDelete(index)}
                    >
                      Sil
                    </button>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="no-contacts">
              <p>Henüz acil durum kişisi eklememişsiniz.</p>
            </div>
          )}
          
          <button 
            className="add-contact-btn"
            onClick={() => setIsAdding(true)}
          >
            + Yeni Kişi Ekle
          </button>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="contact-form">
          <h3>{editingIndex !== null ? 'Kişiyi Düzenle' : 'Yeni Kişi Ekle'}</h3>
          
          <div className="form-group">
            <label htmlFor="name">İsim Soyisim *</label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className={errors.name ? 'error' : ''}
              placeholder="Ahmet Yılmaz"
            />
            {errors.name && <span className="error-text">{errors.name}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="phone">Telefon Numarası *</label>
            <input
              type="tel"
              id="phone"
              name="phone"
              value={formData.phone}
              onChange={handleChange}
              className={errors.phone ? 'error' : ''}
              placeholder="+90 555 123 45 67"
            />
            {errors.phone && <span className="error-text">{errors.phone}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="relationship">İlişki *</label>
            <select
              id="relationship"
              name="relationship"
              value={formData.relationship}
              onChange={handleChange}
              className={errors.relationship ? 'error' : ''}
            >
              <option value="">Seçiniz</option>
              <option value="Aile Üyesi">Aile Üyesi</option>
              <option value="Arkadaş">Arkadaş</option>
              <option value="Eş">Eş</option>
              <option value="Ebeveyn">Ebeveyn</option>
              <option value="Çocuk">Çocuk</option>
              <option value="Doktor">Doktor</option>
              <option value="Diğer">Diğer</option>
            </select>
            {errors.relationship && <span className="error-text">{errors.relationship}</span>}
          </div>
          
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
              className="save-btn"
            >
              {editingIndex !== null ? 'Güncelle' : 'Ekle'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}

export default EmergencyContacts;