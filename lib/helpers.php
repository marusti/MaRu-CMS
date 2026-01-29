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
                $output = $funcName();

                // Ersetze {{plugin=Pluginname}}
                $html = str_replace("{{plugin={$plugin}}}", $output, $html);

                // Ersetze auch {{Pluginname}} (für Rückwärtskompatibilität)
                $html = str_replace("{{{$plugin}}}", $output, $html);
            }
        }
    }

    return $html;
}

function render_galleries_in_content(string $html): string {
    static $lightboxIncluded = false;
    $galleryDir = __DIR__ . '/../uploads/gallery';

    $html = preg_replace_callback('/\{\{gallery=([a-zA-Z0-9_-]+)\}\}/', function($matches) use ($galleryDir) {
        $galleryName = $matches[1];

        if ($galleryName === 'alle') {
    // Basis-URL zur Galerieansicht – z.B. '/gallery'
    $baseUrl = url('gallery'); // Passe das ggf. an

    return list_all_galleries($galleryDir, $baseUrl);
}


        // Einzelne Galerie
        $path = "$galleryDir/$galleryName";
        if (!is_dir($path)) {
            return "<p><em>Galerie '$galleryName' nicht gefunden.</em></p>";
        }

        $images = array_filter(scandir($path), function($file) use ($path) {
            return is_file("$path/$file") && preg_match('/\.(jpe?g|png|gif|webp)$/i', $file);
        });

        if (empty($images)) {
            return "<p><em>Keine Bilder in Galerie '$galleryName'.</em></p>";
        }

        // metadata.json auslesen
        $metadataPath = "$path/metadata.json";
        $metadata = [];
        if (is_file($metadataPath)) {
            $jsonContent = file_get_contents($metadataPath);
            $metadata = json_decode($jsonContent, true);
            if (!is_array($metadata)) {
                $metadata = [];
            }
        }

        $baseUrl = url("uploads/gallery/$galleryName");

        $htmlGallery = '<div class="gallery">';
        foreach ($images as $img) {
            $imgUrl = htmlspecialchars("$baseUrl/$img");

            // Alt- und Title-Text holen, falls vorhanden
            if (isset($metadata[$img]) && is_array($metadata[$img])) {
                $altRaw = $metadata[$img]['alt'] ?? '';
                $titleRaw = $metadata[$img]['title'] ?? '';
                $altText = $altRaw !== '' ? htmlspecialchars($altRaw) : 'Galeriebild';
                $titleText = $titleRaw !== '' ? htmlspecialchars($titleRaw) : '';
            } else {
                $altText = 'Galeriebild';
                $titleText = '';
            }

            $titleAttr = $titleText !== '' ? " title=\"$titleText\"" : '';

            $htmlGallery .= "<img src=\"$imgUrl\" alt=\"$altText\"$titleAttr style=\"max-width:200px; margin:5px; cursor:pointer;\" class=\"lightbox-trigger\" tabindex=\"0\">";
        }
        $htmlGallery .= '</div>';

        return $htmlGallery;
    }, $html);

    if (!$lightboxIncluded) {
        $html .= <<<HTML
<style>
#lightbox {
    visibility: hidden;
    opacity: 0;
    pointer-events: none;
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    cursor: pointer;
    transition: opacity 0.3s ease;
}
#lightbox.active {
    visibility: visible;
    opacity: 1;
    pointer-events: auto;
}
#lightbox img {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(255,255,255,0.5);
    outline: none;
}
#lightbox button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    font-size: 2rem;
    color: #fff;
    background: none;
    border: none;
    cursor: pointer;
    user-select: none;
}
#lightbox #lightbox-prev {
    left: 20px;
}
#lightbox #lightbox-next {
    right: 20px;
}
</style>

<div id="lightbox" role="dialog" aria-modal="true" aria-labelledby="lightbox-label" tabindex="-1">
    <button id="lightbox-prev" aria-label="Vorheriges Bild">&#10094;</button>
    <img id="lightbox-img" src="" alt="">
    <button id="lightbox-next" aria-label="Nächstes Bild">&#10095;</button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const lightbox = document.getElementById("lightbox");
    const lightboxImg = document.getElementById("lightbox-img");
    const btnPrev = document.getElementById("lightbox-prev");
    const btnNext = document.getElementById("lightbox-next");

    let images = [];
    let alts = [];
    let currentIndex = -1;

    function updateImages() {
        images = Array.from(document.querySelectorAll(".lightbox-trigger"));
        alts = images.map(img => img.alt);
    }

    updateImages();

    function openLightbox(index) {
        currentIndex = index;
        const img = images[currentIndex];
        lightboxImg.src = img.src;
        lightboxImg.alt = alts[currentIndex] || 'Bild in der Lightbox';
        lightbox.classList.add("active");
        lightbox.focus();
    }

    function closeLightbox() {
        lightbox.classList.remove("active");
        lightboxImg.src = "";
        currentIndex = -1;
    }

    function showNext() {
        if(images.length === 0) return;
        currentIndex = (currentIndex + 1) % images.length;
        openLightbox(currentIndex);
    }

    function showPrev() {
        if(images.length === 0) return;
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        openLightbox(currentIndex);
    }

    document.body.addEventListener("click", function(e) {
        if (e.target.classList.contains("lightbox-trigger")) {
            updateImages();
            const index = images.indexOf(e.target);
            if(index !== -1) {
                openLightbox(index);
            }
        } else if (e.target === lightbox) {
            closeLightbox();
        }
    });

    btnPrev.addEventListener("click", function(e) {
        e.stopPropagation();
        showPrev();
    });

    btnNext.addEventListener("click", function(e) {
        e.stopPropagation();
        showNext();
    });

    document.addEventListener("keydown", function(e) {
        if (lightbox.classList.contains("active")) {
            switch(e.key) {
                case "Escape":
                    closeLightbox();
                    break;
                case "ArrowRight":
                    showNext();
                    break;
                case "ArrowLeft":
                    showPrev();
                    break;
                case "Tab":
                    const focusable = [btnPrev, btnNext, lightboxImg];
                    const focusedIndex = focusable.indexOf(document.activeElement);
                    if(e.shiftKey) {
                        if(focusedIndex === 0) {
                            e.preventDefault();
                            focusable[focusable.length - 1].focus();
                        }
                    } else {
                        if(focusedIndex === focusable.length - 1) {
                            e.preventDefault();
                            focusable[0].focus();
                        }
                    }
                    break;
            }
        }
    });
});
</script>
HTML;
        $lightboxIncluded = true;
    }

    return $html;
}



function list_all_galleries(string $galleryBasePath, string $baseUrl): string {
    if (!is_dir($galleryBasePath)) {
        return '<p>Galerien-Verzeichnis nicht gefunden.</p>';
    }

    $dirs = array_filter(scandir($galleryBasePath), function($item) use ($galleryBasePath) {
        return $item !== '.' && $item !== '..' && is_dir($galleryBasePath . '/' . $item);
    });

    if (empty($dirs)) {
        return '<p>Keine Galerien gefunden.</p>';
    }

    // Clean baseUrl: entferne "/gallery" am Ende, falls vorhanden
    $baseUrlClean = preg_replace('#/gallery/?$#', '', rtrim($baseUrl, '/'));

    $html = '<ul class="gallery-list" style="list-style:none; padding:0;">';
    foreach ($dirs as $dir) {
        $galleryPath = $galleryBasePath . '/' . $dir;

        $images = glob($galleryPath . '/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG,GIF}', GLOB_BRACE);
        $thumbnailUrl = '';
        if (!empty($images)) {
            $firstImage = basename($images[0]);
            $thumbnailUrl = $baseUrlClean . '/uploads/gallery/' . rawurlencode($dir) . '/' . rawurlencode($firstImage);
        }

        $galleryPage = 'gallery?name=' . urlencode($dir);

        $html .= '<li style="margin-bottom:10px;">';
        if ($thumbnailUrl) {
            $html .= '<img src="' . htmlspecialchars($thumbnailUrl) . '" alt="Thumbnail ' . htmlspecialchars($dir) . '" style="max-width:100px; max-height:100px; margin-right:10px; vertical-align:middle;">';
        }
        $html .= '<a href="' . htmlspecialchars($galleryPage) . '">' . htmlspecialchars($dir) . '</a>';
        $html .= '</li>';
    }
    $html .= '</ul>';

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

?>