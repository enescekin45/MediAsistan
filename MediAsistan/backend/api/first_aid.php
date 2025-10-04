<?php
/**
 * MediAsistan - İlk Yardım API
 * İlk yardım kategorileri ve talimatları için RESTful API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
 * GET Request Handler - İlk yardım verilerini getir
 */
function handleGetRequest($conn, $user_id) {
    $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    if ($category_id) {
        // Belirli bir kategori ve talimatlarını getir
        getCategoryWithInstructions($conn, $category_id);
    } else if ($search_query) {
        // Arama sorgusu ile talimatları getir
        searchInstructions($conn, $search_query);
    } else {
        // Tüm kategorileri getir
        getAllCategories($conn);
    }
}

/**
 * Tüm ilk yardım kategorilerini getir
 */
function getAllCategories($conn) {
    $sql = "SELECT * FROM ilk_yardim_kategorileri WHERE aktif_mi = 1 ORDER BY sira_no, kategori_adi";
    $stmt = execute_query($conn, $sql);
    
    if ($stmt) {
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Her kategori için talimat sayısını ekle
        foreach ($categories as &$category) {
            $category['talimat_sayisi'] = getInstructionCount($conn, $category['kategori_id']);
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $categories,
            'total' => count($categories)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch categories']);
    }
}

/**
 * Belirli bir kategoriyi ve talimatlarını getir
 */
function getCategoryWithInstructions($conn, $category_id) {
    // Kategori bilgilerini getir
    $category_sql = "SELECT * FROM ilk_yardim_kategorileri WHERE kategori_id = ? AND aktif_mi = 1";
    $category_stmt = execute_query($conn, $category_sql, [$category_id], "i");
    
    if (!$category_stmt || $category_stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        return;
    }
    
    $category = $category_stmt->get_result()->fetch_assoc();
    $category_stmt->close();
    
    // Kategoriye ait talimatları getir
    $instructions_sql = "SELECT * FROM ilk_yardim_talimatlari 
                        WHERE kategori_id = ? 
                        ORDER BY adim_numarasi";
    $instructions_stmt = execute_query($conn, $instructions_sql, [$category_id], "i");
    
    $instructions = [];
    if ($instructions_stmt) {
        $instructions = $instructions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $instructions_stmt->close();
    }
    
    $category['talimatlar'] = $instructions;
    $category['toplam_adim'] = count($instructions);
    
    echo json_encode([
        'success' => true, 
        'data' => $category
    ]);
}

/**
 * Talimatlarda arama yap
 */
function searchInstructions($conn, $search_query) {
    $search_term = "%" . $search_query . "%";
    
    $sql = "SELECT i.*, k.kategori_adi, k.kategori_ikon 
            FROM ilk_yardim_talimatlari i
            INNER JOIN ilk_yardim_kategorileri k ON i.kategori_id = k.kategori_id
            WHERE (i.baslik LIKE ? OR i.aciklama LIKE ?) 
            AND k.aktif_mi = 1
            ORDER BY k.sira_no, i.adim_numarasi";
    
    $stmt = execute_query($conn, $sql, [$search_term, $search_term], "ss");
    
    if ($stmt) {
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'search_query' => $search_query,
            'total_results' => count($results)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
    }
}

/**
 * POST Request Handler - İlk yardım kullanımını kaydet
 */
function handlePostRequest($conn, $user_id) {
    $input_data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input_data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing action parameter']);
        return;
    }
    
    switch ($input_data['action']) {
        case 'log_usage':
            logFirstAidUsage($conn, $user_id, $input_data);
            break;
            
        case 'rate_instruction':
            rateInstruction($conn, $user_id, $input_data);
            break;
            
        case 'get_emergency_guide':
            getEmergencyGuide($conn, $user_id, $input_data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
}

/**
 * İlk yardım kullanımını logla
 */
function logFirstAidUsage($conn, $user_id, $data) {
    $required_fields = ['kategori_id', 'talimat_id'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $kategori_id = intval($data['kategori_id']);
    $talimat_id = intval($data['talimat_id']);
    $adim_numarasi = isset($data['adim_numarasi']) ? intval($data['adim_numarasi']) : null;
    $tamamlandi = isset($data['tamamlandi']) ? (bool)$data['tamamlandi'] : false;
    $gecirilen_sure = isset($data['gecirilen_sure']) ? intval($data['gecirilen_sure']) : null;
    
    // Acil durum kaydı oluştur
    $sql = "INSERT INTO acil_durum_kayitlari 
            (kullanici_id, kategori_id, tetikleme_tipi, durum, aciklama) 
            VALUES (?, ?, 'ilk_yardim_rehberi', 'tamamlandi', ?)";
    
    $aciklama = "İlk yardım rehberi kullanıldı - Kategori: $kategori_id, Talimat: $talimat_id";
    if ($adim_numarasi) {
        $aciklama .= ", Adım: $adim_numarasi";
    }
    
    $stmt = execute_query($conn, $sql, [$user_id, $kategori_id, $aciklama], "iis");
    
    if ($stmt) {
        $kayit_id = $conn->insert_id;
        
        // Detaylı kullanım logu
        $detail_sql = "INSERT INTO ilk_yardim_kullanim_loglari 
                      (kayit_id, kullanici_id, talimat_id, adim_numarasi, tamamlandi, gecirilen_sure) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        
        $detail_stmt = execute_query($conn, $detail_sql, 
            [$kayit_id, $user_id, $talimat_id, $adim_numarasi, $tamamlandi, $gecirilen_sure], 
            "iiiiii");
        
        if ($detail_stmt) {
            $detail_stmt->close();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Usage logged successfully',
            'kayit_id' => $kayit_id
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to log usage']);
    }
}

/**
 * Talimatı değerlendir
 */
function rateInstruction($conn, $user_id, $data) {
    $required_fields = ['talimat_id', 'rating'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $talimat_id = intval($data['talimat_id']);
    $rating = intval($data['rating']);
    $yorum = isset($data['yorum']) ? trim($data['yorum']) : null;
    
    // Rating değerini sınırla (1-5)
    $rating = max(1, min(5, $rating));
    
    // Önce var olan ratingi kontrol et
    $check_sql = "SELECT id FROM ilk_yardim_ratingleri 
                  WHERE kullanici_id = ? AND talimat_id = ?";
    $check_stmt = execute_query($conn, $check_sql, [$user_id, $talimat_id], "ii");
    
    if ($check_stmt && $check_stmt->get_result()->num_rows > 0) {
        // Update existing rating
        $sql = "UPDATE ilk_yardim_ratingleri 
                SET rating = ?, yorum = ?, guncelleme_tarihi = NOW() 
                WHERE kullanici_id = ? AND talimat_id = ?";
    } else {
        // Insert new rating
        $sql = "INSERT INTO ilk_yardim_ratingleri 
                (kullanici_id, talimat_id, rating, yorum) 
                VALUES (?, ?, ?, ?)";
    }
    
    $stmt = execute_query($conn, $sql, [$rating, $yorum, $user_id, $talimat_id], "isii");
    
    if ($stmt) {
        echo json_encode([
            'success' => true, 
            'message' => 'Rating submitted successfully'
        ]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit rating']);
    }
}

/**
 * Acil durum kılavuzu getir (hızlı erişim için)
 */
function getEmergencyGuide($conn, $user_id, $data) {
    $kategori_adi = isset($data['kategori']) ? trim($data['kategori']) : null;
    
    if (!$kategori_adi) {
        // Varsayılan olarak en önemli 3 kategoriyi getir
        $sql = "SELECT k.*, 
                (SELECT COUNT(*) FROM ilk_yardim_talimatlari i WHERE i.kategori_id = k.kategori_id) as talimat_sayisi
                FROM ilk_yardim_kategorileri k 
                WHERE k.aktif_mi = 1 
                ORDER BY k.onem_derecesi DESC, k.sira_no 
                LIMIT 3";
        
        $stmt = execute_query($conn, $sql);
    } else {
        // Belirli kategoriyi ara
        $search_term = "%" . $kategori_adi . "%";
        $sql = "SELECT k.*, 
                (SELECT COUNT(*) FROM ilk_yardim_talimatlari i WHERE i.kategori_id = k.kategori_id) as talimat_sayisi
                FROM ilk_yardim_kategorileri k 
                WHERE k.kategori_adi LIKE ? AND k.aktif_mi = 1 
                ORDER BY k.onem_derecesi DESC 
                LIMIT 5";
        
        $stmt = execute_query($conn, $sql, [$search_term], "s");
    }
    
    if ($stmt) {
        $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Her kategori için ilk 3 talimatı getir
        foreach ($categories as &$category) {
            $instructions_sql = "SELECT * FROM ilk_yardim_talimatlari 
                               WHERE kategori_id = ? 
                               ORDER BY adim_numarasi 
                               LIMIT 3";
            $instructions_stmt = execute_query($conn, $instructions_sql, [$category['kategori_id']], "i");
            
            if ($instructions_stmt) {
                $category['hizli_talimatlar'] = $instructions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $instructions_stmt->close();
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $categories,
            'type' => $kategori_adi ? 'search' : 'quick_guide'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch emergency guide']);
    }
}

/**
 * Kategorideki talimat sayısını getir
 */
function getInstructionCount($conn, $kategori_id) {
    $sql = "SELECT COUNT(*) as sayi FROM ilk_yardim_talimatlari WHERE kategori_id = ?";
    $stmt = execute_query($conn, $sql, [$kategori_id], "i");
    
    if ($stmt) {
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['sayi'];
    }
    return 0;
}

// Gerekli tabloları oluştur (eğer yoksa)
createFirstAidTables($conn);

function createFirstAidTables($conn) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS ilk_yardim_kullanim_loglari (
            id INT PRIMARY KEY AUTO_INCREMENT,
            kayit_id INT,
            kullanici_id INT NOT NULL,
            talimat_id INT NOT NULL,
            adim_numarasi INT,
            tamamlandi BOOLEAN DEFAULT FALSE,
            gecirilen_sure INT COMMENT 'Saniye cinsinden',
            kullanım_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kullanici_tarih (kullanici_id, kullanım_tarihi)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS ilk_yardim_ratingleri (
            id INT PRIMARY KEY AUTO_INCREMENT,
            kullanici_id INT NOT NULL,
            talimat_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            yorum TEXT,
            olusturulma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_kullanici_talimat (kullanici_id, talimat_id),
            INDEX idx_talimat_rating (talimat_id, rating)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
}
?>