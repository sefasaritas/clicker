<?php
require_once 'config.php';

// POST verilerini al
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'register':
        register();
        break;
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'save_game':
        saveGame();
        break;
    case 'load_game':
        loadGame();
        break;
    case 'get_leaderboard':
        getLeaderboard();
        break;
    case 'unlock_achievement':
        unlockAchievement();
        break;
    default:
        errorResponse('Geçersiz işlem');
}

function register() {
    global $pdo, $input;
    
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validasyon
    if (empty($username) || empty($email) || empty($password)) {
        errorResponse('Tüm alanlar doldurulmalıdır');
    }
    
    if (strlen($username) < 3 || strlen($username) > 50) {
        errorResponse('Kullanıcı adı 3-50 karakter arasında olmalıdır');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('Geçerli bir e-posta adresi giriniz');
    }
    
    if (strlen($password) < 6) {
        errorResponse('Şifre en az 6 karakter olmalıdır');
    }
    
    try {
        // Kullanıcı adı ve e-posta kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            errorResponse('Bu kullanıcı adı veya e-posta zaten kullanılıyor');
        }
        
        // Kullanıcı oluştur
        $passwordHash = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $passwordHash]);
        
        $userId = $pdo->lastInsertId();
        
        // Varsayılan oyun durumu oluştur
        $stmt = $pdo->prepare("INSERT INTO game_states (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        successResponse(null, 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.');
        
    } catch(PDOException $e) {
        errorResponse('Kayıt sırasında hata oluştu');
    }
}

function login() {
    global $pdo, $input;
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        errorResponse('Kullanıcı adı ve şifre gereklidir');
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($password, $user['password_hash'])) {
            errorResponse('Hatalı kullanıcı adı veya şifre');
        }
        
        // Son giriş zamanını güncelle
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Session'a kaydet
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        // Oyun durumunu yükle
        $gameData = loadUserGameData($user['id']);
        
        successResponse([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ],
            'gameData' => $gameData
        ], 'Giriş başarılı');
        
    } catch(PDOException $e) {
        errorResponse('Giriş sırasında hata oluştu');
    }
}

function logout() {
    session_destroy();
    successResponse(null, 'Çıkış yapıldı');
}

function saveGame() {
    global $pdo, $input;
    
    if (!isset($_SESSION['user_id'])) {
        errorResponse('Oturum bulunamadı', 401);
    }
    
    $userId = $_SESSION['user_id'];
    $gameData = $input['gameData'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE game_states SET 
                coins = ?, 
                click_power = ?, 
                coins_per_second = ?, 
                total_clicks = ?, 
                level = ?, 
                xp = ?, 
                click_upgrade = ?, 
                auto_miner = ?, 
                server_upgrade = ?, 
                blockchain = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([
            $gameData['coins'],
            $gameData['clickPower'],
            $gameData['coinsPerSecond'],
            $gameData['totalClicks'],
            $gameData['level'],
            $gameData['xp'],
            $gameData['upgrades']['clickUpgrade'],
            $gameData['upgrades']['autoMiner'],
            $gameData['upgrades']['server'],
            $gameData['upgrades']['blockchain'],
            $userId
        ]);
        
        successResponse(null, 'Oyun kaydedildi');
        
    } catch(PDOException $e) {
        errorResponse('Kaydetme sırasında hata oluştu');
    }
}

function loadGame() {
    if (!isset($_SESSION['user_id'])) {
        errorResponse('Oturum bulunamadı', 401);
    }
    
    $gameData = loadUserGameData($_SESSION['user_id']);
    successResponse($gameData);
}

function loadUserGameData($userId) {
    global $pdo;
    
    try {
        // Oyun durumunu yükle
        $stmt = $pdo->prepare("SELECT * FROM game_states WHERE user_id = ?");
        $stmt->execute([$userId]);
        $gameState = $stmt->fetch();
        
        // Başarımları yükle
        $stmt = $pdo->prepare("SELECT achievement_id FROM achievements WHERE user_id = ?");
        $stmt->execute([$userId]);
        $achievements = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'coins' => (int)$gameState['coins'],
            'clickPower' => (int)$gameState['click_power'],
            'coinsPerSecond' => (int)$gameState['coins_per_second'],
            'totalClicks' => (int)$gameState['total_clicks'],
            'level' => (int)$gameState['level'],
            'xp' => (int)$gameState['xp'],
            'upgrades' => [
                'clickUpgrade' => (int)$gameState['click_upgrade'],
                'autoMiner' => (int)$gameState['auto_miner'],
                'server' => (int)$gameState['server_upgrade'],
                'blockchain' => (int)$gameState['blockchain']
            ],
            'achievements' => $achievements
        ];
        
    } catch(PDOException $e) {
        errorResponse('Oyun verisi yüklenirken hata oluştu');
    }
}

function getLeaderboard() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, g.coins, g.level, g.total_clicks
            FROM users u 
            JOIN game_states g ON u.id = g.user_id 
            ORDER BY g.coins DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll();
        
        successResponse($leaderboard);
        
    } catch(PDOException $e) {
        errorResponse('Liderlik tablosu yüklenirken hata oluştu');
    }
}

function unlockAchievement() {
    global $pdo, $input;
    
    if (!isset($_SESSION['user_id'])) {
        errorResponse('Oturum bulunamadı', 401);
    }
    
    $userId = $_SESSION['user_id'];
    $achievementId = $input['achievementId'];
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO achievements (user_id, achievement_id) VALUES (?, ?)");
        $stmt->execute([$userId, $achievementId]);
        
        successResponse(null, 'Başarım kaydedildi');
        
    } catch(PDOException $e) {
        errorResponse('Başarım kaydedilirken hata oluştu');
    }
}
?>