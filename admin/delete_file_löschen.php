<?php
session_start();

// Sicherstellen, dass der Benutzer angemeldet ist
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token-Überprüfung
 if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

$uploadDir = __DIR__ . '/../uploads/';
$relativePath = str_replace(['..', "\0"], '', $_POST['filename']);  // Sicherheitsmaßnahme
$filePath = $uploadDir . $relativePath;

// Überprüfen, ob die Datei existiert
if (!file_exists($filePath)) {
    $_SESSION['messages'][] = sprintf('Die Datei %s existiert nicht.', basename($filePath));
    header('Location: filemanager.php');
    exit;
}

// Überprüfen, ob es sich um eine echte Datei handelt und löschen
if (is_file($filePath)) {
    if (unlink($filePath)) {
        $_SESSION['messages'][] = sprintf('Datei %s wurde erfolgreich gelöscht.', basename($filePath));
    } else {
        // Detaillierte Fehlermeldung beim Löschen
        $_SESSION['messages'][] = sprintf('Fehler beim Löschen der Datei %s: %s', basename($filePath), error_get_last()['message']);
    }
} else {
    $_SESSION['messages'][] = sprintf('Die Datei %s ist kein reguläres File.', basename($filePath));
}

// Weiterleitung zur Datei-Manager-Seite nach dem Löschen
header('Location: filemanager.php');
exit;