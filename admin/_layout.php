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
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header role="banner" aria-label="Admin Header">
 <!-- Logo hinzufügen -->
  <img src="assets/images/logo.png" alt="<?= htmlspecialchars($cms['name']) ?> Logo" style="height: 50px;">
  
  <h1><?= htmlspecialchars(__('admin_dashboard')) ?></h1>

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

 <!-- Sidebar Toggle-Button -->
  <div class="sidebar-header">
    <button id="toggleSidebar" class="sidebar-toggle" aria-label="<?= htmlspecialchars(__('toggle_sidebar')) ?>" aria-controls="sidebar">
  <!-- Pfeil nach links (zum Einklappen) -->
  <svg class="icon-collapse" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       viewBox="0 0 24 24">
    <polyline points="15 18 9 12 15 6" />
  </svg>

  <!-- Pfeil nach rechts (zum Ausklappen) -->
  <svg class="icon-expand" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
       viewBox="0 0 24 24">
    <polyline points="9 18 15 12 9 6" />
  </svg>
</button>

  </div>
  <nav role="navigation" aria-label="Sidebar Menu">
    <ul>
      <li><a href="dashboard.php" data-tooltip="<?= htmlspecialchars(__('dashboard')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="9"></rect>
          <rect x="14" y="3" width="7" height="5"></rect>
          <rect x="14" y="12" width="7" height="9"></rect>
          <rect x="3" y="16" width="7" height="5"></rect>
        </svg>
        <span><?= __('dashboard') ?></span>
      </a></li>
      
      <li><a href="manage_categories.php" data-tooltip="<?= htmlspecialchars(__('categories')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M3 4h18M3 10h18M3 16h18"></path>
        </svg>
        <span><?= __('categories') ?></span>
      </a></li>

      <li><a href="content_manager.php" data-tooltip="<?= htmlspecialchars(__('content')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <line x1="4" y1="6" x2="20" y2="6"></line>
          <line x1="4" y1="12" x2="20" y2="12"></line>
          <line x1="4" y1="18" x2="20" y2="18"></line>
        </svg>
        <span><?= __('content') ?></span>
      </a></li>

      <li><a href="filemanager.php" data-tooltip="<?= htmlspecialchars(__('filemanager')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <span><?= __('filemanager') ?></span>
      </a></li>     

      <li><a href="users.php" data-tooltip="<?= htmlspecialchars(__('users')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <circle cx="12" cy="7" r="4"></circle>
          <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        <span><?= __('users') ?></span>
      </a></li>

      <li><a href="settings.php" data-tooltip="<?= htmlspecialchars(__('settings')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
        <span><?= __('settings') ?></span>
      </a></li>

      <li><a href="template_manager.php" data-tooltip="<?= htmlspecialchars(__('templates')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="16" rx="2" ry="2"></rect>
          <line x1="3" y1="10" x2="21" y2="10"></line>
          <line x1="9" y1="4" x2="9" y2="20"></line>
        </svg>
        <span><?= __('templates') ?></span>
      </a></li>

      <li><a href="plugin_manager.php" data-tooltip="<?= htmlspecialchars(__('plugins')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"></circle>
          <path d="M14 10l-4 4m0-4l4 4"></path>
        </svg>
        <span><?= __('plugins') ?></span>
      </a></li>

      <li><a href="logout.php" data-tooltip="<?= htmlspecialchars(__('logout')) ?>">
        <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span><?= __('logout') ?></span>
      </a></li>
    </ul>
  </nav>
</aside>

<main role="main">
<?php if ($maintenanceActive): ?>
<div class="warning maintenance-warning">
    <strong><?= htmlspecialchars(__('maintenance_active_note')) ?>:</strong> <?= htmlspecialchars(__('maintenance_active')) ?>
</div>
<?php endif; ?>

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
<?php include 'includes/dialog.php'; ?>

<footer role="contentinfo" aria-label="Footer">
  <small>
    © <?= date('Y') ?> | 
    <a href="https://github.com/marusti/MaRu-CMS"
       target="_blank"
       rel="noopener noreferrer">
      <?= htmlspecialchars($cms['name']) ?>
    </a>
    <?= htmlspecialchars($cms['version']) ?>
    <?= htmlspecialchars($cms['status']) ?>
  </small>
</footer>

<script src="assets/js/editor.js"></script>
<script src="assets/js/core.js"></script>
<script src="assets/js/dialog.js" defer></script>

</body>
</html>
