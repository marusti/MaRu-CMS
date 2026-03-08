<?php

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

// Signal, dass diese Seite Filter braucht
$pageHasFilter = true;
$pageHasDialog = true;

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_content');
$categoriesFile = __DIR__ . '/../content/categories.json';
$pagesDir       = __DIR__ . '/../content/pages';

$categories   = [];
$groupedPages = [];
$messages     = [];

/**
 * Kategorien laden
 */
if (is_file($categoriesFile)) {
    $json = file_get_contents($categoriesFile);
    $data = json_decode($json, true);
    if (is_array($data)) {
        $categories = $data;
    }
}

/**
 * Kategoriebaum erstellen
 */
function buildCategoryTree(array $categories): array {
    $tree = [];
    $lookup = [];

    foreach ($categories as $cat) {
        $cat['children'] = [];
        $lookup[$cat['id']] = $cat;
    }

    foreach ($lookup as $id => $cat) {
        $parentId = $cat['parent_id'] ?? '';
        if ($parentId && isset($lookup[$parentId])) {
            $lookup[$parentId]['children'][] = &$lookup[$id];
        } else {
            $tree[$id] = &$lookup[$id];
        }
    }

    return $tree;
}

$categoryTree = buildCategoryTree($categories);

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
        $relativePath = str_replace($pagesDir . DIRECTORY_SEPARATOR, '', $filePath);
        $id = basename($filePath, '.json');
        $directory = dirname($relativePath);
        $directory = ($directory === '.') ? '' : $directory;

        $json = file_get_contents($filePath);
        if ($json === false) continue;

        $meta = json_decode($json, true);
        if (!is_array($meta)) continue;

        $category = $meta['category'] ?? $directory;
        $category = is_string($category) ? trim($category) : '';
        if ($category === '') $category = 'uncategorized';

        $page = [
            'id'       => $id,
            'title'    => isset($meta['title']) && is_string($meta['title']) ? $meta['title'] : ucfirst($id),
            'category' => $category,
            'status'   => isset($meta['status']) && is_string($meta['status']) ? $meta['status'] : 'draft',
            'order'    => isset($meta['order']) ? (int)$meta['order'] : 9999,
        ];

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
    usort($pages, fn($a, $b) => $a['order'] <=> $b['order']);
}
unset($pages);

/**
 * Seiten löschen
 */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $category = $_GET['category'] ?? '';
    $safeId = basename($id);
    $safeCategory = basename($category);

    $jsonFile = $safeCategory ? "$pagesDir/$safeCategory/$safeId.json" : "$pagesDir/$safeId.json";
    $mdFile   = $safeCategory ? "$pagesDir/$safeCategory/$safeId.md"   : "$pagesDir/$safeId.md";

    $deleted = false;
    if (file_exists($jsonFile)) { unlink($jsonFile); $deleted = true; }
    if (file_exists($mdFile))   { unlink($mdFile);   $deleted = true; }

    if ($deleted) {
        addMessage($messages, sprintf(__('page_deleted_successfully'), htmlspecialchars($safeId)), 'success');
    } else {
        addMessage($messages, sprintf(__('page_not_found'), htmlspecialchars($safeId)), 'error');
    }

    header('Location: content_manager.php');
    exit;
}

/**
 * Rekursive Funktion für HTML-Ausgabe der Kategorien + Seiten
 */
function renderCategoryPagesHtml(array $categoryNode, array $groupedPages, $level = 0): string {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
    $categoryId = $categoryNode['id'];
    $categoryName = $categoryNode['name'];

    $html = '<div class="maru-card page-card">';
    $html .= '<strong>' . $indent . htmlspecialchars($categoryName) . '</strong>';

    if (isset($groupedPages[$categoryId])) {
        foreach ($groupedPages[$categoryId] as $index => $page) {
            $html .= '<div class="page-entry entry-block">';
            $html .= '<div class="cat-name">';
            $html .= '<span class="entry-name">' . htmlspecialchars($page['title']) . '</span>';
            $html .= '<small>(' . htmlspecialchars($page['id']) . ')</small>';
            $html .= ($page['status'] === 'published')
                ? '<span class="badge badge-success">' . htmlspecialchars(__('status_published')) . '</span>'
                : '<span class="badge badge-warning">' . htmlspecialchars(__('status_' . $page['status'])) . '</span>';
            $html .= '</div>';

            $html .= '<div class="page-actions">';
            $html .= '<a href="edit_page.php?id=' . urlencode($page['id']) . '&category=' . urlencode($page['category']) . '" class="icon-button" title="' . htmlspecialchars(__('edit')) . '">'
                . getIcon('edit') . '</a>';

            $html .= '<button class="maru-delete js-delete" aria-label="' . __('delete_page') . '" data-title="' . __('delete_page') . '" data-message="' . htmlspecialchars(__('delete_confirm_page')) . '" data-url="content_manager.php?delete=' . urlencode($page['id']) . '&category=' . urlencode($page['category']) . '" data-value="' . htmlspecialchars($page['id']) . '" data-form="deletePageForm" data-input="deletePageInput">'
                . getIcon('delete') . '</button>';

            if ($index > 0) {
                $html .= '<a href="move_page.php?id=' . urlencode($page['id']) . '&category=' . urlencode($categoryId) . '&dir=up" class="icon-button" title="' . htmlspecialchars(__('move_up')) . '">'
                    . getIcon('arrow-up') . '</a>';
            } else {
                $html .= '<span class="icon-button disabled">' . getIcon('arrow-up') . '</span>';
            }

            if ($index < count($groupedPages[$categoryId]) - 1) {
                $html .= '<a href="move_page.php?id=' . urlencode($page['id']) . '&category=' . urlencode($categoryId) . '&dir=down" class="icon-button" title="' . htmlspecialchars(__('move_down')) . '">'
                    . getIcon('arrow-down') . '</a>';
            } else {
                $html .= '<span class="icon-button disabled">' . getIcon('arrow-down') . '</span>';
            }

            $html .= '</div>'; // page-actions
            $html .= '</div>'; // page-entry
        }
    }

    // Rekursiv Sub-Kategorien rendern
    foreach ($categoryNode['children'] as $child) {
        $html .= renderCategoryPagesHtml($child, $groupedPages, $level + 1);
    }

    $html .= '</div>'; // page-card
    return $html;
}

// Gesamtes HTML für alle Kategorien generieren
$allCategoriesHtml = '';
foreach ($categoryTree as $catNode) {
    $allCategoriesHtml .= renderCategoryPagesHtml($catNode, $groupedPages);
}

ob_start();
?>

<div id="content-manage">
    <h1><?= htmlspecialchars(__('manage_content')) ?></h1>
    <label for="filter"><?= __('search_pages') ?>:</label>
    <input type="search" id="filter" class="admin-search" placeholder="<?= htmlspecialchars(__('search_pages_placeholder')) ?>">
    <a href="edit_page.php" class="btn-create">➕ <?= htmlspecialchars(__('create_new_page')) ?></a>

    <h2><?= htmlspecialchars(__('existing_pages')) ?></h2>
    <?= $allCategoriesHtml ?>

    <form method="post" id="deletePageForm" hidden>
        <input type="hidden" name="delete_page" id="deletePageInput">
    </form>
</div>

<?php
$content = ob_get_clean();
include '_layout.php';
?>