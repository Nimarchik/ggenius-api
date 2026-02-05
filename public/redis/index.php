<?php
$redis = new Redis();

$redis_url = getenv('REDIS_URL'); // например, REDIS_URL=redis://default:password@host:port
$parts = parse_url($redis_url);

$host = $parts['host'];
$port = $parts['port'];
$password = $parts['pass'] ?? null;
$db = isset($parts['path']) ? ltrim($parts['path'], '/') : 0;

if (!$redis->connect($host, $port)) {
  die("Не удалось подключиться к Redis");
}

if ($password) {
  $redis->auth($password);
}

$redis->select((int)$db);

$redis->set('key', 'value');
echo $redis->get('key'); // value
