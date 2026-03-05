<?php
header("Access-Control-Allow-Origin: https://ggenius.gg");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
}


require __DIR__ . '/../../vendor/autoload.php';

// Берём URL из переменной окружения или задаём вручную
$dbUrl = getenv('DATABASE_URL');

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
$redisUrl = getenv('REDIS_URL');
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
