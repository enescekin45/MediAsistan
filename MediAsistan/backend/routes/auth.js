/**
 * MediAsistan - Kimlik Doğrulama Route'ları
 * Kullanıcı girişi, kayıt, token yönetimi işlemleri
 */

const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const rateLimit = require('express-rate-limit');
const { body, validationResult } = require('express-validator');
const config = require('../config/config');
const db = require('../models');
const logger = require('../utils/logger');

const router = express.Router();

// Rate limiting for auth endpoints
const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 dakika
  max: 5, // Her IP için 5 giriş denemesi
  message: {
    success: false,
    error: 'Çok fazla giriş denemesi. Lütfen 15 dakika sonra tekrar deneyin.'
  },
  skipSuccessfulRequests: true
});

// Input validation rules
const loginValidation = [
  body('eposta')
    .isEmail()
    .normalizeEmail()
    .withMessage('Geçerli bir e-posta adresi giriniz.'),
  body('sifre')
    .isLength({ min: 6 })
    .withMessage('Şifre en az 6 karakter olmalıdır.')
];

const registerValidation = [
  body('eposta')
    .isEmail()
    .normalizeEmail()
    .withMessage('Geçerli bir e-posta adresi giriniz.'),
  body('sifre')
    .isLength({ min: 6 })
    .withMessage('Şifre en az 6 karakter olmalıdır.')
    .matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/)
    .withMessage('Şifre en az bir büyük harf, bir küçük harf ve bir rakam içermelidir.'),
  body('ad')
    .isLength({ min: 2 })
    .trim()
    .escape()
    .withMessage('Ad en az 2 karakter olmalıdır.'),
  body('soyad')
    .isLength({ min: 2 })
    .trim()
    .escape()
    .withMessage('Soyad en az 2 karakter olmalıdır.')
];

const refreshTokenValidation = [
  body('refreshToken')
    .notEmpty()
    .withMessage('Refresh token gereklidir.')
];

// Utility functions
const generateTokens = (user) => {
  const payload = {
    kullanici_id: user.kullanici_id,
    eposta: user.eposta,
    ad: user.ad,
    soyad: user.soyad,
    iat: Math.floor(Date.now() / 1000)
  };

  // Farklı secret kullanarak güvenliği artır
  const accessToken = jwt.sign(payload, config.jwt.secret, {
    expiresIn: config.jwt.expiresIn || '1h',
    algorithm: 'HS256'
  });

  const refreshToken = jwt.sign(payload, config.jwt.refreshSecret || config.jwt.secret, {
    expiresIn: config.jwt.refreshExpiresIn || '7d',
    algorithm: 'HS256'
  });

  return { accessToken, refreshToken };
};

const verifyToken = (token, isRefreshToken = false) => {
  try {
    const secret = isRefreshToken && config.jwt.refreshSecret ? 
      config.jwt.refreshSecret : config.jwt.secret;
    
    return jwt.verify(token, secret, { algorithms: ['HS256'] });
  } catch (error) {
    logger.error('Token doğrulama hatası:', error.message);
    throw new Error('Geçersiz token');
  }
};

// Routes

/**
 * @route   POST /api/auth/login
 * @desc    Kullanıcı girişi
 * @access  Public
 */
router.post('/login', authLimiter, loginValidation, async (req, res) => {
  try {
    // Validation check
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({
        success: false,
        error: 'Geçersiz giriş bilgileri',
        details: errors.array()
      });
    }

    const { eposta, sifre } = req.body;

    // Kullanıcıyı veritabanında ara
    const user = await db.Kullanicilar.findOne({
      where: { eposta },
      attributes: ['kullanici_id', 'eposta', 'sifre_hash', 'ad', 'soyad', 'durum']
    });

    if (!user) {
      logger.warn(`Başarısız giriş denemesi - E-posta: ${eposta}`);
      return res.status(401).json({
        success: false,
        error: 'Geçersiz e-posta veya şifre.'
      });
    }

    // Kullanıcı durumunu kontrol et
    if (user.durum !== 'aktif') {
      return res.status(401).json({
        success: false,
        error: 'Hesabınız pasif durumda. Lütfen yöneticiyle iletişime geçin.'
      });
    }

    // Şifreyi doğrula
    const isValidPassword = await bcrypt.compare(sifre, user.sifre_hash);
    if (!isValidPassword) {
      logger.warn(`Başarısız giriş denemesi - E-posta: ${eposta}`);
      return res.status(401).json({
        success: false,
        error: 'Geçersiz e-posta veya şifre.'
      });
    }

    // JWT token'ları oluştur
    const tokens = generateTokens(user);

    // Kullanıcı bilgilerini token ile birlikte döndür (şifre hash'i hariç)
    const { sifre_hash, ...userWithoutPassword } = user.get({ plain: true });

    // Refresh token'ı veritabanına kaydet
    await db.RefreshTokens.create({
      kullanici_id: user.kullanici_id,
      token: tokens.refreshToken,
      expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000) // 7 gün
    });

    logger.info(`Başarılı giriş - Kullanıcı ID: ${user.kullanici_id}`);

    res.json({
      success: true,
      message: 'Giriş başarılı.',
      data: {
        tokens,
        user: userWithoutPassword
      }
    });

  } catch (error) {
    logger.error('Giriş hatası:', error);
    res.status(500).json({
      success: false,
      error: 'Sunucu hatası. Lütfen daha sonra tekrar deneyin.'
    });
  }
});

/**
 * @route   POST /api/auth/register
 * @desc    Yeni kullanıcı kaydı
 * @access  Public
 */
router.post('/register', registerValidation, async (req, res) => {
  try {
    // Validation check
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({
        success: false,
        error: 'Geçersiz kayıt bilgileri',
        details: errors.array()
      });
    }

    const { ad, soyad, eposta, sifre, dogum_tarihi, kan_grubu, telefon } = req.body;

    // E-posta adresinin zaten kullanımda olup olmadığını kontrol et
    const existingUser = await db.Kullanicilar.findOne({ where: { eposta } });
    if (existingUser) {
      return res.status(400).json({
        success: false,
        error: 'Bu e-posta adresi zaten kullanımda.'
      });
    }

    // Şifreyi hashle
    const saltRounds = 12;
    const sifre_hash = await bcrypt.hash(sifre, saltRounds);

    // Transaction başlat
    const transaction = await db.sequelize.transaction();

    try {
      // Kullanıcıyı veritabanına kaydet
      const newUser = await db.Kullanicilar.create({
        ad,
        soyad,
        eposta,
        sifre_hash,
        dogum_tarihi: dogum_tarihi || null,
        kan_grubu: kan_grubu || null,
        telefon: telefon || null,
        durum: 'aktif'
      }, { transaction });

      // Varsayılan acil durum kategorilerini oluştur
      await createDefaultFirstAidCategories(transaction);
      
      // JWT token'ları oluştur
      const tokens = generateTokens(newUser);

      // Refresh token'ı kaydet
      await db.RefreshTokens.create({
        kullanici_id: newUser.kullanici_id,
        token: tokens.refreshToken,
        expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000)
      }, { transaction });

      // Transaction'ı commit et
      await transaction.commit();

      // Kullanıcı bilgilerini token ile birlikte döndür
      const { sifre_hash: _, ...userWithoutPassword } = newUser.get({ plain: true });

      logger.info(`Yeni kullanıcı kaydı - Kullanıcı ID: ${newUser.kullanici_id}`);

      res.status(201).json({
        success: true,
        message: 'Kullanıcı başarıyla kaydedildi.',
        data: {
          tokens,
          user: userWithoutPassword
        }
      });

    } catch (error) {
      // Transaction'ı rollback et
      await transaction.rollback();
      throw error;
    }

  } catch (error) {
    logger.error('Kayıt hatası:', error);
    res.status(500).json({
      success: false,
      error: 'Kayıt sırasında bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    });
  }
});

/**
 * @route   POST /api/auth/refresh
 * @desc    Access token'ı yenile
 * @access  Public
 */
router.post('/refresh', refreshTokenValidation, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({
        success: false,
        error: 'Geçersiz istek',
        details: errors.array()
      });
    }

    const { refreshToken } = req.body;

    // Refresh token'ı doğrula
    let decoded;
    try {
      decoded = verifyToken(refreshToken, true); // true parametresi ile refresh token olduğunu belirtiyoruz
    } catch (error) {
      return res.status(401).json({
        success: false,
        error: 'Geçersiz refresh token'
      });
    }

    // Refresh token'ın veritabanında olup olmadığını kontrol et
    const storedToken = await db.RefreshTokens.findOne({
      where: {
        token: refreshToken,
        kullanici_id: decoded.kullanici_id,
        expires_at: { [db.Sequelize.Op.gt]: new Date() }
      }
    });

    if (!storedToken) {
      return res.status(401).json({
        success: false,
        error: 'Geçersiz veya süresi dolmuş refresh token'
      });
    }

    // Kullanıcıyı bul
    const user = await db.Kullanicilar.findByPk(decoded.kullanici_id, {
      attributes: ['kullanici_id', 'eposta', 'ad', 'soyad', 'durum']
    });

    if (!user || user.durum !== 'aktif') {
      return res.status(401).json({
        success: false,
        error: 'Kullanıcı bulunamadı veya pasif durumda'
      });
    }

    // Yeni token'lar oluştur
    const tokens = generateTokens(user);

    // Eski refresh token'ı sil ve yenisini kaydet
    await db.RefreshTokens.destroy({
      where: { token: refreshToken }
    });

    await db.RefreshTokens.create({
      kullanici_id: user.kullanici_id,
      token: tokens.refreshToken,
      expires_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000)
    });

    res.json({
      success: true,
      message: 'Token başarıyla yenilendi.',
      data: { tokens }
    });

  } catch (error) {
    logger.error('Token yenileme hatası:', error);
    res.status(500).json({
      success: false,
      error: 'Token yenileme sırasında bir hata oluştu.'
    });
  }
});

/**
 * @route   POST /api/auth/logout
 * @desc    Kullanıcı çıkışı
 * @access  Private
 */
router.post('/logout', async (req, res) => {
  try {
    const authHeader = req.headers.authorization;
    const refreshToken = req.body.refreshToken;

    if (!authHeader) {
      return res.status(400).json({
        success: false,
        error: 'Authorization header gereklidir.'
      });
    }

    const token = authHeader.replace('Bearer ', '');

    // Token'ı doğrula ve kullanıcı ID'sini al
    let decoded;
    try {
      decoded = verifyToken(token);
    } catch (error) {
      // Token geçersiz olsa bile çıkış işlemini tamamla
    }

    // Refresh token'ı sil
    if (refreshToken) {
      await db.RefreshTokens.destroy({
        where: { token: refreshToken }
      });
    }

    // Tüm refresh token'ları sil (isteğe bağlı)
    if (decoded && decoded.kullanici_id) {
      await db.RefreshTokens.destroy({
        where: { kullanici_id: decoded.kullanici_id }
      });

      logger.info(`Kullanıcı çıkışı - Kullanıcı ID: ${decoded.kullanici_id}`);
    }

    res.json({
      success: true,
      message: 'Çıkış başarılı.'
    });

  } catch (error) {
    logger.error('Çıkış hatası:', error);
    res.status(500).json({
      success: false,
      error: 'Çıkış sırasında bir hata oluştu.'
    });
  }
});

/**
 * @route   GET /api/auth/verify
 * @desc    Token doğrulama
 * @access  Private
 */
router.get('/verify', async (req, res) => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader) {
      return res.status(401).json({
        success: false,
        error: 'Authorization header gereklidir.'
      });
    }

    const token = authHeader.replace('Bearer ', '');

    // Token'ı doğrula
    const decoded = verifyToken(token);

    // Kullanıcıyı bul
    const user = await db.Kullanicilar.findByPk(decoded.kullanici_id, {
      attributes: ['kullanici_id', 'eposta', 'ad', 'soyad', 'durum']
    });

    if (!user || user.durum !== 'aktif') {
      return res.status(401).json({
        success: false,
        error: 'Geçersiz token'
      });
    }

    res.json({
      success: true,
      data: { user }
    });

  } catch (error) {
    res.status(401).json({
      success: false,
      error: 'Geçersiz token'
    });
  }
});

/**
 * @route   POST /api/auth/forgot-password
 * @desc    Şifre sıfırlama isteği
 * @access  Public
 */
router.post('/forgot-password', [
  body('eposta').isEmail().normalizeEmail()
], async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({
        success: false,
        error: 'Geçerli bir e-posta adresi giriniz.'
      });
    }

    const { eposta } = req.body;

    // Kullanıcıyı bul
    const user = await db.Kullanicilar.findOne({ where: { eposta } });
    
    // Güvenlik nedeniyle, kullanıcı olmasa bile başarılı mesajı döndür
    if (!user) {
      return res.json({
        success: true,
        message: 'Şifre sıfırlama talimatları e-posta adresinize gönderildi.'
      });
    }

    // Şifre sıfırlama token'ı oluştur
    const resetToken = jwt.sign(
      { 
        kullanici_id: user.kullanici_id, 
        type: 'password_reset' 
      },
      config.jwt.secret,
      { expiresIn: '1h' }
    );

    // Şifre sıfırlama token'ını veritabanına kaydet
    await db.PasswordResets.create({
      kullanici_id: user.kullanici_id,
      token: resetToken,
      expires_at: new Date(Date.now() + 60 * 60 * 1000) // 1 saat
    });

    // E-posta gönderme işlemi burada yapılacak
    // Şimdilik token'ı logluyoruz
    logger.info(`Şifre sıfırlama tokenı - Kullanıcı ID: ${user.kullanici_id}, Token: ${resetToken}`);

    res.json({
      success: true,
      message: 'Şifre sıfırlama talimatları e-posta adresinize gönderildi.'
    });

  } catch (error) {
    logger.error('Şifre sıfırlama hatası:', error);
    res.status(500).json({
      success: false,
      error: 'Şifre sıfırlama isteği sırasında bir hata oluştu.'
    });
  }
});

// Helper function: Varsayılan ilk yardım kategorilerini oluştur
async function createDefaultFirstAidCategories(transaction) {
  const defaultCategories = [
    {
      kategori_adi: 'Kalp Krizi',
      kategori_ikon: 'heart',
      onem_derecesi: 5,
      sira_no: 1
    },
    {
      kategori_adi: 'Yanık',
      kategori_ikon: 'fire',
      onem_derecesi: 3,
      sira_no: 2
    },
    {
      kategori_adi: 'Boğulma',
      kategori_ikon: 'water',
      onem_derecesi: 4,
      sira_no: 3
    }
  ];

  try {
    // Kategorileri tek tek ekle (bulkCreate hata verirse)
    if (!db.IlkYardimKategorileri) {
      logger.warn('IlkYardimKategorileri modeli bulunamadı, varsayılan kategoriler oluşturulamadı');
      return;
    }
    
    await db.IlkYardimKategorileri.bulkCreate(defaultCategories, { 
      transaction,
      ignoreDuplicates: true // Aynı kategoriler varsa hata verme
    });
  } catch (error) {
    logger.error('Varsayılan ilk yardım kategorileri oluşturulurken hata:', error);
    // İşlemi durdurmamak için hatayı yutuyoruz
  }
}

module.exports = router;