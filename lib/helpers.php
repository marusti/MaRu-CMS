<?php
function run_setup_wizard() {
    echo "<p>Setup erforderlich. Bitte richten Sie das CMS ein.</p>";
}
function get_setting($key) {
    $settings = json_decode(file_get_contents(__DIR__ . '/../config/settings.json'), true);
    return $settings[$key] ?? null;
}

function delete_folder($path) {
    if (!is_dir($path)) return;
    $files = array_diff(scandir($path), ['.', '..']);
    foreach ($files as $file) {
        $full = "$path/$file";
        is_dir($full) ? delete_folder($full) : unlink($full);
    }
    rmdir($path);
}

function load_settings() {
    $file = __DIR__ . '/../config/settings.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function url(string $path): string {
    $settings = load_settings();
    $base = rtrim($settings['base_url'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}


function save_settings($settings): bool {
    $file = __DIR__ . '/../config/settings.json';
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    // prüfe, ob JSON korrekt kodiert wurde
    if ($json === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }

    $result = file_put_contents($file, $json);
    if ($result === false) {
        error_log("Failed to write settings to $file");
        return false;
    }

    return true;
}


function load_cms_info() {
    $file = __DIR__ . '/../config/cms.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [];
}

function get_folder_size($path) {
    $totalSize = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        $totalSize += $file->getSize();
    }
    return $totalSize;
}

function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function render_with_plugins(string $html): string {
    $settings = load_settings();
    $activePlugins = $settings['plugins'] ?? [];

    foreach ($activePlugins as $plugin) {
        $pluginFile = __DIR__ . "/../plugins/$plugin/plugin.php";

        if (file_exists($pluginFile)) {
            include_once $pluginFile;
            $funcName = "plugin_output_" . $plugin;

            if (function_exists($funcName)) {
                $output = $funcName() ?? '';

                // Ersetze {{plugin=Pluginname}}
                $html = str_replace("{{plugin={$plugin}}}", $output, $html);

                // Ersetze auch {{Pluginname}} (für Rückwärtskompatibilität)
                $html = str_replace("{{{$plugin}}}", $output, $html);
            }
        }
    }

    return $html;
}

function load_language_admin($lang = 'de'): array {
    $file = __DIR__ . "/../admin/lang/$lang.php";
    if (file_exists($file)) {
        return include $file;
    } else {
        return include __DIR__ . '/../admin/lang/de.php';
    }
}

function __($key, ...$args) {
    global $L;
    $text = $L[$key] ?? $key;
    return count($args) ? vsprintf($text, $args) : $text;
}

function extractPageTitleFromFile(string $filePath): ?string {
    $content = file_get_contents($filePath);
    if ($content === false) {
        return null;
    }

    // Regex to find: $pageTitle = 'some title';
    // Handles single or double quotes, ignores whitespace
    if (preg_match('/\$pageTitle\s*=\s*[\'"](.+?)[\'"]\s*;/', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function render_sitemap_from_content_with_metadata(): string {
    $settings = load_settings();
    $modRewrite = $settings['mod_rewrite'] ?? false;

    $contentDir = __DIR__ . '/../content/pages';
    if (!is_dir($contentDir)) {
        return '<p role="alert" class="sitemap-error">Content-Verzeichnis nicht gefunden.</p>';
    }

    $groups = [];

    $categories = scandir($contentDir);
    if ($categories === false) {
        return '<p role="alert" class="sitemap-error">Fehler beim Lesen des Content-Verzeichnisses.</p>';
    }

    foreach ($categories as $category) {
        if ($category === '.' || $category === '..') continue;

        $categoryPath = $contentDir . '/' . $category;
        if (!is_dir($categoryPath)) continue;

        $pages = scandir($categoryPath);
        if ($pages === false) continue;

        foreach ($pages as $page) {
            if ($page === '.' || $page === '..') continue;

            $pagePath = $categoryPath . '/' . $page;
            if (!is_file($pagePath)) continue;

            $pageName = pathinfo($page, PATHINFO_FILENAME);

            if ($modRewrite) {
                // mod_rewrite ist an: "schöne" URLs
                $relativeUrl = rawurlencode($category) . '/' . rawurlencode($pageName);
            } else {
                // mod_rewrite ist aus: Query-String URLs
                $relativeUrl = 'index.php?page=' . rawurlencode($category . '/' . $pageName);
            }

            $url = url($relativeUrl);

            $title = extractPageTitleFromFile($pagePath);
            if (!$title) {
                $title = ucfirst(str_replace(['-', '_'], ' ', $pageName));
            }

            $groups[$category][] = [
                'url' => $url,
                'title' => $title,
            ];
        }
    }

    if (empty($groups)) {
        return '<p role="alert" class="sitemap-error">Keine Seiten gefunden.</p>';
    }

    $html = '<nav id="sitemap" class="sitemap-nav" aria-label="Sitemap">';
    $html .= '<ul class="sitemap-list">';

    foreach ($groups as $category => $entries) {
        $html .= '<li class="sitemap-group">';
        $html .= '<h2 class="sitemap-group-title">' . htmlspecialchars(ucfirst($category), ENT_QUOTES, 'UTF-8') . '</h2>';
        $html .= '<ul class="sitemap-sublist">';

        foreach ($entries as $entry) {
            $safeUrl = htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8');
            $safeTitle = htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8');
            $html .= '<li class="sitemap-item"><a class="sitemap-link" href="' . $safeUrl . '" title="Zur Seite ' . $safeTitle . '">' . $safeTitle . '</a></li>';
        }

        $html .= '</ul></li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

function getAllPages(): array {
    $settings = load_settings();
    $modRewrite = $settings['mod_rewrite'] ?? false;

    $contentDir = __DIR__ . '/../content/pages';
    $pages = [];

    if (!is_dir($contentDir)) {
        return $pages; // leeres Array, falls Ordner nicht existiert
    }

    $categories = scandir($contentDir);
    if ($categories === false) {
        return $pages;
    }

    foreach ($categories as $category) {
        if ($category === '.' || $category === '..') continue;

        $categoryPath = $contentDir . '/' . $category;
        if (!is_dir($categoryPath)) continue;

        $files = scandir($categoryPath);
        if ($files === false) continue;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $filePath = $categoryPath . '/' . $file;
            if (!is_file($filePath)) continue;

            $filename = pathinfo($file, PATHINFO_FILENAME);

            // URL generieren je nach mod_rewrite Einstellung
            if ($modRewrite) {
                $url = url(rawurlencode($category) . '/' . rawurlencode($filename));
            } else {
                $url = url('index.php?page=' . rawurlencode($category . '/' . $filename));
            }

            // Titel aus Datei extrahieren oder Fallback
            $title = extractPageTitleFromFile($filePath);
            if (!$title) {
                $title = ucfirst(str_replace(['-', '_'], ' ', $filename));
            }

            $pages[] = [
                'category' => $category,
                'filename' => $filename,
                'filepath' => $filePath,
                'url' => $url,
                'title' => $title,
            ];
        }
    }

    return $pages;
}

function rrmdir(string $dir): bool {
    if (!is_dir($dir)) return false;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $fileinfo->isDir()
            ? rmdir($fileinfo->getRealPath())
            : unlink($fileinfo->getRealPath());
    }

    return rmdir($dir);
}

?>