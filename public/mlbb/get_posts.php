<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");

$table = $_GET['table'] ?? 'posts'; // по умолчанию posts

// белый список, чтобы не дать выполнить любой SQL
$allowed = ['chat_members', 'posts', 'news'];

if (!in_array($table, $allowed)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid table"]);
  exit;
}

$stmt = $pdo->query("SELECT * FROM $table ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
