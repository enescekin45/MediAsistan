<?php
session_start();
require_once 'config/config.php';

// Hata ayıklama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kullanıcı zaten giriş yapmışsa yönlendir
if(isset($_SESSION['user_id'])) {
    if(isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin') {
        header('Location: admin/admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// Giriş formu işleme
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $eposta = trim($_POST['eposta']);
    $sifre = $_POST['sifre'];
    $login_type = $_POST['login_type'] ?? 'user';
    
    if(empty($eposta) || empty($sifre)) {
        $hata = "Lütfen e-posta ve şifre giriniz.";
    } else {
        try {
            // Basit giriş kontrolü - test hesapları için
            if($login_type == 'admin' && $eposta == 'admin@mediasistan.com' && $sifre == '123') {
                // Admin girişi başarılı
                $_SESSION['user_id'] = 1;
                $_SESSION['user_email'] = 'admin@mediasistan.com';
                $_SESSION['user_name'] = 'Admin';
                $_SESSION['user_surname'] = 'User';
                $_SESSION['user_role'] = 'admin';
                $_SESSION['full_name'] = 'Admin User';
                $_SESSION['user_permissions'] = ['users', 'medications', 'reports', 'settings'];
                
                $_SESSION['success_message'] = "Hoş geldiniz, Admin User!";
                header('Location: admin/admin_dashboard.php');
                exit();
                
            } else if($login_type == 'user' && $eposta == 'ornek@email.com' && $sifre == 'sifre123') {
                // Kullanıcı girişi başarılı
                $_SESSION['user_id'] = 1;
                $_SESSION['user_email'] = 'ornek@email.com';
                $_SESSION['user_name'] = 'Örnek';
                $_SESSION['user_surname'] = 'Kullanıcı';
                $_SESSION['user_role'] = 'user';
                $_SESSION['full_name'] = 'Örnek Kullanıcı';
                
                $_SESSION['success_message'] = "Hoş geldiniz, Örnek Kullanıcı!";
                header('Location: index.php');
                exit();
                
            } else {
                // Veritabanı kontrolü
                if($login_type == 'admin') {
                    $check_sql = "SHOW TABLES LIKE 'admin'";
                    $check_result = $conn->query($check_sql);
                    
                    if($check_result && $check_result->num_rows > 0) {
                        $sql = "SELECT admin_id, eposta, sifre_hash, ad, soyad, yetkiler, durum FROM admin WHERE eposta = ?";
                        $role = 'admin';
                    } else {
                        $hata = "Admin girişi şu an kullanılamıyor. Test hesabını kullanın: admin@mediasistan.com / 123";
                    }
                } else {
                    $sql = "SELECT kullanici_id, eposta, sifre_hash, ad, soyad, durum FROM kullanicilar WHERE eposta = ?";
                    $role = 'user';
                }
                
                if(!isset($hata)) {
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("s", $eposta);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if($result->num_rows == 1) {
                            $user = $result->fetch_assoc();
                            
                            if($user['durum'] != 'aktif') {
                                $hata = "Hesabınız pasif durumda. Lütfen yönetici ile iletişime geçin.";
                            } else {
                                // Şifre doğrulama
                                $sifre_dogru = false;
                                
                                if(password_verify($sifre, $user['sifre_hash'])) {
                                    $sifre_dogru = true;
                                } else if ($sifre === $user['sifre_hash']) {
                                    $sifre_dogru = true;
                                }
                                
                                if($sifre_dogru) {
                                    // Session başlat
                                    if($role == 'admin') {
                                        $_SESSION['user_id'] = $user['admin_id'];
                                        $_SESSION['user_email'] = $user['eposta'];
                                        $_SESSION['user_name'] = $user['ad'];
                                        $_SESSION['user_surname'] = $user['soyad'];
                                        $_SESSION['user_role'] = $role;
                                        $_SESSION['full_name'] = $user['ad'] . ' ' . $user['soyad'];
                                        
                                        if(isset($user['yetkiler'])) {
                                            $_SESSION['user_permissions'] = json_decode($user['yetkiler'], true);
                                        }
                                        
                                        $_SESSION['success_message'] = "Hoş geldiniz, " . $_SESSION['full_name'] . "!";
                                        header('Location: admin/admin_dashboard.php');
                                        exit();
                                    } else {
                                        $_SESSION['user_id'] = $user['kullanici_id'];
                                        $_SESSION['user_email'] = $user['eposta'];
                                        $_SESSION['user_name'] = $user['ad'];
                                        $_SESSION['user_surname'] = $user['soyad'];
                                        $_SESSION['user_role'] = $role;
                                        $_SESSION['full_name'] = $user['ad'] . ' ' . $user['soyad'];
                                        
                                        $_SESSION['success_message'] = "Hoş geldiniz, " . $_SESSION['full_name'] . "!";
                                        header('Location: index.php');
                                        exit();
                                    }
                                } else {
                                    $hata = "Geçersiz şifre.";
                                }
                            }
                        } else {
                            $hata = "Bu e-posta adresi ile kayıtlı " . ($login_type == 'admin' ? 'yönetici' : 'kullanıcı') . " bulunamadı.";
                        }
                        $stmt->close();
                    } else {
                        $hata = "Sistem hatası. Lütfen daha sonra tekrar deneyin.";
                    }
                }
            }
        } catch (Exception $e) {
            $hata = "Sistem hatası. Lütfen daha sonra tekrar deneyin.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - MediAsistan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #7c3aed;
            --gray-light: #f8fafc;
            --gray: #6b7280;
            --gray-dark: #374151;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 1rem;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1.8rem 1.5rem 1.2rem 1.5rem;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .login-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            background: var(--gray-light);
        }
        
        .login-tab {
            flex: 1;
            padding: 0.85rem 0.5rem;
            text-align: center;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            font-size: 0.85rem;
            position: relative;
        }
        
        .login-tab.active {
            color: var(--primary);
        }
        
        .login-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--primary);
        }
        
        .login-body {
            padding: 1.8rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }
        
        .input-icon {
            position: relative;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem;
            border: 1.5px solid #e5e7eb;
            font-size: 0.9rem;
            width: 100%;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .input-icon i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.85rem;
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-admin {
            background: linear-gradient(135deg, var(--secondary) 0%, #6d28d9 100%);
        }
        
        .btn-admin:hover {
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 0.9rem 1rem;
            margin-bottom: 1.2rem;
            font-size: 0.85rem;
        }
        
        .alert-danger {
            background: rgba(220, 38, 38, 0.08);
            color: #dc2626;
            border-left: 3px solid #dc2626;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.08);
            color: #16a34a;
            border-left: 3px solid #16a34a;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <span style="font-size: 2rem;">🏥</span>
            <h1>MediAsistan</h1>
            <p>Akıllı Sağlık Yönetim Sistemi</p>
        </div>
        
        <div class="login-tabs">
            <button type="button" class="login-tab active" data-tab="user">
                <i class="fas fa-user me-1"></i>Kullanıcı Girişi
            </button>
            <button type="button" class="login-tab" data-tab="admin">
                <i class="fas fa-shield-alt me-1"></i>Yönetici Girişi
            </button>
        </div>
        
        <div class="login-body">
            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($hata)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $hata; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="userForm" class="login-form">
                <input type="hidden" name="login_type" value="user">
                
                <div class="form-group">
                    <label class="form-label">E-posta Adresi</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" name="eposta" required 
                               placeholder="ornek@email.com" value="<?php echo isset($_POST['eposta']) && ($_POST['login_type'] ?? '') == 'user' ? htmlspecialchars($_POST['eposta']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" name="sifre" required
                               placeholder="Şifrenizi girin">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-1"></i>Giriş Yap
                </button>
            </form>
            
            <form method="POST" action="" id="adminForm" class="login-form" style="display: none;">
                <input type="hidden" name="login_type" value="admin">
                
                <div class="form-group">
                    <label class="form-label">Yönetici E-posta</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" name="eposta" 
                               placeholder="admin@mediasistan.com" required
                               value="<?php echo isset($_POST['eposta']) && ($_POST['login_type'] ?? '') == 'admin' ? htmlspecialchars($_POST['eposta']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Yönetici Şifre</label>
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                        <input type="password" class="form-control" name="sifre" 
                               placeholder="Yönetici şifrenizi girin" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login btn-admin">
                    <i class="fas fa-shield-alt me-1"></i>Yönetici Girişi
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.login-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.login-form').forEach(f => f.style.display = 'none');
                
                this.classList.add('active');
                const tabType = this.getAttribute('data-tab');
                document.getElementById(tabType + 'Form').style.display = 'block';
                
                document.querySelectorAll('input[name="login_type"]').forEach(input => {
                    input.value = tabType;
                });
            });
        });
        
        <?php if(isset($_POST['login_type'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const loginType = '<?php echo $_POST["login_type"]; ?>';
                if(loginType === 'admin') {
                    document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
                    document.querySelector('.login-tab[data-tab="admin"]').classList.add('active');
                    document.getElementById('userForm').style.display = 'none';
                    document.getElementById('adminForm').style.display = 'block';
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>