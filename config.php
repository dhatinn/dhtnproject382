<?php
/**
 * RM PADANG HARAPAN BUNDO - Configuration & Security Engine
 * Developed with zero-vulnerability principles (PDO, Prepared Statements, Strict CSRF, XSS prevention)
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Database Configuration (Default XAMPP)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'harapan_bundo_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// DOKU Configuration (Sandbox & Production)
define('DOKU_SANDBOX_URL', 'https://api-sandbox.doku.com');
define('DOKU_PRODUCTION_URL', 'https://api.doku.com');

/**
 * Get PDO Database Connection
 * @return PDO
 */
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Koneksi database gagal. Pastikan MySQL di XAMPP sudah berjalan.',
                'error'   => $e->getMessage()
            ]));
        }
    }
    return $pdo;
}

/**
 * Format currency to Indonesian Rupiah
 */
function formatRupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

/**
 * Sanitize & Escape Input
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return trim(htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8'));
}

/**
 * Send JSON Response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Generate Unique Order Code
 */
function generateOrderCode() {
    $prefix = 'HB';
    $date = date('Ymd');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    return "{$prefix}-{$date}-{$rand}";
}

/**
 * Get Restaurant Setting
 */
function getSetting($key, $default = '') {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT setting_value FROM restaurant_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * CSRF Protection Helpers
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Admin Session Validation
 */
function requireAdminLogin() {
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            jsonResponse(['success' => false, 'message' => 'Unauthorized. Sesi telah berakhir.'], 401);
        } else {
            $loginUrl = (strpos($_SERVER['REQUEST_URI'] ?? '', '/restorant/') !== false) ? '/restorant/admin/login.php' : ((strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? 'login.php' : 'admin/login.php');
            header("Location: $loginUrl");
            exit;
        }
    }
}

/**
 * Calculate CRC16-CCITT for Indonesian QRIS specification
 */
function crc16_ccitt($str) {
    $crc = 0xFFFF;
    for ($c = 0; $c < strlen($str); $c++) {
        $crc ^= (ord($str[$c]) << 8);
        for ($i = 0; $i < 8; $i++) {
            if ($crc & 0x8000) {
                $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
            } else {
                $crc = ($crc << 1) & 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/**
 * Generate Valid Indonesian Standard QRIS Payload (EMVCo Spec)
 */
function generateQrisPayload($orderCode, $amount) {
    $merchantName = getSetting('qris_merchant_name', 'RM PADANG HARAPAN BUNDO');
    $nmid = getSetting('qris_nmid', 'ID1020039281720');
    $city = 'JAKARTA';
    $postal = '10220';
    $cleanAmount = (int)$amount;

    // EMVCo QRIS Payload Construction
    $payload = "000201"; // Format Indicator
    $payload .= "010212"; // Dynamic QR (12 = Dynamic, 11 = Static)
    
    // 26 Merchant Account Info
    $gui = "ID.CO.QRIS.WWW";
    $maiData = "00" . str_pad(strlen($gui), 2, '0', STR_PAD_LEFT) . $gui;
    $maiData .= "01" . str_pad(strlen($nmid), 2, '0', STR_PAD_LEFT) . $nmid;
    $maiData .= "0208" . "00000000";
    $maiData .= "0303" . "UME";
    $payload .= "26" . str_pad(strlen($maiData), 2, '0', STR_PAD_LEFT) . $maiData;
    
    // Merchant Category Code: 5812 (Eating places, Restaurants)
    $payload .= "52045812";
    // Transaction Currency: 360 (IDR)
    $payload .= "5303360";
    
    // Transaction Amount
    $amountStr = (string)$cleanAmount;
    $payload .= "54" . str_pad(strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
    
    // Country Code
    $payload .= "5802ID";
    // Merchant Name
    $payload .= "59" . str_pad(strlen($merchantName), 2, '0', STR_PAD_LEFT) . $merchantName;
    // Merchant City
    $payload .= "60" . str_pad(strlen($city), 2, '0', STR_PAD_LEFT) . $city;
    // Postal Code
    $payload .= "61" . str_pad(strlen($postal), 2, '0', STR_PAD_LEFT) . $postal;
    
    // Additional Data Field (Order Code)
    $addVal = "01" . str_pad(strlen($orderCode), 2, '0', STR_PAD_LEFT) . $orderCode;
    $payload .= "62" . str_pad(strlen($addVal), 2, '0', STR_PAD_LEFT) . $addVal;
    
    // CRC placeholder
    $payloadToCrc = $payload . "6304";
    $crc = crc16_ccitt($payloadToCrc);
    
    return $payloadToCrc . $crc;
}

/**
 * Generate DOKU Signature (HMAC-SHA256)
 */
function generateDokuSignature($clientId, $requestId, $requestTimestamp, $requestTarget, $jsonBody, $secretKey) {
    $digest = base64_encode(hash('sha256', $jsonBody, true));
    $component = "Client-Id:" . $clientId . "\n" .
                 "Request-Id:" . $requestId . "\n" .
                 "Request-Timestamp:" . $requestTimestamp . "\n" .
                 "Request-Target:" . $requestTarget . "\n" .
                 "Digest:" . $digest;
    $signature = base64_encode(hash_hmac('sha256', $component, $secretKey, true));
    return "HMACSHA256=" . $signature;
}
