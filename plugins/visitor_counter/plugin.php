<?php
function visitor_counter_render() {
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return;

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings) return;

    $countFile = __DIR__ . '/' . ($settings['count_file'] ?? 'visitors.txt');
    $showCount = $settings['show_count'] ?? true;

    // Besucher zählen
    $count = 0;
    if (file_exists($countFile)) {
        $count = (int)file_get_contents($countFile);
    }
    $count++;
    file_put_contents($countFile, $count, LOCK_EX);

    // Anzeige
    if ($showCount) {
        echo '<div class="visitor-counter" style="padding:10px; background:#e0f7fa; border:1px solid #00acc1; margin:10px 0;">';
        echo 'Besucher: ' . htmlspecialchars($count);
        echo '</div>';
    }
}

// Optional: Reset-Funktion, falls Admin das Plugin nutzen möchte
function visitor_counter_reset($inputCode) {
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return false;

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings || empty($settings['reset_code'])) return false;

    if (password_verify($inputCode, $settings['reset_code'])) {
        $countFile = __DIR__ . '/' . ($settings['count_file'] ?? 'visitors.txt');
        file_put_contents($countFile, '0', LOCK_EX);
        return true;
    }
    return false;
}

