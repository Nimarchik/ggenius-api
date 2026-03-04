<?php
// return.php — обработчик редиректа от Monobank (использует DATABASE_URL из окружения)
date_default_timezone_set('Europe/Kiev');

$databaseUrl = getenv('DATABASE_URL'); // getenv('DATABASE_URL')
$monoApiToken = getenv('MONOBANK_TOKEN'); // опционально, если нужен прямой опрос провайдера

function redirectTo($url) {
  header("Location: $url");
  exit;
}

$invoiceId = $_GET['invoiceId'] ?? null;
$reference = $_GET['reference'] ?? null;

if (!$invoiceId && !$reference) {
  http_response_code(400);
  echo "Missing invoiceId or reference";
  exit;
}

// Парсим DATABASE_URL в DSN для PDO
if (!$databaseUrl) {
  // Если DATABASE_URL не задан — считаем как fail
  redirectTo('https://ggenius.gg/payment-fail');
}
$db = parse_url($databaseUrl);
if (!$db || !isset($db['host'])) {
  redirectTo('https://ggenius.gg/payment-fail');
}
$dbHost = $db['host'];
$dbPort = $db['port'] ?? 5432;
$dbName = isset($db['path']) ? ltrim($db['path'], '/') : '';
$dbUser = $db['user'] ?? null;
$dbPass = $db['pass'] ?? null;
$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";

try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

  // 1. Попробуем найти заказ в БД
  if ($invoiceId) {
    $stmt = $pdo->prepare("SELECT id, status, updated_at FROM orders WHERE mono_invoice_id = :inv LIMIT 1");
    $stmt->execute(['inv' => $invoiceId]);
  } else {
    $stmt = $pdo->prepare("SELECT id, status, updated_at FROM orders WHERE id = :ref LIMIT 1");
    $stmt->execute(['ref' => $reference]);
  }
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  // 2. Если статус однозначный — редиректим
  if ($order && in_array($order['status'], ['PAID','FULFILLED'])) {
    redirectTo('https://ggenius.gg/payment-success');
  } elseif ($order && in_array($order['status'], ['FAILED','ERROR'])) {
    redirectTo('https://ggenius.gg/payment-fail');
  }

  // 3. Если статус PROCESSING или нет записи — опрашиваем Monobank API если доступно
  if ($invoiceId && !empty($monoApiToken)) {
    $ch = curl_init("https://api.monobank.ua/invoice/status/{$invoiceId}");
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$monoApiToken}",
        "Content-Type: application/json"
      ],
      CURLOPT_TIMEOUT => 6
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp && !$err && $httpCode >= 200 && $httpCode < 300) {
      $j = json_decode($resp, true);
      $monoStatus = strtolower($j['status'] ?? '');
      if (in_array($monoStatus, ['paid','success','completed','ok'])) {
        redirectTo('https://ggenius.gg/payment-success');
      } elseif (in_array($monoStatus, ['fail','failed','failure','cancelled','canceled','expired','rejected'])) {
        redirectTo('https://ggenius.gg/payment-fail');
      }
    }
  }

  // 4. Если всё ещё неясно — показываем страницу ожидания с автообновлением
  header('Content-Type: text/html; charset=utf-8');
  $selfUrl = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES | ENT_SUBSTITUTE);
  echo "<!doctype html><html><head><meta charset='utf-8'><title>Платёж обрабатывается</title>";
  echo "<meta name='robots' content='noindex,nofollow'>";
  echo "<style>body{font-family:Arial,Helvetica,sans-serif;padding:24px;color:#222}button{padding:10px 16px;border-radius:6px;border:0;background:#6b46c1;color:#fff;cursor:pointer}</style>";
  echo "</head><body>";
  echo "<h2>Платёж обрабатывается</h2>";
  echo "<p>Проверка статуса... Страница автоматически обновится. Если ничего не изменится, нажмите кнопку.</p>";
  echo "<button id='checkBtn'>Проверить сейчас</button>";
  // JS: периодически перезагружать страницу, чтобы сервер повторно выполнил логику и, при изменении статуса, сделал редирект
  echo "<script>
    (function(){
      const btn = document.getElementById('checkBtn');
      btn.addEventListener('click', ()=> location.reload());
      let tries = 0;
      const maxTries = 20;
      const intervalMs = 3000;
      const doPoll = () => {
        tries++;
        if (tries > maxTries) return;
        // Выполняем fetch текущего URL; если сервер решит редиректить, обычный fetch не выполнит редирект,
        // поэтому после получения ответа просто перезагружаем страницу, чтобы браузер получил возможный Location.
        fetch(window.location.href, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(()=> { location.reload(); })
          .catch(()=> { /* ignore */ });
      };
      setInterval(doPoll, intervalMs);
    })();
  </script>";
  echo "</body></html>";
  exit;

} catch (Exception $e) {
  // При ошибке считаем как fail
  redirectTo('https://ggenius.gg/payment-fail');
}
