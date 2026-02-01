<?php
// avatar.php — прокси для Telegram photo_url

$url = $_GET['url'] ?? '';

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
  http_response_code(400);
  exit('Invalid URL');
}

// Telegram иногда режет без User-Agent
$opts = [
  "http" => [
    "method" => "GET",
    "header" => "User-Agent: Mozilla/5.0\r\n"
  ]
];

$context = stream_context_create($opts);
$image = @file_get_contents($url, false, $context);

if (!$image) {
  http_response_code(404);
  exit;
}

// Определяем тип (Telegram обычно jpeg)
header("Content-Type: image/jpeg");
header("Cache-Control: public, max-age=86400");

echo $image;
