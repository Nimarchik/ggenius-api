<?php
header("Content-Type: application/json");

// Подключение к базе PostgreSQL
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
  http_response_code(500);
  exit(json_encode(['error' => 'DATABASE_URL не задан']));
}

$dbopts = parse_url($dbUrl);
$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);
if (!$conn) {
  http_response_code(500);
  exit(json_encode(['error' => 'Не удалось подключиться к базе']));
}

// Проверка JWT
$JWT_SECRET = getenv('JWT_SECRET');
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

[$headerB64, $payloadB64, $signatureB64] = $parts;
$signatureCheck = base64_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true));

if ($signatureB64 !== $signatureCheck) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

// Декодируем payload
$payload = json_decode(base64_decode($payloadB64), true);
if (!$payload || !isset($payload['uid'], $payload['exp'])) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный payload']));
}

if ($payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Токен устарел']));
}

// Получаем пользователя из базы
// Приводим telegram_id к тексту для сравнения с payload['uid']
$uid = (string)$payload['uid'];
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id::text = $1", [$uid]);

if (!$res) {
  http_response_code(500);
  exit(json_encode(['error' => 'Ошибка запроса к базе']));
}

$user = pg_fetch_assoc($res);

echo json_encode(['user' => $user]);
