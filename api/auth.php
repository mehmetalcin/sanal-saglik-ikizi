<?php
require 'db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if (empty($username) || empty($password)) {
        echo json_encode(array("status" => "error", "message" => "Kullanıcı adı ve şifre zorunludur."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(array($username));
    if ($stmt->fetch()) {
        echo json_encode(array("status" => "error", "message" => "Bu kullanıcı adı zaten alınmış."));
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(openssl_random_pseudo_bytes(32)); 

    $gender = isset($data['gender']) ? $data['gender'] : 'erkek';
    $age_group = isset($data['age_group']) ? $data['age_group'] : 'yetiskin';
    $avatar_id = isset($data['avatar_id']) ? $data['avatar_id'] : 'm1';

    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, auth_token, gender, age_group, avatar_id) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute(array($username, $hash, $token, $gender, $age_group, $avatar_id))) {
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO user_targets (user_id, target_steps, target_water) VALUES (?, 8000, 2500)");
        $stmt->execute(array($user_id));

        $defaultMeds = array("Parol (500mg)", "Aspirin", "C Vitamini", "D Vitamini", "Magnezyum", "Omega 3");
        $stmt = $pdo->prepare("INSERT INTO user_medication_list (user_id, med_name) VALUES (?, ?)");
        foreach($defaultMeds as $med) {
            $stmt->execute(array($user_id, $med));
        }

        echo json_encode(array("status" => "success", "message" => "Kayıt başarılı!", "token" => $token, "username" => $username));
    } else {
        echo json_encode(array("status" => "error", "message" => "Kayıt sırasında bir hata oluştu."));
    }
} 
elseif ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(array($username));
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $token = $user['auth_token'];
        if (empty($token)) {
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?")->execute(array($token, $user['id']));
        }
        
        echo json_encode(array("status" => "success", "username" => $user['username'], "token" => $token, "gender" => $user['gender'], "age_group" => $user['age_group'], "avatar_id" => $user['avatar_id']));
    } else {
        echo json_encode(array("status" => "error", "message" => "Geçersiz kullanıcı adı veya şifre."));
    }
}
elseif ($action === 'logout') {
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'check') {
    $headers = getallheaders();
    $authHeader = '';
    foreach($headers as $key => $val) {
        if(strtolower($key) == 'authorization') {
            $authHeader = $val;
            break;
        }
    }
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $stmt = $pdo->prepare("SELECT username, gender, age_group, avatar_id FROM users WHERE auth_token = ?");
        $stmt->execute(array($matches[1]));
        if ($user = $stmt->fetch()) {
            echo json_encode(array("status" => "authenticated", "username" => $user['username'], "gender" => $user['gender'], "age_group" => $user['age_group'], "avatar_id" => $user['avatar_id']));
            exit;
        }
    }
    echo json_encode(array("status" => "unauthenticated"));
}
elseif ($action === 'update_profile') {
    $user_id = require_login();
    $data = json_decode(file_get_contents('php://input'), true);
    $gender = $data['gender'] ?? 'erkek';
    $age_group = $data['age_group'] ?? 'yetiskin';
    $avatar_id = $data['avatar_id'] ?? 'm1';

    $stmt = $pdo->prepare("UPDATE users SET gender = ?, age_group = ?, avatar_id = ? WHERE id = ?");
    if ($stmt->execute(array($gender, $age_group, $avatar_id, $user_id))) {
        echo json_encode(array("status" => "success", "message" => "Profil güncellendi.", "gender" => $gender, "age_group" => $age_group, "avatar_id" => $avatar_id));
    } else {
        echo json_encode(array("status" => "error", "message" => "Profil güncellenemedi."));
    }
}
else {
    echo json_encode(array("status" => "error", "message" => "Bilinmeyen işlem."));
}
?>
