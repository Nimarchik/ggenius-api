<?php

require_once __DIR__ . '/.env';

$dsn = sprintf(
  'pgsql:host=%s;port=%s;dbname=%s',
  $_ENV['HOST_DB_GG'],
  $_ENV['PORT_DB_GG'],
  $_ENV['USER_DB_GG']
);

try {
  $pdo = new PDO(
    $dsn,
    $_ENV['USER_DB_GG'],
    $_ENV['PASS_DB_GG'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'error' => 'Database connection failed'
  ]);
  exit;
}
