<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: https://ggenius.gg/");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Подключение к базе
$dbUrl = getenv("DATABASE_URL"); // getenv('DATABASE_URL')
$dbopts = parse_url($dbUrl);
$conn = pg_connect(
  "host={$dbopts['host']} port={$dbopts['port']} dbname=" . ltrim($dbopts['path'], '/') .
    " user={$dbopts['user']} password={$dbopts['pass']} sslmode=require"
);

if (!$conn) {
  http_response_code(500);
  echo json_encode(['error' => 'Не удалось подключиться к базе']);
  exit;
}

// Получаем tg_user_id
$tg_user_id = $_GET['tg_user_id'] ?? null;
if (!$tg_user_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Не указан tg_user_id']);
  exit;
}

// Если пришёл POST — обновляем статус
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents("php://input"), true);
  $orderId = $input['order_id'] ?? null;
  $newStatus = $input['status'] ?? null;

  if (!$orderId || !$newStatus) {
    http_response_code(400);
    echo json_encode(['error' => 'Не хватает данных для обновления']);
    exit;
  }

  $update = pg_query_params(
    $conn,
    "UPDATE orders SET status = $1 WHERE id = $2 AND tg_user_id = $3 RETURNING *",
    [$newStatus, $orderId, $tg_user_id]
  );

  if (!$update) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка обновления']);
    exit;
  }

  $updatedRow = pg_fetch_assoc($update);
  echo json_encode(['updated' => $updatedRow], JSON_UNESCAPED_UNICODE);
  exit;
}

// Если GET — достаём транзакции
$res = pg_query_params(
  $conn,
  "SELECT * FROM orders WHERE tg_user_id = $1 ORDER BY created_at DESC",
  [$tg_user_id]
);

if (!$res) {
  http_response_code(500);
  echo json_encode(['error' => 'Ошибка запроса']);
  exit;
}

$rows = pg_fetch_all($res);
echo json_encode(['transactions' => $rows ?: []], JSON_UNESCAPED_UNICODE);
exit;
