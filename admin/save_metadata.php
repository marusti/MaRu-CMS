<?php
session_start();
require_once __DIR__ . '/../core/gallery_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Nicht autorisiert']);
  exit;
}

$album = $_POST['album'] ?? '';
$filename = $_POST['filename'] ?? '';
$alt = $_POST['alt'] ?? '';

if (!$album || !preg_match('/^[a-zA-Z0-9_-]+$/', $album) ||
    !$filename || !preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
  http_response_code(400);
  echo json_encode(['error' => 'Ungültige Eingaben']);
  exit;
}

$metadata = loadMetadata($album);
if (!is_array($metadata)) $metadata = [];

if (!isset($metadata[$filename])) $metadata[$filename] = [];

$metadata[$filename]['alt'] = trim($alt);

// Speichern (funktion in gallery_functions.php)
if (saveMetadata($album, $metadata)) {
  echo json_encode(['success' => true]);
} else {
  http_response_code(500);
  echo json_encode(['error' => 'Fehler beim Speichern']);
}

