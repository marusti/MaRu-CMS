<?php
session_start();

// Sicherstellen, dass der Benutzer angemeldet ist
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token-Überprüfung
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('Ungültiges CSRF-Token');
}

$uploadDir = __DIR__ . '/../uploads/';
$relativePath = str_replace(['..', "\0"], '', $_POST['filename']);  // Sicherheitsmaßnahme
$filePath = $uploadDir . $relativePath;


// Weiterleitung zur Datei-Manager-Seite nach dem Löschen
header('Location: filemanager.php');
exit;