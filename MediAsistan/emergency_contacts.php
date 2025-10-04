<?php
// emergency_contacts.php - Acil Durum Kişileri Yönetimi

// Config dosyasını dahil et (içinde session_start() var)
require_once 'config/config.php';
require_once 'includes/functions.php';

// Kullanıcı giriş yapmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Sayfa başlığı
$page_title = "Acil Kişiler - MediAsistan";

// Acil kişi ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kisi_ekle'])) {
    $ad_soyad = trim($_POST['ad_soyad']);
    $telefon = trim($_POST['telefon']);
    $eposta = trim($_POST['eposta']);
    $iliski = $_POST['iliski'];
    
    if (!empty($ad_soyad) && !empty($telefon)) {
        // Telefon numarasını temizle (sadece rakamlar)
        $telefon = preg_replace('/[^0-9]/', '', $telefon);
        
        // Sıra numarasını belirle
        $sira_sorgu = $conn->prepare("SELECT COALESCE(MAX(sira_no), 0) + 1 as yeni_sira FROM acil_durum_kisileri WHERE kullanici_id = ?");
        $sira_sorgu->bind_param("i", $_SESSION['user_id']);
        $sira_sorgu->execute();
        $sira_sonuc = $sira_sorgu->get_result();
        $yeni_sira = $sira_sonuc->fetch_assoc()['yeni_sira'];
        $sira_sorgu->close();
        
        $sql = "INSERT INTO acil_durum_kisileri (kullanici_id, ad_soyad, telefon, eposta, iliski, sira_no) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("issssi", $_SESSION['user_id'], $ad_soyad, $telefon, $eposta, $iliski, $yeni_sira);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Acil kişi başarıyla eklendi!";
            } else {
                $_SESSION['error_message'] = "Kişi eklenirken hata oluştu: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "SQL hazırlama hatası: " . $conn->error;
        }
    } else {
        $_SESSION['warning_message'] = "Lütfen en azından ad ve telefon bilgilerini giriniz.";
    }
    
    header("Location: emergency_contacts.php");
    exit;
}

// Kişi silme işlemi
if (isset($_GET['sil'])) {
    $kisi_id = intval($_GET['sil']);
    
    $sql = "DELETE FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ii", $kisi_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Kişi başarıyla silindi!";
        } else {
            $_SESSION['error_message'] = "Kişi silinirken hata oluştu.";
        }
        $stmt->close();
    }
    
    header("Location: emergency_contacts.php");
    exit;
}

// Kişi düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kisi_duzenle'])) {
    $kisi_id = intval($_POST['kisi_id']);
    $ad_soyad = trim($_POST['ad_soyad']);
    $telefon = trim($_POST['telefon']);
    $eposta = trim($_POST['eposta']);
    $iliski = $_POST['iliski'];
    $aktif_mi = isset($_POST['aktif_mi']) ? 1 : 0;
    $sira_no = intval($_POST['sira_no']);
    
    if (!empty($ad_soyad) && !empty($telefon)) {
        $telefon = preg_replace('/[^0-9]/', '', $telefon);
        
        $sql = "UPDATE acil_durum_kisileri SET ad_soyad = ?, telefon = ?, eposta = ?, iliski = ?, aktif_mi = ?, sira_no = ? 
                WHERE kisi_id = ? AND kullanici_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ssssiiii", $ad_soyad, $telefon, $eposta, $iliski, $aktif_mi, $sira_no, $kisi_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Kişi bilgileri başarıyla güncellendi!";
            } else {
                $_SESSION['error_message'] = "Güncelleme sırasında hata oluştu.";
            }
            $stmt->close();
        }
    }
    
    header("Location: emergency_contacts.php");
    exit;
}

// Sıra numarası güncelleme
if (isset($_POST['sira_guncelle'])) {
    $siralama = $_POST['sira'];
    
    foreach ($siralama as $sira => $kisi_id) {
        $kisi_id = intval($kisi_id);
        $sira = intval($sira) + 1;
        
        $sql = "UPDATE acil_durum_kisileri SET sira_no = ? WHERE kisi_id = ? AND kullanici_id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("iii", $sira, $kisi_id, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $_SESSION['success_message'] = "Sıralama başarıyla güncellendi!";
    header("Location: emergency_contacts.php");
    exit;
}

// Kullanıcının acil kişilerini getir
$sql = "SELECT * FROM acil_durum_kisileri WHERE kullanici_id = ? ORDER BY sira_no ASC, ad_soyad ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$kisiler = $stmt->get_result();

// Düzenlenecek kişi bilgilerini getir
$duzenlenecek_kisi = null;
if (isset($_GET['duzenle'])) {
    $kisi_id = intval($_GET['duzenle']);
    $sql = "SELECT * FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $kisi_id, $_SESSION['user_id']);
    $stmt->execute();
    $duzenlenecek_kisi = $stmt->get_result()->fetch_assoc();
}

include_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-address-book text-primary me-2"></i>Acil Durum Kişileri</h2>
                    <p class="text-muted">Acil durumlarda aranacak kişileri ekleyin ve yönetin.</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-users me-1"></i> 
                        <?php echo $kisiler->num_rows; ?> Kişi
                    </span>
                </div>
            </div>
            
            <!-- Mesaj Gösterme -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning_message'])): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['warning_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['warning_message']); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Kişi Ekleme/Düzenleme Formu -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header <?php echo $duzenlenecek_kisi ? 'bg-warning' : 'bg-primary'; ?> text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-<?php echo $duzenlenecek_kisi ? 'edit' : 'plus-circle'; ?> me-2"></i>
                        <?php echo $duzenlenecek_kisi ? 'Kişi Düzenle' : 'Yeni Kişi Ekle'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="kisiForm">
                        <?php if ($duzenlenecek_kisi): ?>
                            <input type="hidden" name="kisi_id" value="<?php echo $duzenlenecek_kisi['kisi_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ad_soyad" 
                                   value="<?php echo $duzenlenecek_kisi ? htmlspecialchars($duzenlenecek_kisi['ad_soyad']) : ''; ?>" 
                                   required maxlength="100">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Telefon <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="telefon" 
                                   value="<?php echo $duzenlenecek_kisi ? htmlspecialchars($duzenlenecek_kisi['telefon']) : ''; ?>" 
                                   required pattern="[0-9+\s()-]{10,}" title="Geçerli bir telefon numarası giriniz">
                            <small class="form-text text-muted">Örnek: 05551234567 veya +905551234567</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="eposta" 
                                   value="<?php echo $duzenlenecek_kisi ? htmlspecialchars($duzenlenecek_kisi['eposta']) : ''; ?>"
                                   maxlength="255">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">İlişki</label>
                            <select class="form-control" name="iliski" required>
                                <option value="aile" <?php echo ($duzenlenecek_kisi && $duzenlenecek_kisi['iliski'] == 'aile') ? 'selected' : ''; ?>>Aile</option>
                                <option value="arkadas" <?php echo ($duzenlenecek_kisi && $duzenlenecek_kisi['iliski'] == 'arkadas') ? 'selected' : ''; ?>>Arkadaş</option>
                                <option value="doktor" <?php echo ($duzenlenecek_kisi && $duzenlenecek_kisi['iliski'] == 'doktor') ? 'selected' : ''; ?>>Doktor</option>
                                <option value="komsu" <?php echo ($duzenlenecek_kisi && $duzenlenecek_kisi['iliski'] == 'komsu') ? 'selected' : ''; ?>>Komşu</option>
                                <option value="diger" <?php echo ($duzenlenecek_kisi && $duzenlenecek_kisi['iliski'] == 'diger') ? 'selected' : ''; ?>>Diğer</option>
                            </select>
                        </div>
                        
                        <?php if ($duzenlenecek_kisi): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sıra No</label>
                                    <input type="number" class="form-control" name="sira_no" 
                                           value="<?php echo $duzenlenecek_kisi['sira_no']; ?>" min="1" max="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-check form-switch pt-4">
                                    <input type="checkbox" class="form-check-input" name="aktif_mi" id="aktif_mi" 
                                           <?php echo $duzenlenecek_kisi['aktif_mi'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="aktif_mi">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <?php if ($duzenlenecek_kisi): ?>
                                <button type="submit" name="kisi_duzenle" class="btn btn-warning">
                                    <i class="fas fa-save me-2"></i>Güncelle
                                </button>
                                <a href="emergency_contacts.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>İptal
                                </a>
                            <?php else: ?>
                                <button type="submit" name="kisi_ekle" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Kişi Ekle
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- İstatistikler -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>İstatistikler</h6>
                </div>
                <div class="card-body">
                    <?php
                    $aktif_kisiler = 0;
                    $pasif_kisiler = 0;
                    
                    if ($kisiler->num_rows > 0) {
                        $kisiler->data_seek(0); // Result pointer'ı resetle
                        while($kisi = $kisiler->fetch_assoc()) {
                            if ($kisi['aktif_mi']) {
                                $aktif_kisiler++;
                            } else {
                                $pasif_kisiler++;
                            }
                        }
                        $kisiler->data_seek(0); // Tekrar resetle
                    }
                    ?>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-success"><?php echo $aktif_kisiler; ?></h4>
                                <small class="text-muted">Aktif Kişi</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-secondary"><?php echo $pasif_kisiler; ?></h4>
                            <small class="text-muted">Pasif Kişi</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kişi Listesi -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Kayıtlı Kişiler</h5>
                    <span class="badge bg-light text-dark">Toplam: <?php echo $kisiler->num_rows; ?></span>
                </div>
                <div class="card-body">
                    <?php if ($kisiler->num_rows > 0): ?>
                        <!-- Sıralama Formu -->
                        <form method="POST" action="" id="siraForm" class="mb-3">
                            <div class="alert alert-info py-2">
                                <i class="fas fa-info-circle me-2"></i>
                                Kişileri sürükleyip bırakarak sıralayabilirsiniz. Sıra numarası acil durumda bildirim önceliğini belirler.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="kisilerTablosu">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="50">Sıra</th>
                                            <th>Ad Soyad</th>
                                            <th>Telefon</th>
                                            <th>E-posta</th>
                                            <th>İlişki</th>
                                            <th>Durum</th>
                                            <th width="120">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sortable">
                                        <?php while($kisi = $kisiler->fetch_assoc()): ?>
                                        <tr data-kisi-id="<?php echo $kisi['kisi_id']; ?>">
                                            <td>
                                                <span class="badge bg-primary"><?php echo $kisi['sira_no']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($kisi['ad_soyad']); ?></strong>
                                            </td>
                                            <td>
                                                <a href="tel:<?php echo $kisi['telefon']; ?>" class="text-decoration-none">
                                                    <i class="fas fa-phone me-1 text-success"></i>
                                                    <?php echo format_phone($kisi['telefon']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($kisi['eposta']): ?>
                                                    <a href="mailto:<?php echo $kisi['eposta']; ?>" class="text-decoration-none">
                                                        <i class="fas fa-envelope me-1 text-primary"></i>
                                                        <?php echo htmlspecialchars($kisi['eposta']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $iliski_renk = [
                                                    'aile' => 'success',
                                                    'arkadas' => 'info',
                                                    'doktor' => 'warning',
                                                    'komsu' => 'secondary',
                                                    'diger' => 'dark'
                                                ];
                                                $renk = $iliski_renk[$kisi['iliski']] ?? 'dark';
                                                ?>
                                                <span class="badge bg-<?php echo $renk; ?>">
                                                    <i class="fas fa-<?php echo $kisi['iliski'] == 'doktor' ? 'user-md' : 'user'; ?> me-1"></i>
                                                    <?php echo ucfirst($kisi['iliski']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($kisi['aktif_mi']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Aktif
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-times me-1"></i>Pasif
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="emergency_contacts.php?duzenle=<?php echo $kisi['kisi_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="emergency_contacts.php?sil=<?php echo $kisi['kisi_id']; ?>" 
                                                       class="btn btn-outline-danger" 
                                                       onclick="return confirm('<?php echo htmlspecialchars($kisi['ad_soyad']); ?> isimli kişiyi silmek istediğinizden emin misiniz?')"
                                                       title="Sil">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button type="submit" name="sira_guncelle" class="btn btn-primary">
                                    <i class="fas fa-sort me-2"></i>Sıralamayı Kaydet
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">Henüz hiç acil kişi eklememişsiniz</h5>
                            <p class="text-muted">Yukarıdaki formu kullanarak ilk kişinizi ekleyin.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery UI for sortable -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>

<style>
.sortable-helper {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.table tbody tr {
    cursor: move;
}
.table tbody tr:hover {
    background-color: #f8f9fa;
}
</style>

<script>
// Sürükle-bırak sıralama
$(document).ready(function() {
    $("#sortable").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            // Sıra numaralarını güncelle
            $('#sortable tr').each(function(index) {
                $(this).find('.badge').text(index + 1);
            });
        }
    });
    $("#sortable").disableSelection();
    
    // Telefon formatlama
    $('input[name="telefon"]').on('input', function() {
        this.value = this.value.replace(/[^0-9+]/g, '');
    });
    
    // Form doğrulama
    $('#kisiForm').on('submit', function() {
        const telefon = $('input[name="telefon"]').val();
        if (telefon.length < 10) {
            alert('Lütfen geçerli bir telefon numarası giriniz (en az 10 karakter)');
            return false;
        }
        return true;
    });
});

// Sıra formu gönderimi
$('#siraForm').on('submit', function() {
    // Gizli input'larla sıralama verisini gönder
    $('#sortable tr').each(function(index) {
        const kisiId = $(this).data('kisi-id');
        $(this).append('<input type="hidden" name="sira[' + index + ']" value="' + kisiId + '">');
    });
});
</script>

<?php 
// Veritabanı bağlantısını kapat
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();

include_once 'includes/footer.php'; 
?>