<?php
// Veritabanı bağlantı ayarları
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'sql_clicker');
define('DB_PASSWORD', 'sjSZ7aYFhwHyzbTh');
define('DB_NAME', 'sql_clicker');

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                   DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Session başlat
session_start();

// CORS başlıkları (gerekirse)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// JSON yanıt fonksiyonu
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hata yanıtı fonksiyonu
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

// Başarı yanıtı fonksiyonu
function successResponse($data = null, $message = null) {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    jsonResponse($response);
}

// Güvenlik: XSS koruması
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Şifre hash fonksiyonu
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Şifre doğrulama fonksiyonu
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
?>