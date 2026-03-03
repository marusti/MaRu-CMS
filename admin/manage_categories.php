<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php'; 

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_categories');
$categoriesFile = __DIR__ . '/../content/categories.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];

$message = '';
$messageType = '';

// Kategorie löschen, wenn ID gesetzt ist
if (isset($_GET['id'])) {
    // ID aus der URL holen
    $id = $_GET['id'] ?? '';

    if (!empty($id)) {
        $categoryName = ''; // Standardwert für den Namen

        // Kategorie aus dem Array finden und entfernen
        foreach ($categories as $index => $category) {
            if ($category['id'] === $id) {
                $categoryName = $category['name'];

                // Entferne die Kategorie aus dem Array
                unset($categories[$index]);

                // Die Änderungen in der JSON-Datei speichern
                file_put_contents($categoriesFile, json_encode(array_values($categories), JSON_PRETTY_PRINT));

                break;
            }
        }

        // Erfolgs- oder Fehlermeldung formatieren
        if ($categoryName) {
            $message = sprintf(__('category_deleted_successfully'), $categoryName);
        } else {
            $message = __('category_not_found');
        }
    } else {
        $message = __('category_id_missing');
    }

    $messageType = 'success'; // Standardmäßig auf 'success' setzen
}

$categoryName = isset($_GET['category_name']) ? $_GET['category_name'] : null;

if (isset($_GET['success']) && $_GET['success'] === 'category') {
    if ($categoryName) {
        $message = sprintf(__('category_created_successfully'), $categoryName);  
    } else {
        $message = __('category_created_successfully');
    }
    $messageType = 'success';
} elseif (isset($_GET['error']) && $_GET['error'] === 'category') {
    if ($categoryName) {
        $message = sprintf(__('category_creation_failed'), $categoryName);
    } else {
        $message = __('category_creation_failed');
    }
    $messageType = 'error';
}

ob_start();
?>

<div id="content-manage">
    <!-- Nachricht anzeigen, falls vorhanden -->
    <?php if ($message): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= nl2br(htmlspecialchars($message)) ?>
        </div>
    <?php endif; ?>

    <h1><?= __('manage_categories') ?></h1>

    <!-- Das Formular für das Hinzufügen einer Kategorie bleibt unverändert -->
    <form method="post" action="save_category.php" id="category-form" class="category-form">
        <label for="name"><?= __('name') ?></label>
        <input type="text" id="name" name="name" required oninput="generateId()">

        <input type="hidden" id="id" name="id" readonly required>

        <button type="submit"><?= __('add_category') ?></button>
    </form>

    <h2><?= __('existing_categories') ?></h2>
    <?php foreach ($categories as $index => $cat): ?>
    <div class="maru-card category-card">
        <div class="category-header">
            <span id="cat-<?= $cat['id'] ?>" class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
            <div class="page-actions">
                <a href="edit_category.php?id=<?= urlencode($cat['id']) ?>"
                   aria-label="<?= __('edit_category') ?>"
                   title="<?= __('edit_category') ?>">
                    <?= getIcon('edit') ?>
                </a>

                <button
                   class="maru-delete delete-cat"
                   data-title="<?= htmlspecialchars(__('delete_category')) ?>"
                   data-message="<?= htmlspecialchars(__('delete_confirm_category')) ?>"
                   data-url="manage_categories.php?id=<?= urlencode($cat['id']) ?>"
                   aria-label="<?= __('delete_category') ?>"
                   title="<?= __('delete_category') ?>">
                    <?= getIcon('delete') ?>
                </button>

                <!-- Smart Move-Buttons -->
                <?php if ($index > 0): ?>
                    <a href="move_category.php?id=<?= urlencode($cat['id']) ?>&dir=up"
                       aria-label="<?= __('move_up') ?>"
                       title="<?= __('move_up') ?>">
                        <?= getIcon('arrow-up') ?>
                    </a>
                <?php else: ?>
                    <span class="disabled" aria-hidden="true">
                        <?= getIcon('arrow-up') ?>
                    </span>
                <?php endif; ?>


                <?php if ($index < count($categories) - 1): ?>
                    <a href="move_category.php?id=<?= urlencode($cat['id']) ?>&dir=down"
                       aria-label="<?= __('move_down') ?>"
                       title="<?= __('move_down') ?>">
                        <?= getIcon('arrow-down') ?>
                    </a>
                <?php else: ?>
                    <span class="disabled" aria-hidden="true">
                        <?= getIcon('arrow-down') ?>
                    </span>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<script>
    // ID-Generierung für neue Kategorie
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

    const nameInput = document.getElementById('name');
    nameInput.addEventListener('input', generateId);
</script>

<?php
$content = ob_get_clean();
include '_layout.php';
?>