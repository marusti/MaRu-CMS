<?php
function build_menu($categoriesPath, $pagesDir) {
    $settingsPath = __DIR__ . '/../config/settings.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

    $baseUrl = rtrim($settings['base_url'] ?? '', '/');
    $useModRewrite = !empty($settings['mod_rewrite']);

    $menuHtml = '<nav role="navigation" aria-label="Hauptmenü">';
    $menuHtml .= '<ul class="main-menu">';

    $categories = file_exists($categoriesPath) ? json_decode(file_get_contents($categoriesPath), true) : [];

    foreach ($categories as $category) {
        $categoryId = $category['id'];
        $categoryName = $category['name'];

        $menuHtml .= '<li class="menu-item">';
        $menuHtml .= '<button class="submenu-toggle" aria-haspopup="true" aria-expanded="false" onclick="toggleSubmenu(this)">';
        $menuHtml .= htmlspecialchars($categoryName) . '</button>';
        $menuHtml .= '<ul class="submenu" hidden>';

        $categoryPath = rtrim($pagesDir, '/') . '/' . $categoryId;
        if (is_dir($categoryPath)) {
            $files = scandir($categoryPath);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                    $jsonPath = $categoryPath . '/' . $file;
                    $meta = json_decode(file_get_contents($jsonPath), true);

                    // Nur veröffentlichte Seiten anzeigen
                    if (($meta['status'] ?? 'draft') !== 'published') continue;

                    $pageId = $meta['id'] ?? pathinfo($file, PATHINFO_FILENAME);
                    $title = $meta['title'] ?? ucfirst($pageId);

                    $relPath = $categoryId . '/' . $pageId;
                    $pageUrl = $useModRewrite
                        ? $baseUrl . '/' . $relPath
                        : $baseUrl . '/index.php?page=' . rawurlencode($relPath);

                    $menuHtml .= '<li><a href="' . htmlspecialchars($pageUrl) . '">' . htmlspecialchars($title) . '</a></li>';
                }
            }
        }

        $menuHtml .= '</ul>';
        $menuHtml .= '</li>';
    }

    $menuHtml .= '</ul>';
    $menuHtml .= '</nav>';

    return $menuHtml;
}
