<?php
/**
 * 3D Avatar GLB Proxy (v2 - Hardened)
 * Bypasses client-side DNS/Network blocks.
 * If 502 occurs, it means the server also cannot reach the origin.
 */

// Enable error reporting for debugging (User can see the real error now)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400');

$url = $_GET['url'] ?? '';

if (empty($url) || strpos($url, 'readyplayer.me') === false) {
    header('Content-Type: text/plain');
    http_response_code(400);
    exit("Hata: Geçersiz veya eksik model URL'si.");
}

// 1. Check local cache first
$cache_dir = __DIR__ . '/../cache/models/';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0777, true);
}
$cache_file = $cache_dir . md5($url) . '.glb';

if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400)) {
    header('Content-Type: model/gltf-binary');
    header('X-Proxy-Cache: HIT');
    readfile($cache_file);
    exit;
}

// 2. Attempt fetch using file_get_contents (often more stable on shared hosts)
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'header' => "User-Agent: HealthAssistant/1.0\r\n"
                  . "Accept: */*\r\n"
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
]);

$data = @file_get_contents($url, false, $context);

if ($data === false) {
    // 3. Fallback to CURL if file_get_contents is disabled
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($data)) {
            header('Content-Type: text/plain');
            http_response_code(502);
            exit("Hata: Model sunucusuna (ReadyPlayerMe) sunucu taraflı erişim de engellenmiş. Lütfen GLB dosyasını manuel indirip sunucunuza 'models/' klasörüne yükleyin.");
        }
    } else {
        header('Content-Type: text/plain');
        http_response_code(502);
        exit("Hata: Sunucunuzda dış bağlantı (file_get_contents/CURL) kapalı veya ReadyPlayerMe engellenmiş.");
    }
}

// 4. Success - Save and Output
header('Content-Type: model/gltf-binary');
header('X-Proxy-Cache: MISS');
@file_put_contents($cache_file, $data);
echo $data;
