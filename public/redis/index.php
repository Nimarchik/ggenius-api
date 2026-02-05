<?php
header('Content-Type: application/json; charset=utf-8');

$redis = new Redis();

// Берём URL из переменной окружения
$redis_url = getenv('REDIS_URL');
$parts = parse_url($redis_url);

$host = $parts['host'];
$port = $parts['port'];
$password = $parts['pass'] ?? null;
$db = isset($parts['path']) ? ltrim($parts['path'], '/') : 0;

// Подключение
if (!$redis->connect($host, $port)) {
  http_response_code(500);
  echo json_encode(["error" => "Не удалось подключиться к Redis"]);
  exit;
}

if ($password) {
  $redis->auth($password);
}

$redis->select((int)$db);

// Итеративно сканируем все ключи
$it = null;
$data = [];

do {
  $keys = $redis->scan($it);
  if ($keys !== false) {
    foreach ($keys as $key) {
      $value = $redis->get($key);
      $data[$key] = $value;
    }
  }
} while ($it > 0);

// Выводим JSON
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
