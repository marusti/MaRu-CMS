<?php

require_once __DIR__ . '/init.php';

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

require_once __DIR__ . '/../lib/helpers.php';

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
    $file = __DIR__ . "/../plugins/$pluginName/settings.json"; // Korrekt: $pluginName
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // Prüfe JSON-Encoding
    if ($json === false) {
        error_log("JSON encode error for plugin '$pluginName': " . json_last_error_msg());
        return false;
    }

    // Schreibe in Datei
    $result = file_put_contents($file, $json);
    if ($result === false) {
        error_log("Failed to write plugin settings to $file");
        return false;
    }

    return true;
}



function load_plugin_info($plugin) {
    return load_json_file(__DIR__ . "/../plugins/$plugin/plugin.json");
}

// Alle Plugins laden
$allPlugins = array_map('basename', glob(__DIR__ . '/../plugins/*', GLOB_ONLYDIR));
sort($allPlugins, SORT_NATURAL | SORT_FLAG_CASE);

// Aktuelle Einstellungen laden
$currentSettings = load_settings();
$activePlugins = $currentSettings['plugins'] ?? [];

$message = '';
$messageType = 'success'; // 'success' oder 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $message = __('invalid_csrf_token');
        $messageType = 'error';
    } else {
        $errors = [];

        // Plugins aktivieren/deaktivieren speichern
        $activePlugins = $_POST['plugins'] ?? [];
        $currentSettings['plugins'] = $activePlugins;
        if (!save_settings($currentSettings)) {
            $errors[] = __('save_failed_global_settings');
        }

        // Plugin-Einstellungen speichern
        if (isset($_POST['plugin_settings'])) {
            foreach ($_POST['plugin_settings'] as $plugin => $submittedSettings) {
                $existingSettings = load_plugin_settings($plugin);
                $pluginSchema = load_json_file(__DIR__ . "/../plugins/$plugin/settings-schema.json");

                foreach ($submittedSettings as $key => $value) {
                    $field = null;

                    // Feld im Schema suchen
                    if (isset($pluginSchema['fields'])) {
                        foreach ($pluginSchema['fields'] as $f) {
                            if ($f['key'] === $key) {
                                $field = $f;
                                break;
                            }
                        }
                    }

                    // Passwortfelder speziell behandeln
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
            $message = implode("\n", $errors);
            $messageType = 'error';
        } else {
            $message = __('save_success');
            $messageType = 'success';
        }
    }
}


$pageTitle = __('plugin_manager');
$csrfToken = csrf_token();

function render_plugin_settings_form(string $plugin, array $settings): string {
    $schemaFile = __DIR__ . "/../plugins/$plugin/settings-schema.json";
    $schema = load_json_file($schemaFile);
    if (!$schema || empty($schema['fields'])) {
        // Fallback: Einfach Key-Value Textfelder rendern
        $html = '';
        foreach ($settings as $key => $value) {
            $html .= '<label>' . htmlspecialchars($key) . ': ';
            $html .= '<input type="text" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '">';
            $html .= '</label>';
        }
        return $html;
    }

    $html = '<fieldset class="plugin-settings"><legend>' . __('settings') . '</legend>';
    foreach ($schema['fields'] as $field) {
        $key = $field['key'];
        $label = $field['label'] ?? $key;
        $type = $field['type'] ?? 'text';
        $value = $settings[$key] ?? ($field['default'] ?? '');

        $html .= '<label>' . htmlspecialchars($label) . ': ';

        switch ($type) {
            case 'boolean':
                $checked = ($value === true || $value === '1' || $value === 1) ? 'checked' : '';
                $html .= '<input type="checkbox" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="1" ' . $checked . '>';
                break;

            case 'number':
                $min = isset($field['min']) ? ' min="' . (int)$field['min'] . '"' : '';
                $max = isset($field['max']) ? ' max="' . (int)$field['max'] . '"' : '';
                $html .= '<input type="number" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '"' . $min . $max . '>';
                break;

            case 'select':
    $optionsByCategory = [];
    if (!empty($field['options_source']) && $field['options_source'] === 'pages') {
        $pages = getAllPages(); // aus helpers.php
        foreach ($pages as $page) {
            $category = $page['category'];
            $keyValue = $page['category'] . '/' . $page['filename'];
            $title = $page['title'];
            $optionsByCategory[$category][$keyValue] = $title;
        }
    } elseif (!empty($field['options'])) {
        // Fallback: keine Kategorien, nur flache Liste
        $optionsByCategory[''] = $field['options'];
    }

    $html .= '<select name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']">';

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
                $html .= '<textarea name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']">' . htmlspecialchars($value) . '</textarea>';
                break;

            case 'password':
                $placeholder = isset($field['placeholder']) ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';
                $html .= '<input type="password" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value=""' . $placeholder . '>';
                break;

            case 'text':
            default:
                $placeholder = isset($field['placeholder']) ? ' placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';
                $html .= '<input type="text" name="plugin_settings[' . htmlspecialchars($plugin) . '][' . htmlspecialchars($key) . ']" value="' . htmlspecialchars($value) . '"' . $placeholder . '>';
                break;
        }
        $html .= '</label>';
    }
    $html .= '</fieldset>';

    return $html;
}


ob_start();
?>

<h1><?= htmlspecialchars($pageTitle) ?></h1>

<?php if ($message): ?>
    <div class="message <?= htmlspecialchars($messageType) ?>">
        <?= nl2br(htmlspecialchars($message)) ?>
    </div>
<?php endif; ?>

<!-- Suchfeld -->
<label for="pluginSearch"><?= __('search_plugins') ?>:</label>
<input id="pluginSearch"  class="admin-search" type="search" placeholder="<?= __('search_plugins_placeholder') ?>">

<!-- Drag & Drop Upload -->
<form id="pluginUploadForm" class="upload-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <div id="dropZone"
         class="drop-zone"
         tabindex="0"
         role="button"
         aria-label="<?= __('upload_instruction') ?>">
        <p><?= __('upload_instruction') ?></p>

        <input
            type="file"
            name="plugin_zip"
            accept=".zip"
            hidden
        >
    </div>
    
    <button type="submit"><?= __('upload') ?></button>
</form>


<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
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
            <details class="plugin-block">
                <summary class="plugin-summary" aria-expanded="false">
                    <svg class="toggle-arrow" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                        <path d="M5 3l5 5-5 5"/>
                    </svg>
                    <input type="checkbox" id="plugin_<?= htmlspecialchars($plugin) ?>" name="plugins[]" value="<?= htmlspecialchars($plugin) ?>" <?= $checked ?>>
<label for="plugin_<?= htmlspecialchars($plugin) ?>">
    <strong><?= htmlspecialchars($pluginInfo['name'] ?? $plugin) ?></strong>
    <?php if ($pluginInfo): ?>
        – <?= __('version') ?> <?= htmlspecialchars($pluginInfo['version']) ?>, <?= __('by') ?> <?= htmlspecialchars($pluginInfo['author']) ?>
    <?php endif; ?>
</label>

                    <button type="button" class="delete-plugin" data-plugin="<?= htmlspecialchars($plugin) ?>" title="<?= __('delete') ?>">🗑️</button>
                </summary>

                <div class="plugin-details">
                    <?php if ($pluginInfo): ?>
                        <p><strong><?= __('description') ?>:</strong> <?= nl2br(htmlspecialchars($pluginInfo['description'] ?? '-')) ?></p>
                    <?php endif; ?>

                    <?= render_plugin_settings_form($plugin, $pluginSettings) ?>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="submit"><?= __('save') ?></button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const pluginList = document.getElementById('pluginList');
    const statusBox  = document.getElementById('statusBox');
    const dropZone   = document.getElementById('dropZone');
    const fileInput  = dropZone ? dropZone.querySelector('input[type="file"]') : null;
    const csrfToken  = <?= json_encode($csrfToken) ?>;
    const searchInput = document.getElementById('pluginSearch');

    /* ===============================
       Accessibility: aria-expanded
    =============================== */
    document.querySelectorAll('details.plugin-block').forEach(details => {
        const summary = details.querySelector('summary.plugin-summary');
        details.addEventListener('toggle', () => {
            summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
        });
    });

    /* ===============================
       Plugin löschen (AJAX)
    =============================== */
    if (pluginList) {
        pluginList.addEventListener('click', function (e) {
            if (e.target.classList.contains('delete-plugin')) {
                const plugin = e.target.dataset.plugin;
                if (!confirm('<?= addslashes(__('confirm_delete_plugin')) ?>')) return;

                fetch('plugin_delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'plugin=' + encodeURIComponent(plugin) +
                          '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        e.target.closest('details.plugin-block')?.remove();
                        showStatus('<?= addslashes(__('plugin_deleted')) ?>', 'success');
                    } else {
                        showStatus(data.error || '<?= addslashes(__('error_occurred')) ?>', 'error');
                    }
                })
                .catch(() => {
                    showStatus('<?= addslashes(__('error_occurred')) ?>', 'error');
                });
            }
        });
    }

    /* ===============================
       Suche
    =============================== */
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase();
            document.querySelectorAll('#pluginList > details.plugin-block').forEach(block => {
                const nameEl = block.querySelector('summary.plugin-summary strong');
                const name = nameEl ? nameEl.textContent.toLowerCase() : '';
                block.style.display = name.includes(query) ? '' : 'none';
            });
        });
    }

    /* ===============================
       Upload: Drag & Drop / Klick
    =============================== */
    if (!dropZone || !fileInput) {
        console.warn('Upload-Dropzone nicht gefunden');
        return;
    }

    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');

        if (e.dataTransfer.files.length !== 1) {
            showStatus('<?= addslashes(__('only_one_file')) ?>', 'error');
            return;
        }

        fileInput.files = e.dataTransfer.files;
        upload();
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length === 1) {
            upload();
        }
    });

    function upload() {
        const file = fileInput.files[0];

        if (!file.name.toLowerCase().endsWith('.zip')) {
            showStatus('<?= addslashes(__('only_zip_files')) ?>', 'error');
            fileInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('plugin_zip', file);
        formData.append('csrf_token', csrfToken);

        fetch('plugin_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showStatus(data.error || '<?= addslashes(__('error_occurred')) ?>', 'error');
            }
        })
        .catch(() => {
            showStatus('<?= addslashes(__('error_occurred')) ?>', 'error');
        });
    }

    /* ===============================
       Statusmeldung
    =============================== */
    function showStatus(message, type) {
        if (!statusBox) return;
        statusBox.textContent = message;
        statusBox.className = 'message ' + type;
        statusBox.style.display = 'block';
        setTimeout(() => {
            statusBox.style.display = 'none';
        }, 4000);
    }
});
</script>


<?php
$content = ob_get_clean();

include '_layout.php';