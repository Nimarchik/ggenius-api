<?php
require "db.php";

header("Access-Control-Allow-Origin: ggenius.gg,
                                     localhost:5173");
header("Content-Type: application/json");

$stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
