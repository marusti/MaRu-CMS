<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$file = __DIR__ . '/../content/categories.json';
$categories = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$id = trim($_POST['id'] ?? '');
$name = trim($_POST['name'] ?? '');
$parentId = trim($_POST['parent_id'] ?? ''); // NEU: parent_id aus dem Formular

if ($name === '') {
    header('Location: manage_categories.php?error=category');
    exit;
}

if ($id === '') {
    $id = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
}

// 🔹 Prüfen, ob Name schon existiert (rekursiv)
function nameExists($categories, $name) {
    foreach ($categories as $cat) {
        if (mb_strtolower($cat['name']) === mb_strtolower($name)) {
            return true;
        }
        if (!empty($cat['children']) && nameExists($cat['children'], $name)) {
            return true;
        }
    }
    return false;
}

if (nameExists($categories, $name)) {
    header('Location: manage_categories.php?error=category_exists&category_name=' . urlencode($name));
    exit;
}

// ➕ Neue Kategorie-Daten
$newCategory = [
    'id' => $id,
    'name' => $name
];

// 🔹 Sub-Kategorie in parent einfügen, falls parent_id gesetzt
if ($parentId !== '') {
    function addSubcategory(&$categories, $parentId, $newCategory) {
        foreach ($categories as &$cat) {
            if ($cat['id'] === $parentId) {
                if (!isset($cat['children'])) {
                    $cat['children'] = [];
                }
                $cat['children'][] = $newCategory;
                return true;
            }
            if (!empty($cat['children']) && addSubcategory($cat['children'], $parentId, $newCategory)) {
                return true;
            }
        }
        return false;
    }

    addSubcategory($categories, $parentId, $newCategory);
} else {
    // Keine parent_id, in die oberste Ebene
    $categories[] = $newCategory;
}

// 🔒 JSON speichern
$saveSuccess = file_put_contents(
    $file,
    json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

if ($saveSuccess) {
    header('Location: manage_categories.php?success=category&category_name=' . urlencode($name));
} else {
    header('Location: manage_categories.php?error=category');
}
exit;