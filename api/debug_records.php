<?php
require 'db.php';
$headers = getallheaders();
$authHeader = '';
foreach($headers as $key => $val) {
    if(strtolower($key) == 'authorization') {
        $authHeader = $val;
        break;
    }
}
// For testing, just list last 10 records of ALL users IF token is valid or just list
$stmt = $pdo->prepare("SELECT * FROM user_daily_records ORDER BY date DESC LIMIT 20");
$stmt->execute();
$recs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($recs);
