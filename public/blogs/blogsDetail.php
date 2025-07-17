<?php
header('Content-Type: application/json');

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

$id = intval($_GET['id'] ?? 0);
$blogs = json_decode(file_get_contents(__DIR__ . './blog.json'), true);
foreach ($blogs as $b) {
  if ($b['id'] === $id) {
    echo json_encode($b);
    exit;
  }
}
http_response_code(404);
echo json_encode(['error' => 'Not found']);
