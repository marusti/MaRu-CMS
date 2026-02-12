<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Parameter auslesen
$id = $_GET['id'] ?? '';
$dir = $_GET['dir'] ?? '';

if (!$id || !in_array($dir, ['up', 'down'])) {
    header('Location: manage_categories.php');
    exit;
}

$categoriesFile = __DIR__ . '/../content/categories.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];

if (!is_array($categories)) $categories = [];

// Kategorie-Index finden
$index = array_search($id, array_column($categories, 'id'));
if ($index === false) {
    header('Location: manage_categories.php');
    exit;
}

// Verschieben
if ($dir === 'up' && $index > 0) {
    $tmp = $categories[$index - 1];
    $categories[$index - 1] = $categories[$index];
    $categories[$index] = $tmp;
} elseif ($dir === 'down' && $index < count($categories) - 1) {
    $tmp = $categories[$index + 1];
    $categories[$index + 1] = $categories[$index];
    $categories[$index] = $tmp;
}

// Speichern
file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

header('Location: manage_categories.php');
exit;

