<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_content');
$categoriesFile = __DIR__ . '/../content/categories.json';
$pagesDir = __DIR__ . '/../content/pages';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];
$groupedPages = [];

// --- Seiten laden (JSON-basiert) ---
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

// --- Pagination Einstellungen ---
$categoriesPerPage = 5;
$pagesPerPage = 10;

$categoryPage = isset($_GET['category_page']) && is_numeric($_GET['category_page']) && $_GET['category_page'] > 0
    ? (int)$_GET['category_page']
    : 1;

$totalCategories = count($categories);
$categoryPagesCount = max(1, ceil($totalCategories / $categoriesPerPage));

$categoriesPageSlice = array_slice(
    $categories,
    ($categoryPage - 1) * $categoriesPerPage,
    $categoriesPerPage
);

$paginatedGroupedPages = [];
$pagesPagesCounts = [];

foreach ($categoriesPageSlice as $cat) {
    $categoryId = $cat['id'];
    $pagesInCategory = $groupedPages[$categoryId] ?? [];
    $totalPages = count($pagesInCategory);
    $pagesPagesCount = max(1, ceil($totalPages / $pagesPerPage));
    $pagesPagesCounts[$categoryId] = $pagesPagesCount;

    $pagesPageParam = 'pages_page_' . $categoryId;
    $pagesPage = isset($_GET[$pagesPageParam]) && is_numeric($_GET[$pagesPageParam]) && $_GET[$pagesPageParam] > 0
        ? (int)$_GET[$pagesPageParam]
        : 1;

    $pagesSlice = array_slice(
        $pagesInCategory,
        ($pagesPage - 1) * $pagesPerPage,
        $pagesPerPage
    );

    $paginatedGroupedPages[$categoryId] = [
        'category' => $cat,
        'pages' => $pagesSlice,
        'pagesPage' => $pagesPage,
    ];
}

// --- Erfolg-/Fehlermeldungen ---
$message = '';
$messageType = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'category') {
        $message = __('category_created_successfully');
        $messageType = 'success';
    } elseif ($_GET['success'] === 'page') {
        $message = __('page_created_successfully');
        $messageType = 'success';
    }
} elseif (isset($_GET['error'])) {
    if ($_GET['error'] === 'category') {
        $message = __('category_creation_failed');
        $messageType = 'error';
    } elseif ($_GET['error'] === 'page') {
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

<h1><?= __('manage_categories') ?></h1>
<form method="post" action="save_category.php" id="category-form" class="category-form">
    <label for="name"><?= __('name') ?></label>
    <input type="text" id="name" name="name" required oninput="generateId()">

    <label for="id"><?= __('id_auto') ?></label>
    <input type="text" id="id" name="id" readonly required>

    <button type="submit"><?= __('add_category') ?></button>
</form>

<h2><?= __('existing_categories') ?></h2>

<?php foreach ($categoriesPageSlice as $cat): 
    $catId = $cat['id'];
    $paginatedData = $paginatedGroupedPages[$catId] ?? null;
    if (!$paginatedData) continue;
?>
<div class="category-card">
    <div class="category-header">
        <h3 id="cat-<?= $catId ?>"><?= htmlspecialchars($cat['name']) ?></h3>
        <div>
            <a href="edit_category.php?id=<?= urlencode($catId) ?>" title="<?= __('edit_category') ?>">✏️</a>
            <a href="#" onclick="confirmModal('<?= __('delete_category') ?>', '<?= __('delete_category_confirm') ?>', 'delete_category.php?id=<?= urlencode($catId) ?>'); return false;">🗑️ <?= __('delete_category') ?></a>
        </div>
    </div>

    <?php foreach ($paginatedData['pages'] as $page): ?>
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
            <a href="edit_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($catId) ?>" title="<?= __('edit') ?>">✏️</a>
            <a href="#" onclick="confirmModal('<?= __('delete_page') ?>', '<?= __('delete_page_confirm') ?>', 'delete_page.php?id=<?= urlencode($page['id']) ?>&category=<?= urlencode($catId) ?>'); return false;"
               class="delete" aria-label="<?= __('delete') ?> <?= htmlspecialchars($page['title']) ?>"> 🗑️ <?= __('delete') ?></a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($pagesPagesCounts[$catId] > 1): ?>
    <nav class="pagination">
        <ul>
            <?php
            $pagesPage = $paginatedData['pagesPage'];
            $pagesPagesCount = $pagesPagesCounts[$catId];
            for ($p = 1; $p <= $pagesPagesCount; $p++):
                $queryParams = $_GET;
                $queryParams['category_page'] = $categoryPage;
                $queryParams['pages_page_' . $catId] = $p;
                $queryStr = http_build_query($queryParams);
            ?>
            <li>
                <a href="?<?= $queryStr ?>" class="<?= $p === $pagesPage ? 'active' : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<nav class="pagination">
    <ul>
        <?php for ($p = 1; $p <= $categoryPagesCount; $p++): 
            $queryParams = $_GET;
            $queryParams['category_page'] = $p;
            $queryStr = http_build_query($queryParams);
        ?>
        <li>
            <a href="?<?= $queryStr ?>" class="<?= $p === $categoryPage ? 'active' : '' ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>

<hr>
<h2><?= __('pages') ?></h2>
<a href="edit_page.php" class="btn-create">➕ <?= __('create_new_page') ?></a>

<!-- Modal für Löschen -->
<dialog id="deleteModal" aria-labelledby="modalTitle" aria-describedby="modalMessage">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle"><?= __('confirm') ?></h3>
            <button type="button" class="modal-close" id="modalClose" aria-label="<?= __('cancel') ?>">×</button>
        </div>
        <div class="modal-body">
            <p id="modalMessage"><?= __('delete_confirm_generic') ?></p>
        </div>
        <div class="modal-buttons">
            <button class="btn-cancel" id="modalCancel"><?= __('cancel') ?></button>
            <button class="btn-confirm" id="modalConfirm"><?= __('delete') ?></button>
        </div>
    </div>
</dialog>

</div> <!-- #content-manage -->

<script>
function generateId() {
    const nameInput = document.getElementById('name');
    const idInput = document.getElementById('id');
    const name = nameInput.value.toLowerCase()
        .replace(/ä/g, 'ae')
        .replace(/ö/g, 'oe')
        .replace(/ü/g, 'ue')
        .replace(/ß/g, 'ss')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    idInput.value = name;
}

// Modal Funktionen
let deleteUrl = null;
let lastFocusedEl = null;
function openModal(){ const overlay = document.getElementById('deleteModal'); overlay.showModal(); overlay.focus(); }
function closeModal(){ const overlay = document.getElementById('deleteModal'); overlay.close(); deleteUrl=null; if(lastFocusedEl) lastFocusedEl.focus(); }
function confirmModal(title,message,url){ lastFocusedEl=document.activeElement; document.getElementById('modalTitle').textContent=title||'<?= __('confirm') ?>'; document.getElementById('modalMessage').textContent=message||'<?= __('delete_confirm_generic') ?>'; deleteUrl=url; openModal(); document.getElementById('modalConfirm').focus(); }
document.getElementById('modalCancel').addEventListener('click', closeModal);
document.getElementById('modalClose').addEventListener('click', closeModal);
document.getElementById('deleteModal').addEventListener('click',(e)=>{if(e.target===e.currentTarget) closeModal();});
document.getElementById('modalConfirm').addEventListener('click', ()=>{if(deleteUrl) window.location.href=deleteUrl;});
</script>

<?php
$content = ob_get_clean();
include '_layout.php';

