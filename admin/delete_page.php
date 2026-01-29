<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';
$pagesDir = __DIR__ . '/../content/pages';

if ($id && $category) {
    // Sicherheit: Nur Basenamen zulassen
    $safeId = basename($id);
    $safeCategory = basename($category);
    $file = "$pagesDir/$safeCategory/$safeId.php";

    if (file_exists($file)) {
        unlink($file);
        $message = "Seite '$safeId' wurde erfolgreich gelöscht.";
    } else {
        $message = "Fehler: Seite '$safeId' konnte nicht gefunden werden.";
    }
} else {
    $message = "Fehler: Ungültige Anfrage.";
}

header("Location: content_manager.php?message=" . urlencode($message));
exit;
