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

// Получаем все ключи
$keys = $redis->keys('*');

if (empty($keys)) {
  echo "Нет ключей в Redis\n";
} else {
  foreach ($keys as $key) {
    $value = $redis->get($key);
    echo $key . " => " . $value . "\n";
  }
}
