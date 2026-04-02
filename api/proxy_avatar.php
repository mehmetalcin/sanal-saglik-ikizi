<?php
/**
 * 3D Avatar GLB Proxy
 * Bypasses client-side DNS/Network blocks (ERR_NAME_NOT_RESOLVED) 
 * by fetching the model server-to-server.
 */

header('Content-Type: model/gltf-binary');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');

$url = $_GET['url'] ?? '';

if (empty($url) || strpos($url, 'readyplayer.me') === false) {
    http_response_code(400);
    exit("Geçersiz model URL.");
}

// Basic caching to speed up loads
$cache_dir = __DIR__ . '/../cache/models/';
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0777, true);
}

$cache_file = $cache_dir . md5($url) . '.glb';

if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
    readfile($cache_file);
    exit;
}

// Fetch from origin
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL errors for better compatibility
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) HealthAssistantProxy/1.0');

$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && !empty($data)) {
    file_put_contents($cache_file, $data);
    echo $data;
} else {
    http_response_code(502);
    exit("Model sunucusuna erişilemedi.");
}
