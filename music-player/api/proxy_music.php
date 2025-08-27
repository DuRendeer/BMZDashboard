<?php
header('Content-Type: audio/mpeg');
header('Access-Control-Allow-Origin: *');

$file_id = $_GET['id'] ?? null;

if (!$file_id) {
    http_response_code(400);
    echo "ID required";
    exit;
}

$url = "https://docs.google.com/uc?export=download&id=" . $file_id;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $data) {
    echo $data;
} else {
    http_response_code(404);
    echo "File not found";
}
?>