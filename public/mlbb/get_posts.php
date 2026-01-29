<?php
require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");

$table = $_GET['table'] ?? 'posts'; // по умолчанию posts

// белый список, чтобы не дать выполнить любой SQL
$allowed = [
  'chat_members',
  'posts',
  'news',
  'arena_stats',
  'arena_defense',
  'arena_battle_log',
  'tournaments',
  'tournament_teams',
  'tournament_team_members',
  'tournament_matches',
  'tournament_registrations',
  'tournament_solo_registrations',
  'tournament_team_join_requests',
  'stream_requests',
  'squads',
  'squad_members',
  'squad_applications',
  'squad_invitations',
  'party_lobbies',
  'party_lobby_members',
  'duel_stats'
];

if (!in_array($table, $allowed)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid table"]);
  exit;
}

$stmt = $pdo->query("SELECT * FROM $table ORDER BY id DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
