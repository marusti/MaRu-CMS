<?php
session_start();
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$file = __DIR__ . '/../content/categories.json';
$pagesDir = __DIR__ . '/../content/pages';
$categories = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$oldId = $_GET['id'] ?? '';
$category = null;

foreach ($categories as $cat) {
    if ($cat['id'] === $oldId) {
        $category = $cat;
        break;
    }
}

if (!$category) {
    header('Location: content_manager.php?message=' . urlencode(__('category_not_found')));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newName = $_POST['name'] ?? '';

    // Neuen Slug (ID) generieren
    $newId = strtolower($newName);
    $newId = str_replace(['ä','ö','ü','ß'], ['ae','oe','ue','ss'], $newId);
    $newId = preg_replace('/[^a-z0-9]+/', '-', $newId);
    $newId = trim($newId, '-');

    // Prüfung auf Duplikate
    foreach ($categories as $cat) {
        if ($cat['id'] === $newId && $cat['id'] !== $oldId) {
            $error = __('category_exists_error');
            break;
        }
    }

    if (empty($error)) {
        // Kategorie in der Liste aktualisieren
        foreach ($categories as &$cat) {
            if ($cat['id'] === $oldId) {
                $cat['id'] = $newId;
                $cat['name'] = $newName;
                break;
            }
        }

        // Seitenverzeichnis umbenennen
        $oldPath = $pagesDir . '/' . $oldId;
        $newPath = $pagesDir . '/' . $newId;

        if (is_dir($oldPath) && $oldId !== $newId) {
            // $pageCategory in Seiten-Dateien aktualisieren
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($oldPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = file_get_contents($file->getPathname());
                    $updated = preg_replace(
                        "/(\\\$pageCategory\s*=\s*')[^']*(';)/",
                        "$1$newId$2",
                        $content
                    );
                    file_put_contents($file->getPathname(), $updated);
                }
            }

            rename($oldPath, $newPath);
        }

        file_put_contents($file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        header('Location: content_manager.php?message=' . urlencode(__('category_renamed_successfully')));
        exit;
    }
}

ob_start();
?>

<div id="edit-category">
    <h1><?= __('edit_category') ?></h1>

    <?php if ($error): ?>
    <div role="alert" style="color: red; margin-bottom: 1em;">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <label for="name"><?= __('new_category_name') ?></label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? $category['name']) ?>" required>

        <p><small><?= __('slug_will_be_updated') ?>: <code><?= htmlspecialchars($oldId) ?> → <?= htmlspecialchars($newId ?? $oldId) ?></code></small></p>

        <button type="submit"><?= __('save_changes') ?></button>
    </form>

    <p><a href="content_manager.php">⬅ <?= __('back_to_overview') ?></a></p>
</div>

<?php
$content = ob_get_clean();
$pageTitle = __('edit_category');
include '_layout.php';
