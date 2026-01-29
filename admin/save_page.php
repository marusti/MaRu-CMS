<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Basisverzeichnis für Seiten
$baseDir = __DIR__ . '/../content/pages';
if (!is_dir($baseDir)) mkdir($baseDir, 0775, true);

// Eingaben validieren
$id = trim($_POST['id'] ?? '');
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$metaDescription = trim($_POST['meta_description'] ?? '');
$metaKeywords = trim($_POST['meta_keywords'] ?? '');
$content = $_POST['content'] ?? ''; // Markdown
$defaultImage = trim($_POST['default_image'] ?? '');
$defaultImageAlt = trim($_POST['default_image_alt'] ?? '');

// Status prüfen
$status = trim($_POST['status'] ?? 'draft');
$validStatus = ['draft', 'published'];
if (!in_array($status, $validStatus)) $status = 'draft';

// Pflichtfelder prüfen
if ($id === '' || $title === '' || $category === '') die('Fehlende Pflichtfelder');

// ID-Sicherheitscheck
if (!preg_match('/^[a-z0-9\-_]+$/', $id)) die('Ungültige ID.');

// Kategorie-Ordner erstellen
$categoryDir = $baseDir . '/' . $category;
if (!is_dir($categoryDir)) mkdir($categoryDir, 0775, true);

// Robots prüfen
$robots = $_POST['robots'] ?? 'index, follow';
$validRobots = ['index, follow','noindex, follow','index, nofollow','noindex, nofollow'];
if (!in_array($robots, $validRobots)) $robots = 'index, follow';

// --- Markdown speichern ---
$mdPath = "$categoryDir/$id.md";
file_put_contents($mdPath, $content);

// --- Meta speichern ---
$meta = [
    'id' => $id,
    'title' => $title,
    'category' => $category,
    'status' => $status,
    'meta_description' => $metaDescription,
    'meta_keywords' => $metaKeywords,
    'robots' => $robots,
    'default_image' => $defaultImage,
    'default_image_alt' => $defaultImageAlt,
    'updated' => time()
];
$jsonPath = "$categoryDir/$id.json";
file_put_contents($jsonPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// --- Sitemap automatisch aktualisieren ---
$sitemapFile = __DIR__ . '/../sitemap.xml';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
$cmsBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . preg_replace('#/admin/?$#', '', dirname($_SERVER['SCRIPT_NAME']));

$entries = [];
foreach (glob($baseDir . '/*/*.json') as $metaFile) {
    $relPath = str_replace([$baseDir . '/', '.json'], '', $metaFile);
    $url = "$cmsBaseUrl/$relPath";
    $lastmod = date('Y-m-d', filemtime($metaFile));
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    $entries[] = "<url>
    <loc>$safeUrl</loc>
    <lastmod>$lastmod</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
</url>";
}

// Homepage einfügen
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

// --- Redirect ---
$action = $_POST['action'] ?? 'save';
if ($action === 'save_close') {
    header('Location: content_manager.php?success=page');
} else {
    header('Location: edit_page.php?id=' . urlencode($id) . '&success=page');
}
exit;
