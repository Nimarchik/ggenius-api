<?php
header('Content-Type: application/json');
$allowedOrigins = [
  'http://localhost:5173',
  'http://127.0.0.1:5173',
  'https://ggenius.gg',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Метод не дозволений']);
  exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
  echo json_encode(['success' => false, 'message' => 'ID не передано']);
  exit();
}

$heroId = intval($data['id']);
$apiUrl = "https://mapi.mobilelegends.com/hero/detail?id=$heroId";

$heroData = file_get_contents($apiUrl);

if ($heroData === false) {
  echo json_encode(['success' => false, 'message' => 'Не вдалося отримати дані від API']);
  exit();
}

echo $heroData;
