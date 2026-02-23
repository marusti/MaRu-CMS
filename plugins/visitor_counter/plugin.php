<?php
function plugin_output_visitor_counter(): string {
    // Session starten, falls noch nicht
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Settings laden
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return '';

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings || !is_array($settings)) return '';

    $showCount = $settings['show_count'] ?? true;
    $dataFile = basename($settings['count_file'] ?? 'visitors.json');
    $dataPath = __DIR__ . '/' . $dataFile;

    // Bot-Filter
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawl|slurp|spider/i', $userAgent)) {
        return '';
    }

    // Besucher-IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // JSON-Daten sicher laden oder neu initialisieren
    $data = [];
    if (file_exists($dataPath)) {
        $tmp = json_decode(file_get_contents($dataPath), true);
        if (is_array($tmp)) {
            $data = $tmp;
        } else {
            $data = []; // Alte Datei ungültig → neu initialisieren
        }
    }

    $today = date('Y-m-d');
    $month = date('Y-m');

    if (!isset($data['visits']) || !is_array($data['visits'])) $data['visits'] = [];
    if (!isset($data['visits'][$today]) || !is_array($data['visits'][$today])) $data['visits'][$today] = [];
    if (!isset($data['totals']) || !is_array($data['totals'])) $data['totals'] = ['all' => 0];

    // Prüfen, ob diese IP heute schon gezählt wurde
    if (!in_array($ip, $data['visits'][$today], true)) {
        $data['visits'][$today][] = $ip;
        $data['totals']['all']++;
        $data['totals'][$month] = ($data['totals'][$month] ?? 0) + 1;
        file_put_contents($dataPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    // HTML erzeugen
    if ($showCount) {
        $countToday = count($data['visits'][$today]);
        $totalCount = $data['totals']['all'] ?? 0;

        return '<div class="visitor-counter">'
             . '<p class="visitor-today">Besucher heute: ' . htmlspecialchars((string)$countToday) . '</p>'
             . '<p class="visitor-total">Gesamtbesucher: ' . htmlspecialchars((string)$totalCount) .'</p>'
             . '</div>';
    }

    return '';
}

// Reset-Funktion: optional mit Passwortschutz
function visitor_counter_reset(string $inputCode): bool {
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return false;

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings || empty($settings['reset_code'])) return false;

    if (password_verify($inputCode, $settings['reset_code'])) {
        $dataFile = basename($settings['count_file'] ?? 'visitors.json');
        $dataPath = __DIR__ . '/' . $dataFile;
        $emptyData = ['visits' => [], 'totals' => ['all' => 0]];
        file_put_contents($dataPath, json_encode($emptyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return true;
    }

    return false;
}

// Statistik abrufen
function visitor_counter_stats(): array {
    $settingsFile = __DIR__ . '/settings.json';
    if (!file_exists($settingsFile)) return [];

    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings) return [];

    $dataFile = basename($settings['count_file'] ?? 'visitors.json');
    $dataPath = __DIR__ . '/' . $dataFile;

    if (!file_exists($dataPath)) return [];

    $data = json_decode(file_get_contents($dataPath), true);
    return is_array($data) ? $data : [];
}