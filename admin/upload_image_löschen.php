<?php
session_start();
require_once __DIR__ . '/../core/gallery_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Nicht autorisiert']);
  exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Ungültiger CSRF-Token']);
  exit;
}


$album = $_POST['album'] ?? '';
if (!$album || !preg_match('/^[a-zA-Z0-9_-]+$/', $album)) {
  http_response_code(400);
  echo json_encode(['error' => 'Ungültiger Albumname']);
  exit;
}

if (!isset($_FILES['image'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Keine Datei hochgeladen']);
  exit;
}

$file = $_FILES['image'];

// Prüfen auf Fehler
if ($file['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['error' => 'Upload Fehler']);
  exit;
}

// Dateityp prüfen (Bild)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime, $allowedTypes)) {
  http_response_code(400);
  echo json_encode(['error' => 'Nur JPEG, PNG, GIF erlaubt']);
  exit;
}

// Zielverzeichnis prüfen
$targetDir = __DIR__ . '/../albums/' . $album . '/';
if (!is_dir($targetDir)) {
  mkdir($targetDir, 0755, true);
}

// Dateiname sicher machen
$filename = basename($file['name']);
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

// Datei verschieben
$targetFile = $targetDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
  http_response_code(500);
  echo json_encode(['error' => 'Fehler beim Speichern']);
  exit;
}

// Thumbnail erzeugen (funktion in gallery_functions.php)
createThumbnail($targetFile, $targetDir . 'thumbs/' . $filename);

echo json_encode(['success' => true]);