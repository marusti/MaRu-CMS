<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/admin/init.php';

$isAdmin = isset($_SESSION['admin']);
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title><?= __('maintenance') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #f7f7f7;
      font-family: Arial, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      margin: 0;
    }
    .box {
      background: white;
      padding: 2em 3em;
      border-radius: 12px;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      text-align: center;
      max-width: 400px;
    }
    h1 {
      color: #d9534f;
    }
    a {
      color: #337ab7;
      text-decoration: none;
    }
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="box">
    <h1><?= __('maintenance_heading') ?></h1>
    <p><?= __('maintenance_text_1') ?><br><?= __('maintenance_text_2') ?></p>

    <?php if ($isAdmin): ?>
      <hr>
      <p><strong><?= __('admin_notice') ?></strong></p>
<p><a href="/admin/dashboard.php"><?= __('admin_dashboard_link') ?></a></p>
    <?php endif; ?>
  </div>
</body>
</html>

