<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';
$pagesDir = __DIR__ . '/../content/pages';

if ($id) {
    $safeId = basename($id);
    $safeCategory = basename($category);

    // Pfade zu den beiden Dateien
    $jsonFile = $safeCategory
        ? "$pagesDir/$safeCategory/$safeId.json"
        : "$pagesDir/$safeId.json";

    $mdFile = $safeCategory
        ? "$pagesDir/$safeCategory/$safeId.md"
        : "$pagesDir/$safeId.md";

    $deleted = false;

    // JSON-Datei löschen
    if (file_exists($jsonFile)) {
        unlink($jsonFile);
        $deleted = true;
    }

    // MD-Datei löschen
    if (file_exists($mdFile)) {
        unlink($mdFile);
        $deleted = true;
    }

    if ($deleted) {
        $message = "Seite '$safeId' wurde erfolgreich gelöscht.";
    } else {
        $message = "Fehler: Seite '$safeId' konnte nicht gefunden werden.";
    }
} else {
    $message = "Fehler: Ungültige Anfrage.";
}

header("Location: content_manager.php?message=" . urlencode($message));
exit;
