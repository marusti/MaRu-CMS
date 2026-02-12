<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php'; 

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_content');
$categoriesFile = __DIR__ . '/../content/categories.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
$pagesDir = __DIR__ . '/../content/pages';
$groupedPages = [];

if (is_dir($pagesDir)) {
    $directoryIterator = new RecursiveDirectoryIterator($pagesDir);
    $iterator = new RecursiveIteratorIterator($directoryIterator);

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'json') { // nur JSON-Dateien
            $filePath = $file->getPathname();
            $relativePath = str_replace($pagesDir . '/', '', $filePath);
            $id = basename($filePath, '.json');
            $category = dirname($relativePath);

            $meta = json_decode(file_get_contents($filePath), true);
            $page = [
                'id' => $id,
                'title' => $meta['title'] ?? ucfirst($id),
                'category' => $meta['category'] ?? $category,
                'status' => $meta['status'] ?? 'draft',
            ];

            $groupedPages[$page['category']][] = $page;
        }
    }
}

// Erfolg-/Fehlermeldungen
$message = '';
$messageType = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'page') {
        $message = __('page_created_successfully');
        $messageType = 'success';
    }
} elseif (isset($_GET['error'])) {
    if ($_GET['error'] === 'page') {
        $message = __('page_creation_failed');
        $messageType = 'error';
    }
}

ob_start();
?>

<div id="content-manage">
<?php if ($message): ?>
    <div class="message <?= htmlspecialchars($messageType) ?>">
        <?= nl2br(htmlspecialchars($message)) ?>
    </div>
<?php endif; ?>

<h1><?= __('manage_content') ?></h1>

<!-- Seiten erstellen -->
<a href="edit_page.php" class="btn-create">➕ <?= __('create_new_page') ?></a>

<h2><?= __('existing_pages') ?></h2>
<?php foreach ($groupedPages as $categoryId => $pages): ?>
<div class="maru-card page-card">
    <?php 
        // Kategorie-Name aus $categories holen, ansonsten aus $categoryId, sonst "All Categories"
        $categoryName = $categories[$categoryId]['name'] ?? ($categoryId ?? 'All Categories');
    ?>
    <h3><?= htmlspecialchars($categoryName) ?></h3>

    <?php foreach ($pages as $page): ?>
    <div class="page-entry">
        <div>
            <strong><?= htmlspecialchars($page['title']) ?></strong>
            <small>(<?= htmlspecialchars($page['id']) ?>)</small>
            <?php if ($page['status'] === 'published'): ?>
                <span class="badge badge-success"><?= htmlspecialchars(__('status_published')) ?></span>
            <?php else: ?>
                <span class="badge badge-warning"><?= htmlspecialchars(__('status_' . $page['status'])) ?></span>
            <?php endif; ?>
        </div>
        <div class="page-actions">
    <!-- Bearbeiten -->
    <a href="edit_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($page['category'] ?? '') ?>" title="<?= __('edit') ?>" class="icon-button">
        <?= getIcon('edit') ?>
    </a>

    <!-- Löschen -->
    <a href="#"
   class="icon-button"
   data-url="delete_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($page['category'] ?? '') ?>"
   data-title="<?= htmlspecialchars(__('delete_page'), ENT_QUOTES, 'UTF-8') ?>"
   data-message="<?= htmlspecialchars(__('delete_page_confirm'), ENT_QUOTES, 'UTF-8') ?>"
   title="<?= __('delete') ?>"
   aria-label="<?= __('delete') ?>">
    <?= getIcon('delete') ?>
</a>


    <!-- Nach oben verschieben -->
<?php if ($page !== reset($pages)): ?>
    <a href="./move_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($categoryId) ?>&dir=up" title="<?= __('move_up') ?>" class="icon-button">
        <?= getIcon('arrow-up') ?>
    </a>
<?php else: ?>
    <span class="icon-button disabled"><?= getIcon('arrow-up') ?></span>
<?php endif; ?>

<!-- Nach unten verschieben -->
<?php if ($page !== end($pages)): ?>
    <a href="./move_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($categoryId) ?>&dir=down" title="<?= __('move_down') ?>" class="icon-button">
        <?= getIcon('arrow-down') ?>
    </a>
<?php else: ?>
    <span class="icon-button disabled"><?= getIcon('arrow-down') ?></span>
<?php endif; ?>

</div>

    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

</div>

<?php
// Dialog einbinden
include 'includes/dialog.php';
?>

<!-- JavaScript für das Modal -->
<script src="assets/js/dialog.js"></script>

<?php
$content = ob_get_clean();
include '_layout.php';
?>
