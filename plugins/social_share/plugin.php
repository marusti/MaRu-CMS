<?php
function plugin_output_social_share() {
    // Plugin-spezifische Einstellungen laden
    $settingsPath = __DIR__ . '/settings.json';
    $pluginSettings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];

    $shareTextRaw = $pluginSettings['share_text'] ?? 'Schau dir diese tolle Seite an!';

    // Aktivierungen als bool
    function toBool($val) {
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    $enabledFacebook = toBool($pluginSettings['enable_facebook'] ?? false);
    $enabledLinkedIn = toBool($pluginSettings['enable_linkedin'] ?? false);
    $enabledWhatsApp = toBool($pluginSettings['enable_whatsapp'] ?? false);
    $enabledBluesky = toBool($pluginSettings['enable_bluesky'] ?? false);
    $enabledMastodon = toBool($pluginSettings['enable_mastodon'] ?? false);

    $displayStyle = $pluginSettings['display_style'] ?? 'text';

    // Basis-URL aus globaler config/settings.json laden
    $globalSettingsPath = __DIR__ . '/../../config/settings.json';
    $globalSettings = file_exists($globalSettingsPath) ? json_decode(file_get_contents($globalSettingsPath), true) : [];
    $baseUrl = rtrim($globalSettings['base_url'] ?? '', '/');
    $modRewrite = !empty($globalSettings['mod_rewrite']);

    // Aktuelle URL ermitteln
    if (!empty($_GET['page'])) {
        $pagePath = trim($_GET['page'], '/');
        if ($modRewrite) {
            $currentUrl = $baseUrl . '/' . rawurlencode($pagePath);
        } else {
            $currentUrl = $baseUrl . '/index.php?page=' . rawurlencode($pagePath);
        }
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                     || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    $encodedUrl = rawurlencode($currentUrl);
    $shareText = rawurlencode($shareTextRaw);

    // SVG-Icons (einfach Beispiele, du kannst die gern ersetzen)
    $icons = [
        'Facebook' => '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="#3b5998" xmlns="http://www.w3.org/2000/svg"><path d="M22.675 0h-21.35C.597 0 0 .597 0 1.333v21.333C0 23.403.597 24 1.325 24H12.82v-9.294H9.692v-3.622h3.128V8.413c0-3.1 1.894-4.788 4.659-4.788 1.325 0 2.464.099 2.797.142v3.24l-1.918.001c-1.505 0-1.796.717-1.796 1.766v2.317h3.588l-.467 3.622h-3.121V24h6.116c.73 0 1.324-.597 1.324-1.333V1.333C24 .597 23.403 0 22.675 0z"/></svg>',
        'LinkedIn' => '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="#0077b5" xmlns="http://www.w3.org/2000/svg"><path d="M20.447 20.452h-3.554v-5.569c0-1.327-.027-3.037-1.851-3.037-1.853 0-2.136 1.445-2.136 2.938v5.668H9.354V9h3.414v1.561h.049c.476-.9 1.637-1.851 3.372-1.851 3.602 0 4.268 2.37 4.268 5.455v6.287zM5.337 7.433a2.064 2.064 0 110-4.127 2.064 2.064 0 010 4.127zm1.777 13.019H3.559V9h3.555v11.452zM22.225 0H1.771C.792 0 0 .77 0 1.723v20.555C0 23.229.792 24 1.771 24h20.451c.978 0 1.778-.77 1.778-1.722V1.723C24 .77 23.203 0 22.225 0z"/></svg>',
        'WhatsApp' => '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="#25D366" xmlns="http://www.w3.org/2000/svg"><path d="M20.52 3.478A11.987 11.987 0 0012 0C5.373 0 0 5.373 0 12a11.87 11.87 0 001.57 6.106L0 24l5.997-1.553a11.952 11.952 0 006.003 1.555c6.627 0 12-5.373 12-12a11.987 11.987 0 00-3.48-8.524zm-8.353 14.345c-1.942 0-3.751-.636-5.18-1.812l-.37-.29-3.563.922.95-3.465-.24-.36a7.763 7.763 0 01-1.24-4.175c0-4.295 3.495-7.79 7.79-7.79 2.078 0 4.03.81 5.49 2.287a7.652 7.652 0 012.272 5.494c-.002 4.294-3.496 7.788-7.79 7.788zm4.242-5.76c-.234-.117-1.384-.68-1.6-.758-.215-.079-.37-.117-.526.117-.156.234-.602.757-.738.91-.136.156-.273.176-.507.059-.234-.117- .988-.363-1.882-1.16-.695-.618-1.165-1.383-1.302-1.616-.136-.234-.015-.36.104-.476.107-.106.234-.273.352-.41.117-.136.156-.234.234-.39.078-.156.039-.292-.02-.41-.059-.117-.526-1.27-.72-1.74-.19-.457-.38-.395-.526-.402-.136-.007-.292-.008-.446-.008-.156 0-.41.058-.625.273-.215.215-.822.803-.822 1.955 0 1.153.842 2.27.96 2.43.117.156 1.66 2.53 4.02 3.547.562.243 1.002.388 1.345.495.565.18 1.08.155 1.49.094.454-.07 1.384-.566 1.58-1.113.195-.546.195-1.016.136-1.113-.06-.097-.215-.156-.45-.273z"/></svg>',
        'Bluesky' => '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="#1DA1F2" xmlns="http://www.w3.org/2000/svg"><path d="M22.46 6c-.77.35-1.6.58-2.46.69a4.33 4.33 0 001.9-2.38 8.64 8.64 0 01-2.73 1.05 4.3 4.3 0 00-7.33 3.92 12.18 12.18 0 01-8.84-4.48 4.3 4.3 0 001.33 5.75 4.24 4.24 0 01-1.95-.54v.05a4.3 4.3 0 003.45 4.21 4.35 4.35 0 01-1.94.07 4.3 4.3 0 004.01 2.99 8.62 8.62 0 01-5.34 1.84c-.35 0-.7-.02-1.04-.06a12.15 12.15 0 006.57 1.93c7.89 0 12.21-6.54 12.21-12.21 0-.19-.01-.39-.02-.58A8.72 8.72 0 0024 4.56a8.54 8.54 0 01-2.54.7z"/></svg>',
        'Mastodon' => '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="#3088d4" xmlns="http://www.w3.org/2000/svg"><path d="M20.37 7.63c-1.14-.9-2.36-1.3-3.34-1.48-1.11-.21-2.23-.27-3.33-.27s-2.22.07-3.32.27c-.98.18-2.19.58-3.32 1.48a7.28 7.28 0 00-2.02 3.32c-.07.24-.12.49-.15.75-.07.43-.1.89-.1 1.35 0 4.5 1.9 7.45 4.87 7.45 1.03 0 1.86-.44 2.39-1.3.16-.26.27-.57.36-.91.12-.5.14-.89.18-1.39.04-.59.08-1.38.13-2.28.02-.34.07-.6.13-.8.13-.44.3-.64.43-.8.14-.16.3-.23.52-.23.37 0 .63.33.63.87 0 .32-.05.59-.13.81-.17.5-.32 1.04-.46 1.54-.05.21-.1.41-.14.6-.07.35-.12.69-.16 1.02-.11.79-.14 1.55-.14 2.2 0 1.92.7 3.42 1.96 4.38 1.05.82 2.4 1.23 3.99 1.23 3.53 0 5.9-2.94 5.9-6.96 0-1.36-.41-2.5-1.12-3.37a4.92 4.92 0 00-1.29-1.32z"/></svg>',
    ];

    // Helferfunktion, um Links mit Text oder Icon zu bauen
    function renderLink($name, $url, $label, $displayStyle, $icons) {
        if ($displayStyle === 'icon' && isset($icons[$name])) {
            return "<a href=\"$url\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"$label\">{$icons[$name]}</a> ";
        } else {
            return "<a href=\"$url\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"$label\">$name</a> ";
        }
    }

    $html = '<div class="social-share" role="region" aria-label="Social Share">';
    $html .= '<strong>Seite teilen:</strong> ';

    if ($enabledFacebook) {
        $url = "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";
        $label = htmlspecialchars("Auf Facebook teilen", ENT_QUOTES, 'UTF-8');
        $html .= renderLink('Facebook', $url, $label, $displayStyle, $icons);
    }
    if ($enabledLinkedIn) {
        $url = "https://www.linkedin.com/shareArticle?mini=true&url={$encodedUrl}";
        $label = htmlspecialchars("Auf LinkedIn teilen", ENT_QUOTES, 'UTF-8');
        $html .= renderLink('LinkedIn', $url, $label, $displayStyle, $icons);
    }
    if ($enabledWhatsApp) {
        $url = "https://api.whatsapp.com/send?text={$shareText}%20{$encodedUrl}";
        $label = htmlspecialchars("Auf WhatsApp teilen", ENT_QUOTES, 'UTF-8');
        $html .= renderLink('WhatsApp', $url, $label, $displayStyle, $icons);
    }
    if ($enabledBluesky) {
        $url = "https://bsky.app/intent/compose?text={$shareText}%20{$encodedUrl}";
        $label = htmlspecialchars("Auf Bluesky teilen", ENT_QUOTES, 'UTF-8');
        $html .= renderLink('Bluesky', $url, $label, $displayStyle, $icons);
    }
    if ($enabledMastodon) {
        // Mastodon braucht JS-Prompt, deshalb etwas anders
        if ($displayStyle === 'icon' && isset($icons['Mastodon'])) {
            $mastodonLink = "<a href=\"#\" onclick=\"shareToMastodon(); return false;\" aria-label=\"Auf Mastodon teilen\">{$icons['Mastodon']}</a>";
        } else {
            $mastodonLink = '<a href="#" onclick="shareToMastodon(); return false;" aria-label="Auf Mastodon teilen">Mastodon</a>';
        }
        $html .= $mastodonLink;
    }

    // JS für Mastodon-Sharing
    $jsText = json_encode($shareTextRaw);
    $jsUrl = json_encode($currentUrl);

    $html .= <<<HTML
<script>
function shareToMastodon() {
    const text = encodeURIComponent({$jsText});
    const url = encodeURIComponent({$jsUrl});
    const instance = prompt("Gib deine Mastodon-Instanz ein (z.B. mastodon.social):", "mastodon.social");

    if (instance) {
        const safeInstance = instance.replace(/^https?:\\/\\//, '').trim();
        const shareUrl = "https://" + safeInstance + "/share?text=" + text + "%20" + url;
        window.open(shareUrl, '_blank', 'noopener,noreferrer');
    }
}
</script>
HTML;

    $html .= '</div>';

    return $html;
}
