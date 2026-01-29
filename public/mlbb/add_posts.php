<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Получаем данные из POST-запроса
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['title']) || !isset($data['content'])) {
  http_response_code(400);
  echo json_encode(["error" => "Missing title or content"]);
  exit;
}

$stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
$stmt->execute([
  ':title' => $data['title'],
  ':content' => $data['content']
]);

echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
