<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: https://ggenius.gg/");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
try {
  // Получаем данные из формы
  $data = file_get_contents("php://input");
  parse_str($data, $postData);

  $productId = $_POST['product_id'] ?? null;
  $gameId    = $_POST['game_id'] ?? null;
  $server    = $_POST['server'] ?? null;
  $tg_user_id = !empty($_POST['tg_user_id']) ? (int)$_POST['tg_user_id'] : null;

  if (!$productId || !$gameId || !$server) {
    die("Не хватает данных");
  }

  // Подключение к базе
  $url = getenv("DATABASE_URL"); // getenv("DATABASE_URL")
  $db = parse_url($url);
  $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/') .
    ";user={$db['user']};password={$db['pass']}";
  $pdo = new PDO($dsn);

  // Получаем товар
  $stmt = $pdo->prepare("SELECT * FROM shop_packages WHERE moogold_variation_id = :moogold_variation_id  AND is_active = true");
  $stmt->execute(['moogold_variation_id' => $productId]);
  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product) die("Товар не найден");

  // Генерируем UUID для заказа
  $orderUuid = $pdo->query("SELECT gen_random_uuid()")->fetchColumn();

  // Создаём заказ
  $stmt = $pdo->prepare("
  INSERT INTO orders (id, mlbb_user_id, mlbb_zone_id, item_id, moogold_variation_id, tg_user_id, amount, status) 
  VALUES (:id, :mlbb_user_id, :mlbb_zone_id, :item_id, :moogold_variation_id, :tg_user_id, :amount,'CREATED')
");
  $stmt->execute([
    'id'           => $orderUuid,
    'mlbb_user_id' => $gameId,
    'mlbb_zone_id' => $server,
    'item_id'      => $product['item_id'],
    'moogold_variation_id' => $product['moogold_variation_id'],
    'tg_user_id' =>   $tg_user_id,
    'amount'       => $product['price_uah']
  ]);

  $baseReturn = 'https://ggenius-api.onrender.com/pay/return.php'; // замените на ваш реальный URL 
  $redirectUrl = $baseReturn . '?' . http_build_query(['reference' => $orderUuid]);

  // Создаём счёт в Monobank
  $monoToken = getenv("MONOBANK_TOKEN");  // getenv("MONOBANK_TOKEN") 
  $invoiceData = [
    "amount"      => intval($product['price_uah'] * 100), // копейки
    "currency"    => "UAH",
    "merchantPaymInfo" => [
      "reference"   => $orderUuid, // UUID заказа
      "destination" => $product['name'] . "💎"
    ],
    "redirectUrl" =>  $redirectUrl,
    "webHookUrl"  =>  "https://ggenius-api.onrender.com/pay/callback.php"
  ];

  $ch = curl_init("https://api.monobank.ua/api/merchant/invoice/create");
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Token: $monoToken", "Content-Type: application/json"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($invoiceData));
  $response = curl_exec($ch);

  $resp = json_decode($response, true);
  if (!isset($resp['pageUrl'])) {
    http_response_code(400);
    echo json_encode(["error" => "Ошибка Monobank", "details" => $response]);
    exit;
  }

  // Сохраняем invoiceId в заказ
  $stmt = $pdo->prepare("UPDATE orders SET mono_invoice_id = :invoice_id WHERE id = :id");
  $stmt->execute(['invoice_id' => $resp['invoiceId'], 'id' => $orderUuid]);

  // Редиректим пользователя на страницу оплаты Monobank

  echo json_encode(["pageUrl" => $resp['pageUrl'], "invoiceId" => $resp['invoiceId'], "orderId" => $orderUuid]);
  exit;
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["error" => "Server error", "details" => $e->getMessage()]);
  exit;
}
