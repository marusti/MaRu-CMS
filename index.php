<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();

/* ==========================
   SETTINGS LADEN
========================== */

$settingsFile = __DIR__ . '/config/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];

/* ==========================
   BASE URL
========================== */

if (empty($settings['base_url'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $baseUrl = rtrim($protocol . '://' . $host . $scriptDir, '/');

    $settings['base_url'] = $baseUrl;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    $baseUrl = rtrim($settings['base_url'], '/');
}

/* ==========================
   PLUGINS
========================== */

$activePlugins = $settings['plugins'] ?? [];

/* ==========================
   MAINTENANCE
========================== */

if (!empty($settings['maintenance']) && empty($_SESSION['admin'])) {
    include __DIR__ . '/maintenance.php';
    exit;
}

/* ==========================
   REQUEST PAGE
========================== */

$requestedPage = isset($_GET['page']) ? trim($_GET['page'], '/') : '';
if ($requestedPage === '') {
    $requestedPage = $settings['homepage'] ?? 'erste/erste-seite';
}

/* ==========================
   PATHS
========================== */

$mdPath   = __DIR__ . "/content/pages/$requestedPage.md";
$jsonPath = __DIR__ . "/content/pages/$requestedPage.json";

/* ==========================
   META DEFAULTS
========================== */

$pageTitle = '';
$pageMetaDescription = '';
$pageMetaKeywords = '';
$pageRobots = 'index, follow';
$pageContent = '';
$pageDefaultImage = '';
$pageDefaultImageAlt = '';

/* ==========================
   SIMPLE MARKDOWN ENGINE
========================== */

function render_markdown($text) {

    // Sicherheit: Script-Tags entfernen
    $text = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $text);

    /*
     * Block-HTML schützen (nicht wrappen!)
     */
    $blockTags = '(style|div|form|section|article|header|footer|nav|main|table|ul|ol|li|pre|code|blockquote)';
    
    $placeholders = [];
    $i = 0;

    $text = preg_replace_callback(
        "#<($blockTags)(.*?)>(.*?)</\\1>#is",
        function($m) use (&$placeholders, &$i) {
            $key = "%%HTML_BLOCK_$i%%"; // Ersetze Block-HTML mit einem Platzhalter
            $placeholders[$key] = $m[0]; // Speichere den Original-HTML-Block
            $i++;
            return $key; // Gib den Platzhalter zurück
        },
        $text
    );

    // Markdown Syntax umwandeln
    // Überschriften
    $text = preg_replace('/\[h1\|(.*?)\]/', '<h1 class="heading1">$1</h1>', $text);
    $text = preg_replace('/\[h2\|(.*?)\]/', '<h2 class="heading2">$1</h2>', $text);
    $text = preg_replace('/\[h3\|(.*?)\]/', '<h3 class="heading3">$1</h3>', $text);
    $text = preg_replace('/\[h4\|(.*?)\]/', '<h4 class="heading4">$1</h4>', $text);
    $text = preg_replace('/\[h5\|(.*?)\]/', '<h5 class="heading5">$1</h5>', $text);
    $text = preg_replace('/\[h6\|(.*?)\]/', '<h6 class="heading6">$1</h6>', $text);

    // Textformatierungen
    $text = preg_replace('/\[bold\|(.*?)\]/', '<b>$1</b>', $text);
    $text = preg_replace('/\[italic\|(.*?)\]/', '<i>$1</i>', $text);
    $text = preg_replace('/\[underline\|(.*?)\]/', '<u>$1</u>', $text);

    // Blockquote
    $text = preg_replace('/\[quote\|(.*?)\]/', '<blockquote>$1</blockquote>', $text);

    // Codeblock
    $text = preg_replace('/\[codeblock\|(.*?)\]/s', '<pre><code>$1</code></pre>', $text);

    // Listen
    $text = preg_replace_callback('/\[listunordered\|([\s\S]*?)\]/', function($matches) {
        $items = explode("\n", $matches[1]);
        $items = array_map(function($item) {
            return $item ? "<li>$item</li>" : '';
        }, $items);
        return "<ul class='listunordered'>" . implode('', $items) . "</ul>";
    }, $text);

    $text = preg_replace_callback('/\[listordered\|([\s\S]*?)\]/', function($matches) {
        $items = explode("\n", $matches[1]);
        $items = array_map(function($item) {
            return $item ? "<li>$item</li>" : '';
        }, $items);
        return "<ol class='listordered'>" . implode('', $items) . "</ol>";
    }, $text);

    // Links
    $text = preg_replace('/\[link\|(.*?)\|(.*?)\]/', '<a href="$1">$2</a>', $text);

    // Bilder
    $text = preg_replace('/\[image\|(.*?)\|(.*?)\]/', '<img src="$1" alt="$2"/>', $text);

    // Absätze nur für reinen Text
    $parts = preg_split("/\n\s*\n/", $text);
    $text = '<p>' . implode('</p><p>', $parts) . '</p>';

    // Platzhalter zurücksetzen
    foreach ($placeholders as $k => $html) {
        $text = str_replace($k, $html, $text); // Ersetze Platzhalter mit originalem HTML
    }

    return $text;
}





/* ==========================
   CONTENT LOADING
========================== */

// Sitemap
if (basename($requestedPage) === 'sitemap') {

    $pageTitle = 'Sitemap';
    require_once __DIR__ . '/lib/helpers.php';
    $pageContent = '<h1>Sitemap</h1>' . render_sitemap_from_content_with_metadata();

} elseif (file_exists($mdPath)) {

    // Markdown
    $rawMd = file_get_contents($mdPath);
    $pageContent = render_markdown($rawMd);

    // JSON Meta
    if (file_exists($jsonPath)) {
        $pageData = json_decode(file_get_contents($jsonPath), true);

        $pageTitle = $pageData['title'] ?? '';
        $pageMetaDescription = $pageData['description'] ?? '';
        $pageMetaKeywords = $pageData['keywords'] ?? '';
        $pageRobots = $pageData['robots'] ?? 'index, follow';

        if (!empty($pageData['default_image'])) {
            $pageDefaultImage = $pageData['default_image'];
            $pageDefaultImageAlt = $pageData['default_image_alt'] ?? '';
        }
    }

    require_once __DIR__ . '/lib/helpers.php';
    $pageContent = render_galleries_in_content($pageContent);

} else {

    // 404
    http_response_code(404);
    $requestedPage = '404';

    $md404 = __DIR__ . "/content/pages/404.md";
    if (file_exists($md404)) {
        $pageContent = render_markdown(file_get_contents($md404));
    } else {
        $pageContent = '<h1>404 - Seite nicht gefunden</h1>';
    }
}

/* ==========================
   TEMPLATE
========================== */

$template = $settings['template'] ?? 'default';
$templatePath = __DIR__ . "/templates/$template/index.php";

if (file_exists($templatePath)) {

    ob_start();
    include $templatePath;
    $templateHtml = ob_get_clean();

    /* MENU */
    require_once __DIR__ . '/core/build_menu.php';
    $menuHtml = build_menu(__DIR__ . '/content/categories.json', __DIR__ . '/content/pages');

    require_once __DIR__ . '/lib/helpers.php';

    /* PLUGIN CSS */
    preg_match_all('/{{plugin=([a-z0-9_-]+)}}/i', $pageContent, $matchesPage);
    $pluginsInPage = array_map('strtolower', $matchesPage[1] ?? []);

    preg_match_all('/{{plugin=([a-z0-9_-]+)}}/i', $templateHtml, $matchesTemplate);
    $pluginsInTemplate = array_map('strtolower', $matchesTemplate[1] ?? []);

    $allUsedPlugins = array_unique(array_merge($pluginsInPage, $pluginsInTemplate));
    $pluginsToLoadCss = array_intersect($allUsedPlugins, array_map('strtolower', $activePlugins));

    $pluginCssLinks = '';
    foreach ($pluginsToLoadCss as $plugin) {
        $cssFile = __DIR__ . "/plugins/$plugin/plugin.css";
        if (file_exists($cssFile)) {
            $pluginCssLinks .= '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl . "/plugins/$plugin/plugin.css") . '">' . "\n";
        }
    }

    /* CANONICAL */
    $homepage = $settings['homepage'] ?? 'erste/erste-seite';
    if (!isset($canonicalUrl)) {
        if ($requestedPage === $homepage) {
            $canonicalUrl = $baseUrl . '/';
        } else {
            $canonicalUrl = $baseUrl . '/' . ltrim($requestedPage, '/');
        }
    }

    $canonicalUrl = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
    $canonicalLinkTag = '<link rel="canonical" href="' . $canonicalUrl . '">' . "\n";
    $robotsMetaTag = '<meta name="robots" content="' . htmlspecialchars($pageRobots, ENT_QUOTES, 'UTF-8') . '">' . "\n";

    $output = str_replace('</head>', $canonicalLinkTag . $robotsMetaTag . $pluginCssLinks . '</head>', $templateHtml);

    /* PLACEHOLDERS */
    $output = str_replace('{{menu}}', $menuHtml, $output);

    /* ARTICLE SCHEMA */
    if (!empty($pageDefaultImage)) {
        $pageDefaultImage = preg_replace('#^\.\./#', $baseUrl . '/', $pageDefaultImage);
        $altText = !empty($pageDefaultImageAlt) ? htmlspecialchars($pageDefaultImageAlt, ENT_QUOTES, 'UTF-8') : 'Artikelbild';

        $imageHtml = '<img src="' . htmlspecialchars($pageDefaultImage, ENT_QUOTES, 'UTF-8') . '" alt="' . $altText . '" itemprop="image" />';
    } else {
        $imageHtml = '';
    }

    $articleContent = '<article itemscope itemtype="https://schema.org/Article">
        <header class="article-header">
            <h1 itemprop="headline">' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>
        </header>
        ' . $imageHtml . '
        <div itemprop="articleBody">' . $pageContent . '</div>
    </article>';

    $output = str_replace('{{content}}', $articleContent, $output);
    $output = str_replace('{{base_url}}', $baseUrl, $output);

    /* SITEMAP LINK */
    $sitemapLink = !empty($settings['mod_rewrite'])
        ? $baseUrl . '/sitemap'
        : $baseUrl . '/index.php?page=sitemap';

    $output = str_replace(
        '{{Sitemap}}',
        '<a href="' . htmlspecialchars($sitemapLink, ENT_QUOTES, 'UTF-8') . '" class="sitemap-link">Sitemap anzeigen</a>',
        $output
    );

    /* PLUGIN SHORTCODES */
    $output = render_with_plugins($output);

    /* SCRIPTS */
    $menuScript = '<script src="' . $baseUrl . '/core/assets/js/menu.js" defer></script>';
    $galleryScript = '<script src="' . $baseUrl . '/core/assets/js/gallery.js" defer></script>';
    $output = str_replace('</body>', $menuScript . "\n" . $galleryScript . "\n</body>", $output);

    echo $output;

} else {
    echo "<h1>Template nicht gefunden</h1>";
}
