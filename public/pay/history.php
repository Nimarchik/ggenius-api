<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: https://ggenius.gg/");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
date_default_timezone_set('Europe/Kiev');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
//MooGold Sample Code for Create Order API
$data = file_get_contents("php://input");
parse_str($data, $postData);




//Obtain Product ID from Product Detail API
$partnerId = getenv("MOOGOLD_API"); //  getenv("MOOGOLD_API")
$secretKey = getenv("MOOGOLD_SECRET_KEY"); // getenv("MOOGOLD_SECRET_KEY")
$url = 'order/transaction_history';
$dt = new DateTime('now', new DateTimeZone('Europe/Kiev'));
$statuses = ['processing', 'completed', 'refunded'];
$allOrders = [];

foreach ($statuses as $status) {
  $payloadData = [
    "path" => $url,
    "start_date" => "2026-03-01",
    "end_date" => $dt->format('Y-m-d'),
    "page" => 1,
    "limit" => 100
  ];
}
$payload_json = json_encode($payloadData, JSON_UNESCAPED_SLASHES);

$timestamp = time();
$path = $url;

//Signature Generation (It is same for every API method)
$STRING_TO_SIGN = $payload_json . $timestamp . $path;
$auth = hash_hmac('SHA256', $STRING_TO_SIGN, $secretKey);
$auth_basic =  base64_encode($partnerId . ':' . $secretKey);

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://moogold.com/wp-json/v1/api/' . $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $payload_json,
  CURLOPT_HTTPHEADER => array(
    'timestamp: ' . $timestamp,
    'auth: ' . $auth,
    'Authorization: Basic ' . $auth_basic,
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

if ($err) {
  http_response_code(500);
  echo json_encode(["error" => "Curl error", "details" => $err]);
  exit;
}

echo $response;
