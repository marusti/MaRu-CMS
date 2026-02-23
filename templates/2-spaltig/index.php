<?php
// Basis-URL aus settings.json laden
$settingsFile = __DIR__ . '/../../config/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
$baseUrl = $settings['base_url'] ?? ''; // Standardwert, falls keine Basis-URL gesetzt ist
$siteName = $settings['site_name'] ?? 'Meine Website';

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>
<?= htmlspecialchars(
    !empty($pageTitle)
        ? $pageTitle . ' | ' . $siteName
        : $siteName,
    ENT_QUOTES,
    'UTF-8'
) ?>
</title>
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
    <h1><?= htmlspecialchars($siteName) ?></h1>
</header>

<div class="layout">
    <aside>
    <!-- Hamburger-Button immer sichtbar -->
    <button class="menu-toggle">☰ Menü</button>

        {{menu}}

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
    const menuToggle = document.querySelector('.menu-toggle');
    const mainMenu = document.querySelector('.main-menu');

    if (menuToggle && mainMenu) {
        menuToggle.addEventListener('click', function() {
            const isOpen = mainMenu.style.maxHeight && mainMenu.style.maxHeight !== "0px";
            if (isOpen) {
                mainMenu.style.maxHeight = "0";
            } else {
                mainMenu.style.maxHeight = mainMenu.scrollHeight + "px"; // Dynamische Höhe
            }
        });
    }

    // Submenu Toggle für mobile
    const submenuButtons = document.querySelectorAll('.submenu-toggle');
    submenuButtons.forEach(button => {
        button.addEventListener('click', function() {
            const submenu = button.nextElementSibling;
            const menuItem = button.closest('.menu-item');
            
            if (!submenu) return;

            // Menüpunkt mit Untermenü öffnet oder schließt
            if (submenu.style.maxHeight && submenu.style.maxHeight !== "0px") {
                submenu.style.maxHeight = "0";
                button.setAttribute('aria-expanded', 'false');
                menuItem.classList.remove('open');
            } else {
                submenu.style.maxHeight = submenu.scrollHeight + "px"; // Dynamische Höhe
                button.setAttribute('aria-expanded', 'true');
                menuItem.classList.add('open');
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
