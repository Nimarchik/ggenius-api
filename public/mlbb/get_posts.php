<?php

require "db.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:5173");

$table = $_GET['table'] ?? 'posts';

$allowed = [
  'chat_members',
  'chat_info',
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
  'duel_stats',
  'users'
];

if (!in_array($table, $allowed)) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid table"]);
  exit;
}

$orderBy = [
  'posts' => 'id',
  'chat_members' => 'id',
  'chat_info' => 'chat_id'
];

$column = $orderBy[$table] ?? null;

$sql = $column
  ? "SELECT * FROM $table ORDER BY $column DESC"
  : "SELECT * FROM $table";

try {
  $stmt = $pdo->query($sql);
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($data ?: []);
} catch (Throwable $e) {
  echo json_encode([
    "error" => true,
    "message" => $e->getMessage()
  ]);
}
