<?php

declare(strict_types=1);

header("Content-Type: application/json");

$allowedOrigins = [
  'http://localhost:5173',
  'https://ggenius.gg',
  'https://d5251569772b.ngrok-free.app',
  'https://ggenius-api.onrender.com/bots/bot.php'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
foreach ($allowedOrigins as $allowed) {
  if ($allowed === $origin || str_contains($allowed, '*')) {
    header("Access-Control-Allow-Origin: $origin");
    break;
  }
}
header("Access-Control-Allow-Credentials: true");

$BOT_TOKEN        = getenv('BOT_TOKEN');
$DATABASE_URL     = getenv('DATABASE_URL');
$JWT_SECRET       = getenv('JWT_SECRET');

if (!$BOT_TOKEN || !$DATABASE_URL || !$JWT_SECRET) {
  http_response_code(500);
  echo json_encode(['error' => 'ENV variables missing']);
  exit;
}


$db = parse_url($DATABASE_URL);

$pdo = new PDO(
  "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/'),
  $db['user'],
  $db['pass'],
  [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]
);

$authData = $_GET;

if (!isset($authData['hash'])) {
  http_response_code(400);
  echo json_encode(['error' => 'No hash']);
  exit;
}

$hash = $authData['hash'];
unset($authData['hash']);

$dataCheckArr = [];
foreach ($authData as $k => $v) {
  $dataCheckArr[] = "$k=$v";
}
sort($dataCheckArr);

$dataCheckString = implode("\n", $dataCheckArr);
$secretKey = hash('sha256', $BOT_TOKEN, true);
$calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($calculatedHash, $hash)) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid Telegram signature']);
  exit;
}

if (time() - (int)$authData['auth_date'] > 86400) {
  http_response_code(401);
  echo json_encode(['error' => 'Auth expired']);
  exit;
}


$telegramId = (int)$authData['id'];
$username   = $authData['username'] ?? null;
$firstName  = $authData['first_name'] ?? null;
$photoUrl   = $authData['photo_url'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = :id");
$stmt->execute(['id' => $telegramId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  $stmt = $pdo->prepare("
    INSERT INTO users (telegram_id, nickname, created_at)
    VALUES (:id, :nickname, NOW())
    RETURNING *
  ");
  $stmt->execute([
    'id' => $telegramId,
    'nickname' => $username ?? $firstName ?? 'Telegram user'
  ]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

function base64url_encode(string $data): string
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function create_jwt(array $payload, string $secret, int $ttl): string
{
  $header = ['alg' => 'HS256', 'typ' => 'JWT'];
  $payload['iat'] = time();
  $payload['exp'] = time() + $ttl;

  $base = base64url_encode(json_encode($header)) . '.' .
    base64url_encode(json_encode($payload));

  $signature = hash_hmac('sha256', $base, $secret, true);

  return $base . '.' . base64url_encode($signature);
}

$accessToken = create_jwt([
  'uid' => $telegramId
], $JWT_SECRET, 900); // 15 min

$refreshToken = bin2hex(random_bytes(64));
$refreshExp = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

// save refresh token
$stmt = $pdo->prepare("
  INSERT INTO refresh_tokens (user_id, token, expires_at)
  VALUES (:uid, :token, :exp)
");
$stmt->execute([
  'uid' => $telegramId,
  'token' => $refreshToken,
  'exp' => $refreshExp
]);

$query = http_build_query([
  'access' => $accessToken,
  'refresh' => $refreshToken
]);

header("Location: https://d5251569772b.ngrok-free.app/auth/callback?$query"); // https://ggenius.gg/auth/callback?$query
exit;
