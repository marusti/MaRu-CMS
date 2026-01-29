<?php
// admin/setup.php
require_once __DIR__ . '/../lib/helpers.php';

function is_valid_password($pass) {
    return strlen($pass) >= 8 &&
           preg_match('/[A-Z]/', $pass) &&
           preg_match('/[0-9]/', $pass);
}

$configPath = __DIR__ . '/../config';

// Schreibrechte prüfen
if (!is_writable($configPath)) {
    echo '<h2>Setup-Assistent</h2>';
    echo '<p style="color:red;">🚫 Der Ordner <code>config/</code> ist nicht beschreibbar. Bitte überprüfe die Dateiberechtigungen.</p>';
    echo '<p>Beispiel: <code>chmod -R 755 config/</code> oder <code>chmod -R 777 config/</code> (je nach Server)</p>';
    exit;
}

// Setup nur starten, wenn Dateien fehlen
if (!file_exists("$configPath/settings.json") || !file_exists("$configPath/users.json")) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $homepage = trim($_POST['homepage']);
        $language = $_POST['language'];
        $adminUser = trim($_POST['user']);
        $adminPass = $_POST['pass'];

        if (empty($adminUser) || empty($adminPass)) {
            echo '<p style="color:red;">Benutzername und Passwort dürfen nicht leer sein.</p>';
        } elseif (!is_valid_password($adminPass)) {
            echo '<p style="color:red;">Passwort muss mindestens 8 Zeichen lang sein, eine Zahl und einen Großbuchstaben enthalten.</p>';
        } else {
            $settings = [
                'homepage' => $homepage ?: 'startseite/index',
                'language' => $language,
                'version' => '1.0.0'
            ];
            file_put_contents("$configPath/settings.json", json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            file_put_contents("$configPath/users.json", json_encode([
                ["user" => $adminUser, "pass" => $hash]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            echo '<p>✅ Setup abgeschlossen.</p>';
            echo '<p><a href="login.php">Jetzt einloggen</a></p>';
            exit;
        }
    }

    echo '<h2>Setup-Assistent</h2>';
    echo '<form method="post">';
    echo '<label>Startseite:</label><input name="homepage" value="startseite/index"><br>';
    echo '<label>Sprache:</label><select name="language">';
    echo '<option value="de">Deutsch</option><option value="en">Englisch</option></select><br>';
    echo '<label>Admin Benutzer:</label><input name="user" value="admin"><br>';
    echo '<label>Passwort:</label><input name="pass" type="password" value=""><br>';
    echo '<button type="submit">Speichern</button>';
    echo '</form>';
} else {
    header('Location: login.php');
    exit;
}
