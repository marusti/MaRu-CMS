<?php
require_once __DIR__ . '/../lib/helpers.php';
@include __DIR__ . '/_nav.php';

$plugin = $_GET['plugin'] ?? '';
$settingsFile = __DIR__ . "/../plugins/$plugin/settings.json";

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($plugin) ?> – Plugin-Einstellungen</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main>
    <h2>Plugin: <?= htmlspecialchars($plugin) ?> – Einstellungen</h2>

    <?php
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $plugin)) {
        echo "<p class='error'>Ungültiger Plugin-Name.</p>";
    } elseif (!file_exists($settingsFile)) {
        echo "<p class='error'>Keine Einstellungen verfügbar.</p>";
    } else {
        include $settingsFile;
    }
    ?>
</main>
</body>
</html>
