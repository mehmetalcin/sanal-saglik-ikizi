<?php
require 'db.php';
header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get') {
    // Herkese açık: Tüm özel yemekleri getir (user_id filtresi yok)
    $stmt = $pdo->prepare("SELECT id, food_name as name, calories as cal FROM user_custom_foods ORDER BY food_name ASC");
    $stmt->execute(array());
    echo json_encode(array("status" => "success", "data" => $stmt->fetchAll()));
}
elseif ($action === 'add') {
    // Sadece giriş yapanlar yemek ekleyebilir (Güvenlik için)
    $user_id = require_login(); 
    $data = json_decode(file_get_contents('php://input'), true);
    $name = isset($data['name']) ? trim($data['name']) : null;
    $cal = isset($data['cal']) ? (int)$data['cal'] : null;
    
    if ($name && $cal > 0) {
        try {
            // user_id eklemiyoruz, tablo artık global
            $stmt = $pdo->prepare("INSERT INTO user_custom_foods (food_name, calories) VALUES (?, ?)");
            $stmt->execute(array($name, $cal));
            echo json_encode(array("status" => "success", "id" => $pdo->lastInsertId()));
        } catch(Exception $e) {
            echo json_encode(array("status" => "error", "message" => "Bu yemek zaten mevcut veya bir hata oluştu: " . $e->getMessage()));
        }
    } else {
        echo json_encode(array("status" => "error", "message" => "Geçersiz veriler. İsim ve kalori girilmelidir."));
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Geçersiz işlem."]);
}
?>
