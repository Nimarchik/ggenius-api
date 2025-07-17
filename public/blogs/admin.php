<?php
// Конфіг бази
$host = 'dpg-d1sg7cre5dus739m5m90-a';
$db   = 'ggenius';
$user = 'ggenius_user';
$pass = 'lJrMaovTX0QjiECpBXnnZwyNN9URPHpa';
$port = 5432;


try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} catch (PDOException $e) {
  die("Помилка підключення до бази: " . $e->getMessage());
}

// Створюємо таблицю blogs, якщо ще нема
$pdo->exec("CREATE TABLE IF NOT EXISTS blogs (
  id SERIAL PRIMARY KEY,
  title TEXT NOT NULL,
  content TEXT NOT NULL,
  date DATE NOT NULL,
  image TEXT
)");

$uploadDir = '/tmp/uploads/';
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

// Показ зображень через параметр ?image=...
if (isset($_GET['image'])) {
  $imgFile = basename($_GET['image']);
  $path = $uploadDir . $imgFile;
  if (file_exists($path)) {
    header('Content-Type: ' . mime_content_type($path));
    readfile($path);
    exit;
  } else {
    http_response_code(404);
    echo "Зображення не знайдено.";
    exit;
  }
}

// Обробка форми
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $date = $_POST['date'] ?? date('Y-m-d');
  $imagePath = '';

  if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
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

// Отримати всі статті
$blogs = $pdo->query("SELECT * FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="uk">

<head>
  <meta charset="UTF-8">
  <title>Адмінка блогу</title>
  <style>
    body {
      font-family: sans-serif;
      padding: 2rem;
      max-width: 800px;
      margin: auto;
    }

    input,
    textarea {
      width: 100%;
      padding: 8px;
      margin: 8px 0;
    }

    img {
      max-width: 150px;
      display: block;
      margin-bottom: 10px;
    }

    .blog {
      border-bottom: 1px solid #ccc;
      margin-top: 20px;
      padding-bottom: 10px;
    }
  </style>
</head>

<body>
  <h1>Додати статтю</h1>
  <form action="admin.php" method="POST" enctype="multipart/form-data">
    <label>Заголовок:</label>
    <input type="text" name="title" required>

    <label>Текст:</label>
    <textarea name="content" rows="5" required></textarea>

    <label>Дата:</label>
    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>

    <label>Картинка:</label>
    <input type="file" name="image" accept="image/*" required>

    <button type="submit">Зберегти</button>
  </form>

  <hr>
  <h2>Існуючі статті</h2>
  <?php foreach ($blogs as $b): ?>
    <div class="blog">
      <?php if ($b['image']): ?>
        <img src="admin.php?image=<?= urlencode($b['image']) ?>" alt="">
      <?php endif; ?>
      <strong><?= htmlspecialchars($b['title']) ?></strong><br>
      <small><?= htmlspecialchars($b['date']) ?></small>
      <p><?= nl2br(htmlspecialchars(mb_substr($b['content'], 0, 200))) ?>...</p>
    </div>
  <?php endforeach; ?>
</body>

</html>