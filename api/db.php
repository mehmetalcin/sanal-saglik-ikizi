<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

function help_me_error($errno, $errstr, $errfile, $errline) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array(
        "status" => "error", 
        "message" => "PHP Hatası: [$errno] $errstr",
        "file" => basename($errfile),
        "line" => $errline
    ));
    exit;
}
set_error_handler("help_me_error");
set_exception_handler(function($e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array("status" => "error", "message" => "Exception: " . $e->getMessage()));
    exit;
});

$host = 'localhost';
$db   = 'alcinyaz_saglik';
$user = 'alcinyaz_saglik';
$pass = 'Saglik0123$';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
);

$pdo = new PDO($dsn, $user, $pass, $options);
$pdo->exec("SET NAMES utf8mb4"); // Türkçe karakter desteği için zorla

// Otomatik Tablo Güncellemeleri
try {
    $pdo->exec("ALTER TABLE users ADD auth_token VARCHAR(100) NULL UNIQUE");
} catch (\Exception $e) { }

try {
    $pdo->exec("ALTER TABLE users ADD gender ENUM('erkek', 'kadin') DEFAULT 'erkek'");
} catch (\Exception $e) { }

try {
    $pdo->exec("ALTER TABLE users ADD age_group ENUM('cocuk', 'yetiskin') DEFAULT 'yetiskin'");
} catch (\Exception $e) { }

try {
    $pdo->exec("ALTER TABLE users ADD avatar_id VARCHAR(50) DEFAULT 'm1'");
} catch (\Exception $e) { }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_custom_foods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_name VARCHAR(255) UNIQUE NOT NULL,
        calories INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Exception $e) { }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_date DATE NOT NULL,
        file_path VARCHAR(512) DEFAULT NULL,
        glucose DECIMAL(6,1) DEFAULT NULL,
        cholesterol_total DECIMAL(6,1) DEFAULT NULL,
        ldl DECIMAL(6,1) DEFAULT NULL,
        hdl DECIMAL(6,1) DEFAULT NULL,
        triglyceride DECIMAL(6,1) DEFAULT NULL,
        hba1c DECIMAL(4,1) DEFAULT NULL,
        iron DECIMAL(6,1) DEFAULT NULL,
        ferritin DECIMAL(6,1) DEFAULT NULL,
        b12 DECIMAL(7,1) DEFAULT NULL,
        vitamin_d DECIMAL(6,1) DEFAULT NULL,
        tsh DECIMAL(6,2) DEFAULT NULL,
        hemoglobin DECIMAL(5,1) DEFAULT NULL,
        creatinine DECIMAL(5,2) DEFAULT NULL,
        alt_sgpt DECIMAL(6,1) DEFAULT NULL,
        ast_sgot DECIMAL(6,1) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_date (user_id, report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Exception $e) { }

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function require_login() {
    global $pdo;
    $headers = getallheaders();
    $authHeader = '';
    foreach($headers as $key => $val) {
        if(strtolower($key) == 'authorization') {
            $authHeader = $val;
            break;
        }
    }
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE auth_token = ?");
        $stmt->execute(array($token));
        if ($user = $stmt->fetch()) {
            return $user['id'];
        }
    }

    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(array("status" => "error", "message" => "Unauthorized"));
    exit;
}
?>
