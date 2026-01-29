<?php

function plugin_output_openmetagraph() {
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return '';

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings) return '';

    $title = htmlspecialchars($settings['og_title'] ?? '');
    $description = htmlspecialchars($settings['og_description'] ?? '');
    $image = htmlspecialchars($settings['og_image'] ?? '');
    $url = htmlspecialchars($settings['og_url'] ?? '');

    $tags = [];
    if ($title) {
        $tags[] = '<meta property="og:title" content="' . $title . '">';
    }
    if ($description) {
        $tags[] = '<meta property="og:description" content="' . $description . '">';
    }
    if ($image) {
        $tags[] = '<meta property="og:image" content="' . $image . '">';
    }
    if ($url) {
        $tags[] = '<meta property="og:url" content="' . $url . '">';
    }

    // Optional Standard-Tags
    $tags[] = '<meta property="og:type" content="website">';
    $tags[] = '<meta property="og:locale" content="de_DE">';

    return implode("\n", $tags);
}

