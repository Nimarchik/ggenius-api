<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

function base64url_encode($data)
{
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
  return base64_decode(strtr($data, '-_', '+/'));
}

/* ---------- DB ---------- */
$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);

$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);

if (!$conn) {
  http_response_code(500);
  exit(json_encode(['error' => 'DB error']));
}

/* ---------- JWT ---------- */
$JWT_SECRET = getenv('JWT_SECRET');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
  http_response_code(401);
  exit(json_encode(['error' => 'No token']));
}

$token = $matches[1];
$parts = explode('.', $token);

if (count($parts) !== 3) {
  http_response_code(401);
  exit(json_encode(['error' => 'Invalid token format']));
}

[$headerB64, $payloadB64, $signatureB64] = $parts;

/* ---------- SIGNATURE CHECK ---------- */
$expectedSignature = base64url_encode(
  hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true)
);

if (!hash_equals($expectedSignature, $signatureB64)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Bad signature']));
}

/* ---------- PAYLOAD ---------- */
$payload = json_decode(base64url_decode($payloadB64), true);

if (!$payload || empty($payload['uid'])) {
  http_response_code(401);
  exit(json_encode(['error' => 'Bad payload']));
}

if (!empty($payload['exp']) && $payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Token expired']));
}

$uid = (int)$payload['uid'];

/* ---------- USER ---------- */
$res = pg_query_params(
  $conn,
  "SELECT * FROM users WHERE telegram_id = $1 LIMIT 1",
  [$uid]
);

$user = pg_fetch_assoc($res);

if (!$user) {
  http_response_code(404);
  exit(json_encode(['error' => 'User not found']));
}

echo json_encode(['user' => $user]);
