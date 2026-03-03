<?php

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_content');

$categoriesFile = __DIR__ . '/../content/categories.json';
$pagesDir       = __DIR__ . '/../content/pages';

$categories   = [];
$groupedPages = [];

/**
 * Kategorien laden (sicher)
 */
if (is_file($categoriesFile)) {
    $json = file_get_contents($categoriesFile);
    $data = json_decode($json, true);

    if (is_array($data)) {
        $categories = $data;
    }
}

/**
 * Seiten laden
 */
if (is_dir($pagesDir)) {

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $pagesDir,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {

        if (!$file->isFile() || $file->getExtension() !== 'json') {
            continue;
        }

        $filePath = $file->getPathname();

        // relativer Pfad
        $relativePath = str_replace($pagesDir . DIRECTORY_SEPARATOR, '', $filePath);

        $id = basename($filePath, '.json');

        $directory = dirname($relativePath);
        $directory = ($directory === '.') ? '' : $directory;

        /**
         * JSON laden (safe)
         */
        $json = file_get_contents($filePath);

        if ($json === false) {
            continue;
        }

        $meta = json_decode($json, true);

        if (!is_array($meta)) {
            continue;
        }

        /**
         * Felder validieren
         */
        $category = $meta['category'] ?? $directory;
        $category = is_string($category) ? trim($category) : '';

        if ($category === '') {
            $category = 'uncategorized';
        }

        $page = [

    'id'       => $id,

    'title'    => isset($meta['title']) && is_string($meta['title'])
        ? $meta['title']
        : ucfirst($id),

    'category' => $category,

    'status'   => isset($meta['status']) && is_string($meta['status'])
        ? $meta['status']
        : 'draft',

    'order'    => isset($meta['order'])
        ? (int)$meta['order']
        : 9999,
];

        /**
         * Gruppierung initialisieren
         */
        if (!isset($groupedPages[$category])) {
            $groupedPages[$category] = [];
        }

        $groupedPages[$category][] = $page;


        
    }
}

/**
 * Seiten sortieren
 */
ksort($groupedPages, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($groupedPages as &$pages) {

    usort($pages, function ($a, $b) {

        return $a['order'] <=> $b['order'];

    });

}

unset($pages);


/**
 * Erfolg / Fehler Meldungen
 */
$message     = '';
$messageType = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'page') {
        // Holen des Seiten Titels, wenn er existiert (du könntest auch den ID als Fallback verwenden)
        $pageTitle = $_GET['pageTitle'] ?? 'Unbekannte Seite';
        
        // Nachricht mit dem Titel der Seite
        $message = __('page_created_successfully') . ' ' . htmlspecialchars($pageTitle);
        $messageType = 'success';
    }
} elseif (isset($_GET['error'])) {
    if ($_GET['error'] === 'page') {
        // Holen des Seiten Titels, wenn er existiert (Fallback auf ID oder generischen Titel)
        $pageTitle = $_GET['pageTitle'] ?? 'Unbekannte Seite';
        
        // Nachricht mit dem Titel der Seite
        $message = __('page_creation_failed') . ' ' . htmlspecialchars($pageTitle);
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


<h1><?= htmlspecialchars(__('manage_content')) ?></h1>

<!-- Search filter -->
<label for="page-search"><?= __('search_pages') ?>:</label>
<input type="text" id="page-search" class="admin-search" placeholder="<?= htmlspecialchars(__('search_pages_placeholder')) ?>" />

<!-- Neue Seite -->
<a href="edit_page.php" class="btn-create">
    ➕ <?= htmlspecialchars(__('create_new_page')) ?>
</a>


<h2><?= htmlspecialchars(__('existing_pages')) ?></h2>


<?php foreach ($groupedPages as $categoryId => $pages): ?>

<div class="maru-card page-card">

<?php

$categoryName =
    $categories[$categoryId]['name']
    ?? ($categoryId !== '' ? $categoryId : __('all_categories'));

?>

<h3><?= htmlspecialchars($categoryName) ?></h3>


<?php foreach ($pages as $index => $page): ?>

<div class="page-entry">

<div class="cat-name">

<strong>
<?= htmlspecialchars($page['title']) ?>
</strong>

<small>
(<?= htmlspecialchars($page['id']) ?>)
</small>


<?php if ($page['status'] === 'published'): ?>

<span class="badge badge-success">
<?= htmlspecialchars(__('status_published')) ?>
</span>

<?php else: ?>

<span class="badge badge-warning">
<?= htmlspecialchars(__('status_' . $page['status'])) ?>
</span>

<?php endif; ?>

</div>


<div class="page-actions">


<!-- EDIT -->
<a
href="edit_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($page['category']) ?>"
class="icon-button"
title="<?= htmlspecialchars(__('edit')) ?>"
>
<?= getIcon('edit') ?>
</a>


<!-- DELETE (dialog.js nutzt data-url) -->
<button class="maru-delete delete-page"
data-url="delete_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($page['category']) ?>"
data-title="<?= htmlspecialchars(__('delete_page'), ENT_QUOTES) ?>"
data-message="<?= htmlspecialchars(__('delete_confirm_page'), ENT_QUOTES) ?>"
title="<?= htmlspecialchars(__('delete')) ?>"
aria-label="<?= htmlspecialchars(__('delete')) ?>"
>
<?= getIcon('delete') ?>
</button>


<!-- MOVE UP -->
<?php if ($index > 0): ?>

<a
href="move_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($categoryId) ?>&dir=up"
class="icon-button"
title="<?= htmlspecialchars(__('move_up')) ?>"
>
<?= getIcon('arrow-up') ?>
</a>

<?php else: ?>

<span class="icon-button disabled">
<?= getIcon('arrow-up') ?>
</span>

<?php endif; ?>


<!-- MOVE DOWN -->
<?php if ($index < count($pages) - 1): ?>

<a
href="move_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($categoryId) ?>&dir=down"
class="icon-button"
title="<?= htmlspecialchars(__('move_down')) ?>"
>
<?= getIcon('arrow-down') ?>
</a>

<?php else: ?>

<span class="icon-button disabled">
<?= getIcon('arrow-down') ?>
</span>

<?php endif; ?>


</div>
</div>

<?php endforeach; ?>

</div>

<?php endforeach; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('page-search');
    const pages = document.querySelectorAll('.page-entry');

    searchInput.addEventListener('input', function () {
        const searchTerm = searchInput.value.toLowerCase();
        
        // Loop through all pages and hide those that don't match the search term
        pages.forEach(function (page) {
            const title = page.querySelector('.cat-name strong').textContent.toLowerCase();
            const category = page.querySelector('.cat-name small').textContent.toLowerCase();

            if (title.includes(searchTerm) || category.includes(searchTerm)) {
                page.style.display = '';  // Show matching pages
            } else {
                page.style.display = 'none';  // Hide non-matching pages
            }
        });
    });
});
</script>

<?php

$content = ob_get_clean();

include '_layout.php';

?>
