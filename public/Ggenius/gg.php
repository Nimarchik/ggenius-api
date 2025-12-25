<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/index.php';

$stmt = $pdo->query('SELECT NOW()');
$time = $stmt->fetchColumn();

echo json_encode([
  'status' => 'ok',
  'db_time' => $time
]);
