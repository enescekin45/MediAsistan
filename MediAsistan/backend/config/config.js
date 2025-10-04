/**
 * MediAsistan - Sunucu Yapılandırma Dosyası
 * Express.js sunucu ayarları ve middleware konfigürasyonu
 */

const path = require('path');
require('dotenv').config();

const config = {
  // Sunucu Ayarları
  server: {
    port: process.env.PORT || 3000,
    host: process.env.HOST || 'localhost',
    environment: process.env.NODE_ENV || 'development',
    sessionSecret: process.env.SESSION_SECRET || 'mediasistan_secret_key_2024',
    apiVersion: process.env.API_VERSION || 'v1'
  },

  // Veritabanı Ayarları
  database: {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'medi_asistan_db',
    dialect: 'mysql',
    port: process.env.DB_PORT || 3306,
    pool: {
      max: 10,
      min: 0,
      acquire: 30000,
      idle: 10000
    },
    logging: process.env.NODE_ENV === 'development' ? console.log : false,
    timezone: '+03:00' // Türkiye saati
  },

  // JWT Ayarları
  jwt: {
    secret: process.env.JWT_SECRET || 'mediasistan_jwt_secret_2024',
    refreshSecret: process.env.JWT_REFRESH_SECRET || 'mediasistan_refresh_secret_2024',
    expiresIn: process.env.JWT_EXPIRES_IN || '24h',
    refreshExpiresIn: process.env.JWT_REFRESH_EXPIRES_IN || '7d'
  },

  // API Güvenlik Ayarları
  security: {
    cors: {
      origin: process.env.CORS_ORIGIN || 'http://localhost:3000',
      methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
      allowedHeaders: ['Content-Type', 'Authorization', 'X-Requested-With'],
      credentials: true
    },
    rateLimit: {
      windowMs: 15 * 60 * 1000, // 15 dakika
      max: process.env.RATE_LIMIT_MAX || 100, // IP başına maksimum istek
      message: {
        error: 'Çok fazla istek gönderdiniz. Lütfen 15 dakika sonra tekrar deneyin.'
      }
    },
    helmet: {
      contentSecurityPolicy: {
        directives: {
          defaultSrc: ["'self'"],
          styleSrc: ["'self'", "'unsafe-inline'", "https://cdnjs.cloudflare.com"],
          scriptSrc: ["'self'", "'unsafe-inline'"],
          imgSrc: ["'self'", "data:", "https:"]
        }
      }
    }
  },

  // E-posta Ayarları (Bildirimler için)
  email: {
    service: process.env.EMAIL_SERVICE || 'gmail',
    host: process.env.EMAIL_HOST || 'smtp.gmail.com',
    port: process.env.EMAIL_PORT || 587,
    secure: process.env.EMAIL_SECURE || false,
    auth: {
      user: process.env.EMAIL_USER || '',
      pass: process.env.EMAIL_PASSWORD || ''
    },
    from: process.env.EMAIL_FROM || 'MediAsistan <noreply@mediasistan.com>'
  },

  // SMS Ayarları (Acil Bildirimler için)
  sms: {
    provider: process.env.SMS_PROVIDER || 'twilio', // twilio, netgsm, etc.
    accountSid: process.env.SMS_ACCOUNT_SID || '',
    authToken: process.env.SMS_AUTH_TOKEN || '',
    fromNumber: process.env.SMS_FROM_NUMBER || '',
    enabled: process.env.SMS_ENABLED === 'true'
  },

  // Dosya Yükleme Ayarları
  upload: {
    maxFileSize: 5 * 1024 * 1024, // 5MB
    allowedMimeTypes: [
      'image/jpeg',
      'image/png',
      'image/gif',
      'application/pdf'
    ],
    uploadPath: process.env.UPLOAD_PATH || path.join(__dirname, '../../uploads')
  },

  // Firebase Cloud Messaging (Push Bildirimler)
  fcm: {
    serviceAccount: require(process.env.FCM_SERVICE_ACCOUNT_PATH || './firebase-service-account.json'),
    databaseURL: process.env.FCM_DATABASE_URL || ''
  },

  // API Yolları
  routes: {
    apiPrefix: '/api',
    auth: {
      login: '/auth/login',
      register: '/auth/register',
      refresh: '/auth/refresh',
      logout: '/auth/logout',
      verify: '/auth/verify'
    },
    medications: {
      base: '/medications',
      reminders: '/medications/reminders',
      intake: '/medications/intake'
    },
    firstAid: {
      base: '/first-aid',
      categories: '/first-aid/categories',
      instructions: '/first-aid/instructions'
    },
    emergency: {
      base: '/emergency',
      contacts: '/emergency/contacts',
      panic: '/emergency/panic'
    },
    users: {
      base: '/users',
      profile: '/users/profile',
      health: '/users/health'
    }
  },

  // Uygulama Ayarları
  app: {
    name: 'MediAsistan',
    version: '1.0.0',
    description: 'Kişisel Sağlık Destek Platformu',
    contact: {
      name: 'MediAsistan Destek',
      email: 'support@mediasistan.com'
    }
  },

  // Loglama Ayarları
  logging: {
    level: process.env.LOG_LEVEL || 'info',
    file: {
      enabled: process.env.LOG_FILE_ENABLED === 'true',
      path: process.env.LOG_FILE_PATH || path.join(__dirname, '../../logs'),
      filename: 'mediasistan-%DATE%.log',
      datePattern: 'YYYY-MM-DD',
      maxSize: '20m',
      maxFiles: '30d'
    },
    console: {
      enabled: process.env.LOG_CONSOLE_ENABLED !== 'false'
    }
  }
};

// Geliştirme ortamı için özel ayarlar
if (config.server.environment === 'development') {
  config.database.logging = console.log;
  config.security.rateLimit.max = 1000; // Daha yüksek limit
  config.logging.level = 'debug';
}

// Test ortamı için özel ayarlar
if (config.server.environment === 'test') {
  config.database.database = process.env.TEST_DB_NAME || 'medi_asistan_test';
  config.security.rateLimit.max = 5000;
}

// Üretim ortamı için özel ayarlar
if (config.server.environment === 'production') {
  config.security.helmet.contentSecurityPolicy.directives.upgradeInsecureRequests = [];
  config.logging.console.enabled = false;
}

// Config validasyonu
const validateConfig = () => {
  const requiredFields = [
    'database.host',
    'database.user',
    'database.database'
  ];

  requiredFields.forEach(field => {
    const value = field.split('.').reduce((obj, key) => obj[key], config);
    if (!value) {
      throw new Error(`Gerekli config değeri eksik: ${field}`);
    }
  });

  if (config.server.environment === 'production') {
    if (!process.env.SESSION_SECRET || process.env.SESSION_SECRET === 'mediasistan_secret_key_2024') {
      throw new Error('Production ortamı için güvenli SESSION_SECRET ayarlayın');
    }
    if (!process.env.JWT_SECRET || process.env.JWT_SECRET === 'mediasistan_jwt_secret_2024') {
      throw new Error('Production ortamı için güvenli JWT_SECRET ayarlayın');
    }
  }
};

// Config'i dışa aktar
module.exports = {
  config,
  validateConfig
};