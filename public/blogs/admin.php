<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();


$adminPassword = 'Bugaev123';

$host = 'dpg-d1sg7cre5dus739m5m90-a';
$db   = 'ggenius';
$user = 'ggenius_user';
$pass = 'lJrMaovTX0QjiECpBXnnZwyNN9URPHpa';
$port = 5432;

$uploadDir = sys_get_temp_dir() . '/uploads/';

if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0777, true);
}


if (isset($_GET['logout'])) {
  session_destroy();
  header("Location: admin.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_password'])) {
  if ($_POST['auth_password'] === $adminPassword) {
    $_SESSION['admin_logged_in'] = true;
    header("Location: admin.php");
    exit;
  } else {
    $error = "❌ Невірний пароль";
  }
}

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


try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
  die("Помилка підключення до бази: " . $e->getMessage());
}


if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM blogs WHERE id = ?")->execute([$id]);
  header("Location: admin.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
  $id = intval($_POST['update_id']);
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $date = $_POST['date'] ?? date('Y-m-d');
  $imagePath = $_POST['current_image'] ?? '';

  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
      $imagePath = $filename;
    }
  }

  $stmt = $pdo->prepare("UPDATE blogs SET title=?, content=?, date=?, image=? WHERE id=?");
  $stmt->execute([$title, $content, $date, $imagePath, $id]);
  header("Location: admin.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['update_id'])) {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $date = $_POST['date'] ?? date('Y-m-d');
  $imagePath = '';

  if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
      $imagePath = $filename;
    }
  }

  $stmt = $pdo->prepare("INSERT INTO blogs (title, content, date, image) VALUES (?, ?, ?, ?)");
  $stmt->execute([$title, $content, $date, $imagePath]);
  header("Location: admin.php");
  exit;
}

if (isset($_GET['image'])) {
  $file = $uploadDir . basename($_GET['image']);
  if (file_exists($file)) {
    header("Content-Type: " . mime_content_type($file));
    readfile($file);
    exit;
  }
}

$editPost = null;
if (isset($_GET['edit'])) {
  $id = intval($_GET['edit']);
  $stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = ?");
  $stmt->execute([$id]);
  $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
      padding: 20px;
      max-width: 800px;
      margin: auto;
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
      padding: 5px;
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

    .logout {
      float: right;
    }
  </style>
</head>

<body>

  <a href="?logout=1" class="logout">🚪 Вийти</a>
  <h1><?= $editPost ? "Редагувати статтю" : "Додати статтю" ?></h1>

  <form method="POST" enctype="multipart/form-data">
    <?php if ($editPost): ?>
      <input type="hidden" name="update_id" value="<?= $editPost['id'] ?>">
      <input type="hidden" name="current_image" value="<?= htmlspecialchars($editPost['image']) ?>">
    <?php endif; ?>

    <label>Заголовок:</label>
    <input type="text" name="title" required value="<?= htmlspecialchars($editPost['title'] ?? '') ?>">

    <label>Текст:</label>
    <textarea name="content" rows="5" required><?= htmlspecialchars($editPost['content'] ?? '') ?></textarea>

    <label>Дата:</label>
    <input type="date" name="date" value="<?= htmlspecialchars($editPost['date'] ?? date('Y-m-d')) ?>" required>

    <label>Картинка:</label>
    <input type="file" name="image" accept="image/*">
    <?php if ($editPost && $editPost['image']): ?>
      <br><img src="admin.php?image=<?= urlencode($editPost['image']) ?>" alt="">
    <?php endif; ?>

    <button type="submit"><?= $editPost ? "Оновити" : "Зберегти" ?></button>
  </form>

  <h2>Існуючі статті</h2>
  <?php foreach ($blogs as $b): ?>
    <div class="blog">
      <?php if ($b['image']): ?>
        <img src="admin.php?image=<?= urlencode($b['image']) ?>" alt="">
      <?php endif; ?>
      <strong><?= htmlspecialchars($b['title']) ?></strong><br>
      <small><?= htmlspecialchars($b['date']) ?></small>
      <p><?= nl2br(htmlspecialchars(mb_substr($b['content'], 0, 200))) ?>...</p>
      <a href="admin.php?edit=<?= $b['id'] ?>">✏️ Редагувати</a> |
      <a href="admin.php?delete=<?= $b['id'] ?>" onclick="return confirm('Точно видалити?')">🗑 Видалити</a>
    </div>
  <?php endforeach; ?>

</body>

</html>