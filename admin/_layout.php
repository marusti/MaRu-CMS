<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$settingsFile = __DIR__ . '/../config/settings.json';
$maintenanceActive = false;
if (file_exists($settingsFile)) {
    $settingsContent = file_get_contents($settingsFile);
$settings = json_decode($settingsContent, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $settings = [];
}
$maintenanceActive = !empty($settings['maintenance']);

}

require_once __DIR__ . '/init.php';

if (!isset($pageTitle)) $pageTitle = 'Admin';
if (!isset($content)) $content = '';
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    (function() {
      const saved = localStorage.getItem('theme') || 'light';
      if (saved !== 'light') {
        document.documentElement.classList.add(saved);
      }
    })();
  </script>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header role="banner" aria-label="Admin Header" style="display: flex; align-items: center; justify-content: space-between; gap: 1em;">
 <!-- Logo hinzufügen -->
  <img src="assets/images/logo.png" alt="Website Logo" style="height: 50px;">
  
  <h1><?= htmlspecialchars(__('admin_dashboard')) ?></h1>

<label for="theme-select" class="sr-only"><?= htmlspecialchars(__('theme_toggle')) ?></label>
<select id="theme-select" aria-label="<?= htmlspecialchars(__('theme_toggle')) ?>">
  <option value="light">☀️ Light</option>
  <option value="dark-mode">🌙 Dark</option>
  <option value="glass-mode">🪟 Glass</option>
</select>

  <!-- Vorschau-Link rechts ausgerichtet -->
  <a href="../" target="_blank" title="<?= htmlspecialchars(__('preview')) ?>" style="display: flex; align-items: center; color: var(--text-color-dark); margin-left: auto;">
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
      
      <li><a href="gallery_manager.php" data-tooltip="<?= htmlspecialchars(__('gallery_admin') ?? 'Galerie verwalten') ?>">
  <svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
    <rect x="3" y="3" width="18" height="14" rx="2" ry="2"></rect>
    <circle cx="8" cy="8" r="2.5"></circle>
    <path d="M21 21l-6-6-3 3-4-4-5 5"></path>
  </svg>
  <span><?= __('gallery_admin') ?? 'Galerie verwalten' ?></span>
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
<div style="background: #ffefc1; padding: 10px; margin-bottom: 1em; border-left: 5px solid #ffcc00;">
    <strong><?= htmlspecialchars(__('maintenance_active_note')) ?>:</strong> <?= htmlspecialchars(__('maintenance_active')) ?>
</div>
<?php endif; ?>

<?= $content ?>
</main>

<footer role="contentinfo" aria-label="Footer">
  <p>© <?= date('Y') ?> Dein CMS</p>
</footer>

<script src="assets/js/editor.js"></script>
<script src="assets/js/core.js"></script>

</body>
</html>
