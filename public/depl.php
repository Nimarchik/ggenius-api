<?php
header("Content-Type: application/json; charset=utf-8");

// Разрешённые источники
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

// Логирование запроса
$raw = file_get_contents('php://input');
file_put_contents('deepl-log.txt', "--- RAW INPUT ---\n$raw\n", FILE_APPEND);

$data = json_decode($raw, true);
file_put_contents('deepl-log.txt', "--- PARSED JSON ---\n" . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

$text = $data['text'] ?? '';
$lang = strtoupper($data['target_lang'] ?? 'EN');

// Подготовка запроса к DeepL
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

// Получаем кодировку ответа
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Определим кодировку
$encoding = null;
if (preg_match('/charset=([a-zA-Z0-9\-]+)/i', $contentType, $matches)) {
  $encoding = strtoupper(trim($matches[1]));
}

if ($encoding && $encoding !== 'UTF-8') {
  $response = mb_convert_encoding($response, 'UTF-8', $encoding);
}

// Лог DeepL-ответа
file_put_contents('deepl-log.txt', "--- DeepL RESPONSE ---\n$response\n", FILE_APPEND);

// Вернём клиенту
echo $response;
