<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/init.php'; // Hier wird __() geladen

define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_DURATION', 600);

$usersFile = __DIR__ . '/../config/users.json';
$failedLoginsFile = __DIR__ . '/../config/failed_logins.json';
$loggedInUsersFile = __DIR__ . '/../config/logged_in_users.json'; // Pfad zur Datei der eingeloggten Benutzer

$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
if (!is_array($users)) $users = [];

if (!file_exists($failedLoginsFile)) {
    file_put_contents($failedLoginsFile, json_encode([], JSON_PRETTY_PRINT));
}
$failedLogins = json_decode(file_get_contents($failedLoginsFile), true);
if (!is_array($failedLogins)) $failedLogins = [];

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$ip = $_SERVER['REMOTE_ADDR'];
$error = '';
$lockActive = false;

if (isset($failedLogins[$ip]) && isset($failedLogins[$ip]['locked_until']) && time() < $failedLogins[$ip]['locked_until']) {
    $lockActive = true;
    $error = __('too_many_attempts');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$lockActive) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) die(__('csrf_error'));

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $_SESSION['admin'] = $username;
        $_SESSION['role'] = $users[$username]['role'] ?? 'editor';

        unset($failedLogins[$ip]);
        file_put_contents($failedLoginsFile, json_encode($failedLogins, JSON_PRETTY_PRINT), LOCK_EX);

        // Prüfen, ob die Datei mit den eingeloggten Benutzern existiert, wenn nicht, erstelle sie
        if (!file_exists($loggedInUsersFile)) {
            file_put_contents($loggedInUsersFile, json_encode([])); // Leere Liste als initialen Inhalt
        }

        // Alle aktuell eingeloggten Benutzer laden
        $currentUsers = json_decode(file_get_contents($loggedInUsersFile), true) ?: [];

        // Benutzer zur Liste der eingeloggten Benutzer hinzufügen, wenn er noch nicht drin ist
        if (!in_array($username, $currentUsers)) {
            $currentUsers[] = $username;
            // Benutzerliste zurück in die Datei speichern
            file_put_contents($loggedInUsersFile, json_encode($currentUsers, JSON_PRETTY_PRINT));
        }

        // Weiterleitung zum Dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        if (!isset($failedLogins[$ip])) $failedLogins[$ip] = ['failed_attempts' => 1, 'locked_until' => 0];
        else $failedLogins[$ip]['failed_attempts']++;

        if ($failedLogins[$ip]['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $failedLogins[$ip]['locked_until'] = time() + LOCKOUT_DURATION;
            $error = __('too_many_attempts');
        } else {
            $error = __('invalid_credentials');
        }

        file_put_contents($failedLoginsFile, json_encode($failedLogins, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('login_title') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body class="login-page">
<main class="login-container" role="main">
<!-- Logo hinzufügen -->
    <div class="logo-container">
        <img src="assets/images/logo.png" alt="MaRu CMS Logo" class="logo">
    </div>
    <h1 id="loginTitle"><?= __('login_title') ?></h1>

    <?php if ($error): ?>
        <div class="error" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" aria-labelledby="loginTitle" aria-label="Loginformular" <?= $lockActive ? 'aria-disabled="true"' : '' ?>>
    <label for="username" id="usernameLabel"><?= __('username') ?></label>
    <input type="text" name="username" id="username" required autocomplete="username" 
           aria-labelledby="usernameLabel" aria-describedby="usernameDescription" <?= $lockActive ? 'disabled' : '' ?>>

    <span id="usernameDescription" class="sr-only"><?= __('enter_username_description') ?></span>

    <label for="password" id="passwordLabel"><?= __('password') ?></label>
    <div class="password-wrapper">
        <input type="password" name="password" id="password" required autocomplete="current-password" 
               aria-labelledby="passwordLabel" aria-describedby="passwordDescription" <?= $lockActive ? 'disabled' : '' ?>>
        <button type="button" id="togglePassword" class="password-toggle" aria-label="<?= __('show_hide_password') ?>" 
                aria-controls="password" <?= $lockActive ? 'disabled' : '' ?>>
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeClosed" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19C5 19 1 12 1 12a21.28 21.28 0 0 1 5.17-5.94"/>
                <path d="M1 1l22 22"/>
                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
            </svg>
        </button>
    </div>
    <span id="passwordDescription" class="sr-only"><?= __('enter_password_description') ?></span>

    <input type="submit" id="submitButton" value="<?= __('login') ?>" disabled>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
</form>

</main>

<script>
// Password visibility toggle script with focus management
const toggleBtn = document.getElementById('togglePassword');
const pwInput = document.getElementById('password');
const eyeOpen = document.getElementById('eyeOpen');
const eyeClosed = document.getElementById('eyeClosed');

toggleBtn.addEventListener('click', () => {
    const isVisible = pwInput.type === 'text';
    pwInput.type = isVisible ? 'password' : 'text';
    eyeOpen.style.display = isVisible ? 'inline' : 'none';
    eyeClosed.style.display = isVisible ? 'none' : 'inline';
    toggleBtn.focus(); // Fokus nach dem Klick auf das Toggle setzen
});

const usernameInput = document.getElementById('username');
const submitButton = document.getElementById('submitButton');

// Funktion zum Überprüfen, ob beide Felder ausgefüllt sind
function checkFormValidity() {
    if (usernameInput.value.trim() !== "" && pwInput.value.trim() !== "") {
        submitButton.disabled = false; // Button aktivieren
    } else {
        submitButton.disabled = true; // Button deaktivieren
    }
}

// Überprüfen beim Ändern der Eingabefelder
usernameInput.addEventListener('input', checkFormValidity);
pwInput.addEventListener('input', checkFormValidity);

// Beim Laden der Seite auch einmal überprüfen
checkFormValidity();
</script>
</body>
</html>
