<?php
// admin.php
$uploadDir = '/tmp/uploads/';
// Створюємо папку для завантажень, якщо нема
if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

$dataFile = '/tmp/blog.json';

// Завантажуємо існуючі блоги
$blogs = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

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
      // Зберігаємо шлях до файлу для внутрішнього використання
      $imagePath = $filename;
    } else {
      // Помилка при завантаженні файлу
      $imagePath = '';
    }
  }

  $newBlog = [
    'id' => time(),
    'title' => $title,
    'content' => $content,
    'date' => $date,
    'image' => $imagePath
  ];

  $blogs[] = $newBlog;

  // Записуємо назад у JSON
  file_put_contents($dataFile, json_encode($blogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

  header("Location: admin.php");
  exit;
}
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
  <?php foreach (array_reverse($blogs) as $b): ?>
    <div class="blog">
      <?php if ($b['image']): ?>
        <img src="<?= htmlspecialchars($b['image']) ?>" alt="">
      <?php endif; ?>
      <strong><?= htmlspecialchars($b['title']) ?></strong><br>
      <small><?= htmlspecialchars($b['date']) ?></small>
      <p><?= nl2br(htmlspecialchars(mb_substr($b['content'], 0, 200))) ?>...</p>
    </div>
  <?php endforeach; ?>
</body>

</html>