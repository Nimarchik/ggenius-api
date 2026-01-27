<?php
$url = parse_url(getenv("mlbb_url"));

$host = $url["host"];
$port = $url["port"];
$user = $url["user"];
$pass = $url["pass"];
$db   = ltrim($url["path"], "/");

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

$pdo = new PDO($dsn, $user, $pass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);
