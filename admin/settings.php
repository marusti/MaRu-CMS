<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/messages.php';

$messages = []; // zentrale Message-Liste

/* =========================
   CSRF-Helfer
   ========================= */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* =========================
   Admin-Check
   ========================= */
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

/* =========================
   Variablen
   ========================= */
$pageTitle = __('settings');
$configFile = __DIR__ . '/../config/settings.json';
$pagesBaseDir = __DIR__ . '/../content/pages';

/* =========================
   Einstellungen laden
   ========================= */
$settings = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

if (json_last_error() !== JSON_ERROR_NONE) {
    $settings = [];
}

$settingsOld = $settings; // 🔑 für Change-Detection

/* =========================
   Seiten nach Kategorien laden
   ========================= */
$pageGroups = [];

foreach (glob($pagesBaseDir . '/*', GLOB_ONLYDIR) as $categoryDir) {
    $category = basename($categoryDir);

    foreach (glob($categoryDir . '/*.json') as $metaFile) {
        $id = basename($metaFile, '.json');
        $meta = json_decode(file_get_contents($metaFile), true);
        $title = $meta['title'] ?? $id;

        $pageGroups[$category][$category . '/' . $id] = $title;
    }
}

ksort($pageGroups);
foreach ($pageGroups as &$pages) {
    asort($pages);
}
unset($pages);

/* =========================
   POST: Speichern
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !csrf_valid($_POST['csrf_token'])) {
        addMessage($messages, __('csrf_error') ?: 'Ungültiger CSRF-Token!', 'error');
        $_SESSION['messages'] = $messages;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    /* --- neue Settings setzen --- */
    $settings['site_name'] = $_POST['site_name'] ?? '';
    $settings['homepage'] = $_POST['homepage'] ?? '';
    $settings['language'] = $_POST['language'] ?? 'de';
    $settings['maintenance'] = isset($_POST['maintenance']);
    $settings['generate_sitemap'] = isset($_POST['generate_sitemap']);
    $settings['mod_rewrite'] = isset($_POST['mod_rewrite']);
    $settings['content_languages'] = $_POST['content_languages'] ?? '';

    /* --- Base URL dynamisch --- */
    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $_SERVER['SERVER_PORT'] == 443
    ) ? 'https' : 'http';

    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $basePath = preg_replace('#/admin(/.*)?$#', '', $scriptDir);

    $settings['base_url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath;

    /* --- settings.json schreiben --- */
    if (!file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
        addMessage($messages, 'Fehler beim Schreiben der settings.json!', 'error');
        $_SESSION['messages'] = $messages;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    addMessage($messages, __('settings_saved'), 'success');

    /* =========================
       .htaccess
       ========================= */
    $parsedUrl = parse_url($settings['base_url']);
    $rewriteBase = preg_replace('#/+#', '/', ($parsedUrl['path'] ?? '/') . '/');

    $htaccessFile = __DIR__ . '/../.htaccess';

    if ($settings['mod_rewrite']) {
        $htaccessContent = <<<HTACCESS
Options -Indexes

RewriteEngine On
RewriteBase {$rewriteBase}

# Admin niemals umleiten
RewriteRule ^admin(/.*)?$ - [L]

# echte Dateien direkt ausliefern
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# alles andere an index.php
RewriteRule ^(.*)$ index.php?page=$1 [QSA,L]

HTACCESS;

        if (!file_put_contents($htaccessFile, $htaccessContent)) {
            addMessage($messages, 'Fehler beim Schreiben der .htaccess!', 'error');
        }
    } elseif (file_exists($htaccessFile)) {
        unlink($htaccessFile);
    }

    /* =========================
       Sitemap: nur bei relevanten Änderungen
       ========================= */
    $sitemapRelevantKeys = ['homepage', 'mod_rewrite', 'generate_sitemap'];
    $sitemapNeedsUpdate = false;
    foreach ($sitemapRelevantKeys as $key) {
        if (($settingsOld[$key] ?? null) !== ($settings[$key] ?? null)) {
            $sitemapNeedsUpdate = true;
            break;
        }
    }

    $sitemapFile = __DIR__ . '/../sitemap.xml';
    $sitemapDir = dirname($sitemapFile);

    if ($sitemapNeedsUpdate && is_writable($sitemapDir)) {
        if ($settings['generate_sitemap']) {
            $entries = [];
            $cmsBaseUrl = rtrim($settings['base_url'], '/');

            foreach (glob($pagesBaseDir . '/*/*.json') as $metaFile) {
                $relPath = str_replace([$pagesBaseDir . '/', '.json'], '', $metaFile);

                $url = $settings['mod_rewrite']
                    ? "$cmsBaseUrl/$relPath"
                    : "$cmsBaseUrl/?page=$relPath";

                $lastmod = date('Y-m-d', filemtime($metaFile));

                $entries[] = "<url>
    <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>
    <lastmod>$lastmod</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
</url>";
            }

            $homepageUrl = htmlspecialchars($cmsBaseUrl . '/', ENT_XML1, 'UTF-8');

            $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $sitemap .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            $sitemap .= "<url>
    <loc>$homepageUrl</loc>
    <lastmod>" . date('Y-m-d') . "</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
</url>\n";
            $sitemap .= implode("\n", $entries);
            $sitemap .= "\n</urlset>";

            file_put_contents($sitemapFile, $sitemap);
            addMessage($messages, __('sitemap_created'), 'success');

        } elseif (file_exists($sitemapFile)) {
            unlink($sitemapFile);
            addMessage($messages, __('sitemap_deleted'), 'info');
        }
    }

    // CSRF rotieren
    unset($_SESSION['csrf_token']);

    // Alle Messages in Session speichern für Redirect
    $_SESSION['messages'] = $messages;

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

ob_start();
?>

<h1><?= __('settings') ?></h1>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <fieldset>
        <legend><?= __('general') ?></legend>
        <div class="maru-settings">
        <label for="site_name"><?= __('site_name') ?>:</label>
        <input type="text" id="site_name" name="site_name"
               value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
               </div>
    </fieldset>

    <fieldset>
        <legend><?= __('homepage_language') ?></legend>
<div class="maru-settings">
        <label for="homepage"><?= __('choose_homepage') ?>:</label>
        <select id="homepage" name="homepage" required>
            <?php foreach ($pageGroups as $category => $pages): ?>
                <optgroup label="<?= htmlspecialchars($category) ?>">
                    <?php foreach ($pages as $id => $title): ?>
                        <option value="<?= htmlspecialchars($id) ?>"
                            <?= ($settings['homepage'] ?? '') === $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($title) ?> (<?= htmlspecialchars($id) ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        </div>

<div class="maru-settings">
        <label for="language"><?= __('language') ?>:</label>
        <select id="language" name="language">
            <option value="de" <?= ($settings['language'] ?? '') === 'de' ? 'selected' : '' ?>>
                <?= __('german') ?>
            </option>
            <option value="en" <?= ($settings['language'] ?? '') === 'en' ? 'selected' : '' ?>>
                <?= __('english') ?>
            </option>
        </select>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= __('system') ?></legend>
        <input type="checkbox" id="maintenance" name="maintenance"
            <?= !empty($settings['maintenance']) ? 'checked' : '' ?>>
        <label for="maintenance"><?= __('enable_maintenance') ?></label>
    </fieldset>

    <fieldset>
        <legend><?= __('sitemap_settings') ?></legend>
        <input type="checkbox" id="generate_sitemap" name="generate_sitemap"
            <?= !empty($settings['generate_sitemap']) ? 'checked' : '' ?>>
        <label for="generate_sitemap"><?= __('generate_sitemap_on_save') ?></label>
    </fieldset>

    <fieldset>
        <legend><?= __('url_settings') ?></legend>
        <input type="checkbox" id="mod_rewrite" name="mod_rewrite"
            <?= !empty($settings['mod_rewrite']) ? 'checked' : '' ?>>
        <label for="mod_rewrite"><?= __('enable_mod_rewrite') ?></label>
    </fieldset>
    
    <fieldset>
    <legend><?= __('content_languages') ?></legend>
    <div class="maru-settings">
        <label for="content_languages"><?= __('content_languages_label') ?>:</label>
        <input type="text" id="content_languages" name="content_languages" 
               value="<?= htmlspecialchars($settings['content_languages'] ?? '') ?>" 
               placeholder="z.B. de, en, fr">
    </div>
</fieldset>

<fieldset>
    <legend>Sonderseiten</legend>
    <div class="maru-settings">
        <a href="edit_page.php?id=404" class="button">
            404-Seite bearbeiten
        </a>
    </div>
</fieldset>

    <button type="submit"><?= __('save') ?></button>
</form>

<?php
$content = ob_get_clean();
include '_layout.php';
?>