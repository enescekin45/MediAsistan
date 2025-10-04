<?php
/**
 * MediAsistan - Acil Durum Kişileri API
 * RESTful API endpoint for emergency contacts management
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
 * GET Request Handler - Kişileri listele
 */
function handleGetRequest($conn, $user_id) {
    $contact_id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if ($contact_id) {
        // Tekil kişi getir
        $sql = "SELECT * FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
        $stmt = execute_query($conn, $sql, [$contact_id, $user_id], "ii");
        
        if ($stmt) {
            $contact = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($contact) {
                echo json_encode(['success' => true, 'data' => $contact]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Contact not found']);
            }
        }
    } else {
        // Tüm kişileri getir
        $sql = "SELECT * FROM acil_durum_kisileri WHERE kullanici_id = ? ORDER BY sira_no, ad_soyad";
        $stmt = execute_query($conn, $sql, [$user_id], "i");
        
        if ($stmt) {
            $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            echo json_encode(['success' => true, 'data' => $contacts]);
        }
    }
}

/**
 * POST Request Handler - Yeni kişi ekle veya özel aksiyonlar
 */
function handlePostRequest($conn, $user_id) {
    $input_data = json_decode(file_get_contents('php://input'), true);
    
    // Özel aksiyonlar için kontrol
    if (isset($input_data['action'])) {
        handleSpecialActions($conn, $user_id, $input_data);
        return;
    }
    
    // Yeni kişi ekleme
    $required_fields = ['ad_soyad', 'telefon'];
    foreach ($required_fields as $field) {
        if (empty($input_data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $ad_soyad = trim($input_data['ad_soyad']);
    $telefon = preg_replace('/[^0-9]/', '', $input_data['telefon']);
    $eposta = isset($input_data['eposta']) ? trim($input_data['eposta']) : null;
    $iliski = isset($input_data['iliski']) ? $input_data['iliski'] : 'diger';
    $aktif_mi = isset($input_data['aktif_mi']) ? (bool)$input_data['aktif_mi'] : true;
    
    // Telefon numarası kontrolü
    if (strlen($telefon) < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
        return;
    }
    
    $sql = "INSERT INTO acil_durum_kisileri (kullanici_id, ad_soyad, telefon, eposta, iliski, aktif_mi) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = execute_query($conn, $sql, [$user_id, $ad_soyad, $telefon, $eposta, $iliski, $aktif_mi], "issssi");
    
    if ($stmt) {
        $contact_id = $conn->insert_id;
        
        // Acil durum kaydı oluştur
        logEmergencyAction($conn, $user_id, 'contact_added', 'Yeni acil kişi eklendi: ' . $ad_soyad);
        
        http_response_code(201);
        echo json_encode([
            'success' => true, 
            'message' => 'Contact added successfully',
            'contact_id' => $contact_id
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to add contact']);
    }
}

/**
 * PUT Request Handler - Kişi güncelle
 */
function handlePutRequest($conn, $user_id) {
    $input_data = json_decode(file_get_contents('php://input'), true);
    $contact_id = isset($input_data['kisi_id']) ? intval($input_data['kisi_id']) : null;
    
    if (!$contact_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing contact ID']);
        return;
    }
    
    // Kişinin kullanıcıya ait olduğunu kontrol et
    $check_sql = "SELECT kisi_id FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$contact_id, $user_id], "ii");
    
    if (!$check_stmt || $check_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Contact not found']);
        return;
    }
    $check_stmt->close();
    
    // Güncelleme alanlarını hazırla
    $update_fields = [];
    $params = [];
    $types = "";
    
    $field_mappings = [
        'ad_soyad' => 's',
        'telefon' => 's',
        'eposta' => 's',
        'iliski' => 's',
        'aktif_mi' => 'i',
        'sira_no' => 'i'
    ];
    
    foreach ($field_mappings as $field => $type) {
        if (isset($input_data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $input_data[$field];
            $types .= $type;
        }
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    $params[] = $contact_id;
    $params[] = $user_id;
    $types .= "ii";
    
    $sql = "UPDATE acil_durum_kisileri SET " . implode(', ', $update_fields) . 
           " WHERE kisi_id = ? AND kullanici_id = ?";
    
    $stmt = execute_query($conn, $sql, $params, $types);
    
    if ($stmt) {
        // Acil durum kaydı oluştur
        logEmergencyAction($conn, $user_id, 'contact_updated', 'Acil kişi güncellendi: ID ' . $contact_id);
        
        echo json_encode(['success' => true, 'message' => 'Contact updated successfully']);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update contact']);
    }
}

/**
 * DELETE Request Handler - Kişi sil
 */
function handleDeleteRequest($conn, $user_id) {
    $contact_id = isset($_GET['id']) ? intval($_GET['id']) : null;
    
    if (!$contact_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing contact ID']);
        return;
    }
    
    // Kişinin kullanıcıya ait olduğunu kontrol et
    $check_sql = "SELECT ad_soyad FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$contact_id, $user_id], "ii");
    
    if (!$check_stmt || $check_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Contact not found']);
        return;
    }
    
    $contact_name = $check_stmt->get_result()->fetch_assoc()['ad_soyad'];
    $check_stmt->close();
    
    $sql = "DELETE FROM acil_durum_kisileri WHERE kisi_id = ? AND kullanici_id = ?";
    $stmt = execute_query($conn, $sql, [$contact_id, $user_id], "ii");
    
    if ($stmt) {
        // Acil durum kaydı oluştur
        logEmergencyAction($conn, $user_id, 'contact_deleted', 'Acil kişi silindi: ' . $contact_name);
        
        echo json_encode(['success' => true, 'message' => 'Contact deleted successfully']);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete contact']);
    }
}

/**
 * Özel aksiyonları yönet
 */
function handleSpecialActions($conn, $user_id, $data) {
    $action = $data['action'];
    
    switch ($action) {
        case 'panic_mode':
            handlePanicMode($conn, $user_id, $data);
            break;
            
        case 'emergency_alert':
            handleEmergencyAlert($conn, $user_id, $data);
            break;
            
        case 'share_location':
            handleLocationShare($conn, $user_id, $data);
            break;
            
        case 'log_emergency':
            logEmergencyAction($conn, $user_id, $data['type'], $data['details']);
            echo json_encode(['success' => true, 'message' => 'Action logged']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
}

/**
 * Panik modu işleme
 */
function handlePanicMode($conn, $user_id, $data) {
    // Aktif acil kişileri getir
    $sql = "SELECT * FROM acil_durum_kisileri WHERE kullanici_id = ? AND aktif_mi = 1";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    
    if ($stmt) {
        $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $notification_count = 0;
        
        foreach ($contacts as $contact) {
            if (sendEmergencyNotification($contact, 'panic_mode', $data)) {
                $notification_count++;
            }
        }
        
        // Panik modu kaydı oluştur
        logEmergencyAction($conn, $user_id, 'panic_mode_activated', 
                         "Panik modu aktif edildi. $notification_count kişiye bildirim gönderildi.");
        
        echo json_encode([
            'success' => true, 
            'message' => "Panic mode activated. Notifications sent to $notification_count contacts.",
            'notifications_sent' => $notification_count
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve contacts']);
    }
}

/**
 * Acil durum bildirimi gönder
 */
function handleEmergencyAlert($conn, $user_id, $data) {
    $message = isset($data['message']) ? $data['message'] : 'Acil yardıma ihtiyacım var!';
    
    // Aktif acil kişileri getir
    $sql = "SELECT * FROM acil_durum_kisileri WHERE kullanici_id = ? AND aktif_mi = 1";
    $stmt = execute_query($conn, $sql, [$user_id], "i");
    
    if ($stmt) {
        $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $notification_count = 0;
        
        foreach ($contacts as $contact) {
            if (sendEmergencyNotification($contact, 'emergency_alert', $data)) {
                $notification_count++;
            }
        }
        
        logEmergencyAction($conn, $user_id, 'emergency_alert_sent', 
                         "Acil durum bildirimi gönderildi: $message");
        
        echo json_encode([
            'success' => true, 
            'message' => "Emergency alert sent to $notification_count contacts.",
            'notifications_sent' => $notification_count
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve contacts']);
    }
}

/**
 * Konum paylaşımı işleme
 */
function handleLocationShare($conn, $user_id, $data) {
    if (!isset($data['location'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing location data']);
        return;
    }
    
    $location = $data['location'];
    
    // Konumu veritabanına kaydet
    $sql = "INSERT INTO acil_durum_kayitlari (kullanici_id, tetikleme_tipi, konum_lat, konum_lon) 
            VALUES (?, 'location_share', ?, ?)";
    
    $stmt = execute_query($conn, $sql, [$user_id, $location['latitude'], $location['longitude']], "idd");
    
    if ($stmt) {
        logEmergencyAction($conn, $user_id, 'location_shared', 
                         "Konum paylaşıldı: {$location['latitude']}, {$location['longitude']}");
        
        echo json_encode(['success' => true, 'message' => 'Location shared successfully']);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to share location']);
    }
}

/**
 * Acil durum bildirimi gönder (SMS/Email simülasyonu)
 */
function sendEmergencyNotification($contact, $type, $data) {
    // Gerçek uygulamada burada SMS veya email API'si kullanılacak
    // Şimdilik log kaydı oluşturuyoruz
    
    $message = "";
    switch ($type) {
        case 'panic_mode':
            $message = "PANIK MODU: Kullanıcı acil yardıma ihtiyaç duyuyor!";
            break;
        case 'emergency_alert':
            $message = "ACIL DURUM: " . ($data['message'] ?? 'Acil yardım gerekli!');
            break;
    }
    
    error_log("EMERGENCY NOTIFICATION to {$contact['telefon']} ({$contact['ad_soyad']}): $message");
    
    // Simüle edilmiş başarılı gönderim
    return true;
}

/**
 * Acil durum aksiyonlarını logla
 */
function logEmergencyAction($conn, $user_id, $action_type, $details) {
    $sql = "INSERT INTO acil_durum_kayitlari (kullanici_id, tetikleme_tipi, aciklama) 
            VALUES (?, ?, ?)";
    
    $stmt = execute_query($conn, $sql, [$user_id, $action_type, $details], "iss");
    
    if ($stmt) {
        $stmt->close();
        return true;
    }
    return false;
}
?>