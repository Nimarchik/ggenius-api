<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");

$stmt = $pdo->query("SELECT * FROM chat_members ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
