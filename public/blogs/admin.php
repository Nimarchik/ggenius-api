<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$adminPassword = 'Bugaev123'; // ЗАМІНИ на свій надійний пароль

// Обробка виходу
if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: admin.php");
  exit;
}

// Обробка входу
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_password'])) {
  if ($_POST['auth_password'] === $adminPassword) {
    $_SESSION['admin_logged_in'] = true;
    header("Location: admin.php");
    exit;
  } else {
    $error = "❌ Невірний пароль";
  }
}

// Якщо не авторизований
if (!isset($_SESSION['admin_logged_in'])):
?>

  <!DOCTYPE html>
  <html lang="uk">

  <head>
    <meta charset="UTF-8">
    <title>Вхід в адмінку</title>
    <style>
      body {
        font-family: sans-serif;
        padding: 50px;
        max-width: 400px;
        margin: auto;
      }

      form {
        text-align: center;
      }

      input {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
      }

      button {
        padding: 10px 20px;
      }

      .error {
        color: red;
      }
    </style>
  </head>

  <body>
    <h2>🔐 Вхід в адмінку</h2>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
    <form method="POST">
      <input type="password" name="auth_password" placeholder="Пароль" required>
      <button type="submit">Увійти</button>
    </form>
  </body>

  </html>

<?php
  exit;
endif;
