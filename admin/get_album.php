<?php
//session_start();
require_once __DIR__ . '/init.php'; 
require_once __DIR__ . '/../core/gallery_functions.php';

// CSRF-Token aus Session hinzufügen
$csrf_token = $_SESSION['csrf_token'] ?? '';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
  http_response_code(403);
  echo json_encode(['error' => 'Nicht autorisiert']);
  exit;
}

$album = $_GET['album'] ?? '';

if (!$album || !preg_match('/^[a-zA-Z0-9_-]+$/', $album)) {
  http_response_code(400);
  echo json_encode(['error' => 'Ungültiger Albumname']);
  exit;
}

$images = getImagesInAlbum($album);
$metadata = loadMetadata($album);

$resultImages = [];
foreach ($images as $img) {
  $filename = $img['filename'];
  $filePath = __DIR__ . '/../uploads/gallery/' . $album . '/' . $filename;

  // Dateigröße in KB (2 Nachkommastellen)
  $filesize = file_exists($filePath) ? round(filesize($filePath) / 1024, 2) : 0;

  // Bilddimensionen
  $dimensions = file_exists($filePath) ? getimagesize($filePath) : false;
  $width = $dimensions ? $dimensions[0] : 0;
  $height = $dimensions ? $dimensions[1] : 0;

  $resultImages[] = [
    'filename' => $filename,
    'thumb' => $img['thumb'],
    'alt' => $metadata[$filename]['alt'] ?? '',
    'filesize_kb' => $filesize,
    'dimensions' => ['width' => $width, 'height' => $height],
  ];
}
echo json_encode([
  'images' => $resultImages,
  'csrf_token' => $csrf_token
]);