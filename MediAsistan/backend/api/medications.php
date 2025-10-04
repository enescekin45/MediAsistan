<?php
/**
 * MediAsistan - İlaç Yönetimi API
 * İlaç ekleme, listeleme, güncelleme ve hatırlatıcılar için RESTful API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// CORS preflight request handling
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($request_method) {
        case 'GET':
            handleGetRequest($conn, $user_id);
            break;
            
        case 'POST':
            handlePostRequest($conn, $user_id);
            break;
            
        case 'PUT':
            handlePutRequest($conn, $user_id);
            break;
            
        case 'DELETE':
            handleDeleteRequest($conn, $user_id);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()]);
}

$conn->close();

/**
 * GET Request Handler - İlaç verilerini getir
 */
function handleGetRequest($conn, $user_id) {
    $ilac_id = isset($_GET['ilac_id']) ? intval($_GET['ilac_id']) : null;
    $with_reminders = isset($_GET['reminders']) ? boolval($_GET['reminders']) : false;
    $low_stock_only = isset($_GET['low_stock']) ? boolval($_GET['low_stock']) : false;
    $upcoming_reminders = isset($_GET['upcoming']) ? boolval($_GET['upcoming']) : false;
    
    if ($ilac_id) {
        // Belirli bir ilacı getir
        getMedication($conn, $user_id, $ilac_id, $with_reminders);
    } else if ($upcoming_reminders) {
        // Yaklaşan hatırlatıcıları getir
        getUpcomingReminders($conn, $user_id);
    } else if ($low_stock_only) {
        // Düşük stoktaki ilaçları getir
        getLowStockMedications($conn, $user_id);
    } else {
        // Tüm ilaçları getir
        getAllMedications($conn, $user_id, $with_reminders);
    }
}

/**
 * Tüm ilaçları getir
 */
function getAllMedications($conn, $user_id, $with_reminders = false) {
    $sql = "SELECT * FROM ilaclar WHERE kullanici_id = ? ORDER BY ilac_adi";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    
    if ($stmt) {
        $medications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Stok durumu hesapla ve ekle
        foreach ($medications as &$medication) {
            $medication['stok_durumu'] = calculateStockStatus(
                $medication['stok_adedi'], 
                $medication['kritik_stok_seviyesi']
            );
            
            // Hatırlatıcıları ekle
            if ($with_reminders) {
                $medication['hatirlaticilar'] = getMedicationReminders($conn, $medication['ilac_id']);
            }
        }
        
        // İstatistikler hesapla
        $stats = calculateMedicationStats($conn, $user_id);
        
        echo json_encode([
            'success' => true, 
            'data' => $medications,
            'stats' => $stats,
            'total' => count($medications)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch medications']);
    }
}

/**
 * Belirli bir ilacı getir
 */
function getMedication($conn, $user_id, $ilac_id, $with_reminders = false) {
    $sql = "SELECT * FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $stmt = execute_query($conn, $sql, [$ilac_id, $user_id], "ii");
    
    if (!$stmt || $stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medication not found']);
        return;
    }
    
    $medication = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Stok durumu hesapla
    $medication['stok_durumu'] = calculateStockStatus(
        $medication['stok_adedi'], 
        $medication['kritik_stok_seviyesi']
    );
    
    // Hatırlatıcıları ekle
    if ($with_reminders) {
        $medication['hatirlaticilar'] = getMedicationReminders($conn, $ilac_id);
    }
    
    // Kullanım geçmişi
    $medication['kullanim_gecmisi'] = getUsageHistory($conn, $ilac_id);
    
    echo json_encode([
        'success' => true, 
        'data' => $medication
    ]);
}

/**
 * Düşük stoktaki ilaçları getir
 */
function getLowStockMedications($conn, $user_id) {
    $sql = "SELECT * FROM ilaclar 
            WHERE kullanici_id = ? AND stok_adedi <= kritik_stok_seviyesi 
            ORDER BY stok_adedi ASC";
    
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    
    if ($stmt) {
        $medications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($medications as &$medication) {
            $medication['stok_durumu'] = calculateStockStatus(
                $medication['stok_adedi'], 
                $medication['kritik_stok_seviyesi']
            );
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $medications,
            'total_low_stock' => count($medications)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch low stock medications']);
    }
}

/**
 * Yaklaşan hatırlatıcıları getir
 */
function getUpcomingReminders($conn, $user_id) {
    $current_time = date('H:i:s');
    $current_day = strtolower(date('l')); // Monday, Tuesday, etc.
    
    // Türkçe gün isimlerini İngilizce'ye çevir
    $day_mapping = [
        'pazartesi' => 'monday',
        'salı' => 'tuesday',
        'çarşamba' => 'wednesday',
        'perşembe' => 'thursday',
        'cuma' => 'friday',
        'cumartesi' => 'saturday',
        'pazar' => 'sunday'
    ];
    
    $current_day_en = $day_mapping[$current_day] ?? $current_day;
    
    $sql = "SELECT i.ilac_id, i.ilac_adi, i.dozaj, hz.saat, hz.hatirlatma_id
            FROM ilaclar i
            INNER JOIN ilac_hatirlatma_zamanlari hz ON i.ilac_id = hz.ilac_id
            WHERE i.kullanici_id = ? 
            AND i.stok_adedi > 0
            AND hz.aktif_mi = TRUE
            AND hz.saat >= ?
            AND hz.$current_day_en = TRUE
            ORDER BY hz.saat
            LIMIT 10";
    
    $stmt = execute_query($conn, $sql, [$user_id, $current_time], "is");
    
    if ($stmt) {
        $reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => $reminders,
            'current_time' => $current_time,
            'total_upcoming' => count($reminders)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch upcoming reminders']);
    }
}

/**
 * POST Request Handler - Yeni ilaç ekle veya ilaç alımını kaydet
 */
function handlePostRequest($conn, $user_id) {
    $input_data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input_data['action'])) {
        // Varsayılan: yeni ilaç ekle
        addNewMedication($conn, $user_id, $input_data);
        return;
    }
    
    switch ($input_data['action']) {
        case 'add_medication':
            addNewMedication($conn, $user_id, $input_data);
            break;
            
        case 'log_intake':
            logMedicationIntake($conn, $user_id, $input_data);
            break;
            
        case 'add_reminder':
            addMedicationReminder($conn, $user_id, $input_data);
            break;
            
        case 'check_interactions':
            checkDrugInteractions($conn, $user_id, $input_data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
}

/**
 * Yeni ilaç ekle
 */
function addNewMedication($conn, $user_id, $data) {
    $required_fields = ['ilac_adi', 'dozaj'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $ilac_adi = trim($data['ilac_adi']);
    $dozaj = trim($data['dozaj']);
    $stok_adedi = isset($data['stok_adedi']) ? intval($data['stok_adedi']) : 0;
    $kritik_stok = isset($data['kritik_stok_seviyesi']) ? intval($data['kritik_stok_seviyesi']) : 3;
    $ilac_tipi = isset($data['ilac_tipi']) ? $data['ilac_tipi'] : 'tablet';
    $aciklama = isset($data['aciklama']) ? trim($data['aciklama']) : null;
    
    $sql = "INSERT INTO ilaclar 
            (kullanici_id, ilac_adi, dozaj, stok_adedi, kritik_stok_seviyesi, ilac_tipi, aciklama) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = execute_query($conn, $sql, 
        [$user_id, $ilac_adi, $dozaj, $stok_adedi, $kritik_stok, $ilac_tipi, $aciklama], 
        "issiiss");
    
    if ($stmt) {
        $ilac_id = $conn->insert_id;
        
        // İlaç ekleme kaydı
        logMedicationAction($conn, $user_id, $ilac_id, 'ilac_eklendi', 'Yeni ilaç eklendi');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Medication added successfully',
            'ilac_id' => $ilac_id
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to add medication']);
    }
}

/**
 * İlaç alımını kaydet
 */
function logMedicationIntake($conn, $user_id, $data) {
    $required_fields = ['ilac_id', 'alinan_dozaj'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $ilac_id = intval($data['ilac_id']);
    $alinan_dozaj = trim($data['alinan_dozaj']);
    $planlanan_saat = isset($data['planlanan_saat']) ? $data['planlanan_saat'] : date('H:i:s');
    $notlar = isset($data['notlar']) ? trim($data['notlar']) : null;
    
    // İlacın kullanıcıya ait olduğunu kontrol et
    $check_sql = "SELECT ilac_id FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$ilac_id, $user_id], "ii");
    
    if (!$check_stmt || $check_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medication not found']);
        return;
    }
    $check_stmt->close();
    
    // Alımı kaydet
    $sql = "INSERT INTO ilac_alim_gecmisi 
            (ilac_id, kullanici_id, alim_tarihi, alim_saati, planlanan_saat, alinan_dozaj, notlar) 
            VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, ?)";
    
    $stmt = execute_query($conn, $sql, 
        [$ilac_id, $user_id, $planlanan_saat, $alinan_dozaj, $notlar], 
        "iisss");
    
    if ($stmt) {
        // Stok güncelle
        updateStockAfterIntake($conn, $ilac_id);
        
        // Alım kaydı
        logMedicationAction($conn, $user_id, $ilac_id, 'ilac_alindi', 'İlaç alımı kaydedildi: ' . $alinan_dozaj);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Medication intake logged successfully'
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to log medication intake']);
    }
}

/**
 * PUT Request Handler - İlaç güncelle
 */
function handlePutRequest($conn, $user_id) {
    $input_data = json_decode(file_get_contents('php://input'), true);
    $ilac_id = isset($input_data['ilac_id']) ? intval($input_data['ilac_id']) : null;
    
    if (!$ilac_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing medication ID']);
        return;
    }
    
    // İlacın kullanıcıya ait olduğunu kontrol et
    $check_sql = "SELECT ilac_id FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$ilac_id, $user_id], "ii");
    
    if (!$check_stmt || $check_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medication not found']);
        return;
    }
    $check_stmt->close();
    
    updateMedication($conn, $user_id, $ilac_id, $input_data);
}

/**
 * İlaç bilgilerini güncelle
 */
function updateMedication($conn, $user_id, $ilac_id, $data) {
    $update_fields = [];
    $params = [];
    $types = "";
    
    $field_mappings = [
        'ilac_adi' => 's',
        'dozaj' => 's',
        'stok_adedi' => 'i',
        'kritik_stok_seviyesi' => 'i',
        'ilac_tipi' => 's',
        'aciklama' => 's'
    ];
    
    foreach ($field_mappings as $field => $type) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    $params[] = $ilac_id;
    $params[] = $user_id;
    $types .= "ii";
    
    $sql = "UPDATE ilaclar SET " . implode(', ', $update_fields) . 
           " WHERE ilac_id = ? AND kullanici_id = ?";
    
    $stmt = execute_query($conn, $sql, $params, $types);
    
    if ($stmt) {
        // Güncelleme kaydı
        logMedicationAction($conn, $user_id, $ilac_id, 'ilac_guncellendi', 'İlaç bilgileri güncellendi');
        
        echo json_encode(['success' => true, 'message' => 'Medication updated successfully']);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update medication']);
    }
}

/**
 * DELETE Request Handler - İlaç sil
 */
function handleDeleteRequest($conn, $user_id) {
    $ilac_id = isset($_GET['ilac_id']) ? intval($_GET['ilac_id']) : null;
    
    if (!$ilac_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing medication ID']);
        return;
    }
    
    // İlacın kullanıcıya ait olduğunu kontrol et
    $check_sql = "SELECT ilac_adi FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$ilac_id, $user_id], "ii");
    
    if (!$check_stmt || $check_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Medication not found']);
        return;
    }
    
    $ilac_adi = $check_stmt->get_result()->fetch_assoc()['ilac_adi'];
    $check_stmt->close();
    
    $sql = "DELETE FROM ilaclar WHERE ilac_id = ? AND kullanici_id = ?";
    $stmt = execute_query($conn, $sql, [$ilac_id, $user_id], "ii");
    
    if ($stmt) {
        // Silme kaydı
        logMedicationAction($conn, $user_id, $ilac_id, 'ilac_silindi', 'İlaç silindi: ' . $ilac_adi);
        
        echo json_encode(['success' => true, 'message' => 'Medication deleted successfully']);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete medication']);
    }
}

/**
 * Yardımcı Fonksiyonlar
 */

function calculateStockStatus($stok_adedi, $kritik_stok) {
    if ($stok_adedi == 0) {
        return 'stok_yok';
    } elseif ($stok_adedi <= $kritik_stok) {
        return 'dusuk_stok';
    } else {
        return 'yeterli_stok';
    }
}

function getMedicationReminders($conn, $ilac_id) {
    $sql = "SELECT * FROM ilac_hatirlatma_zamanlari WHERE ilac_id = ? AND aktif_mi = TRUE ORDER BY saat";
    $stmt = execute_query($conn, $sql, [$ilac_id], "i");
    
    if ($stmt) {
        $reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $reminders;
    }
    return [];
}

function getUsageHistory($conn, $ilac_id, $limit = 10) {
    $sql = "SELECT * FROM ilac_alim_gecmisi 
            WHERE ilac_id = ? 
            ORDER BY alim_tarihi DESC, alim_saati DESC 
            LIMIT ?";
    $stmt = execute_query($conn, $sql, [$ilac_id, $limit], "ii");
    
    if ($stmt) {
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $history;
    }
    return [];
}

function calculateMedicationStats($conn, $user_id) {
    $stats = [];
    
    // Toplam ilaç sayısı
    $sql = "SELECT COUNT(*) as total FROM ilaclar WHERE kullanici_id = ?";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    if ($stmt) {
        $stats['total_medications'] = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    }
    
    // Düşük stok sayısı
    $sql = "SELECT COUNT(*) as low_stock FROM ilaclar 
            WHERE kullanici_id = ? AND stok_adedi <= kritik_stok_seviyesi AND stok_adedi > 0";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    if ($stmt) {
        $stats['low_stock_count'] = $stmt->get_result()->fetch_assoc()['low_stock'];
        $stmt->close();
    }
    
    // Stokta olmayan ilaç sayısı
    $sql = "SELECT COUNT(*) as out_of_stock FROM ilaclar 
            WHERE kullanici_id = ? AND stok_adedi = 0";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    if ($stmt) {
        $stats['out_of_stock_count'] = $stmt->get_result()->fetch_assoc()['out_of_stock'];
        $stmt->close();
    }
    
    return $stats;
}

function updateStockAfterIntake($conn, $ilac_id) {
    $sql = "UPDATE ilaclar SET stok_adedi = stok_adedi - 1 WHERE ilac_id = ? AND stok_adedi > 0";
    $stmt = execute_query($conn, $sql, [$ilac_id], "i");
    if ($stmt) {
        $stmt->close();
    }
}

function logMedicationAction($conn, $user_id, $ilac_id, $action_type, $details) {
    $sql = "INSERT INTO ilac_islem_loglari (kullanici_id, ilac_id, islem_tipi, aciklama) 
            VALUES (?, ?, ?, ?)";
    $stmt = execute_query($conn, $sql, [$user_id, $ilac_id, $action_type, $details], "iiss");
    if ($stmt) {
        $stmt->close();
    }
}

// Gerekli tabloları oluştur (eğer yoksa)
createMedicationTables($conn);

function createMedicationTables($conn) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS ilac_islem_loglari (
            log_id INT PRIMARY KEY AUTO_INCREMENT,
            kullanici_id INT NOT NULL,
            ilac_id INT NOT NULL,
            islem_tipi ENUM('ilac_eklendi', 'ilac_guncellendi', 'ilac_silindi', 'ilac_alindi', 'stok_guncellendi'),
            aciklama TEXT,
            islem_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kullanici_tarih (kullanici_id, islem_tarihi),
            INDEX idx_ilac (ilac_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS ilac_hatirlatma_zamanlari (
            hatirlatma_id INT PRIMARY KEY AUTO_INCREMENT,
            ilac_id INT NOT NULL,
            saat TIME NOT NULL,
            pazartesi BOOLEAN DEFAULT TRUE,
            sali BOOLEAN DEFAULT TRUE,
            carsamba BOOLEAN DEFAULT TRUE,
            persembe BOOLEAN DEFAULT TRUE,
            cuma BOOLEAN DEFAULT TRUE,
            cumartesi BOOLEAN DEFAULT TRUE,
            pazar BOOLEAN DEFAULT TRUE,
            aktif_mi BOOLEAN DEFAULT TRUE,
            olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ilac_id) REFERENCES ilaclar(ilac_id) ON DELETE CASCADE,
            UNIQUE KEY unique_ilac_saat (ilac_id, saat)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
}
?>