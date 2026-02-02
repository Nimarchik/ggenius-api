<?php
header("Content-Type: application/json");

$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);
$conn = pg_connect("host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') . " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require");

$JWT_SECRET = getenv('JWT_SECRET');
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
  http_response_code(401);
  exit(json_encode(['error' => 'Нет токена']));
}

$token = substr($authHeader, 7);
[$headerB64, $payloadB64, $signatureB64] = explode('.', $token);
$signatureCheck = base64_encode(hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true));
if ($signatureB64 !== $signatureCheck) {
  http_response_code(401);
  exit(json_encode(['error' => 'Неверный токен']));
}

$payload = json_decode(base64_decode($payloadB64), true);
if ($payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Токен устарел']));
}

// Получаем пользователя из БД
$res = pg_query_params($conn, "SELECT * FROM users WHERE telegram_id=$1", [$payload['uid']]);
$user = pg_fetch_assoc($res);
echo json_encode(['user' => $user]);
