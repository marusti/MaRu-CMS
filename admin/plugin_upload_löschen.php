<?php
require_once __DIR__ . '/init.php';

// CSRF Token check
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

if (!isset($_FILES['plugin_zip'])) {
    echo json_encode(['success' => false, 'error' => 'Keine Datei hochgeladen']);
    exit;
}

$file = $_FILES['plugin_zip'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Fehler beim Upload']);
    exit;
}

// Prüfen ob ZIP-Datei
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if ($mime !== 'application/zip' && substr($file['name'], -4) !== '.zip') {
    echo json_encode(['success' => false, 'error' => 'Nur ZIP-Dateien erlaubt']);
    exit;
}

// Plugin entpacken und prüfen
$pluginDir = __DIR__ . '/../plugins/';

// Temporäres Verzeichnis für Entpacken
$tmpDir = sys_get_temp_dir() . '/plugin_upload_' . bin2hex(random_bytes(5));
mkdir($tmpDir);

$zip = new ZipArchive;
if ($zip->open($file['tmp_name']) === TRUE) {
    $zip->extractTo($tmpDir);
    $zip->close();
} else {
    rmdir($tmpDir);
    echo json_encode(['success' => false, 'error' => 'Kann ZIP nicht entpacken']);
    exit;
}

// Plugin-Ordner ermitteln
$dirs = array_filter(glob($tmpDir . '/*'), 'is_dir');
if (count($dirs) !== 1) {
    // Cleanup
    array_map('unlink', glob("$tmpDir/*.*"));
    rmdir($tmpDir);
    echo json_encode(['success' => false, 'error' => 'ZIP muss genau einen Ordner enthalten']);
    exit;
}
$pluginFolder = basename(reset($dirs));

// Plugin-Verzeichnis existiert?
if (is_dir($pluginDir . $pluginFolder)) {
    // Plugin überschreiben?
    // Für Sicherheit besser ablehnen, hier überscheiben:
    function rrmdir($dir) {
        foreach(array_diff(scandir($dir), ['.','..']) as $file) {
            $path = "$dir/$file";
            is_dir($path) ? rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
    rrmdir($pluginDir . $pluginFolder);
}

// Plugin verschieben
if (!rename($tmpDir . '/' . $pluginFolder, $pluginDir . $pluginFolder)) {
    // Cleanup
    rrmdir($tmpDir);
    echo json_encode(['success' => false, 'error' => 'Kann Plugin nicht verschieben']);
    exit;
}

// Temp cleanup
rmdir($tmpDir);

// Erfolg
echo json_encode(['success' => true]);
exit;
