<?php
session_start();
header('Content-Type: application/json');

// Fehler anzeigen / loggen
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'msg' => 'Ungültige Anfrage']);
    exit;
}

// Daten aus JSON Body
$data = json_decode(file_get_contents('php://input'), true);

// Admin-Check
if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'msg' => 'Keine Berechtigung']);
    exit;
}

// CSRF-Check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'msg' => 'Ungültiges CSRF-Token']);
    exit;
}

// Eingaben
$filename = basename($data['filename'] ?? '');
$altText  = trim($data['altText'] ?? '');

$uploadBase = realpath(__DIR__ . '/../uploads');
$systemDir  = $uploadBase . '/system';
$mediaDir   = $uploadBase . '/media';

// System-Ordner anlegen, falls nicht vorhanden
if (!is_dir($systemDir)) mkdir($systemDir, 0755, true);

// Datei in Media-Ordner suchen
$fileFound = false;
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($mediaDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($rii as $file) {
    if ($file->isFile() && basename($file) === $filename) {
        $fileFound = true;
        break;
    }
}

if (!$fileFound) {
    echo json_encode(['success' => false, 'msg' => 'Datei nicht gefunden']);
    exit;
}

// Alt-Text JSON laden / speichern
$altTextsPath = $systemDir . '/alt_texts.json';
$altTexts = [];
if (file_exists($altTextsPath)) {
    $altTexts = json_decode(file_get_contents($altTextsPath), true) ?: [];
}

$altTexts[$filename] = $altText;

$resultAlt = file_put_contents($altTextsPath, json_encode($altTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($resultAlt !== false) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => 'Speichern fehlgeschlagen']);
}
