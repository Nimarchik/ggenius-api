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
$token = $_ENV['BOT_TOKEN'];
$website_url = 'https://ggenius-api.onrender.com/bots/auth.php'; // Ссылка на обработчик

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
  $chat_id = $update['message']['chat']['id'];
  $text = $update['message']['text'];

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

    // Отправка сообщения через API Telegram
    file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query([
      'chat_id' => $chat_id,
      'text' => $message,
      'reply_markup' => json_encode($keyboard)
    ]));
  }
}
