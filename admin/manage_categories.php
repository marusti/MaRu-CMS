<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

$pageHasFilter = true;
$pageHasDialog = true;

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_categories');
$categoriesFile = __DIR__ . '/../content/categories.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];

$messages = [];

// Rekursive Funktion zum Löschen einer Kategorie
function deleteCategoryById(&$categories, $id) {
    foreach ($categories as $index => &$cat) {
        if ($cat['id'] === $id) {
            unset($categories[$index]);
            return true;
        }
        if (!empty($cat['children']) && deleteCategoryById($cat['children'], $id)) {
            return true;
        }
    }
    return false;
}

// Kategorie löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $id = $_POST['delete_category'];

if (deleteCategoryById($categories, $id)) {
    file_put_contents($categoriesFile, json_encode(array_values($categories), JSON_PRETTY_PRINT), LOCK_EX);
    addMessage(
$messages,
sprintf(
 __('category_deleted_successfully'),
$id ?? '' 
 ),
        'success'
    );
}

// Erfolg / Fehler über GET-Parameter
$categoryName = $_GET['category_name'] ?? null;

if (isset($_GET['success']) && $_GET['success'] === 'category') {
    addMessage(
        $messages,
        sprintf(
            __('category_created_successfully'), 
            $categoryName ?? ''  
        ),
        'success'
    );
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'category':
            addMessage($messages, __('category_creation_failed'), 'error');
            break;
        case 'category_exists':
            addMessage(
                $messages,
                sprintf(
                    $categoryName ? __('category_name_exists') : __('category_name_exists_generic'),
                    $categoryName ?? ''
                ),
                'error'
            );
            break;
    }
}
}

ob_start();
?>

<div id="content-manage">

    <h1><?= __('manage_categories') ?></h1>
    
    <label for="filter"><?= __('search_cat') ?>:</label>
    <input id="filter" class="admin-search" type="search"
           placeholder="<?= __('search_cat_placeholder') ?>">

    <form method="post" action="save_category.php" id="category-form" class="category-form">
        <label for="name"><?= __('name') ?></label>
        <input type="text" id="name" name="name" required oninput="generateId()">

        <label for="parent_id"><?= __('parent_category') ?></label>
        <select id="parent_id" name="parent_id">
            <option value=""><?= __('none') ?></option>
            <?php
            function renderParentOptions($categories, $prefix = '') {
                foreach ($categories as $cat):
            ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>"><?= $prefix . htmlspecialchars($cat['name']) ?></option>
                <?php if (!empty($cat['children'])): ?>
                    <?php renderParentOptions($cat['children'], $prefix . '--'); ?>
                <?php endif; ?>
            <?php
                endforeach;
            }
            renderParentOptions($categories);
            ?>
        </select>

        <input type="hidden" id="id" name="id" readonly required>

        <button type="submit"><?= __('add_category') ?></button>
    </form>

    <h2><?= __('existing_categories') ?></h2>

    <?php
    function renderCategories($categories) {
        foreach ($categories as $cat):
    ?>
        <div class="maru-card category-card entry-block">
            <div class="category-header">
                <span id="cat-<?= htmlspecialchars($cat['id']) ?>" class="entry-name cat-name">
                    <?= htmlspecialchars($cat['name']) ?>
                </span>
                <div class="page-actions">
                    <a href="edit_category.php?id=<?= urlencode($cat['id']) ?>"
                       aria-label="<?= __('edit_category') ?>" title="<?= __('edit_category') ?>">
                        <?= getIcon('edit') ?>
                    </a>
                    <button class="maru-delete js-delete" aria-label="<?= __('delete_category') ?>"
                            data-form="deleteCategoryForm" data-input="deleteCategoryInput"
                            data-value="<?= htmlspecialchars($cat['id']) ?>"
                            data-message="<?= htmlspecialchars(__('delete_confirm_category')) ?>">
                        <?= getIcon('delete') ?>
                    </button>
                </div>
            </div>

            <?php if (!empty($cat['children'])): ?>
                <div class="sub-categories" style="margin-left:20px;">
                    <?php renderCategories($cat['children']); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
        endforeach;
    }

    renderCategories($categories);
    ?>

    <form method="post" id="deleteCategoryForm" hidden>
        <input type="hidden" name="delete_category" id="deleteCategoryInput">
    </form>
</div>

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
document.getElementById('name').addEventListener('input', generateId);
</script>

<?php
$content = ob_get_clean();
include '_layout.php';