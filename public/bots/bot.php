<?php

file_put_contents(__DIR__ . '/telegram_log.txt', date('c') . " webhook hit\n", FILE_APPEND);
http_response_code(200);
echo 'OK';

$token = getenv('BOT_TOKEN');
if (!$token) exit;

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!isset($update['message'])) exit;

$chat_id = $update['message']['chat']['id'];
$text = $update['message']['text'] ?? '';

if (strpos($text, '/start') === 0) {

  $keyboard = [
    'inline_keyboard' => [[
      [
        'text' => '🚀 Увійти на сайт',
        'login_url' => [
          'url' => 'https://ggenius-api.onrender.com/bots/auth.php',
          'request_write_access' => true
        ]
      ]
    ]]
  ];

  $data = [
    'chat_id' => $chat_id,
    'text' => 'Натисни кнопку, щоб авторизуватися на сайті:',
    'reply_markup' => json_encode($keyboard)
  ];

  $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_exec($ch);
  curl_close($ch);
}
