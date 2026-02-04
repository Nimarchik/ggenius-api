<?php
header("Content-Type: application/json");

// === Подключение к базе через DATABASE_URL Render ===
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) exit(json_encode(['error' => 'DATABASE_URL не настроен']));

$dbopts = parse_url($dbUrl);
$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);
if (!$conn) exit(json_encode(['error' => 'Не удалось подключиться к БД']));

// === Telegram Bot Token ===
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) exit(json_encode(['error' => 'BOT_TOKEN не настроен']));

// === JWT secret ===
$JWT_SECRET = getenv('JWT_SECRET');
if (!$JWT_SECRET) exit(json_encode(['error' => 'JWT_SECRET не настроен']));

// === Получаем данные Telegram login_url ===
$auth_data = $_GET;
if (!isset($auth_data['hash'])) exit(json_encode(['error' => 'Нет данных hash']));
$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

// Проверка подписи Telegram
$data_check_arr = [];
foreach ($auth_data as $k => $v) $data_check_arr[] = "$k=$v";
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);
$secret_key = hash('sha256', $bot_token, true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

if (!hash_equals($hash, $check_hash)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Подпись неверна']));
}

// Проверка актуальности данных (не старше 24 часов)
if ((time() - $auth_data['auth_date']) > 86400) {
  http_response_code(401);
  exit(json_encode(['error' => 'Данные устарели']));
}

// Проверяем пользователя в базе
$user_id = $auth_data['id']; // telegram_id
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id=$1", [$user_id]);
$user = pg_fetch_assoc($res);

// Если пользователя нет, добавляем
if (!$user) {
  $columns = ['telegram_id', 'first_name', 'username', 'photo_url', 'created_at'];
  $values = [
    $user_id,
    $auth_data['first_name'] ?? '',
    $auth_data['username'] ?? '',
    $auth_data['photo_url'] ?? '',
    date('Y-m-d H:i:s')
  ];
  $query = "INSERT INTO users(" . implode(',', $columns) . ") VALUES('" . implode("','", $values) . "')";
  pg_query($conn, $query);
  $user = [
    'telegram_id' => $user_id,
    'first_name' => $auth_data['first_name'] ?? '',
    'username' => $auth_data['username'] ?? '',
    'photo_url' => $auth_data['photo_url'] ?? ''
  ];
}

// === JWT функции ===
function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Генерация Access Token (1 час)
$header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$payload = base64url_encode(json_encode([
  'uid' => (string)$user_id,
  'iat' => time(),
  'exp' => time() + 3600
]));
$signature = base64url_encode(hash_hmac('sha256', "$header.$payload", $JWT_SECRET, true));
$accessToken = "$header.$payload.$signature";

// Генерация Refresh Token (7 дней)
$refreshToken = bin2hex(random_bytes(64));
$expiresAt = date('Y-m-d H:i:s', time() + 604800); // 7 дней
pg_query_params($conn, "INSERT INTO refresh_tokens(telegram_id, token, expires_at) VALUES($1,$2,$3)", [$user_id, $refreshToken, $expiresAt]);

// Редирект на фронтенд с токенами
$frontend = getenv('FRONTEND_URL') ?: 'https://2c1baceb6325.ngrok-free.app';
header("Location: {$frontend}?access={$accessToken}&refresh={$refreshToken}");
exit;
