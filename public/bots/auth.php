<?php
header("Content-Type: application/json");

// === Подключение к БД через DATABASE_URL Render ===
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) exit(json_encode(['error' => 'DATABASE_URL не настроен']));

$dbopts = parse_url($dbUrl);
$conn = pg_connect("host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') . " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require");
if (!$conn) exit(json_encode(['error' => 'Не удалось подключиться к базе']));

// === JWT Secret ===
$JWT_SECRET = getenv('JWT_SECRET');
if (!$JWT_SECRET) exit(json_encode(['error' => 'JWT_SECRET не настроен']));

// === Telegram login_url данные ===
$auth_data = $_GET;
if (!isset($auth_data['hash'])) exit(json_encode(['error' => 'Нет данных hash']));
$check_hash = $auth_data['hash'];
unset($auth_data['hash']);

// === Проверка подписи Telegram ===
$data_check_arr = [];
foreach ($auth_data as $k => $v) $data_check_arr[] = "$k=$v";
sort($data_check_arr);
$data_check_string = implode("\n", $data_check_arr);
$secret_key = hash('sha256', $JWT_SECRET, true);
$hash = hash_hmac('sha256', $data_check_string, $secret_key);

if (!hash_equals($hash, $check_hash)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Подпись неверна']));
}

// === Проверка актуальности данных (24 часа) ===
if ((time() - $auth_data['auth_date']) > 86400) exit(json_encode(['error' => 'Данные устарели']));

// === Функция для base64url ===
function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// === Проверяем пользователя в базе ===
$user_id = $auth_data['id']; // telegram_id
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id=$1", [$user_id]);
$user = pg_fetch_assoc($res);

if (!$user) {
  // Добавляем нового пользователя
  $columns = ['telegram_id', 'first_name', 'username', 'photo_url', 'created_at'];
  $values = [
    $user_id,
    $auth_data['first_name'] ?? '',
    $auth_data['username'] ?? '',
    $auth_data['photo_url'] ?? '',
    date('Y-m-d H:i:s')
  ];
  $query = "INSERT INTO users(telegram_id, first_name, username, photo_url, created_at) VALUES($1,$2,$3,$4,$5)";
  pg_query_params($conn, $query, $values);

  $user = [
    'telegram_id' => $user_id,
    'first_name' => $auth_data['first_name'] ?? '',
    'username' => $auth_data['username'] ?? '',
    'photo_url' => $auth_data['photo_url'] ?? ''
  ];
}

// === Генерация Access Token (JWT) ===
$header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$payload = base64url_encode(json_encode([
  'uid' => $user_id,
  'iat' => time(),
  'exp' => time() + 3600 // 1 час
]));
$signature = base64url_encode(hash_hmac('sha256', "$header.$payload", $JWT_SECRET, true));
$accessToken = "$header.$payload.$signature";

// === Генерация Refresh Token ===
$refreshToken = bin2hex(random_bytes(64));
$expiresAt = date('Y-m-d H:i:s', time() + 604800); // 7 дней
pg_query_params($conn, "INSERT INTO refresh_tokens(user_id, token, expires_at) VALUES($1,$2,$3)", [$user_id, $refreshToken, $expiresAt]);

// === Редирект на фронтенд с токенами ===
$frontend = 'https://c815c23fbf0e.ngrok-free.app/'; // <-- поменяй на свой фронтенд
header("Location: {$frontend}?access={$accessToken}&refresh={$refreshToken}");
exit;
