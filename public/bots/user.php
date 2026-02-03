<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Логирование
function writeLog($message)
{
  $logFile = __DIR__ . '/user.log';
  $time = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$time] $message\n", FILE_APPEND);
}

// Подключение к базе
$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);
$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);

if (!$conn) {
  writeLog("DB connection failed");
  http_response_code(500);
  exit(json_encode(['error' => 'Не удалось подключиться к базе данных']));
}

// JWT секрет
$JWT_SECRET = getenv('JWT_SECRET');

// Получаем токен из заголовка Authorization
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
  writeLog("No token provided");
  http_response_code(401);
  exit(json_encode(['error' => 'Нет токена']));
}

$token = substr($authHeader, 7);

// Разбираем JWT
function base64url_decode($data)
{
  return base64_decode(strtr($data, '-_', '+/'));
}
function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$parts = explode('.', $token);
if (count($parts) !== 3) {
  writeLog("Invalid token structure");
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

list($headerB64, $payloadB64, $signatureB64) = $parts;

// Проверка подписи
$expectedSig = base64url_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true));
if (!hash_equals($expectedSig, $signatureB64)) {
  writeLog("Invalid token signature");
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

// Декодируем payload
$payload = json_decode(base64url_decode($payloadB64), true);
if (!$payload || !isset($payload['uid'])) {
  writeLog("Invalid payload");
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный payload']));
}

// Проверяем срок действия
if (isset($payload['exp']) && $payload['exp'] < time()) {
  writeLog("Token expired for uid={$payload['uid']}");
  http_response_code(401);
  exit(json_encode(['error' => 'Токен устарел']));
}

// Получаем пользователя из базы
$uid = (int)$payload['uid'];
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id = $1", [$uid]);

if (!$res) {
  writeLog("DB query failed for uid=$uid");
  http_response_code(500);
  exit(json_encode(['error' => 'Ошибка запроса к базе']));
}

$user = pg_fetch_assoc($res);

if (!$user) {
  writeLog("User not found for uid=$uid");
  http_response_code(404);
  exit(json_encode(['error' => 'Пользователь не найден']));
}

// Всё ок, возвращаем пользователя
writeLog("User fetched successfully for uid=$uid");
echo json_encode(['user' => $user]);

echo json_encode([
  'debug_uid' => $uid,
  'debug_payload' => $payload
]);
exit;
