<?php
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
  die("Не удалось подключиться к Redis");
}

if ($password) {
  $redis->auth($password);
}

$redis->select((int)$db);

// Итеративно сканируем все ключи
$it = null;
do {
  $keys = $redis->scan($it);
  if ($keys !== false) {
    foreach ($keys as $key) {
      $value = $redis->get($key);
      echo $key . " => " . $value . "\n";
    }
  }
} while ($it > 0);
