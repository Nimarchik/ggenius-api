<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
  http_response_code(400);
  echo json_encode(["error" => "Missing id"]);
  exit;
}

$stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
$stmt->execute([':id' => $data['id']]);

echo json_encode(["success" => true]);
