<?php
// plugins/breadcrumb/plugin.php

function plugin_output_breadcrumb(array $options = []) {
    // Optionale Parameter mit Defaults
    $separator = $options['separator'] ?? ' / ';
    $homeName = $options['homeName'] ?? 'Startseite';

    // Einstellungen laden
    $settingsPath = __DIR__ . '/../../config/settings.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

    $baseUrl = rtrim($settings['base_url'] ?? '', '/');
    $useModRewrite = !empty($settings['mod_rewrite']);
    
    // Pfad ermitteln
    if (!empty($_GET['page'])) {
        $path = trim($_GET['page'], '/');
        $segments = $path === '' ? [] : explode('/', $path);
    } else {
        $basePath = parse_url($baseUrl, PHP_URL_PATH);
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($uri, $basePath) === 0) {
            $relativePath = substr($uri, strlen($basePath));
        } else {
            $relativePath = $uri;
        }
        $relativePath = trim($relativePath, '/');
        $segments = $relativePath === '' ? [] : explode('/', $relativePath);
    }

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

    // CSS (Separator wird über ::before eingefügt, außer beim ersten Element)
    $html = '<nav aria-label="Breadcrumb">
  <ol class="breadcrumb">';


    $count = count($breadcrumbs);
    foreach ($breadcrumbs as $i => $crumb) {
        $label = htmlspecialchars($crumb['label']);
        $url = htmlspecialchars($crumb['url']);
        if ($i + 1 === $count) {
            $html .= '<li aria-current="page">' . $label . '</li>';
        } else {
            $html .= '<li><a href="' . $url . '">' . $label . '</a></li>';
        }
    }

    $html .= "</ol>\n</nav>\n";

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

    $html .= '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';

    return $html;
}
