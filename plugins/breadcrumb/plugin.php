<?php
// plugins/breadcrumb/plugin.php

function plugin_output_breadcrumb(array $options = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    ob_start();

    // Optionale Parameter
    $separator = $options['separator'] ?? ' / ';
    $homeName = $options['homeName'] ?? 'Startseite';

    // Core-Einstellungen laden
    $settingsPath = __DIR__ . '/../../config/settings.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];
    $baseUrl = rtrim($settings['base_url'] ?? '', '/');
    $useModRewrite = !empty($settings['mod_rewrite']);

    // Aktuelle Seite bestimmen (Markdown- oder Template-Kontext)
    global $requestedPage;
    if (!empty($options['requestedPage'])) {
        $path = trim($options['requestedPage'], '/');
    } elseif (!empty($requestedPage)) {
        $path = trim($requestedPage, '/');
    } elseif (!empty($_GET['page'])) {
        $path = trim($_GET['page'], '/');
    } else {
        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $relativePath = (strpos($uri, $basePath) === 0) ? substr($uri, strlen($basePath)) : $uri;
        $path = trim($relativePath, '/');
    }

    $segments = $path === '' ? [] : explode('/', $path);

    // Breadcrumb-Array erstellen
    $breadcrumbs = [];
    $breadcrumbs[] = ['label' => $homeName, 'url' => $baseUrl ?: '/'];

    $accumulatedPath = '';
    foreach ($segments as $segment) {
        $accumulatedPath .= ($accumulatedPath === '' ? '' : '/') . $segment;
        if ($useModRewrite) {
            $url = $baseUrl . '/' . $accumulatedPath;
        } else {
            $url = $baseUrl . '/index.php?page=' . rawurlencode($accumulatedPath);
        }
        $breadcrumbs[] = ['label' => ucfirst($segment), 'url' => $url];
    }

    // HTML-Ausgabe
    echo '<nav aria-label="Breadcrumb"><ol class="breadcrumb">';
    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $i => $crumb) {
        $label = htmlspecialchars($crumb['label']);
        $url = htmlspecialchars($crumb['url']);
        if ($i + 1 === $count) {
            echo '<li aria-current="page">' . $label . '</li>';
        } else {
            echo '<li><a href="' . $url . '">' . $label . '</a></li>';
        }
    }
    echo '</ol></nav>';

    // JSON-LD für strukturierte Daten
    $itemListElements = [];
    foreach ($breadcrumbs as $index => $crumb) {
        $itemListElements[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['label'],
            'item' => $crumb['url'],
        ];
    }
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $itemListElements,
    ];

    echo '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';

    return ob_get_clean();
}
