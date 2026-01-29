<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id']) || !isset($data['title']) || !isset($data['content'])) {
  http_response_code(400);
  echo json_encode(["error" => "Missing id, title or content"]);
  exit;
}

$stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
$stmt->execute([
  ':id' => $data['id'],
  ':title' => $data['title'],
  ':content' => $data['content']
]);

echo json_encode(["success" => true]);
