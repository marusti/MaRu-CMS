<?php
function build_menu($categoriesPath, $pagesDir) {
    $settingsPath = __DIR__ . '/../config/settings.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

    $baseUrl = rtrim($settings['base_url'] ?? '', '/');
    $useModRewrite = !empty($settings['mod_rewrite']);

    $categories = file_exists($categoriesPath) ? json_decode(file_get_contents($categoriesPath), true) : [];

    // 🔹 Kategorien nach order sortieren, default 9999
    usort($categories, fn($a, $b) => ($a['order'] ?? 9999) <=> ($b['order'] ?? 9999));

    $menuHtml = '<nav role="navigation" aria-label="Hauptmenü">';
    $menuHtml .= '<ul class="main-menu">';

    // Rekursive Funktion zum Bauen des Menüs
    $buildCategoryMenu = function($categories, $pagesDir, $baseUrl, $useModRewrite) use (&$buildCategoryMenu) {
        $html = '';

        // Kategorien sortieren
        usort($categories, fn($a, $b) => ($a['order'] ?? 9999) <=> ($b['order'] ?? 9999));

        foreach ($categories as $category) {
            $categoryId = $category['id'];
            $categoryName = $category['name'];

            $html .= '<li class="menu-item">';
            $html .= '<button class="submenu-toggle" aria-haspopup="true" aria-expanded="false" onclick="toggleSubmenu(this)">';
            $html .= htmlspecialchars($categoryName) . '</button>';

            $html .= '<ul class="submenu" hidden>';

            // Seiten innerhalb der Kategorie
            $categoryPath = rtrim($pagesDir, '/') . '/' . $categoryId;
            $pages = [];
            if (is_dir($categoryPath)) {
                $files = scandir($categoryPath);

                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;

                    $meta = json_decode(file_get_contents($categoryPath . '/' . $file), true);
                    if (!is_array($meta)) continue;
                    if (($meta['status'] ?? 'draft') !== 'published') continue;

                    $pageId = $meta['id'] ?? pathinfo($file, PATHINFO_FILENAME);
                    $pages[] = [
                        'id' => $pageId,
                        'title' => $meta['title'] ?? ucfirst($pageId),
                        'order' => isset($meta['order']) ? (int)$meta['order'] : 9999
                    ];
                }

                // Seiten nach order sortieren
                usort($pages, fn($a, $b) => $a['order'] <=> $b['order']);

                foreach ($pages as $page) {
                    $relPath = $categoryId . '/' . $page['id'];
                    $pageUrl = $useModRewrite
                        ? $baseUrl . '/' . $relPath
                        : $baseUrl . '/index.php?page=' . rawurlencode($relPath);

                    $html .= '<li><a href="' . htmlspecialchars($pageUrl) . '">' .
                             htmlspecialchars($page['title']) . '</a></li>';
                }
            }

            // Sub-Kategorien rekursiv einfügen
            if (!empty($category['children'])) {
                $html .= $buildCategoryMenu($category['children'], $pagesDir, $baseUrl, $useModRewrite);
            }

            $html .= '</ul>';
            $html .= '</li>';
        }

        return $html;
    };

    $menuHtml .= $buildCategoryMenu($categories, $pagesDir, $baseUrl, $useModRewrite);

    $menuHtml .= '</ul>';
    $menuHtml .= '</nav>';

    return $menuHtml;
}