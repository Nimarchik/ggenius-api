<?php
// -------------------- CORS --------------------
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// -------------------- DB --------------------
$dbUrl = getenv('DATABASE_URL');
$dbopts = parse_url($dbUrl);

$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);

if (!$conn) {
  http_response_code(500);
  exit(json_encode(['error' => 'DB connection failed']));
}

// -------------------- JWT --------------------
$JWT_SECRET = getenv('JWT_SECRET');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($authHeader, 'Bearer ')) {
  http_response_code(401);
  exit(json_encode(['error' => 'No token']));
}

$token = substr($authHeader, 7);
$parts = explode('.', $token);

if (count($parts) !== 3) {
  http_response_code(401);
  exit(json_encode(['error' => 'Invalid token format']));
}

[$headerB64, $payloadB64, $signatureB64] = $parts;

// base64url decode
$payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
$payload = json_decode($payloadJson, true);

if (!$payload || !isset($payload['uid'])) {
  http_response_code(401);
  exit(json_encode(['error' => 'Invalid payload']));
}

// signature check (base64url)
$signatureCheck = rtrim(strtr(
  base64_encode(
    hash_hmac('sha256', "$headerB64.$payloadB64", $JWT_SECRET, true)
  ),
  '+/',
  '-_'
), '=');

if (!hash_equals($signatureCheck, $signatureB64)) {
  http_response_code(401);
  exit(json_encode(['error' => 'Invalid signature']));
}

// expiration
if (isset($payload['exp']) && $payload['exp'] < time()) {
  http_response_code(401);
  exit(json_encode(['error' => 'Token expired']));
}

// -------------------- USER --------------------
$uid = (int)$payload['uid'];

$res = pg_query_params(
  $conn,
  "SELECT * FROM users WHERE telegram_id = $1",
  [$uid]
);

$user = pg_fetch_assoc($res);

if (!$user) {
  http_response_code(404);
  exit(json_encode(['error' => 'User not found']));
}

// -------------------- RESPONSE --------------------
echo json_encode([
  'user' => $user
]);
