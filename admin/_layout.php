<?php

// init.php lädt schon session, csrf, helpers und settings
require_once __DIR__ . '/init.php';

// Admin-Login prüfen
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Funktion: Aktuelle Base-URL ermitteln (mit /admin Entfernen)
function get_current_base_url(): string
{
    // Protokoll sauber erkennen (Proxy-freundlich)
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $protocol = $https ? 'https' : 'http';

    // Host ohne Whitespace
    $host = trim($_SERVER['HTTP_HOST'] ?? 'localhost');

    // Pfad ermitteln
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    // /admin am Ende sicher entfernen
    if (preg_match('~/admin$~i', $path)) {
        $path = preg_replace('~/admin$~i', '', $path);
    }

    // Sonderfälle bereinigen
    if ($path === '.' || $path === '/') {
        $path = '';
    }

    return $protocol . '://' . $host . $path . '/';
}
function normalize_url($url) {
    return strtolower(rtrim(trim($url), '/'));
}

// Wartungsmodus prüfen (aus bereits geladenem $settings)
$maintenanceActive = !empty($settings['maintenance']);

// CMS-Daten laden
$cmsFile = __DIR__ . '/../config/cms.json';
$cms = json_decode(@file_get_contents($cmsFile) ?: '{}', true) ?: [];

// Seitentitel und Content default setzen
if (!isset($pageTitle)) $pageTitle = 'Admin';
if (!isset($content)) $content = '';

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

require_once __DIR__ . '/assets/icons/icons.php';

$currentPage = basename($_SERVER['SCRIPT_NAME']); // z.B. "dashboard.php"

$sidebarLinks = [
    ['href' => 'dashboard.php', 'label' => __('dashboard'), 'icon' => 'dashboard'],
    ['href' => 'manage_categories.php', 'label' => __('categories'), 'icon' => 'folder'],
    ['href' => 'content_manager.php', 'label' => __('content'), 'icon' => 'content'],
    ['href' => 'filemanager.php', 'label' => __('filemanager'), 'icon' => 'filemanager'],
    ['href' => 'users.php', 'label' => __('users'), 'icon' => 'users'],
    ['href' => 'settings.php', 'label' => __('settings'), 'icon' => 'settings'],
    ['href' => 'template_manager.php', 'label' => __('templates'), 'icon' => 'templates'],
    ['href' => 'plugin_manager.php', 'label' => __('plugins'), 'icon' => 'plugins'],
    ['href' => 'logout.php', 'label' => __('logout'), 'icon' => 'logout'],
];

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($cms['name']) ?></title>
  <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    (function() {
      const saved = localStorage.getItem('theme') || 'light';
      if (saved !== 'light') {
        document.documentElement.classList.add(saved);
      }
    })();
  </script>
  <script>
if (localStorage.getItem('sidebar-collapsed') === 'true') {
  document.documentElement.classList.add('sidebar-collapsed');
}
</script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header role="banner" aria-label="Admin Header">
 <!-- Logo hinzufügen -->
  <img src="assets/images/logo.png" alt="<?= htmlspecialchars($cms['name']) ?> Logo" style="height: 50px;">
  
  <span class="heading"><?= htmlspecialchars(__('admin_dashboard')) ?></span>

<label for="theme-select" class="sr-only"><?= htmlspecialchars(__('theme_toggle')) ?></label>
<select id="theme-select" aria-label="<?= htmlspecialchars(__('theme_toggle')) ?>">
  <option value="light">☀️ Light</option>
  <option value="dark-mode">🌙 Dark</option>
  <option value="glass-mode">🪟 Glass</option>
</select>

  <!-- Vorschau-Link rechts ausgerichtet -->
  <a href="<?= htmlspecialchars($baseUrl) ?>" target="_blank" style="display: flex; align-items: center; color: var(--text-color-dark); margin-left: auto;">
    <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.3em;">
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="12" y1="8" x2="12" y2="16"></line>
      <line x1="8" y1="12" x2="16" y2="12"></line>
    </svg>
    <?= htmlspecialchars(__('preview')) ?>
  </a>
 
</header>

<aside id="sidebar" role="navigation" aria-label="Main Navigation">
    <div class="sidebar-header">
        <button id="toggleSidebar" class="sidebar-toggle" aria-label="<?= htmlspecialchars(__('toggle_sidebar')) ?>" aria-controls="sidebar">
            <svg class="icon-collapse" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6" />
            </svg>
            <svg class="icon-expand" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 viewBox="0 0 24 24">
                <polyline points="9 18 15 12 9 6" />
            </svg>
        </button>
    </div>

    <nav role="navigation" aria-label="Sidebar Menu">
        <ul>
            <?php foreach ($sidebarLinks as $link): ?>
                <li>
                    <a href="<?= htmlspecialchars($link['href']) ?>"
                       class="<?= ($currentPage === $link['href']) ? 'nav-active' : '' ?>"
                       data-tooltip="<?= htmlspecialchars($link['label']) ?>">
                        <?= getIcon($link['icon']) ?>
                        <span><?= htmlspecialchars($link['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>

<main role="main">
<?php if ($maintenanceActive): ?>
<div class="warning maintenance-warning">
    <strong><?= htmlspecialchars(__('maintenance_active_note')) ?>:</strong> <?= htmlspecialchars(__('maintenance_active')) ?>
</div>
<?php endif; ?>

<?php
    // Meldungen ausgeben, falls vorhanden
if (!empty($_SESSION['messages'])) {
    renderMessages($_SESSION['messages']);
    unset($_SESSION['messages']); // nach Anzeige löschen
}
    ?>

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
    

        <?= $content ?>  

</main>

<!-- Dialoge -->
<?php if (!empty($pageHasDialog)): ?>
<?php include 'includes/dialog.php'; ?>
<?php endif; ?>

<footer role="contentinfo" aria-label="Footer">
  <small>
    © <?= date('Y') ?> | 
    <a href="https://github.com/marusti/MaRu-CMS"  target="_blank" rel="noopener noreferrer">
      <?= htmlspecialchars($cms['name']) ?>
    </a>
    <?= htmlspecialchars($cms['version']) ?>
    <?= htmlspecialchars($cms['status']) ?>
  </small>
</footer>

<?php if (!empty($pageHasEditor)): ?>
<script src="assets/js/editor.js"></script>
<?php endif; ?>
<script src="assets/js/core.js"></script>
<?php if (!empty($pageHasDialog)): ?>
<script src="assets/js/dialog.js" defer></script>
<?php endif; ?>
<?php if (!empty($pageHasFilter)): ?>
<script src="assets/js/filter.js"></script>
<?php endif; ?>
</body>
</html>
