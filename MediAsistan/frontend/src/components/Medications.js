import React, { useState, useEffect } from 'react';
import './MedicationTracker.css';

function MedicationTracker({ user, setUser }) {
  const [isAdding, setIsAdding] = useState(false);
  const [editingIndex, setEditingIndex] = useState(null);
  const [formData, setFormData] = useState({
    name: '',
    dosage: '',
    times: [''],
    stock: 0
  });
  const [errors, setErrors] = useState({});

  useEffect(() => {
    // İlaç hatırlatıcılarını kontrol et (simülasyon)
    checkMedicationReminders();
  }, []);

  const checkMedicationReminders = () => {
    // Bu fonksiyon gerçek uygulamada belirli aralıklarla çalışacak
    // ve ilaç zamanı geldiğinde bildirim gösterecek
    console.log('İlaç hatırlatıcıları kontrol ediliyor...');
  };

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

  const handleTimeChange = (index, value) => {
    const newTimes = [...formData.times];
    newTimes[index] = value;
    setFormData({
      ...formData,
      times: newTimes
    });
  };

  const addTimeField = () => {
    setFormData({
      ...formData,
      times: [...formData.times, '']
    });
  };

  const removeTimeField = (index) => {
    if (formData.times.length > 1) {
      const newTimes = formData.times.filter((_, i) => i !== index);
      setFormData({
        ...formData,
        times: newTimes
      });
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.name.trim()) newErrors.name = 'İlaç adı zorunludur';
    if (!formData.dosage.trim()) newErrors.dosage = 'Dozaj bilgisi zorunludur';
    
    const validTimes = formData.times.filter(time => time.trim() !== '');
    if (validTimes.length === 0) newErrors.times = 'En az bir alım zamanı belirtin';
    
    if (formData.stock < 0) newErrors.stock = 'Stok miktarı negatif olamaz';
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    const updatedMedications = user.medications ? [...user.medications] : [];
    const medicationData = {
      ...formData,
      times: formData.times.filter(time => time.trim() !== ''),
      id: editingIndex !== null ? updatedMedications[editingIndex].id : Date.now()
    };
    
    if (editingIndex !== null) {
      updatedMedications[editingIndex] = medicationData;
    } else {
      updatedMedications.push(medicationData);
    }
    
    const updatedUser = {
      ...user,
      medications: updatedMedications
    };
    
    setUser(updatedUser);
    localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
    
    resetForm();
    alert(editingIndex !== null ? 'İlaç başarıyla güncellendi' : 'İlaç başarıyla eklendi');
  };

  const handleEdit = (index) => {
    const medication = user.medications[index];
    setFormData({
      name: medication.name,
      dosage: medication.dosage,
      times: [...medication.times, ''], // Düzenleme için boş alan ekliyoruz
      stock: medication.stock
    });
    setEditingIndex(index);
    setIsAdding(true);
  };

  const handleDelete = (index) => {
    if (window.confirm('Bu ilacı silmek istediğinizden emin misiniz?')) {
      const updatedMedications = user.medications.filter((_, i) => i !== index);
      
      const updatedUser = {
        ...user,
        medications: updatedMedications
      };
      
      setUser(updatedUser);
      localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
      alert('İlaç başarıyla silindi');
    }
  };

  const handleTakeMedication = (index) => {
    const updatedMedications = [...user.medications];
    if (updatedMedications[index].stock > 0) {
      updatedMedications[index].stock -= 1;
      
      const updatedUser = {
        ...user,
        medications: updatedMedications
      };
      
      setUser(updatedUser);
      localStorage.setItem('mediAsistanUser', JSON.stringify(updatedUser));
      
      // Stok kontrolü
      if (updatedMedications[index].stock <= 3) {
        alert(`Dikkat! ${updatedMedications[index].name} ilacınız bitmek üzere. Lütfen eczanenize uğrayın.`);
      }
    }
  };

  const resetForm = () => {
    setFormData({
      name: '',
      dosage: '',
      times: [''],
      stock: 0
    });
    setErrors({});
    setIsAdding(false);
    setEditingIndex(null);
  };

  const handleCancel = () => {
    resetForm();
  };

  return (
    <div className="medication-container">
      <div className="medication-header">
        <h2>İlaç Takip Sistemi</h2>
        <p>İlaçlarınızı düzenli almayı unutmayın, sistem sizi hatırlatacak!</p>
      </div>

      {!isAdding ? (
        <div className="medication-list">
          {user.medications && user.medications.length > 0 ? (
            <div className="medications-grid">
              {user.medications.map((medication, index) => (
                <div key={index} className="medication-card">
                  <div className="medication-info">
                    <h3>{medication.name}</h3>
                    <p><strong>Dozaj:</strong> {medication.dosage}</p>
                    <p><strong>Zamanlar:</strong> {medication.times.join(', ')}</p>
                    <p><strong>Stok:</strong> {medication.stock} adet</p>
                    {medication.stock <= 3 && (
                      <div className="low-stock-warning">
                        ⚠️ İlacınız bitmek üzere!
                      </div>
                    )}
                  </div>
                  <div className="medication-actions">
                    <button 
                      className="take-btn"
                      onClick={() => handleTakeMedication(index)}
                      disabled={medication.stock <= 0}
                    >
                      İlacı Aldım
                    </button>
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
            <div className="no-medications">
              <p>Henüz ilaç eklememişsiniz.</p>
            </div>
          )}
          
          <button 
            className="add-medication-btn"
            onClick={() => setIsAdding(true)}
          >
            + Yeni İlaç Ekle
          </button>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="medication-form">
          <h3>{editingIndex !== null ? 'İlacı Düzenle' : 'Yeni İlaç Ekle'}</h3>
          
          <div className="form-group">
            <label htmlFor="name">İlaç Adı *</label>
            <input
              type="text"
              id="name"
              name="name"
              value={formData.name}
              onChange={handleChange}
              className={errors.name ? 'error' : ''}
              placeholder="Parol, Ventolin, vs."
            />
            {errors.name && <span className="error-text">{errors.name}</span>}
          </div>
          
          <div className="form-group">
            <label htmlFor="dosage">Dozaj *</label>
            <input
              type="text"
              id="dosage"
              name="dosage"
              value={formData.dosage}
              onChange={handleChange}
              className={errors.dosage ? 'error' : ''}
              placeholder="1 tablet, 10 mg, vs."
            />
            {errors.dosage && <span className="error-text">{errors.dosage}</span>}
          </div>
          
          <div className="form-group">
            <label>Alım Zamanları *</label>
            {formData.times.map((time, index) => (
              <div key={index} className="time-input-group">
                <input
                  type="time"
                  value={time}
                  onChange={(e) => handleTimeChange(index, e.target.value)}
                  className={errors.times ? 'error' : ''}
                />
                {formData.times.length > 1 && (
                  <button 
                    type="button" 
                    className="remove-time-btn"
                    onClick={() => removeTimeField(index)}
                  >
                    ✕
                  </button>
                )}
              </div>
            ))}
            {errors.times && <span className="error-text">{errors.times}</span>}
            <button 
              type="button" 
              className="add-time-btn"
              onClick={addTimeField}
            >
              + Zaman Ekle
            </button>
          </div>
          
          <div className="form-group">
            <label htmlFor="stock">Stok Adedi</label>
            <input
              type="number"
              id="stock"
              name="stock"
              value={formData.stock}
              onChange={handleChange}
              min="0"
              className={errors.stock ? 'error' : ''}
            />
            {errors.stock && <span className="error-text">{errors.stock}</span>}
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

export default MedicationTracker;