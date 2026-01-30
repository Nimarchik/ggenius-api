<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$BOT_TOKEN = getenv("TELEGRAM_BOT_TOKEN"); 


if (!isset($_GET['chat_id'])) {
  http_response_code(400);
  echo json_encode(["error" => "chat_id required"]);
  exit;
}

$chat_id = $_GET['chat_id'];

// 1️⃣ Получаем инфу о чате
$chatUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/getChat?chat_id={$chat_id}";
$chatResponse = json_decode(file_get_contents($chatUrl), true);

if (!$chatResponse['ok']) {
  http_response_code(400);
  echo json_encode(["error" => "Telegram error", "details" => $chatResponse]);
  exit;
}

$result = [
  "id" => $chatResponse['result']['id'],
  "title" => $chatResponse['result']['title'] ?? null,
  "type" => $chatResponse['result']['type'],
  "avatar" => null
];

// 2️⃣ Если есть аватар — получаем ссылку
if (isset($chatResponse['result']['photo']['big_file_id'])) {
  $file_id = $chatResponse['result']['photo']['big_file_id'];

  $fileUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/getFile?file_id={$file_id}";
  $fileResponse = json_decode(file_get_contents($fileUrl), true);

  if ($fileResponse['ok']) {
    $file_path = $fileResponse['result']['file_path'];
    $result['avatar'] = "https://api.telegram.org/file/bot{$BOT_TOKEN}/{$file_path}";
  }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
