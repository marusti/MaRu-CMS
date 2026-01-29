<?php
// Basis-URL aus settings.json laden
$settingsFile = __DIR__ . '/../../config/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
$baseUrl = $settings['base_url'] ?? ''; // Standardwert, falls keine Basis-URL gesetzt ist
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Willkommen') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($pageMetaDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageMetaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($pageMetaKeywords)): ?>
        <meta name="keywords" content="<?= htmlspecialchars($pageMetaKeywords) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/templates/2-spaltig/css/style.css">
</head>
<body>

<header>
    <h1><?= htmlspecialchars($pageTitle ?? 'Willkommen') ?></h1>
</header>

<div class="layout">
    <aside>
    <!-- Hamburger-Button immer sichtbar -->
    <button class="menu-toggle" onclick="toggleMenu()">☰ Menü</button>
    <nav role="navigation" aria-label="Hauptmenü">
        {{menu}}
    </nav>
</aside>

    <main>
        {{plugin=cookieconsent}}
        {{plugin=breadcrumb}}
        {{content}}
    </main>
</div>

<footer>
    <p>© <?= date('Y') ?> Mein CMS</p>
    <p>{{Sitemap}}</p>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Hamburger Menü Toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const mainMenu = document.querySelector('.main-menu');

    if (menuToggle && mainMenu) {
        menuToggle.addEventListener('click', function() {
            const isOpen = mainMenu.style.maxHeight && mainMenu.style.maxHeight !== "0px";
            if (isOpen) {
                mainMenu.style.maxHeight = "0";
            } else {
                mainMenu.style.maxHeight = mainMenu.scrollHeight + "px";
            }
        });
    }

    // Submenu Toggle für mobile
    const submenuButtons = document.querySelectorAll('.submenu-toggle');
    submenuButtons.forEach(button => {
        button.addEventListener('click', function() {
            const submenu = button.nextElementSibling;
            if (!submenu) return;

            // Hidden entfernen, falls gesetzt
            if (submenu.hasAttribute('hidden')) submenu.removeAttribute('hidden');

            const isOpen = submenu.style.maxHeight && submenu.style.maxHeight !== "0px";
            if (isOpen) {
                submenu.style.maxHeight = "0";
                button.setAttribute('aria-expanded', 'false');
            } else {
                submenu.style.maxHeight = submenu.scrollHeight + "px";
                button.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Alle Submenus initial auf 0 setzen
    document.querySelectorAll('.submenu').forEach(sub => {
        sub.style.maxHeight = "0";
        sub.style.overflow = "hidden";
        sub.style.transition = "max-height 0.3s ease-out";
    });
});
</script>





</body>
</html>
