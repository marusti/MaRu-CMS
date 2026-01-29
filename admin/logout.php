<?php
session_start();

// Pfad zur Datei mit den angemeldeten Benutzern
$loggedInUsersFile = __DIR__ . '/../config/logged_in_users.json';

// Wenn die Datei existiert und gelesen werden kann
if (file_exists($loggedInUsersFile)) {
    $currentUsers = json_decode(file_get_contents($loggedInUsersFile), true) ?: [];

    // Wenn der Benutzer in der Liste der angemeldeten Benutzer ist, entferne ihn
    if (isset($_SESSION['admin'])) {
        $username = $_SESSION['admin'];
        
        // Entferne den Benutzer aus der Liste
        $currentUsers = array_filter($currentUsers, function($user) use ($username) {
            return $user !== $username;
        });

        // Indizes neu sortieren, da array_filter die Indizes beibehalten könnte
        $currentUsers = array_values($currentUsers);

        // Speichern der aktualisierten Liste zurück in die Datei
        file_put_contents($loggedInUsersFile, json_encode($currentUsers, JSON_PRETTY_PRINT));
    }
}

// Sitzung zerstören und den Benutzer abmelden
session_destroy();

// Weiterleitung zur Login-Seite
header('Location: login.php');
exit;
