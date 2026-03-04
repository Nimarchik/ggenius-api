<?php
// db.php — подключение к PostgreSQL через DATABASE_URL
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://ggenius.gg");
function getPDO(): PDO
{
  $dbUrl = getenv("DATABASE_URL"); //  getenv("DATABASE_URL")
  if (!$dbUrl) {
    throw new Exception("DATABASE_URL not set in environment");
  }

  $parts = parse_url($dbUrl);
  if ($parts === false) {
    throw new Exception("Invalid DATABASE_URL format");
  }

  $host   = $parts['host'] ?? null;
  $port   = $parts['port'] ?? 5432;
  $user   = $parts['user'] ?? null;
  $pass   = $parts['pass'] ?? null;
  $dbname = ltrim($parts['path'] ?? '', '/');

  // DSN для PostgreSQL
  $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}
