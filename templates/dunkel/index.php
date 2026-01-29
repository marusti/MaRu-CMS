<?php
// Basis-URL aus settings.json laden
$settingsFile = __DIR__ . '/../../config/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
$baseUrl = $settings['base_url'] ?? ''; // Standardwert, falls keine Basis-URL gesetzt ist
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Willkommen') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!empty($pageMetaDescription)): ?>
        <meta name="description" content="<?= htmlspecialchars($pageMetaDescription) ?>">
    <?php endif; ?>
    <?php if (!empty($pageMetaKeywords)): ?>
        <meta name="keywords" content="<?= htmlspecialchars($pageMetaKeywords) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="{{base_url}}/templates/dunkel/css/style.css">
</head>
<body>

<header>
    <h1><?= htmlspecialchars($pageTitle ?? 'Willkommen') ?></h1>
</header>

{{menu}}

<main>
    {{content}}
</main>

<footer>
    <p>© <?= date('Y') ?> Mein CMS</p>
</footer>

</body>
</html>
