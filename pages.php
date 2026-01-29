<?php
$categories = json_decode(file_get_contents(__DIR__ . '/content/categories.json'), true);
$pagesDir = __DIR__ . '/content/pages';
$pages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $idToDelete = $_POST['delete'];
    $fileToDelete = "$pagesDir/$idToDelete.json";
    if (file_exists($fileToDelete)) {
        unlink($fileToDelete);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


foreach (glob("$pagesDir/*.json") as $file) {
    $pages[] = json_decode(file_get_contents($file), true);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Seiten</title>
</head>
<body>
    <h1>Seitenübersicht</h1>
    <?php foreach ($categories as $cat): ?>
        <h2><?= htmlspecialchars($cat['name']) ?></h2>
        <ul>
            <?php foreach ($pages as $page): ?>
                <?php if ($page['category'] === $cat['id']): ?>
                    <li>
    <a href="show.php?id=<?= urlencode($page['id']) ?>">
        <?= htmlspecialchars($page['title']) ?>
    </a>
    <form method="post" style="display:inline" onsubmit="return confirm('Wirklich löschen?');">
        <input type="hidden" name="delete" value="<?= htmlspecialchars($page['id']) ?>">
        <button type="submit">🗑️</button>
    </form>
</li>

                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</body>
</html>

