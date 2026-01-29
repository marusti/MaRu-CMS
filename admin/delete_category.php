<?php
session_start();

// Sicherstellen, dass der Admin eingeloggt ist
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Die ID der Kategorie abrufen
$id = $_GET['id'] ?? '';
if (empty($id)) {
    // Fehlerbehandlung, falls keine ID übergeben wurde
    header('Location: content_manager.php?message=' . urlencode('Category ID is missing.'));
    exit;
}

// Der Pfad zur JSON-Datei der Kategorien
$file = __DIR__ . '/../content/categories.json';
if (!file_exists($file)) {
    // Fehlerbehandlung, falls die Datei nicht existiert
    header('Location: content_manager.php?message=' . urlencode('Categories file not found.'));
    exit;
}

// Alle Kategorien einlesen
$categories = json_decode(file_get_contents($file), true);
if ($categories === null) {
    // Fehlerbehandlung, falls das JSON-Dekodieren fehlschlägt
    header('Location: content_manager.php?message=' . urlencode('Failed to decode categories.'));
    exit;
}

// Die Kategorie mit der gegebenen ID löschen
$categories = array_filter($categories, fn($c) => $c['id'] !== $id);
file_put_contents($file, json_encode(array_values($categories), JSON_PRETTY_PRINT));

// Die aktuelle `category_page` ermitteln
$categoryPage = $_GET['category_page'] ?? 1;

// Alle URL-Parameter beibehalten, aber `id` entfernen
$queryParams = $_GET;
unset($queryParams['id']); // Entferne `id` aus den Weiterleitungsparametern
$queryParams['message'] = 'Category deleted successfully'; // Erfolgsnachricht hinzufügen

// Die Weiterleitung zur ursprünglichen Seite mit allen Parametern
// Verwendet $_SERVER['QUERY_STRING'], um die URL-Parameter korrekt zu übernehmen
$redirectUrl = 'content_manager.php?' . http_build_query($queryParams);

// Weiterleiten
header('Location: ' . $redirectUrl);
exit;
