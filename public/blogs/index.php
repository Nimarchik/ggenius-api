<?php
header("Content-Type: application/json");
$allowedOrigins = [
  'https://ggenius.gg',
  'http://localhost:5173',
  'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}


$host = 'dpg-d1sg7cre5dus739m5m90-a';
$db   = 'ggenius';
$user = 'ggenius_user';
$pass = 'lJrMaovTX0QjiECpBXnnZwyNN9URPHpa';
$port = 5432;

try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  $stmt = $pdo->query("SELECT * FROM blogs ORDER BY id DESC");
  $blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($blogs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(["error" => "Помилка з'єднання: " . $e->getMessage()]);
}
