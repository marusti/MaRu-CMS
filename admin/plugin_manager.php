<?php

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php'; 
require_once __DIR__ . '/../lib/helpers.php';

// Signal, dass diese Seite Filter braucht
$pageHasFilter = true;
$pageHasDialog = true;
// CSRF Token Funktionen
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Prüfen, ob Admin eingeloggt ist
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);



// JSON Ladefunktion mit Fehlerbehandlung
function load_json_file($file) {
    if (!file_exists($file)) return null;
    $content = file_get_contents($file);
    $json = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in $file: " . json_last_error_msg());
        return null;
    }
    return $json;
}

function load_plugin_settings($plugin) {
    return load_json_file(__DIR__ . "/../plugins/$plugin/settings.json") ?: [];
}

function save_plugin_settings($pluginName, $settings): bool {
    $file = __DIR__ . "/../plugins/$pluginName/settings.json";
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log("JSON encode error for plugin '$pluginName': " . json_last_error_msg());
        return false;
    }
    return file_put_contents($file, $json) !== false;
}

function load_plugin_info($plugin) {
    return load_json_file(__DIR__ . "/../plugins/$plugin/plugin.json");
}

// Zentrale Messages
$messages = [];

$allPlugins = array_map('basename', glob(__DIR__ . '/../plugins/*', GLOB_ONLYDIR));
sort($allPlugins, SORT_NATURAL | SORT_FLAG_CASE);

$currentSettings = load_settings();
$activePlugins = $currentSettings['plugins'] ?? [];

$csrfToken = csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        addMessage($messages, __('invalid_csrf_token'), 'error');
    } else {
        $errors = [];
        
        // 🔴 Plugin löschen
if (isset($_POST['delete_plugin'])) {
    $plugin = basename($_POST['delete_plugin']);
    $pluginDir = __DIR__ . '/../plugins/' . $plugin;

    if (!is_dir($pluginDir)) {
        $msg = sprintf(__('plugin_not_found'), $plugin);
        addMessage($messages, $msg, 'error');

    } else {

        // 🔹 Plugin-Displayname vor dem Löschen laden
        $pluginInfo = load_plugin_info($plugin);
        $displayName = $pluginInfo['name'] ?? $plugin;

        if (rrmdir($pluginDir)) {

            $msg = sprintf(__('plugin_deleted'), $displayName);
            addMessage($messages, $msg, 'success');

            // Aus aktiven Plugins entfernen
            $currentSettings['plugins'] = array_values(
                array_diff($currentSettings['plugins'], [$plugin])
            );
            save_settings($currentSettings);

        } else {
            $msg = sprintf(__('plugin_delete_failed'), $displayName);
            addMessage($messages, $msg, 'error');
        }
    }

    $_SESSION['messages'] = $messages;
    header("Location: plugin_manager.php");
    exit;
}

// 🔹 Plugin hochladen
elseif (isset($_FILES['plugin_zip'])) {

    $file = $_FILES['plugin_zip'];
    $zipFile = $file['tmp_name'];
    $zipName = basename($file['name']);

    // Fehler prüfen
    if ($file['error'] !== UPLOAD_ERR_OK) {
        addMessage($messages, __('upload_error'), 'error');
    } 
    elseif (strtolower(pathinfo($zipName, PATHINFO_EXTENSION)) !== 'zip') {
        addMessage($messages, __('invalid_zip_file'), 'error');
    } 
    else {
        $pluginDir = __DIR__ . '/../plugins/';
        $tmpDir = sys_get_temp_dir() . '/plugin_upload_' . bin2hex(random_bytes(5));
        mkdir($tmpDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($tmpDir);
            $zip->close();

            // Root-Ordner des Plugins ermitteln
            $dirs = array_filter(glob($tmpDir . '/*'), 'is_dir');
            if (count($dirs) !== 1) {
                rrmdir($tmpDir);
                addMessage($messages, __('zip_must_have_one_folder'), 'error');
            } 
            else {
                $pluginFolderName = basename(reset($dirs));
                $extractPath = $pluginDir . $pluginFolderName;

                // Prüfen ob Plugin existiert
                if (is_dir($extractPath)) {
                    rrmdir($extractPath); // bestehendes Plugin entfernen
                }

                // Plugin verschieben
                // Plugin verschieben – cross-device safe
$srcDir = $tmpDir . '/' . $pluginFolderName;
$dstDir = $extractPath;

// Rekursive Copy-Funktion für Cross-Device
function rrmdir_copy($src, $dst) {
    if (!is_dir($src)) return false;
    mkdir($dst, 0755, true);
    foreach (scandir($src) as $file) {
        if ($file === '.' || $file === '..') continue;
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            rrmdir_copy($srcPath, $dstPath);
        } else {
            copy($srcPath, $dstPath);
        }
    }
    return true;
}

// Verschieben durchführen
if (!rrmdir_copy($srcDir, $dstDir)) {
    rrmdir($tmpDir);
    addMessage($messages, __('failed_to_move_plugin'), 'error');
} else {
    rrmdir($tmpDir); // Temp cleanup
    // Plugin erfolgreich verschoben
    $pluginInfoFile = $dstDir . '/plugin.json';
    $pluginInfo = [];
    if (file_exists($pluginInfoFile)) {
        $pluginInfo = json_decode(file_get_contents($pluginInfoFile), true) ?: [];
    }
    $displayName = $pluginInfo['name'] ?? $pluginFolderName;
    addMessage($messages, sprintf(__('plugin_installed_success'), $displayName), 'success');

    $_SESSION['messages'] = $messages;
    header("Location: plugin_manager.php");
    exit;
}
            }
        } 
        else {
            rrmdir($tmpDir);
            addMessage($messages, __('failed_to_extract_zip'), 'error');
        }
    }
}

        // Plugins aktivieren/deaktivieren speichern
if (isset($_POST['plugins'])) {
    $activePlugins = $_POST['plugins'];
    $currentSettings['plugins'] = $activePlugins;

    if (!save_settings($currentSettings)) {
        $errors[] = __('save_failed_global_settings');
    }
}

        // Plugin-Einstellungen speichern
        if (isset($_POST['plugin_settings'])) {
            foreach ($_POST['plugin_settings'] as $plugin => $submittedSettings) {
                $existingSettings = load_plugin_settings($plugin);
                $pluginSchema = load_json_file(__DIR__ . "/../plugins/$plugin/settings-schema.json");

                foreach ($submittedSettings as $key => $value) {
                    $field = null;
                    if (isset($pluginSchema['fields'])) {
                        foreach ($pluginSchema['fields'] as $f) {
                            if ($f['key'] === $key) {
                                $field = $f;
                                break;
                            }
                        }
                    }

                    if ($field && $field['type'] === 'password') {
                        $existingHash = $existingSettings[$key] ?? '';
                        if ($value !== '') {
                            if ($existingHash === '' || !password_verify($value, $existingHash)) {
                                $submittedSettings[$key] = password_hash($value, PASSWORD_DEFAULT);
                            } else {
                                $submittedSettings[$key] = $existingHash;
                            }
                        } else {
                            $submittedSettings[$key] = $existingHash;
                        }
                    }
                }

                if (!save_plugin_settings($plugin, $submittedSettings)) {
                    $errors[] = sprintf(__('plugin_save_failed'), $plugin);
                }
            }
        }
        

        if (!empty($errors)) {
            foreach ($errors as $err) {
                addMessage($messages, $err, 'error');
            }
        } else {
            addMessage($messages, __('save_success'), 'success');
        }
    }
}

$pageTitle = __('plugin_manager');

function render_plugin_settings_form(string $plugin, array $settings): string {
    $schemaFile = __DIR__ . "/../plugins/$plugin/settings-schema.json";
    $schema = load_json_file($schemaFile);

    if (!$schema || empty($schema['fields'])) {
        $html = '';
        foreach ($settings as $key => $value) {
            $id = 'plugin_' . htmlspecialchars($plugin) . '_' . htmlspecialchars($key);
            $html .= '<div class="maru-settings">';
            $html .= '<label for="' . $id . '">' . htmlspecialchars($key) . ':</label>';
            $html .= '<input id="' . $id . '" type="text" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '">';
            $html .= '</div>';
        }
        return $html;
    }

    $html = '<fieldset class="plugin-settings"><legend>' . __('settings') . '</legend>';

    foreach ($schema['fields'] as $field) {
        $key = $field['key'];
        $label = $field['label'] ?? $key;
        $type = $field['type'] ?? 'text';
        $value = $settings[$key] ?? ($field['default'] ?? '');
        $id = 'plugin_' . htmlspecialchars($plugin) . '_' . htmlspecialchars($key);

        $html .= '<div class="maru-settings">';
        $html .= '<label for="' . $id . '">' . htmlspecialchars($label) . ':</label>';

        switch ($type) {
            case 'boolean':
    $checked = ($value === true || $value === '1' || $value === 1) ? ' checked' : '';

    $name = 'plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']';

    $html .= '<input type="hidden" name="' . $name . '" value="0">';
    $html .= '<input id="' . $id . '" type="checkbox" name="' . $name . '" value="1"' . $checked . '>';

    break;

            case 'number':
                $min = isset($field['min']) ? ' min="' . (int)$field['min'] . '"' : '';
                $max = isset($field['max']) ? ' max="' . (int)$field['max'] . '"' : '';
                $html .= '<input id="' . $id . '" type="number" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '"' . $min . $max . '>';
                break;

            case 'select':
                $optionsByCategory = [];
                if (!empty($field['options_source']) && $field['options_source'] === 'pages') {
                    $pages = getAllPages();
                    foreach ($pages as $page) {
                        $category = $page['category'];
                        $keyValue = $page['category'] . '/' . $page['filename'];
                        $title = $page['title'];
                        $optionsByCategory[$category][$keyValue] = $title;
                    }
                } elseif (!empty($field['options'])) {
                    $optionsByCategory[''] = $field['options'];
                }

                $html .= '<select id="' . $id . '" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']">';
                foreach ($optionsByCategory as $category => $options) {
                    if ($category !== '') {
                        $html .= '<optgroup label="' . htmlspecialchars($category) . '">';
                    }
                    foreach ($options as $optValue => $optLabel) {
                        $selected = ($value == $optValue) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($optValue) . '"' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
                    }
                    if ($category !== '') {
                        $html .= '</optgroup>';
                    }
                }
                $html .= '</select>';
                break;

            case 'textarea':
                $html .= '<textarea id="' . $id . '" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']">' . htmlspecialchars($value) . '</textarea>';
                break;

            case 'password':
                $placeholder = isset($field['placeholder']) ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';
                $html .= '<input id="' . $id . '" type="password" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value=""' . $placeholder . '>';
                break;

            case 'text':
            default:
                $placeholder = isset($field['placeholder']) ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';
                $html .= '<input id="' . $id . '" type="text" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '"' . $placeholder . '>';
                break;
        }

        $html .= '</div>'; // close maru-settings
    }

    $html .= '</fieldset>';
    return $html;
}

ob_start();
?>

<h1><?= htmlspecialchars($pageTitle) ?></h1>

<form id="pluginUploadForm"  method="post" class="upload-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <div id="dropZone" class="drop-zone" tabindex="0" role="button" aria-label="<?= __('upload_instruction') ?>">
        <p><?= __('upload_instruction') ?></p>
        <input type="file" name="plugin_zip" accept=".zip" hidden aria-label="Choose a zip file" required>
    </div>
    <button type="submit"><?= __('upload') ?></button>
</form>
<h2><?= __('existing_plugins') ?></h2>
<div class="maru-toolbar">
<div class="filter">

<label for="filter"><?= __('search_plugins') ?>:</label>
<input id="filter" class="admin-search" type="search" placeholder="<?= __('search_plugins_placeholder') ?>">
</div>
</div>
<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <fieldset class="fieldset-list">
        <legend class="sr-only"><?= __('select plugins to activate') ?></legend>

        <div id="pluginList">
            <?php if (count($allPlugins) === 0): ?>
                <p><?= __('no_plugins_found') ?></p>
            <?php else: ?>
                <?php foreach ($allPlugins as $plugin): ?>
                    <?php
                        $checked = in_array($plugin, $activePlugins) ? 'checked' : '';
                        $pluginSettings = load_plugin_settings($plugin);
                        $pluginInfo = load_plugin_info($plugin);
                    ?>
                    <details class="entry-block plugin-block">
                        <summary class="plugin-summary" aria-expanded="false">
                        <div>
                            <svg class="toggle-arrow" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                <path d="M5 3l5 5-5 5"/>
                            </svg>
                            <input type="checkbox"
                                   id="plugin_<?= htmlspecialchars($plugin) ?>"
                                   name="plugins[]"
                                   value="<?= htmlspecialchars($plugin) ?>"
                                   <?= $checked ?>>
                            <label for="plugin_<?= htmlspecialchars($plugin) ?>">
                                <span class="entry-name"><?= htmlspecialchars($pluginInfo['name'] ?? $plugin) ?></span>
                            </label>
</div>
                            <button type="button" class="maru-delete js-delete"  
        aria-label="<?= htmlspecialchars(__('delete'), ENT_QUOTES) ?>" 
        data-title="<?= htmlspecialchars(__('delete'), ENT_QUOTES) ?>"
        data-message="<?= htmlspecialchars(sprintf(__('delete_confirm_plugin'), $pluginInfo['name'] ?? $plugin), ENT_QUOTES) ?>"
        data-form="deletePluginForm"
        data-input="deletePluginInput"
        data-value="<?= htmlspecialchars($plugin, ENT_QUOTES) ?>" 
>
    <?= getIcon('delete') ?>
</button>
                        </summary>

                        <div class="maru-ext-details plugin-details">
                        
                                <p class="plugin-details-meta"><strong class="plugin-details-desc"><?= __('description') ?>:</strong> <?= htmlspecialchars($pluginInfo['description'] ?? __('no_description')) ?></p>
                                <p class="plugin-details-meta"><strong><?= __('version') ?>:</strong> <?= htmlspecialchars($pluginInfo['version'] ?? __('no_version_info')) ?></p>
                                 <p class="plugin-details-meta"><strong><?= __('author') ?>:</strong>  <?= htmlspecialchars($pluginInfo['author'] ?? __('unknown') )?></p>
                        
                            
                        </div>
                        <?= render_plugin_settings_form($plugin, $pluginSettings) ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </fieldset>

    <button type="submit"><?= __('save') ?></button>
</form>

<form method="post" id="deletePluginForm" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="delete_plugin" id="deletePluginInput">
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('details.plugin-block').forEach(details => {
        const summary = details.querySelector('summary.plugin-summary');
        details.addEventListener('toggle', () => {
            summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        });
    });

});
</script>


<?php
$content = ob_get_clean();
include '_layout.php';