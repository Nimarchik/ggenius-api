<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
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

// JWT secret
$JWT_SECRET = getenv('JWT_SECRET');

// === JWT функции ===
function base64url_decode($data)
{
  $remainder = strlen($data) % 4;
  if ($remainder) $data .= str_repeat('=', 4 - $remainder);
  return base64_decode(strtr($data, '-_', '+/'));
}

function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Получаем токен из заголовка
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
  http_response_code(401);
  exit(json_encode(['error' => 'Нет токена']));
}

$token = substr($authHeader, 7);
$parts = explode('.', $token);
if (count($parts) !== 3) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

list($headerB64, $payloadB64, $signatureB64) = $parts;

// Проверка подписи
$expectedSig = base64url_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true));
if (!hash_equals($expectedSig, $signatureB64)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

// Декодируем payload
$payload = json_decode(base64url_decode($payloadB64), true);
if (!$payload || !isset($payload['uid'])) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный payload']));
}

// Проверяем срок действия
if ($payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Токен устарел']));
}

// Получаем пользователя из базы
$uid = (int)$payload['uid'];
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id=$1", [$uid]);
$user = pg_fetch_assoc($res);

if (!$user) {
  http_response_code(404);
  exit(json_encode(['error' => 'Пользователь не найден']));
}

// Всё ок, возвращаем пользователя
echo json_encode(['user' => $user]);
