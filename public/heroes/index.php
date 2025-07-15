<?php
header("Content-Type: application/json");
$allowedOrigins = [
  'http://localhost:5173',
  'http://127.0.0.1:5173',
  'https://ggenius.gg',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}


$apiUrl = 'https://mapi.mobilelegends.com/hero/list';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
  http_response_code(500);
  echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
  curl_close($ch);
  exit();
}
curl_close($ch);

echo $response;
