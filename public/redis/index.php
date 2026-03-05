<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require __DIR__ . '/vendor/autoload.php';

// Берём URL из переменной окружения или задаём вручную
$dbUrl = getenv('DATABASE_URL') ?: 'postgres://ufk3frgco7l9d1:p7aad477be5e7c084f8d9c2e9998fdfd75ed3eb573c808a6b3db95bbdb221b234@ccaml3dimis7eh.cluster-czz5s0kz4scl.eu-west-1.rds.amazonaws.com:5432/d7rglea9jc6ggd';

// Разбираем URL
$parts = parse_url($dbUrl);

$host = $parts['host'];
$user = $parts['user'];
$pass = $parts['pass'] ?? '';
$dbname = ltrim($parts['path'], '/');
$port = $parts['port'] ?? 5432;

// Подключение к PostgreSQL через PDO
try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
  $db = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  die("Ошибка подключения к PostgreSQL: " . $e->getMessage());
}

// Подключение к Redis через URL
$redisUrl = getenv('REDIS_URL') ?: 'redis://default:7S4SIp5IRoUuYlYkjnLhas3j58NgA4Kc@redis-14211.c269.eu-west-1-3.ec2.redns.redis-cloud.com:14211';
$redis = new Predis\Client($redisUrl);

// Ключ для кэша
$cacheKey = "lesha:shop_packages";

// Проверяем кэш
$cachedData = $redis->get($cacheKey);

if ($cachedData) {
  header('Content-Type: application/json');
  echo $cachedData;
  exit;
}

// Если нет в кэше → идём в базу
$table = $_GET['table'] ?? 'shop_packages';
$stmt = $db->query("SELECT * FROM \"$table\"");
$data = $stmt->fetchAll();

// Сохраняем в Redis на 5 минут
$redis->setex($cacheKey, 300, json_encode($data));

// Отдаём клиенту
header('Content-Type: application/json');
echo json_encode($data);
