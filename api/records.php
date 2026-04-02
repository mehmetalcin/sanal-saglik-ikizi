<?php
require 'db.php';
$user_id = require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true);

if ($action === 'get_all') {
    $stmt = $pdo->prepare("SELECT * FROM daily_records WHERE user_id = ? ORDER BY record_date DESC");
    $stmt->execute(array($user_id));
    $days = $stmt->fetchAll();

    $mealsStmt = $pdo->prepare("SELECT id, record_date, time, food_name as name, calories as cal FROM meals WHERE user_id = ?");
    $mealsStmt->execute(array($user_id));
    $mealsArr = $mealsStmt->fetchAll();
    
    $medsStmt = $pdo->prepare("SELECT id, record_date, time, med_name as name FROM medications_log WHERE user_id = ?");
    $medsStmt->execute(array($user_id));
    $medsArr = $medsStmt->fetchAll();
    
    $sugarStmt = $pdo->prepare("SELECT id, record_date, time, sugar_val as val, sugar_type as type FROM sugar_log WHERE user_id = ?");
    $sugarStmt->execute(array($user_id));
    $sugarArr = $sugarStmt->fetchAll();

    $mealMap = array();
    foreach($mealsArr as $m) { $mealMap[$m['record_date']][] = $m; }
    
    $medMap = array();
    foreach($medsArr as $m) { $medMap[$m['record_date']][] = $m; }

    $sugarMap = array();
    foreach($sugarArr as $s) { $sugarMap[$s['record_date']][] = $s; }

    $result = array();
    foreach($days as $d) {
        $rd = $d['record_date'];
        $item = array(
            "id" => $d['id'],
            "date" => $rd,
            "weight" => $d['weight'],
            "height" => $d['height'],
            "systolic" => $d['systolic'],
            "diastolic" => $d['diastolic'],
            "pulse" => $d['pulse'],
            "steps" => $d['steps'],
            "notes" => $d['notes'],
            "water" => (int)$d['water'],
            "updatedAt" => $d['updated_at']
        );
        if (isset($mealMap[$rd])) $item["meals"] = $mealMap[$rd];
        if (isset($medMap[$rd])) $item["meds"] = $medMap[$rd];
        if (isset($sugarMap[$rd])) $item["sugars"] = $sugarMap[$rd];
        
        $result[] = $item;
    }

    echo json_encode(array("status" => "success", "data" => $result));
}
elseif ($action === 'upsert_daily') {
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $fields = isset($data['fields']) ? $data['fields'] : array();

    $stmt = $pdo->prepare("SELECT id FROM daily_records WHERE user_id = ? AND record_date = ?");
    $stmt->execute(array($user_id, $date));
    $existing = $stmt->fetch();

    if (!$existing) {
        $stmt = $pdo->prepare("INSERT INTO daily_records (user_id, record_date) VALUES (?, ?)");
        $stmt->execute(array($user_id, $date));
    }

    $updates = array();
    $params = array();
    $allowed = array('weight', 'height', 'systolic', 'diastolic', 'pulse', 'steps', 'notes', 'water');
    
    foreach($allowed as $key) {
        if (array_key_exists($key, $fields)) {
            $updates[] = "$key = ?";
            $params[] = $fields[$key];
        }
    }

    if (count($updates) > 0) {
        $params[] = $user_id;
        $params[] = $date;
        $sql = "UPDATE daily_records SET " . implode(", ", $updates) . " WHERE user_id = ? AND record_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'add_meal') {
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $time = isset($data['time']) ? $data['time'] : date('H:i');
    $name = $data['name'];
    $cal = $data['cal'];
    
    $pdo->prepare("INSERT IGNORE INTO daily_records (user_id, record_date) VALUES (?, ?)")->execute(array($user_id, $date));

    $stmt = $pdo->prepare("INSERT INTO meals (user_id, record_date, time, food_name, calories) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(array($user_id, $date, $time, $name, $cal));
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'add_med') {
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $time = isset($data['time']) ? $data['time'] : date('H:i');
    $name = $data['name'];

    $pdo->prepare("INSERT IGNORE INTO daily_records (user_id, record_date) VALUES (?, ?)")->execute(array($user_id, $date));
    
    $stmt = $pdo->prepare("INSERT INTO medications_log (user_id, record_date, time, med_name) VALUES (?, ?, ?, ?)");
    $stmt->execute(array($user_id, $date, $time, $name));
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'add_sugar') {
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
    $time = isset($data['time']) ? $data['time'] : date('H:i');
    $val = $data['val'];
    $type = $data['type'];

    $pdo->prepare("INSERT IGNORE INTO daily_records (user_id, record_date) VALUES (?, ?)")->execute(array($user_id, $date));

    $stmt = $pdo->prepare("INSERT INTO sugar_log (user_id, record_date, time, sugar_val, sugar_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(array($user_id, $date, $time, $val, $type));
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'delete_item') {
    $type = $data['type'] ?? '';
    $id = $data['id'] ?? 0;
    $date = $data['date'] ?? '';

    $timestamp = date('H:i:s');
    $logMsg = "[$timestamp] DELETE ITEM REQUEST: type=$type, id=$id, date=$date, user=$user_id\n";
    file_put_contents('delete_log.txt', $logMsg, FILE_APPEND);

    if ($type === 'food') {
        $pdo->prepare("DELETE FROM meals WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
    } elseif ($type === 'meds') {
        $pdo->prepare("DELETE FROM medications_log WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
    } elseif ($type === 'sugar') {
        $pdo->prepare("DELETE FROM sugar_log WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
    } elseif (in_array($type, ['body', 'heart', 'steps', 'water'])) {
        $map = [
            'body' => 'weight = NULL, height = NULL',
            'heart' => 'systolic = NULL, diastolic = NULL, pulse = NULL',
            'steps' => 'steps = NULL, notes = NULL',
            'water' => 'water = 0'
        ];
        $sql = "UPDATE daily_records SET " . $map[$type] . " WHERE record_date = ? AND user_id = ?";
        $pdo->prepare($sql)->execute(array($date, $user_id));
    }
    echo json_encode(array("status" => "success"));
}
elseif ($action === 'delete_day') {
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $date = isset($data['date']) ? trim($data['date']) : '';
    
    $timestamp = date('H:i:s');
    $logMsg = "[$timestamp] DELETE REQUEST: id=$id, date=$date, user=$user_id\n";
    file_put_contents('delete_log.txt', $logMsg, FILE_APPEND);
    
    if ($id <= 0) {
        $logHeader = "--- SON LOGLAR ---\n" . (file_exists('delete_log.txt') ? substr(file_get_contents('delete_log.txt'), -500) : '');
        echo json_encode(array("status" => "error", "message" => "HATA: Gecersiz ID. \n\nLoglar:\n" . $logHeader));
        exit;
    }
    
    try {
        // HANGİ KAYDI SİLİYORUZ? (Silmeden önce kontrol edelim)
        $check = $pdo->prepare("SELECT record_date FROM daily_records WHERE id = ? AND user_id = ?");
        $check->execute(array($id, $user_id));
        $row = $check->fetch();
        
        if (!$row) {
            $logHeader = "--- SON LOGLAR ---\n" . (file_exists('delete_log.txt') ? substr(file_get_contents('delete_log.txt'), -500) : '');
            echo json_encode(array("status" => "error", "message" => "Kayıt veritabanında bulunamadı. \n\nLoglar:\n" . $logHeader));
            exit;
        }
        
        $dbDate = $row['record_date'];
        file_put_contents('delete_log.txt', "[$timestamp] FOUND: DB Date is $dbDate\n", FILE_APPEND);

        // ASIL SİLME İŞLEMİ
        $pdo->prepare("DELETE FROM daily_records WHERE id = ? AND user_id = ?")->execute(array($id, $user_id));
        $pdo->prepare("DELETE FROM meals WHERE user_id = ? AND record_date = ?")->execute(array($user_id, $dbDate));
        $pdo->prepare("DELETE FROM medications_log WHERE user_id = ? AND record_date = ?")->execute(array($user_id, $dbDate));
        $pdo->prepare("DELETE FROM sugar_log WHERE user_id = ? AND record_date = ?")->execute(array($user_id, $dbDate));
        
        $msg = "BASARILI: ID $id ($dbDate) silindi.";
        file_put_contents('delete_log.txt', "[$timestamp] $msg\n", FILE_APPEND);
        
        $logHeader = "--- SON LOGLAR ---\n" . (file_exists('delete_log.txt') ? substr(file_get_contents('delete_log.txt'), -500) : '');
        echo json_encode(array("status" => "success", "message" => $msg . " \n\nLoglar:\n" . $logHeader));
        
    } catch(Exception $e) {
        $logHeader = "--- SON LOGLAR ---\n" . (file_exists('delete_log.txt') ? substr(file_get_contents('delete_log.txt'), -500) : '');
        echo json_encode(array("status" => "error", "message" => $e->getMessage() . " \n\nLoglar:\n" . $logHeader));
    }
}
else {
    echo json_encode(array("status" => "error", "message" => "Bilinmeyen işlem."));
}
?>
