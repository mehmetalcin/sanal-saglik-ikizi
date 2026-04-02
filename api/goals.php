<?php
require 'db.php';
$user_id = require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT target_steps as steps, target_water as water, target_calories as cal FROM user_targets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $targets = $stmt->fetch();
    
    if(!$targets) { // fallback
        $targets = ["steps" => 8000, "water" => 2500, "cal" => null];
    }
    
    echo json_encode(["status" => "success", "data" => $targets]);
} 
elseif ($action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $updates = [];
    $params = [];
    
    if (isset($data['steps'])) { $updates[] = "target_steps = ?"; $params[] = $data['steps']; }
    if (isset($data['water'])) { $updates[] = "target_water = ?"; $params[] = $data['water']; }
    if (isset($data['cal'])) { $updates[] = "target_calories = ?"; $params[] = $data['cal']; }
    
    if (count($updates) > 0) {
        $params[] = $user_id;
        $sql = "UPDATE user_targets SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    echo json_encode(["status" => "success"]);
}
else {
    echo json_encode(["status" => "error", "message" => "Bilinmeyen işlem."]);
}
?>
