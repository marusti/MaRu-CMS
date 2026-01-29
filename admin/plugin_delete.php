<?php
require_once __DIR__ . '/init.php';

function csrf_check($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Ungültige Methode']);
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Ungültiger CSRF-Token']);
    exit;
}

if (empty($_POST['plugin'])) {
    echo json_encode(['success' => false, 'error' => 'Kein Plugin angegeben']);
    exit;
}

$plugin = basename($_POST['plugin']);
$pluginDir = __DIR__ . '/../plugins/' . $plugin;

if (!is_dir($pluginDir)) {
    echo json_encode(['success' => false, 'error' => 'Plugin nicht gefunden']);
    exit;
}

// Plugin-Verzeichnis rekursiv löschen
function rrmdir($dir) {
    foreach(array_diff(scandir($dir), ['.','..']) as $file) {
        $path = "$dir/$file";
        is_dir($path) ? rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

try {
    rrmdir($pluginDir);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Fehler beim Löschen']);
}
exit;
