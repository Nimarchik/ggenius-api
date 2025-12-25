<?php

$host = getenv('HOST_DB_GG');
$port = getenv('PORT_DB_GG');
$db   = getenv('NAME_DB_GG');
$user = getenv('USER_DB_GG');
$pass = getenv('PASS_DB_GG');

if (!$host || !$db || !$user) {
  http_response_code(500);
  die('ENV variables not found');
}

$dsn = "pgsql:host=$host;port=$port;dbname=$db";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Database connection failed',
    'details' => $e->getMessage()
  ]);
  exit;
}
