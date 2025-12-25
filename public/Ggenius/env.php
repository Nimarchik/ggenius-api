<?php

$envPath = __DIR__ . '/.env';

if (!file_exists($envPath)) {
  die('.env not found');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
  if (str_starts_with(trim($line), '#')) continue;

  [$key, $value] = explode('=', $line, 2);

  $key = trim($key);
  $value = trim($value);

  $_ENV[$key] = $value;
  putenv("$key=$value");
}
