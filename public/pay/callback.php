<?php
header('Content-Type: application/json; charset=utf-8');

// --- Настройки через окружение (без хардкода) ---
$databaseUrl = getenv('DATABASE_URL');
$partnerId   = getenv('MOOGOLD_API');
$secretKey   = getenv('MOOGOLD_SECRET_KEY');
$moogoldApiBase = 'https://moogold.com/wp-json/v1/api/';
$telegramToken = getenv('BOT_TOKEN');
$allowDowngrade = getenv('ALLOW_DOWNGRADE') === '1' ? true : false;
// --- /Настройки ---

// Логи
// $logDir = __DIR__ . '/logs';
// if (!is_dir($logDir)) mkdir($logDir, 0755, true);
// $mainLog = $logDir . '/callback.log';
// $tgLog   = $logDir . '/telegram.log';

// function //logMain($msg)
// {
//   global $mainLog;
//   file_put_contents($mainLog, date('c') . " " . $msg . PHP_EOL, FILE_APPEND);
// }
// function logTg($msg)
// {
//   global $tgLog;
//   file_put_contents($tgLog, date('c') . " " . $msg . PHP_EOL, FILE_APPEND);
// }

// Вспомогательные функции
function escapeHtml($text)
{
  return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirectJson($arr, $code = 200)
{
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

// Парсим DATABASE_URL
if (!$databaseUrl) {
  //logMain("DATABASE_URL not set");
  redirectJson(['error' => 'Server misconfiguration'], 500);
}
$db = parse_url($databaseUrl);
if (!$db || !isset($db['host'])) {
  //logMain("Invalid DATABASE_URL: " . $databaseUrl);
  redirectJson(['error' => 'Server misconfiguration'], 500);
}
$dbHost = $db['host'];
$dbPort = $db['port'] ?? 5432;
$dbName = isset($db['path']) ? ltrim($db['path'], '/') : '';
$dbUser = $db['user'] ?? null;
$dbPass = $db['pass'] ?? null;
$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";

try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
  //logMain("DB connect failed: " . $e->getMessage());
  redirectJson(['error' => 'DB connection failed'], 500);
}

try {
  // Читаем тело запроса
  $raw = file_get_contents('php://input');
  //logMain("RAW: " . $raw);
  if (empty($raw)) {
    // Некоторые провайдеры могут присылать form-data; пробуем $_POST
    $data = $_POST ?: [];
  } else {
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      // fallback: parse_str если это form-urlencoded
      parse_str($raw, $parsed);
      $data = $parsed ?: $_POST ?: [];
    }
  }

  //logMain("Parsed data: " . json_encode($data));

  $status    = $data['status'] ?? null;
  $invoiceId = $data['invoiceId'] ?? $data['invoice_id'] ?? null;
  $reference = $data['reference'] ?? $data['merchantPaymInfo']['reference'] ?? null;

  if (!$status && !$invoiceId && !$reference) {
    //logMain("Missing identifiers: status/invoiceId/reference");
    redirectJson(['error' => 'Missing identifiers'], 400);
  }

  // 1) Найти заказ: сначала по invoiceId, затем по reference
  $order = null;
  if ($invoiceId) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE mono_invoice_id = :invoice_id LIMIT 1");
    $stmt->execute(['invoice_id' => $invoiceId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    //logMain("Lookup by invoiceId={$invoiceId} result=" . json_encode($order));
  }

  if (!$order && $reference) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :reference LIMIT 1");
    $stmt->execute(['reference' => $reference]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    //logMain("Lookup by reference={$reference} result=" . json_encode($order));
    if ($order && $invoiceId && empty($order['mono_invoice_id'])) {
      // привязываем пришедший invoiceId к заказу
      $u = $pdo->prepare("UPDATE orders SET mono_invoice_id = :invoice_id, updated_at = now() WHERE id = :id");
      $u->execute(['invoice_id' => $invoiceId, 'id' => $order['id']]);
      //logMain("Bound invoiceId {$invoiceId} to order {$order['id']}");
      $order['mono_invoice_id'] = $invoiceId;
    }
  }

  if (!$order) {
    //logMain("Order not found. invoiceId={$invoiceId} reference={$reference}");
    redirectJson(['error' => 'Order not found'], 404);
  }

  // Нормализация входного статуса
  $s = trim(strtolower((string)$status));
  if (in_array($s, ['success', 'paid', 'completed', 'ok'])) {
    $paymentStatus = 'PAID';
  } elseif (in_array($s, ['fail', 'failed', 'failure', 'cancelled', 'canceled', 'cancel', 'rejected', 'expired'])) {
    $paymentStatus = 'FAILED';
  } else {
    $paymentStatus = 'PROCESSING';
  }

  // Подготовка сообщения и оригинала
  $txMessage = $data['failureReason'] ?? $data['errDescription'] ?? ($data['errCode'] ?? null);
  $origStatus = strtoupper((string)$status);

  // Вставляем транзакцию — только одна запись, с унифицированным статусом и оригиналом в message
  try {
    $stmt = $pdo->prepare(
      "INSERT INTO transactions (order_id, provider_tx_id, status, message, created_at)
       VALUES (:order_id, :provider_tx_id, :status, :message, now())"
    );
    $stmt->execute([
      'order_id' => $order['id'],
      'provider_tx_id' => $invoiceId ?? $reference,
      'status' => $paymentStatus,
      'message' => trim(($txMessage ? $txMessage . ' ' : '') . '(orig_status=' . $origStatus . ')')
    ]);
    //logMain("Inserted transaction for order {$order['id']} tx_status={$paymentStatus} affected_rows=" . $stmt->rowCount());
  } catch (PDOException $e) {
    //logMain("Transaction insert failed: " . $e->getMessage());
    // продолжаем — не фатально
  }

  // Обновление orders — записываем только унифицированный статус, с защитой от даунгрейда
  $finalStatus = $order['status'] ?? 'PROCESSING';
  try {
    $currentStatus = $order['status'] ?? '';
    // Если уже PAID или FULFILLED, не понижаем в FAILED (по умолчанию)
    if ($currentStatus === 'PAID' && $paymentStatus === 'FAILED' && !$allowDowngrade) {
      //logMain("Skipping downgrade PAID -> FAILED for order {$order['id']}");
      $finalStatus = $currentStatus;
    } else {
      $updateStmt = $pdo->prepare(
        "UPDATE orders SET status = :status, failure_reason = :failure_reason, updated_at = now() WHERE id = :id"
      );
      $updateStmt->execute([
        'status' => $paymentStatus,
        'failure_reason' => $txMessage,
        'id' => $order['id']
      ]);
      $affected = $updateStmt->rowCount();
      //logMain("Order {$order['id']} paymentStatus={$paymentStatus} updated affected_rows={$affected}");
      // Обновим локальную копию $order для дальнейшей логики
      $order['status'] = $paymentStatus;
      $finalStatus = $paymentStatus;
    }
  } catch (PDOException $e) {
    //logMain("Order update failed for {$order['id']}: " . $e->getMessage());
    $finalStatus = $order['status'] ?? $finalStatus;
  }

  // Если получили PAID — пробуем создать/проверить заказ в MooGold (если настроено)
  if ($finalStatus === 'PAID' && !empty($partnerId) && !empty($secretKey)) {
    // Подготовка payload для MooGold (пример, адаптируйте под реальный API)
    $payloadData = [
      "path" => "order/create_order",
      "data" => [
        "category"   => 50,
        "product-id" => $order['moogold_variation_id'] ?? null,
        "quantity"   => 1,
        "User ID"    => $order['mlbb_user_id'] ?? null,
        "Server ID"  => $order['mlbb_zone_id'] ?? null
      ]
    ];
    $payload_json = json_encode($payloadData, JSON_UNESCAPED_SLASHES);
    $timestamp = time();
    $path = "order/create_order";
    $stringToSign = $payload_json . $timestamp . $path;
    $auth = hash_hmac('SHA256', $stringToSign, $secretKey);
    $auth_basic = base64_encode($partnerId . ":" . $secretKey);

    $ch = curl_init($moogoldApiBase . 'order/create_order');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload_json,
      CURLOPT_HTTPHEADER => [
        'timestamp: ' . $timestamp,
        'auth: ' . $auth,
        'Authorization: Basic ' . $auth_basic,
        'Content-Type: application/json'
      ],
      CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    //logMain("MooGold create_order http={$httpCode} err={$curlErr} resp=" . substr($response ?? '', 0, 2000));
    $result = json_decode($response, true);
    $moogoldOrderId = $result['order_id'] ?? null;

    if ($moogoldOrderId) {
      try {
        $pdo->prepare("UPDATE orders SET moogold_order_id = :moogold_order_id, updated_at = now() WHERE id = :id")
          ->execute(['moogold_order_id' => $moogoldOrderId, 'id' => $order['id']]);
        //logMain("Saved moogold_order_id {$moogoldOrderId} for order {$order['id']}");
        $order['moogold_order_id'] = $moogoldOrderId;
      } catch (PDOException $e) {
        //logMain("Failed to save moogold_order_id: " . $e->getMessage());
      }
    }

    // Проверяем историю MooGold чтобы определить финальный статус
    $payloadHistory = json_encode([
      "path"       => "order/transaction_history",
      "start_date" => date("Y-m-d", strtotime("-1 day")),
      "end_date"   => date("Y-m-d"),
      "page"       => 1,
      "limit"      => 20
    ], JSON_UNESCAPED_SLASHES);
    $timestampHistory = time();
    $pathHistory = "order/transaction_history";
    $stringToSignHist = $payloadHistory . $timestampHistory . $pathHistory;
    $authHistory = hash_hmac('SHA256', $stringToSignHist, $secretKey);
    $authBasicHistory = base64_encode($partnerId . ":" . $secretKey);

    $chHist = curl_init($moogoldApiBase . 'order/transaction_history');
    curl_setopt_array($chHist, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payloadHistory,
      CURLOPT_HTTPHEADER => [
        'timestamp: ' . $timestampHistory,
        'auth: ' . $authHistory,
        'Authorization: Basic ' . $authBasicHistory,
        'Content-Type: application/json'
      ],
      CURLOPT_TIMEOUT => 15
    ]);
    $historyResponse = curl_exec($chHist);
    $curlErrHist = curl_error($chHist);
    $httpCodeHist = curl_getinfo($chHist, CURLINFO_HTTP_CODE);
    curl_close($chHist);

    //logMain("MooGold history http={$httpCodeHist} err={$curlErrHist} resp=" . substr($historyResponse ?? '', 0, 2000));
    $history = json_decode($historyResponse, true);

    $moogoldFinal = 'PAID';
    if ($moogoldOrderId && isset($history['orders']) && is_array($history['orders'])) {
      foreach ($history['orders'] as $moogoldOrder) {
        if (isset($moogoldOrder['order_id']) && $moogoldOrder['order_id'] == $moogoldOrderId) {
          $st = strtolower($moogoldOrder['status'] ?? '');
          if ($st === 'completed') $moogoldFinal = 'FULFILLED';
          elseif ($st === 'failed') $moogoldFinal = 'ERROR';
          elseif ($st === 'processing') $moogoldFinal = 'PAID';
          break;
        }
      }
    } else {
      //logMain("MooGold order {$moogoldOrderId} not found in history response; leaving status PAID and scheduling recheck");
    }

    // Обновляем статус только если он улучшает состояние (PAID -> FULFILLED) или если текущий не PAID/FULFILLED
    if (($order['status'] ?? '') !== $moogoldFinal) {
      // Защита: не понижаем FULFILLED -> ERROR автоматически
      $current = $order['status'] ?? '';
      $shouldUpdate = true;
      if ($current === 'FULFILLED' && $moogoldFinal === 'ERROR') $shouldUpdate = false;
      if ($shouldUpdate) {
        try {
          $pdo->prepare("UPDATE orders SET status = :status, updated_at = now() WHERE id = :id")
            ->execute(['status' => $moogoldFinal, 'id' => $order['id']]);
          //logMain("Order {$order['id']} status updated to {$moogoldFinal} by MooGold");
          $finalStatus = $moogoldFinal;
        } catch (PDOException $e) {
          //logMain("Failed to update order status from MooGold for {$order['id']}: " . $e->getMessage());
        }
      } else {
        //logMain("Skipping downgrade from {$current} to {$moogoldFinal} for order {$order['id']}");
      }
    } else {
      //logMain("Order {$order['id']} status remains {$moogoldFinal}");
      $finalStatus = $moogoldFinal;
    }
  }

  // Подготовка уведомления для Telegram (если есть tg_user_id)
  $tgUserId = $order['tg_user_id'] ?? null;

  // Попытка получить читаемое название товара
  $productName = null;
  try {
    $variation = $order['moogold_variation_id'] ?? null;
    $itemId = $order['item_id'] ?? null;

    if ($variation) {
      $q = $pdo->prepare("SELECT name FROM shop_packages WHERE moogold_variation_id = :var LIMIT 1");
      $q->execute(['var' => $variation]);
      $p = $q->fetch(PDO::FETCH_ASSOC);
      if ($p && !empty($p['name'])) $productName = $p['name'];
    }

    if (!$productName && $itemId) {
      $q = $pdo->prepare("SELECT name FROM shop_packages WHERE item_id = :item LIMIT 1");
      $q->execute(['item' => $itemId]);
      $p = $q->fetch(PDO::FETCH_ASSOC);
      if ($p && !empty($p['name'])) $productName = $p['name'];
    }
  } catch (Exception $e) {
    //logMain("Product lookup failed: " . $e->getMessage());
  }
  if (!$productName) $productName = ($order['item_id'] ?? 'Товар');

  // Формируем сообщение
  $prodEsc  = escapeHtml($productName);
  $orderEsc = escapeHtml($order['id']);
  $amount   = isset($order['amount']) ? escapeHtml($order['amount']) : '';
  $statusEsc = escapeHtml($finalStatus);

  $message  = "<b>✅ Ваше замовлення оновлено</b>\n";
  $message .= "<b>Товар:</b> <i>{$prodEsc}</i>\n";
  if ($amount !== '') $message .= "<b>Ціна:</b> <code>{$amount} UAH</code>\n";
  $message .= "<b>Статус:</b> <b>{$statusEsc}</b>\n";
  $message .= "<b>Номер замовлення:</b> <code>{$orderEsc}</code>\n";
  $message .= "Дякую за покупку! 💎";

  // Inline keyboard
  $replyMarkup = [
    'inline_keyboard' => [
      [
        ['text' => 'Підтримка', 'url' => 'https://t.me/ggenius_support']
      ]
    ]
  ];

  if (!empty($tgUserId) && !empty($telegramToken)) {
    $tgResp = sendTelegramNotification($tgUserId, $message, $telegramToken, $replyMarkup);
    // logTg("CHAT:{$tgUserId} RESP: " . json_encode($tgResp));
  } else {
    // logTg("No tg_user_id or telegram token for order {$order['id']}");
  }

  // Ответ провайдеру
  redirectJson(['ok' => true, 'order' => $order['id'], 'status' => $finalStatus], 200);
} catch (Exception $e) {
  //logMain("Exception: " . $e->getMessage());
  redirectJson(['error' => 'Server error', 'details' => $e->getMessage()], 500);
  exit;
}

// --- Функция отправки уведомлений в Telegram с логированием ---
function sendTelegramNotification($chatId, $message, $token, $replyMarkup = null)
{
  $url = "https://api.telegram.org/bot{$token}/sendMessage";
  $payload = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML'];

  if ($replyMarkup !== null) {
    $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 10
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $resp = ['http' => $http, 'err' => $err, 'body' => $res];
  // logTg("sendMessage chat_id={$chatId} http={$http} err={$err} resp=" . substr($res ?? '', 0, 2000));
  return $resp;
}
