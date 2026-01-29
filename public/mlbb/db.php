<?php
$databaseUrl = getenv("DATABASE_URL");

if (!$databaseUrl) {
  die("DATABASE_URL not set");
}

$url = parse_url($databaseUrl);

$host = $url["host"];
$port = $url["port"];
$user = $url["user"];
$pass = $url["pass"];
$db   = ltrim($url["path"], "/");

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (PDOException $e) {
  die("DB connection failed: " . $e->getMessage());
}
