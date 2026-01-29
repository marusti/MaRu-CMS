<?php

require_once __DIR__ . '/init.php';

// Prüfen, ob Admin eingeloggt ist
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Funktion: Aktuelle Base-URL ermitteln (mit /admin Entfernen)
function get_current_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = rtrim(dirname($scriptName), '/\\');

    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = substr($scriptDir, 0, -6);
    }

    return $protocol . '://' . $host . $scriptDir . '/';
}

function normalize_url($url) {
    return strtolower(rtrim(trim($url), '/'));
}

// Settings laden (angenommen $settings ist ein Array mit deiner config)
$settingsFile = __DIR__ . '/../config/settings.json';
$settings = [];
if (is_readable($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$minVersion = $settings['php_min_version'] ?? '8.1.0';
$phpVersionOk = version_compare(PHP_VERSION, $minVersion, '>=');

$cmsRoot = realpath(__DIR__ . '/..');
$cmsSizeBytes = get_folder_size($cmsRoot);
$cmsSizeFormatted = format_size($cmsSizeBytes);

$writableChecks = [
    'content/pages/' => is_writable(__DIR__ . '/../content/pages/'),
    'content/categories.json' => is_writable(__DIR__ . '/../content/categories.json'),
    'config/settings.json' => is_writable($settingsFile),
    'sitemap.xml' => is_writable(__DIR__ . '/../sitemap.xml'),
    '.htaccess' => is_writable(__DIR__ . '/../.htaccess'),
];

function check_for_updates() {
    if (!function_exists('curl_init')) {
        return "Update-Check nicht möglich: cURL ist nicht verfügbar.";
    }

    $url = 'https://api.example.com/latest-version';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        return $data['latest_version'] ?? null;
    }
    return null;
}

$latestVersion = check_for_updates();
$cmsInfo = load_cms_info();

if (!$phpVersionOk) {
    $pageTitle = __('php_too_old');
    ob_start(); ?>
    <div class="card error">
        <h2><?= __('php_too_old') ?></h2>
        <p><?= sprintf(__('php_required'), $minVersion) ?></p>
        <p><?= __('php_current') ?> <strong><?= PHP_VERSION ?></strong></p>
    </div>
    <?php
    $content = ob_get_clean();
    include '_layout.php';
    exit;
}

// Base URL ermitteln und vergleichen
$currentBaseUrl = get_current_base_url();

$storedBaseUrlNormalized = normalize_url($settings['base_url'] ?? '');
$currentBaseUrlNormalized = normalize_url($currentBaseUrl);

$baseUrlMismatch = ($storedBaseUrlNormalized !== $currentBaseUrlNormalized);

$saveError = '';

// Formular zum Speichern der neuen Base-URL verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_base_url'], $_POST['new_base_url'])) {
    $newBaseUrl = trim($_POST['new_base_url']);

    $settings['base_url'] = $newBaseUrl;

    if (is_writable($settingsFile)) {
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // Nach Speichern neu laden
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $saveError = __('error_writing_settings');
    }
}




// Setze die Variable $currentUsers auf ein leeres Array als Fallback, falls sie nicht definiert ist
$currentUsers = [];

// Pfad zur Datei mit den angemeldeten Benutzern
$loggedInUsersFile = __DIR__ . '/../config/logged_in_users.json';

// Überprüfen, ob die Datei existiert und die Daten korrekt geladen werden können
if (file_exists($loggedInUsersFile)) {
    $loggedInUsers = json_decode(file_get_contents($loggedInUsersFile), true);

    // Überprüfen, ob die JSON-Daten korrekt decodiert wurden und ein Array enthalten
    if (is_array($loggedInUsers)) {
        $currentUsers = $loggedInUsers; // Setze die Benutzerdaten auf $currentUsers
    } else {
        // Wenn die Daten keine gültige Struktur haben, setze $currentUsers auf ein leeres Array
        $currentUsers = [];
    }
}


$pageTitle = __('page_title');

ob_start();
?>

   <h1><?= sprintf(__('welcome'), htmlspecialchars($_SESSION['admin'])) ?></h1>

<div class="dashboard">

    <?php if ($baseUrlMismatch): ?>
        <form method="post" class="warning baseurl-warning">
            <strong><?= __('warning') ?>:</strong>
            <span><?= __('base_url_mismatch') ?></span><br>
            <input type="hidden" name="new_base_url" value="<?= htmlspecialchars($currentBaseUrl) ?>">
            <button type="submit" name="save_base_url"><?= __('update_base_url_to_current') ?></button>
            <?php if ($saveError): ?>
                <div class="error" style="color:red; margin-top:0.5em;">
                    <?= htmlspecialchars($saveError) ?>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>

 

    <section class="cms-info">
        <h2><?= __('cms_info') ?></h2>
        <ul>
            <li><strong><?= __('name') ?>:</strong> <span><?= htmlspecialchars($cmsInfo['name'] ?? 'Unbekannt') ?></span></li>
            <li><strong><?= __('version') ?>:</strong> <span><?= htmlspecialchars($cmsInfo['version'] ?? '-') ?></span></li>
            <li><strong><?= __('status') ?>:</strong> <span><?= htmlspecialchars($cmsInfo['status'] ?? '-') ?></span></li>
            <li><strong><?= __('license') ?>:</strong> <span><?= htmlspecialchars($cmsInfo['license'] ?? '-') ?></span></li>


            <?php if (is_string($latestVersion)): ?>
                <li><strong><?= __('update_available') ?>:</strong> <span class="status red"><?= htmlspecialchars($latestVersion) ?></span></li>
            <?php elseif ($latestVersion): ?>
                <li><strong><?= __('update_available') ?>:</strong> <span class="status green"><?= htmlspecialchars($latestVersion) ?></span></li>
            <?php else: ?>
                <li><strong><?= __('update_available') ?>:</strong> <span class="status green"><?= __('no_updates') ?></span></li>
            <?php endif; ?>
        </ul>
    </section>
    
 
    
    <section class="cms-permissions">
        <h2><?= __('permissions') ?></h2>
        <ul>
            <?php foreach ($writableChecks as $path => $isWritable): ?>
                <li>
                    <strong><?= htmlspecialchars($path) ?>:</strong>
                    <span class="status <?= $isWritable ? 'green' : 'red' ?>">
                        <?= $isWritable ? __('writable') : __('not_writable') ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="server">
    <h2><?= __('server') ?></h2>
    <ul>
        <li>
            <strong><?= __('php_version') ?></strong> 
            <span class="php-version status <?= $phpVersionOk ? 'green' : 'red' ?>">
                <?= PHP_VERSION ?>
            </span>
        </li>
        <li><strong><?= __('used_space') ?>:</strong> <span><?= $cmsSizeFormatted ?></span></li>
        <li><strong><?= __('cms_path') ?>:</strong> <span><?= htmlspecialchars($cmsRoot) ?></span></li>
        <li><strong><?= __('base_url') ?>:</strong> <?= htmlspecialchars($settings['base_url'] ?? '-') ?></li>
    </ul>
</section>
    
<section class="logged-in-users">
    <h2><?= __('currently_logged_in_users') ?></h2>
    <?php if (count($currentUsers) > 0): ?>
        <ul>
            <?php foreach ($currentUsers as $user): ?>
                <li><?= htmlspecialchars($user) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

</div>

<?php
$content = ob_get_clean();
include '_layout.php';
