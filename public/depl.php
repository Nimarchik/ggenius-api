<?php
header("Content-Type: application/json");

$allowedOrigins = [
  'https://ggenius.gg',
  'http://localhost:5173',
  'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
  header("Access-Control-Allow-Credentials: true");
  header("Access-Control-Max-Age: 86400");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

$deeplKey = "fd8f8e9d-389b-4d21-923c-fd4b6da1160e:fx";

// Получаем тело запроса как JSON
$data = json_decode(file_get_contents('php://input'), true);

$text = $data['text'] ?? '';
$lang = strtoupper($data['target_lang'] ?? 'EN');

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api-free.deepl.com/v2/translate");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/x-www-form-urlencoded",
  "Authorization: DeepL-Auth-Key $deeplKey"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
  "text" => $text,
  "target_lang" => $lang
]));

$response = curl_exec($ch);
curl_close($ch);

echo $response;
