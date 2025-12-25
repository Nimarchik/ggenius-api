<?php
header("Content-Type: application/json");
$allowedOrigins = [
  'https://ggenius.gg',
  'http://localhost:5173',
  'https://9ea98d3c1cae.ngrok-free.app/',
  'https://9ea98d3c1cae.ngrok-free.app',
  'https://ggenius-api.onrender.com/bots/auth.php',
  'https://ggenius-api.onrender.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
}


if (file_exists(__DIR__ . '/.env')) { // Укажите путь до вашего .env
  $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $_ENV[trim($name)] = trim($value);
    putenv(trim($name) . "=" . trim($value));
  }
}


// Загрузка токена (убедитесь, что переменная окружения доступна)
$token = $_ENV['BOT_TOKEN'] ?? '8550778477:AAEznwLjymXAQBLmSUG0yvKSrOMkdNEiOU8';

// Ссылка на обработчик АВТОРИЗАЦИИ (не на сам bot.php)
$website_url = 'https://6fbfdc3c2fdc.ngrok-free.app/auth.php';

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
  $chat_id = $update['message']['chat']['id'];
  $text = $update['message']['text'] ?? '';

  if (strpos($text, '/start') === 0) {
    $message = "Нажмите на кнопку ниже, чтобы авторизоваться на сайте:";

    $keyboard = [
      'inline_keyboard' => [[
        [
          'text' => '🚀 Войти на сайт',
          'login_url' => [
            'url' => $website_url,
            'request_write_access' => true
          ]
        ]
      ]]
    ];

    // Используем cURL (более надежно для JSON)
    $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
      'chat_id' => $chat_id,
      'text' => $message,
      'reply_markup' => json_encode($keyboard) // Здесь оставляем json_encode
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // Для отладки: можно записать ответ от Telegram в файл
    file_put_contents('log.txt', $response);
  }
}
