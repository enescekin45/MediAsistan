<?php
// Main entry point for the MediAsistan application
session_start();
require_once 'config/config.php';
require_once 'includes/functions.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'register.php') {
    header("Location: login.php");
    exit;
}

// Include header
include_once 'includes/header.php';

// Main content
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 text-center">
            <h1>MediAsistan</h1>
            <p class="lead">Kişiselleştirilmiş Dijital Sağlık Platformu</p>
        </div>
    </div>

    <?php if(isset($_SESSION['user_id'])): ?>
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <i class="fas fa-pills fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">İlaç Yönetimi</h5>
                    <p class="card-text">İlaçlarınızı ekleyin, takip edin ve hatırlatıcılar alın.</p>
                    <a href="medications.php" class="btn btn-primary">İlaçlarım</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <i class="fas fa-first-aid fa-3x mb-3 text-danger"></i>
                    <h5 class="card-title">İlk Yardım Rehberi</h5>
                    <p class="card-text">Acil durumlarda adım adım ilk yardım talimatları.</p>
                    <a href="first_aid.php" class="btn btn-danger">İlk Yardım</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <i class="fas fa-user-md fa-3x mb-3 text-success"></i>
                    <h5 class="card-title">Sağlık Profilim</h5>
                    <p class="card-text">Sağlık bilgilerinizi ve acil durum kişilerinizi yönetin.</p>
                    <a href="profile.php" class="btn btn-success">Profilim</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-2">
        <div class="col-12 text-center">
            <a href="tel:112" class="btn btn-lg btn-danger emergency-button">
                <i class="fas fa-phone-alt"></i> 112'yi Ara
            </a>
            <a href="panic_test.php" class="btn btn-lg btn-warning emergency-button ml-3">
                <i class="fas fa-exclamation-triangle"></i> Panik Butonu
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="row mt-4">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Hoş Geldiniz</h5>
                    <p class="card-text">MediAsistan'ı kullanmak için lütfen giriş yapın veya kayıt olun.</p>
                    <div class="d-flex justify-content-between">
                        <a href="login.php" class="btn btn-primary">Giriş Yap</a>
                        <a href="register.php" class="btn btn-success">Kayıt Ol</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>