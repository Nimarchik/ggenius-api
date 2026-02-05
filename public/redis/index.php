<?php
header('Content-Type: application/json; charset=utf-8');

$redis = new Redis();

$redis_url = getenv('REDIS_URL');
$parts = parse_url($redis_url);

$host = $parts['host'];
$port = $parts['port'];
$password = $parts['pass'] ?? null;
$db = isset($parts['path']) ? ltrim($parts['path'], '/') : 0;

if (!$redis->connect($host, $port)) {
  http_response_code(500);
  echo json_encode(["error" => "Не удалось подключиться к Redis"]);
  exit;
}

if ($password) {
  $redis->auth($password);
}

$redis->select((int)$db);

$it = 0;
$data = [];

do {
  $keys = $redis->scan($it);
  if ($keys !== false) {
    foreach ($keys as $key) {
      $data[$key] = $redis->get($key);
    }
  }
} while ($it !== 0);

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
