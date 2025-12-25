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

// Данные от Telegram в этом случае приходят в $_GET
$auth_data = $_GET;

if (!isset($auth_data['hash'])) {
  die('Ошибка: Данные не найдены');
}

$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

// Проверка хеша (такая же, как раньше)
$data_check_arr = [];
foreach ($auth_data as $key => $value) {
  $data_check_arr[] = $key . '=' . $value;
}
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);

$secret_key = hash('sha256', $_ENV['BOT_TOKEN'], true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

if (strcmp($hash, $check_hash) === 0) {
  // УСПЕХ!
  // Теперь нам нужно перекинуть пользователя обратно на главную страницу React
  // И передать данные (например, через сессию или временный токен)

  session_start();
  $_SESSION['user'] = $auth_data;

  // Редирект обратно на фронтенд (React)
  header('Location: https://ваш-ngrok.ngrok-free.app/');
  exit;
} else {
  die('Ошибка безопасности');
}
