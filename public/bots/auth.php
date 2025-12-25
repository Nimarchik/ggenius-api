<?php
header("Content-Type: application/json");

// 1. Настройка CORS
$allowedOrigins = [
  'http://localhost:5173',
  'https://9ea98d3c1cae.ngrok-free.app',
  'https://ggenius-api.onrender.com',
  'https://c4f9e433bc8b.ngrok-free.app/bots/bot.php'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if (file_exists(__DIR__ . '/.env'))  // Укажите путь до вашего .env
  $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
  if (strpos(trim($line), '#') === 0) continue;
  list($name, $value) = explode('=', $line, 2);
  $_ENV[trim($name)] = trim($value);
  putenv(trim($name) . "=" . trim($value));
}


// 2. Получаем токен из окружения
$token = $_ENV['BOT_TOKEN']  ?? '8550778477:AAEznwLjymXAQBLmSUG0yvKSrOMkdNEiOU8';

if (!$token) {
  die('Ошибка: BOT_TOKEN не настроен в переменых окружения');
}

// 3. Данные от Telegram приходят через GET при нажатии login_url
$auth_data = $_GET;

if (!isset($auth_data['hash'])) {
  die('Ошибка: Данные не получены (нет hash)');
}

$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

// 4. Подготовка строки для проверки
$data_check_arr = [];
foreach ($auth_data as $key => $value) {
  $data_check_arr[] = $key . '=' . $value;
}
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);

// 5. Вычисление хеша
$secret_key = hash('sha256', $token, true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

// 6. Проверка и редирект
if (strcmp($hash, $check_hash) === 0) {
  // Проверка на актуальность данных (не старше 24 часов)
  if ((time() - $auth_data['auth_date']) > 86400) {
    die('Ошибка: Данные устарели');
  }

  $userData = base64_encode(json_encode($auth_data));

  // Редирект на локальный React (или на ngrok адрес фронтенда)
  header("Location: http://localhost:5173/?user=" . $userData);
  exit;
} else {
  http_response_code(401);
  echo "Ошибка авторизации: подпись не верна.";
}
