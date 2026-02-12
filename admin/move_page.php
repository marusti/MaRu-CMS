<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pagesDir = __DIR__ . '/../content/pages';
$id = $_GET['id'] ?? null;
$category = $_GET['category'] ?? null;
$dir = $_GET['dir'] ?? null;

if (!$id || !$dir) {
    header('Location: content_manager.php?error=page');
    exit;
}

// Alle Pages der Kategorie einlesen
$categoryDir = $category ? $pagesDir . '/' . $category : $pagesDir;
$files = glob($categoryDir . '/*.json');

// Pages sortieren nach Dateiname (oder nach deiner bestehenden Reihenfolge)
$pages = [];
foreach ($files as $file) {
    $pages[] = basename($file, '.json');
}

// Position der aktuellen Page finden
$index = array_search($id, $pages);
if ($index === false) {
    header('Location: content_manager.php?error=page');
    exit;
}

// Verschieben
if ($dir === 'up' && $index > 0) {
    $tmp = $pages[$index - 1];
    $pages[$index - 1] = $pages[$index];
    $pages[$index] = $tmp;
} elseif ($dir === 'down' && $index < count($pages) - 1) {
    $tmp = $pages[$index + 1];
    $pages[$index + 1] = $pages[$index];
    $pages[$index] = $tmp;
}

// Reihenfolge in den Dateien speichern (Optional: wenn du eine Reihenfolge in JSON speichern willst)
foreach ($pages as $order => $pageId) {
    $filePath = $categoryDir . '/' . $pageId . '.json';
    if (file_exists($filePath)) {
        $meta = json_decode(file_get_contents($filePath), true);
        $meta['order'] = $order;
        file_put_contents($filePath, json_encode($meta, JSON_PRETTY_PRINT));
    }
}

header('Location: content_manager.php?success=page');
exit;

