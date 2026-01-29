<?php
declare(strict_types=1);
header('Content-Type: application/json');

// Fehlerausgabe deaktivieren (Produktivmodus)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Prüfen, ob 'gallery' Parameter gesetzt ist
if (!isset($_GET['gallery'])) {
    echo json_encode(['error' => 'Keine Galerie angegeben']);
    exit;
}

// Nur erlaubte Zeichen für Galerie-Name zulassen (Sicherheit)
$galleryName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['gallery']);
$galleryDir = __DIR__ . '/../uploads/gallery';
$galleryPath = $galleryDir . '/' . $galleryName;

// Prüfen, ob Galerie existiert
if (!is_dir($galleryPath)) {
    echo json_encode(['error' => "Galerie '$galleryName' nicht gefunden"]);
    exit;
}

// Einstellungen laden für base_url
$configFile = __DIR__ . '/../config/settings.json';
$configData = [];

if (file_exists($configFile)) {
    $json = file_get_contents($configFile);
    $configData = json_decode($json, true);
}

$base_url = $configData['base_url'] ?? 'http://localhost'; // Default fallback

// Bilder mit erlaubten Dateiendungen auslesen
$images = array_filter(scandir($galleryPath), function($file) use ($galleryPath) {
    return is_file("$galleryPath/$file") && preg_match('/\.(jpe?g|png|gif|webp)$/i', $file);
});

if (empty($images)) {
    echo json_encode(['error' => "Keine Bilder in Galerie '$galleryName'"]);
    exit;
}

// Basis-URL für Bilder korrekt zusammensetzen (URL-encode für Sicherheit)
$baseUrl = rtrim($base_url, '/') . '/uploads/gallery/' . rawurlencode($galleryName);

// Ergebnis-Array mit URLs (voll qualifiziert)
$result = [];
foreach ($images as $img) {
    $result[] = $baseUrl . '/' . rawurlencode($img);
}

// JSON-Antwort mit Bild-URLs
echo json_encode(['images' => $result]);
exit;