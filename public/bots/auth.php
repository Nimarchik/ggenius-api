<?php
header("Content-Type: application/json");
$allowedOrigins = [
  'http://localhost:5173',
  'https://9ea98d3c1cae.ngrok-free.app/',
  'https://9ea98d3c1cae.ngrok-free.app'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

define($_ENV['BOT_TOKEN'], "BOT_TOKEN");

$react_url = 'https://9ea98d3c1cae.ngrok-free.app/'; // Адрес вашего React-приложения

$auth_data = $_GET;

if (!isset($auth_data['hash'])) {
  die('Ошибка: Данные не получены');
}

$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

$data_check_arr = [];
foreach ($auth_data as $key => $value) {
  $data_check_arr[] = $key . '=' . $value;
}
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);

$secret_key = hash('sha256', $_ENV['BOT_TOKEN'], true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

if (strcmp($hash, $check_hash) === 0) {
  // 1. Кодируем данные пользователя в JSON и Base64, чтобы безопасно передать в URL
  $userData = base64_encode(json_encode($auth_data));

  // 2. Перенаправляем обратно в React с параметром user
  header("Location: $react_url?user=$userData");
  exit;
} else {
  echo "Ошибка авторизации: подпись не верна.";
}
