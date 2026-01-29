<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
