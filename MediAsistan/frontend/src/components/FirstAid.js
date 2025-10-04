import React, { useState } from 'react';
import './FirstAid.css';

function FirstAid() {
  const [selectedEmergency, setSelectedEmergency] = useState(null);
  const [currentStep, setCurrentStep] = useState(0);

  // İlk yardım kılavuzu verileri
  const firstAidGuides = {
    heartAttack: {
      title: "Kalp Krizi",
      steps: [
        {
          title: "ADIM 1: Sakin olun ve hastayı rahat ettirin",
          description: "Hastayı sırtüstü yatırın, ayaklarını hafifçe yukarı kaldırın. Sıkı giysileri gevşetin.",
          image: "🫀"
        },
        {
          title: "ADIM 2: Acil servisi arayın",
          description: "Derhal 112'yi arayın veya yakınınızdaki birinden aratın. Durumu net bir şekilde açıklayın.",
          image: "📞"
        },
        {
          title: "ADIM 3: Hastanın solunumunu kontrol edin",
          description: "Hasta nefes alıyorsa, rahat nefes alması için başını hafifçe geriye yatırın. Nefes almıyorsa kalp masajına başlayın.",
          image: "👃"
        },
        {
          title: "ADIM 4: Aspirin verin (alerjisi yoksa)",
          description: "Hasta aspirin alerjisi yoksa ve yutabiliyorsa, bir aspirin tableti çiğneterek verin.",
          image: "💊"
        },
        {
          title: "ADIM 5: Tıbbi yardım gelene kadar yanında kalın",
          description: "Hastanın bilincini ve solunumunu kontrol etmeye devam edin. Tıbbi ekip gelene kadar yanından ayrılmayın.",
          image: "⏳"
        }
      ]
    },
    bleeding: {
      title: "Şiddetli Kanama",
      steps: [
        {
          title: "ADIM 1: Kanayan bölgeye direkt baskı uygulayın",
          description: "Temiz bir bez veya gazlı bezle kanayan bölgeye direkt ve sürekli baskı uygulayın.",
          image: "🩸"
        },
        {
          title: "ADIM 2: Yaralı bölgeyi yukarı kaldırın",
          description: "Mümkünse kanayan bölgeyi kalp seviyesinin üzerine kaldırın.",
          image: "⬆️"
        },
        {
          title: "ADIM 3: Acil servisi arayın",
          description: "Kanama kontrol altına alınamıyorsa derhal 112'yi arayın.",
          image: "📞"
        },
        {
          title: "ADIM 4: Turnike uygulayın (gerekirse)",
          description: "Şiddetli kanamalarda ve uzuv yaralanmalarında turnike uygulayın. Uygulama zamanını not edin.",
          image: "🎗️"
        }
      ]
    },
    fainting: {
      title: "Bayılma",
      steps: [
        {
          title: "ADIM 1: Kişiyi sırtüstü yatırın",
          description: "Bayılan kişiyi sırtüstü yatırın ve ayaklarını yaklaşık 30 cm yukarı kaldırın.",
          image: "🛌"
        },
        {
          title: "ADIM 2: Solunum yolunu açık tutun",
          description: "Başını geriye yatırarak solunum yolunu açık tutun. Sıkı giysileri gevşetin.",
          image: "👄"
        },
        {
          title: "ADIM 3: Bilinç kontrolü yapın",
          description: "Hafifçe sallayarak veya seslenerek bilincinin yerine gelip gelmediğini kontrol edin.",
          image: "👂"
        },
        {
          title: "ADIM 4: 5 dakikadan uzun sürerse acil servisi arayın",
          description: "Bayılma 1-2 dakikadan uzun sürerse veya kişi kendine geldiğinde kafa karışıklığı yaşıyorsa 112'yi arayın.",
          image: "⏰"
        }
      ]
    },
    burn: {
      title: "Yanık",
      steps: [
        {
          title: "ADIM 1: Yanığı soğuk suyla yıkayın",
          description: "Yanık bölgeyi 10-15 dakika soğuk (buzlu değil) su altında tutun.",
          image: "💧"
        },
        {
          title: "ADIM 2: Yanık bölgeyi temizleyin",
          description: "Yanık bölgeyi sabunlu suyla yavaşça temizleyin. Kabarcıkları patlatmayın.",
          image: "🧼"
        },
        {
          title: "ADIM 3: Steril bir pansuman yapın",
          description: "Yanık bölgeyi steril bir gazlı bezle kapatın. Sargıyı çok sıkı yapmayın.",
          image: "🩹"
        },
        {
          title: "ADIM 4: Ağrı kesici alın (gerekirse)",
          description: "Ağrı varsa ibuprofen veya parasetamol gibi ağrı kesiciler alınabilir.",
          image: "💊"
        },
        {
          title: "ADIM 5: Ciddi yanıklarda acil servise başvurun",
          description: "Yanık geniş bir alanı kaplıyorsa, yüzde, elde veya ayaktaysa derhal tıbbi yardım alın.",
          image: "🏥"
        }
      ]
    },
    choking: {
      title: "Boğulma",
      steps: [
        {
          title: "ADIM 1: Öksürmeye teşvik edin",
          description: "Kişi öksürebiliyorsa, öksürmeye devam etmesini söyleyin. Bu nesneyi çıkarabilir.",
          image: "😮"
        },
        {
          title: "ADIM 2: Heimlich manevrası uygulayın",
          description: "Kişi nefes alamıyorsa, arkasına geçin, bir yumruğunuzu göbeğin üstüne koyun, diğer elinizle kavrayın ve içe yukarı doğru baskı uygulayın.",
          image: "👐"
        },
        {
          title: "ADIM 3: Acil servisi arayın",
          description: "Nesne çıkmıyorsa veya kişi bilincini kaybediyorsa derhal 112'yi arayın.",
          image: "📞"
        },
        {
          title: "ADIM 4: Temel yaşam desteğine başlayın",
          description: "Kişi bilincini kaybederse, temel yaşam desteğine (CPR) başlayın.",
          image: "💓"
        }
      ]
    }
  };

  const handleEmergencySelect = (emergencyType) => {
    setSelectedEmergency(emergencyType);
    setCurrentStep(0);
  };

  const handleNextStep = () => {
    if (currentStep < firstAidGuides[selectedEmergency].steps.length - 1) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handlePrevStep = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  const handleReset = () => {
    setSelectedEmergency(null);
    setCurrentStep(0);
  };

  return (
    <div className="firstaid-container">
      <div className="firstaid-header">
        <h2>İlk Yardım Rehberi</h2>
        <div className="warning-banner">
          ⚠️ BU BİLGİLER PROFESYONEL TIBBİ YARDIMIN YERİNE GEÇMEZ. ACİL DURUMLARDA LÜTFEN DERHAL 112'Yİ ARAYINIZ.
        </div>
      </div>

      {!selectedEmergency ? (
        <div className="emergency-list">
          <h3>Acil Durum Seçin:</h3>
          <div className="emergency-buttons">
            <button 
              className="emergency-btn"
              onClick={() => handleEmergencySelect('heartAttack')}
            >
              <span className="icon">🫀</span>
              <span>Kalp Krizi</span>
            </button>
            <button 
              className="emergency-btn"
              onClick={() => handleEmergencySelect('bleeding')}
            >
              <span className="icon">🩸</span>
              <span>Şiddetli Kanama</span>
            </button>
            <button 
              className="emergency-btn"
              onClick={() => handleEmergencySelect('fainting')}
            >
              <span className="icon">😵</span>
              <span>Bayılma</span>
            </button>
            <button 
              className="emergency-btn"
              onClick={() => handleEmergencySelect('burn')}
            >
              <span className="icon">🔥</span>
              <span>Yanık</span>
            </button>
            <button 
              className="emergency-btn"
              onClick={() => handleEmergencySelect('choking')}
            >
              <span className="icon">😮</span>
              <span>Boğulma</span>
            </button>
          </div>
        </div>
      ) : (
        <div className="firstaid-guide">
          <div className="guide-header">
            <h3>{firstAidGuides[selectedEmergency].title}</h3>
            <button className="back-btn" onClick={handleReset}>
              ← Diğer Durumlar
            </button>
          </div>
          
          <div className="step-indicator">
            Adım {currentStep + 1} / {firstAidGuides[selectedEmergency].steps.length}
          </div>
          
          <div className="step-content">
            <div className="step-image">
              {firstAidGuides[selectedEmergency].steps[currentStep].image}
            </div>
            <div className="step-info">
              <h4>{firstAidGuides[selectedEmergency].steps[currentStep].title}</h4>
              <p>{firstAidGuides[selectedEmergency].steps[currentStep].description}</p>
            </div>
          </div>
          
          <div className="step-navigation">
            <button 
              onClick={handlePrevStep}
              disabled={currentStep === 0}
              className="nav-btn prev-btn"
            >
              ← Önceki
            </button>
            
            <button 
              onClick={() => window.location.href = 'tel:112'}
              className="emergency-call-btn"
            >
              📞 112'yi Ara
            </button>
            
            <button 
              onClick={handleNextStep}
              disabled={currentStep === firstAidGuides[selectedEmergency].steps.length - 1}
              className="nav-btn next-btn"
            >
              Sonraki →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

export default FirstAid;