<?php
header('Content-Type: application/json');
$allowedOrigins = [
  'http://localhost:5173',
  'https://9ea98d3c1cae.ngrok-free.app/',
  'https://ggenius.gg',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type");
}

define($_ENV['BOT_TOKEN'], 'BOT_TOKEN');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['hash'])) {
  die(json_encode(['error' => 'No data provided']));
}

$check_hash = $data['hash'];
unset($data['hash']);

// 1. Собираем данные в строку в алфавитном порядке
$data_check_arr = [];
foreach ($data as $key => $value) {
  $data_check_arr[] = $key . '=' . $value;
}
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);

// 2. Вычисляем секретный ключ на основе токена
$secret_key = hash('sha256', true);

// 3. Вычисляем проверочный хеш
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

// 4. Сравниваем и проверяем актуальность (auth_date не старше 24 часов)
if (strcmp($hash, $check_hash) !== 0) {
  echo json_encode(['error' => 'Data is NOT from Telegram']);
} elseif ((time() - $data['auth_date']) > 86400) {
  echo json_encode(['error' => 'Data is outdated']);
} else {
  // ДАННЫЕ ВЕРНЫ. Можно создавать сессию или JWT
  echo json_encode(['success' => true, 'user' => $data]);
}
