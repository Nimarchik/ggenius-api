<?php
header("Content-Type: application/json");

$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);
$conn = pg_connect("host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') . " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require");

$JWT_SECRET = getenv('JWT_SECRET');
if (!$JWT_SECRET) exit(json_encode(['error' => 'JWT_SECRET не настроен']));

$input = json_decode(file_get_contents('php://input'), true);
$refreshToken = $input['refresh'] ?? '';

if (!$refreshToken) exit(json_encode(['error' => 'Нет refresh токена']));

// Проверяем токен в базе
$res = pg_query_params($conn, "SELECT * FROM refresh_tokens WHERE token=$1 AND expires_at > NOW()", [$refreshToken]);
$row = pg_fetch_assoc($res);
if (!$row) exit(json_encode(['error' => 'Неверный или просроченный токен']));

// Генерируем новый Access Token
$header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
$payload = base64_encode(json_encode([
  'uid' => $row['user_id'],
  'iat' => time(),
  'exp' => time() + 3600
]));
$signature = hash_hmac('sha256', "$header.$payload", $JWT_SECRET, true);
$accessToken = "$header.$payload." . base64_encode($signature);

echo json_encode(['access' => $accessToken]);
