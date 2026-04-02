<?php
require 'db.php';
$user_id = require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $stmt = $pdo->prepare("SELECT med_name FROM user_medication_list WHERE user_id = ?");
    $stmt->execute(array($user_id));
    $meds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(array("status" => "success", "data" => $meds));
} 
elseif ($action === 'add') {
    $data = json_decode(file_get_contents('php://input'), true);
    $med_name = trim($data['name'] ?? '');
    
    if ($med_name) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_medication_list (user_id, med_name) VALUES (?, ?)");
        $stmt->execute(array($user_id, $med_name));
        echo json_encode(array("status" => "success"));
    } else {
        echo json_encode(array("status" => "error", "message" => "İsim boş olamaz"));
    }
}
elseif ($action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $oldName = $data['oldName'] ?? '';
    $newName = $data['newName'] ?? '';
    if ($oldName && $newName) {
        $stmt = $pdo->prepare("UPDATE user_medication_list SET med_name = ? WHERE user_id = ? AND med_name = ?");
        $stmt->execute(array($newName, $user_id, $oldName));
        echo json_encode(array("status" => "success"));
    } else {
        echo json_encode(array("status" => "error", "message" => "Geçersiz veriler."));
    }
}
elseif ($action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    if ($name) {
        $stmt = $pdo->prepare("DELETE FROM user_medication_list WHERE user_id = ? AND med_name = ?");
        $stmt->execute(array($user_id, $name));
        echo json_encode(array("status" => "success"));
    } else {
        echo json_encode(array("status" => "error", "message" => "Geçersiz veriler."));
    }
}
?>
