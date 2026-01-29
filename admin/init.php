<?php
// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Helfer laden
require_once __DIR__ . '/../lib/helpers.php';

// Settings laden
$settingsFile = __DIR__ . '/../config/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}

// Verfügbare Sprachen aus admin/lang-Verzeichnis ermitteln
function get_available_languages(string $langDir): array {
    $langs = [];
    foreach (glob($langDir . '/*.php') as $file) {
        $langs[] = basename($file, '.php');
    }
    return $langs;
}

$availableLanguages = get_available_languages(__DIR__ . '/lang');

// Sprache nur aus settings.json nehmen oder Standard 'de'
$lang = $settings['language'] ?? 'de';
if (!in_array($lang, $availableLanguages)) {
    $lang = 'de';
}


$L = load_language_admin($lang);

$baseUrl = rtrim($settings['base_url'], '/'); // abschließenden Slash entfernen