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

    // cURL fehlt → Meldung statt Fatal Error
    if (!function_exists('curl_init')) {
        return __('update_check_unavailable');
    }

    $cmsInfo = load_cms_info();

    if (!isset($cmsInfo['version'])) {
        return __('update_no_version_found');
    }

    $currentVersion = $cmsInfo['version'];
    $url = 'https://api.github.com/repos/marusti/MaRu-CMS/releases/latest';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MaRu-CMS Update Check');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);

    if ($response === false) {
        return __('update_check_failed');
    }

    $data = json_decode($response, true);

    if (!isset($data['tag_name'])) {
        return null;
    }

    $latestVersion = ltrim($data['tag_name'], 'v');

    if (version_compare($currentVersion, $latestVersion, '<')) {
        return $latestVersion;
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
        <p><?= __('php_current') ?> <?= PHP_VERSION ?></p>
    </div>
    <?php
    $content = ob_get_clean();
    include '_layout.php';
    exit;
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
    <div class="section-header"><?= __('server') ?></div>
    <ul>
        <li>
            <strong><?= __('php_version') ?></strong> 
            <span class="status <?= $phpVersionOk ? 'green' : 'red' ?>">
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
