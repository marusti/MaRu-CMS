<?php
require_once __DIR__ . '/init.php';

// --- CSRF-Funktionen ---
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// --- Admin-Check ---
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// --- Variablen ---
$pageTitle = __('settings');
$configFile = __DIR__ . '/../config/settings.json';
$pagesBaseDir = __DIR__ . '/../content/pages';
$message = '';

// Einstellungen laden
$settings = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

// --- Seiten nach Kategorien laden ---
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

// --- POST: Speichern / Sitemap / .htaccess ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !csrf_valid($_POST['csrf_token'])) {
        $message = __('csrf_error') ?: 'Ungültiger CSRF-Token!';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1&msg=' . urlencode($message));
        exit;
    }

    $settings['site_name'] = $_POST['site_name'] ?? '';
    $settings['homepage'] = $_POST['homepage'] ?? '';
    $settings['language'] = $_POST['language'] ?? 'de';
    $settings['maintenance'] = isset($_POST['maintenance']);
    $settings['generate_sitemap'] = isset($_POST['generate_sitemap']);
    $settings['mod_rewrite'] = isset($_POST['mod_rewrite']);

    // Base URL dynamisch
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443
        ? 'https' : 'http';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $settings['base_url'] = $scheme . '://' . $_SERVER['HTTP_HOST'] . preg_replace('#/admin/?$#', '', $scriptDir);

    // settings.json schreiben
    file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $baseUrl = $settings['base_url'] ?? '';
    $parsedUrl = parse_url($baseUrl);
    $basePath = ($parsedUrl['path'] ?? '/') . '/';
    $basePath = preg_replace('#/+#', '/', $basePath);

    // --- .htaccess ---
    $htaccessFile = __DIR__ . '/../.htaccess';
    if (!empty($settings['mod_rewrite'])) {
        $htaccessContent = <<<HTACCESS
RewriteEngine On
RewriteBase {$basePath}
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ index.php?page=$1 [QSA,L]

HTACCESS;
        file_put_contents($htaccessFile, $htaccessContent);
    } elseif (file_exists($htaccessFile)) {
        unlink($htaccessFile);
    }

    // --- Sitemap ---
    $sitemapFile = __DIR__ . '/../sitemap.xml';
    $sitemapDir = dirname($sitemapFile);

    if (is_writable($sitemapDir)) {
        if ($settings['generate_sitemap']) {
            $cmsBaseUrl = $settings['base_url'];
            $entries = [];
            foreach (glob($pagesBaseDir . '/*/*.json') as $metaFile) {
                $relPath = str_replace([$pagesBaseDir . '/', '.json'], '', $metaFile);
                if (!empty($settings['mod_rewrite'])) {
                    $url = "$cmsBaseUrl/$relPath";
                } else {
                    $url = "$cmsBaseUrl/?page=$relPath";
                }
                $lastmod = date('Y-m-d', filemtime($metaFile));
                $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $entries[] = "<url>
    <loc>$safeUrl</loc>
    <lastmod>$lastmod</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
</url>";
            }

            $homepageUrl = htmlspecialchars($cmsBaseUrl . '/', ENT_QUOTES, 'UTF-8');
            $lastmodHome = date('Y-m-d');

            $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $sitemap .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
            $sitemap .= "<url>
    <loc>$homepageUrl</loc>
    <lastmod>$lastmodHome</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
</url>\n";
            $sitemap .= implode("\n", $entries);
            $sitemap .= "\n</urlset>";

            file_put_contents($sitemapFile, $sitemap);
            $message = __('sitemap_created');
        } elseif (file_exists($sitemapFile)) {
            unlink($sitemapFile);
            $message = __('sitemap_deleted');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?saved=1&msg=' . urlencode($message));
    exit;
}

// --- GET: Message aus URL ---
$message = '';
if (isset($_GET['saved'])) {
    $message = !empty($_GET['msg']) ? htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8') : __('settings_saved');
}

ob_start();
?>

<h1><?= __('settings') ?></h1>

<?php if ($message): ?>
    <?php $isError = str_starts_with($message, __('error')) || str_contains($message, 'Ungültig'); ?>
    <p style="color: <?= $isError ? 'red' : 'green' ?>;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <fieldset>
        <legend><?= __('general') ?></legend>
        <label for="site_name"><?= __('site_name') ?>:</label>
        <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" required>
    </fieldset>

    <fieldset>
        <legend><?= __('homepage_language') ?></legend>
        <label for="homepage"><?= __('choose_homepage') ?>:</label>
        <select id="homepage" name="homepage" required>
            <?php foreach ($pageGroups as $category => $pages): ?>
                <optgroup label="<?= htmlspecialchars($category) ?>">
                    <?php foreach ($pages as $id => $title): ?>
                        <option value="<?= htmlspecialchars($id) ?>" <?= ($settings['homepage'] ?? '') === $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($title) ?> (<?= htmlspecialchars($id) ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>

        <label for="language"><?= __('language') ?>:</label>
        <select id="language" name="language">
            <option value="de" <?= ($settings['language'] ?? '') === 'de' ? 'selected' : '' ?>><?= __('german') ?></option>
            <option value="en" <?= ($settings['language'] ?? '') === 'en' ? 'selected' : '' ?>><?= __('english') ?></option>
        </select>
    </fieldset>

    <fieldset>
        <legend><?= __('system') ?></legend>
        <input type="checkbox" id="maintenance" name="maintenance" <?= !empty($settings['maintenance']) ? 'checked' : '' ?>>
        <label for="maintenance"><?= __('enable_maintenance') ?></label>
    </fieldset>

    <fieldset>
        <legend><?= __('sitemap_settings') ?></legend>
        <input type="checkbox" id="generate_sitemap" name="generate_sitemap" <?= !empty($settings['generate_sitemap']) ? 'checked' : '' ?>>
        <label for="generate_sitemap"><?= __('generate_sitemap_on_save') ?></label>
    </fieldset>

    <fieldset>
        <legend><?= __('url_settings') ?></legend>
        <input type="checkbox" id="mod_rewrite" name="mod_rewrite" <?= !empty($settings['mod_rewrite']) ? 'checked' : '' ?>>
        <label for="mod_rewrite"><?= __('enable_mod_rewrite') ?></label>
    </fieldset>

    <button type="submit"><?= __('save') ?></button>
</form>

<?php
$content = ob_get_clean();
include '_layout.php';
