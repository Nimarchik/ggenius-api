<?php
// avatar.php

// Проверка, что параметр url передан
if (!isset($_GET['url'])) {
  http_response_code(400);
  die("Invalid URL: missing parameter");
}

$url = $_GET['url'];

// Простейшая проверка, что это Telegram URL
if (!preg_match('/^https:\/\/t\.me\/i\/userpic\/\d+\/[A-Za-z0-9_-]+\.jpg$/', $url)) {
  http_response_code(400);
  die("Invalid URL: must be Telegram userpic");
}

// Попытка получить картинку
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // на случай редиректов
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");

$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if (!$data || $httpCode !== 200) {
  http_response_code(404);
  die("Failed to fetch image");
}

// Отдаем заголовки и картинку
header("Content-Type: $contentType");
header("Cache-Control: max-age=3600"); // кэш на 1 час
echo $data;
exit;
