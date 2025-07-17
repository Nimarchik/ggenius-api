<?php
// admin.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// *** Авторизация ***
$adminPasswordHash = $_ENV['ADMIN_PASSWORD_HASH'];

if (isset($_POST['login'])) {
  if ($_POST['password'] === $adminPasswordHash) {
    $_SESSION['admin_logged_in'] = true;
    header("Location: admin.php");
    exit;
  } else {
    $login_error = "Невірний пароль!";
  }
}

if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: admin.php");
  exit;
}

if (!isset($_SESSION['admin_logged_in'])) {
?>
  <!DOCTYPE html>
  <html lang="uk">

  <head>
    <meta charset="UTF-8">
    <title>Вхід в адмінку</title>
    <style>
      body {
        font-family: Arial, sans-serif;
        max-width: 400px;
        margin: auto;
        padding: 20px;
        text-align: center;
      }

      input {
        width: 100%;
        padding: 8px;
        margin: 10px 0;
      }
    </style>
  </head>

  <body>
    <h2>Вхід в адмінку</h2>
    <?php if (isset($login_error)): ?>
      <p style="color:red"><?= $login_error ?></p>
    <?php endif; ?>
    <form method="POST">
      <input type="password" name="password" placeholder="Пароль" required>
      <button type="submit" name="login">Увійти</button>
    </form>
  </body>

  </html>
<?php
  exit;
}

use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

$cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'];
$apiKey = $_ENV['CLOUDINARY_API_KEY'];
$apiSecret = $_ENV['CLOUDINARY_API_SECRET'];
// Cloudinary config
Configuration::instance([
  'cloud' => [
    'cloud_name' => $cloudName,
    'api_key'    => $apiKey,
    'api_secret' => $apiSecret,
  ],
  'url' => ['secure' => true]
]);

// DB config
$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'];

try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Помилка підключення до бази: " . $e->getMessage());
}

// Delete post
if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
  header("Location: admin.php");
  exit;
}

// Update post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
  $id = intval($_POST['update_id']);
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $date = $_POST['date'] ?? date('Y-m-d');
  $imageUrl = $_POST['current_image'] ?? '';

  if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $tmpPath = $_FILES['image']['tmp_name'];
    try {
      $upload = (new UploadApi())->upload($tmpPath, [
        'folder' => 'ggenius_blog',
        'overwrite' => true,
        'public_id' => pathinfo($imageUrl, PATHINFO_FILENAME)
      ]);
      $imageUrl = $upload['secure_url'];
    } catch (Exception $e) {
    }
  }

  $stmt = $pdo->prepare("UPDATE blogs SET title=?, content=?, date=?, image=? WHERE id=?");
  $stmt->execute([$title, $content, $date, $imageUrl, $id]);
  header("Location: admin.php");
  exit;
}

// Add new post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['update_id'])) {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $date = $_POST['date'] ?? date('Y-m-d');
  $imageUrl = '';

  if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $tmpPath = $_FILES['image']['tmp_name'];
    try {
      $upload = (new UploadApi())->upload($tmpPath, [
        'folder' => 'ggenius_blog'
      ]);
      $imageUrl = $upload['secure_url'];
    } catch (Exception $e) {
    }
  }

  $stmt = $pdo->prepare("INSERT INTO blogs (title, content, date, image) VALUES (?, ?, ?, ?)");
  $stmt->execute([$title, $content, $date, $imageUrl]);
  header("Location: admin.php");
  exit;
}

// Fetch post for edit
$editPost = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
  $stmt->execute([$id]);
  $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all posts
$stmt = $pdo->query("SELECT * FROM blogs ORDER BY id DESC");
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="uk">

<head>
  <meta charset="UTF-8">
  <title>Адмінка блогу</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      max-width: 800px;
      margin: auto;
      padding: 20px;
    }

    form {
      margin-bottom: 40px;
    }

    label {
      display: block;
      margin-top: 10px;
    }

    input,
    textarea {
      width: 100%;
      padding: 8px;
      box-sizing: border-box;
    }

    img {
      max-width: 150px;
      margin-top: 5px;
    }

    .blog {
      border: 1px solid #ccc;
      padding: 10px;
      margin-bottom: 10px;
    }

    .actions a {
      margin-right: 10px;
      text-decoration: none;
      color: blue;
    }

    .logout {
      float: right;
    }
  </style>
</head>

<body>

  <div class="logout">
    <a href="admin.php?logout=1">🚪 Вийти</a>
  </div>

  <h1><?= $editPost ? "Редагувати статтю" : "Додати статтю" ?></h1>
  <form action="admin.php" method="POST" enctype="multipart/form-data">
    <?php if ($editPost): ?>
      <input type="hidden" name="update_id" value="<?= $editPost['id'] ?>">
      <input type="hidden" name="current_image" value="<?= htmlspecialchars($editPost['image']) ?>">
    <?php endif; ?>

    <label>Заголовок:</label>
    <input type="text" name="title" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>">

    <label>Текст:</label>
    <textarea name="content" rows="6" required><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>

    <label>Дата:</label>
    <input type="date" name="date" required value="<?= htmlspecialchars($editPost['date'] ?? date('Y-m-d')) ?>">

    <label>Картинка:</label>
    <input type="file" name="image" accept="image/*">
    <?php if ($editPost && $editPost['image']): ?>
      <br><img src="<?= htmlspecialchars($editPost['image']) ?>" alt="Картинка">
    <?php endif; ?>

    <button type="submit"><?= $editPost ? "Оновити" : "Зберегти" ?></button>
  </form>

  <h2>Існуючі статті</h2>
  <?php foreach ($blogs as $b): ?>
    <div class="blog">
      <?php if ($b['image']): ?>
        <img src="<?= htmlspecialchars($b['image']) ?>" alt="Картинка">
      <?php endif; ?>
      <h3><?= htmlspecialchars($b['title']) ?></h3>
      <small><?= htmlspecialchars($b['date']) ?></small>
      <p><?= nl2br(htmlspecialchars(mb_substr($b['content'], 0, 200))) ?>...</p>
      <div class="actions">
        <a href="admin.php?edit=<?= $b['id'] ?>">✏️ Редагувати</a>
        <a href="admin.php?delete=<?= $b['id'] ?>" onclick="return confirm('Впевнені, що хочете видалити?')">🗑 Видалити</a>
      </div>
    </div>
  <?php endforeach; ?>

</body>

</html>