<?php
$url = parse_url(getenv("DATABASE_URL"));

$host = $url["mlbb_host"];
$port = $url["mlbb_port"];
$user = $url["mlbb_user"];
$pass = $url["mlbb_pass"];
$db   = ltrim($url["path"], "/");

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (PDOException $e) {
  die("DB connection failed");
}
