<?php
require_once __DIR__ . '/init.php';

// CSRF-Funktion
function csrf_check($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

header('Content-Type: application/json');

// Nur Admin darf löschen
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

// Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Methode']);
    exit;
}

// CSRF prüfen
$plugin = $_POST['delete_plugin'] ?? '';
$csrf   = $_POST['csrf_token'] ?? '';

if (!$plugin || !csrf_check($csrf)) {
    echo json_encode(['success' => false, 'error' => 'Ungültiger Request oder CSRF-Token']);
    exit;
}

// Plugin-Pfad ermitteln
$plugin = basename($plugin);
$pluginDir = __DIR__ . '/../plugins/' . $plugin;

if (!is_dir($pluginDir)) {
    echo json_encode(['success' => false, 'error' => 'Plugin nicht gefunden']);
    exit;
}

// Ordner rekursiv löschen
function rrmdir($dir) {
    if (!is_dir($dir)) return false;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if ($fileinfo->isDir()) {
            if (!rmdir($fileinfo->getRealPath())) return false;
        } else {
            if (!unlink($fileinfo->getRealPath())) return false;
        }
    }

    return rmdir($dir);
}

if (rrmdir($pluginDir)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen (Rechte prüfen)']);
}
exit;