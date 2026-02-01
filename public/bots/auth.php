<?php

// 1. Получаем токен
$token = getenv('BOT_TOKEN');
if (!$token) {
  die('BOT_TOKEN not set');
}

// 2. Получаем данные от Telegram
$auth_data = $_GET;

if (!isset($auth_data['hash'])) {
  die('No hash provided');
}

$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

// 3. Формируем строку проверки
$data_check_arr = [];
foreach ($auth_data as $key => $value) {
  $data_check_arr[] = $key . '=' . (string)$value;
}

sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);

// 4. Проверяем подпись
$secret_key = hash('sha256', $token, true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

if (!hash_equals($hash, $check_hash)) {
  http_response_code(401);
  die('Invalid hash');
}

// 5. Проверка времени
if ((time() - $auth_data['auth_date']) > 86400) {
  die('Auth data expired');
}

// 6. Кодируем данные
$userData = base64_encode(json_encode($auth_data));

// 7. Редирект на фронт
header('Location: https://eba4e580b13c.ngrok-free.app/?user=' . $userData);
exit;
