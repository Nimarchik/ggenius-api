<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Функции base64url
function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
  return base64_decode(strtr($data, '-_', '+/'));
}

// Подключение к базе
$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);
$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);
if (!$conn) {
  http_response_code(500);
  exit(json_encode(['error' => 'Не удалось подключиться к базе данных']));
}

// JWT секрет
$JWT_SECRET = getenv('JWT_SECRET');

// Получаем тело запроса
$input = json_decode(file_get_contents('php://input'), true);
$refreshToken = $input['refresh'] ?? '';
if (!$refreshToken) {
  http_response_code(400);
  exit(json_encode(['error' => 'Нет refresh токена']));
}

// Проверка структуры JWT
$parts = explode('.', $refreshToken);
if (count($parts) !== 3) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный refresh токен']));
}

list($headerB64, $payloadB64, $signatureB64) = $parts;

// Проверка подписи
$expectedSig = base64url_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true));
if (!hash_equals($expectedSig, $signatureB64)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный refresh токен']));
}

// Декодируем payload
$payload = json_decode(base64url_decode($payloadB64), true);
if (!$payload || !isset($payload['uid'])) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный payload']));
}

// Проверяем срок действия refresh токена
if (isset($payload['exp']) && $payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Refresh токен устарел']));
}

// Telegram ID пользователя
$uid = (string)$payload['uid'];

// Проверяем пользователя в базе
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id = $1", [$uid]);
if (!$res) {
  http_response_code(500);
  exit(json_encode(['error' => 'Ошибка запроса к базе']));
}

$user = pg_fetch_assoc($res);
if (!$user) {
  http_response_code(404);
  exit(json_encode(['error' => 'Пользователь не найден']));
}

// Генерируем новый Access Token (1 час)
$headerNew = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$payloadNew = base64url_encode(json_encode([
  'uid' => $uid,
  'iat' => time(),
  'exp' => time() + 3600
]));
$signatureNew = base64url_encode(hash_hmac('sha256', "$headerNew.$payloadNew", $JWT_SECRET, true));
$accessToken = "$headerNew.$payloadNew.$signatureNew";

// Можно по желанию обновлять refresh токен, например, если срок меньше 1 дня
// Здесь оставляем старый refresh токен

echo json_encode([
  'access' => $accessToken,
  'refresh' => $refreshToken
]);
