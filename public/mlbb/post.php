<?php
require "db.php";

$allowedOrigins = [
  'https://ggenius.gg',
  'http://localhost:5173',
  'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: *");
  header("Content-Type: application/json");
}


$stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
