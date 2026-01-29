<?php
require __DIR__ . '/vendor/Parsedown.php';

$id = $_GET['id'] ?? '';
if(!$id){
    http_response_code(404);
    echo "Keine Seite angegeben.";
    exit;
}

$metaFile = __DIR__ . "/content/pages/$id.json";
$mdFile   = __DIR__ . "/content/pages/$id.md";

if (!file_exists($metaFile) || !file_exists($mdFile)) {
    http_response_code(404);
    echo "Seite nicht gefunden.";
    exit;
}

/* Meta laden */
$page = json_decode(file_get_contents($metaFile), true);

/* Markdown laden */
$md = file_get_contents($mdFile);

/* Plugin/Gallery/Page Parser (optional später) */
// $md = parseCMS($md);

/* Markdown → HTML */
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);
$html = $Parsedown->text($md);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page['title'] ?? '') ?></title>

    <meta name="description" content="<?= htmlspecialchars($page['meta_description'] ?? '') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($page['meta_keywords'] ?? '') ?>">
    <meta name="robots" content="<?= htmlspecialchars($page['robots'] ?? 'index, follow') ?>">
</head>
<body>

<h1><?= htmlspecialchars($page['title'] ?? '') ?></h1>

<article>
    <?= $html ?>
</article>

</body>
</html>
