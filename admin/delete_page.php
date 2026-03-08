<?php
session_start();


if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? '';
$category = $_GET['category'] ?? '';
$pagesDir = __DIR__ . '/../content/pages';

$message = ''; // Default message variable

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

    // Success or failure message
    if ($deleted) {
        $message = __('page_deleted_successfully') . ' ' . htmlspecialchars($safeId);
        addMessage($_SESSION['messages'], $message, 'success');
    } else {
        $message = __('page_not_found');
        addMessage($_SESSION['messages'], $message, 'error');
    }
} else {
    $message = __('invalid_request');
    addMessage($_SESSION['messages'], $message, 'error');
}

// Redirect back to content manager
header('Location: content_manager.php');
exit;
?>