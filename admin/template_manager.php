<?php
session_start();

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

// Signal, dass diese Seite Filter braucht
$pageHasFilter = true;
$pageHasDialog = true;
// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$templateDir   = __DIR__ . '/../templates';
$settingsFile  = __DIR__ . '/../config/settings.json';

$settings = file_exists($settingsFile)
    ? json_decode(file_get_contents($settingsFile), true)
    : [];

$activeTemplate = $settings['template'] ?? 'default';

// Zentrale Messages
$messages = [];

/* ===============================
   POST
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        addMessage($messages, __('invalid_csrf_token'), 'error');
    }

    // Template aktivieren
    elseif (isset($_POST['template'])) {
        $tpl = basename($_POST['template']);
        $settings['template'] = $tpl;
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $activeTemplate = $tpl;
        addMessage($messages, sprintf(__('template_activated'), $tpl), 'success');
    }

    // Template löschen
    elseif (isset($_POST['delete_template'])) {
        $tpl = basename($_POST['delete_template']);

        if ($tpl !== $activeTemplate) {
            $path = $templateDir . '/' . $tpl;

            $it = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($ri as $file) {
                $file->isDir() ? rmdir($file) : unlink($file);
            }
            @rmdir($path);

            addMessage($messages, sprintf(__('template_deleted'), $tpl), 'success');
        }
    }

    // Template hochladen
// Template hochladen
elseif (isset($_FILES['template_zip'])) {
    $uploadDir = $templateDir;
    $zipFile = $_FILES['template_zip']['tmp_name'];
    $zipName = basename($_FILES['template_zip']['name']);
    $templateFolder = pathinfo($zipName, PATHINFO_FILENAME); 
    $extractPath = $uploadDir . '/' . $templateFolder;

    // CSRF prüfen
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $templateExistsError = __('invalid_csrf_token');
    }
    elseif ($_FILES['template_zip']['error'] !== UPLOAD_ERR_OK) {
        $templateExistsError = __('upload_error');
    }
    elseif (strtolower(pathinfo($zipName, PATHINFO_EXTENSION)) !== 'zip') {
        $templateExistsError = __('invalid_zip_file');
    }
    elseif (file_exists($extractPath)) {
        // Version des installierten Templates
        $installedVersion = '';
        $installedInfoFile = $extractPath . '/info.json';
        if (file_exists($installedInfoFile)) {
            $installedData = json_decode(file_get_contents($installedInfoFile), true);
            if (isset($installedData['version'])) {
                $installedVersion = $installedData['version'];
            }
        }

        // Version des neuen Templates aus der ZIP
        $newVersion = '';
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === true) {
            // Root-Ordner der ZIP ermitteln
            $firstFolder = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $parts = explode('/', $stat['name']);
                if (!empty($parts[0])) {
                    $firstFolder = $parts[0];
                    break;
                }
            }

            // info.json im Root-Ordner lesen
            $infoIndex = $zip->locateName($firstFolder . '/info.json', ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
            if ($infoIndex !== false) {
                $contents = $zip->getFromIndex($infoIndex);
                $infoData = json_decode($contents, true);
                if (isset($infoData['version'])) {
                    $newVersion = $infoData['version'];
                }
            }
            $zip->close();
        }

        // Dialog-Fehler zusammenstellen
        $templateExistsError = sprintf(
            __('template_already_exists_versions'),
            $templateFolder,
            $installedVersion ?: __('no_version_info'),
            $newVersion ?: __('no_version_info')
        );
    }
    else {
        // ZIP entpacken
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === true) {
            // Root-Ordner der ZIP
            $firstFolder = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $parts = explode('/', $stat['name']);
                if (!empty($parts[0])) {
                    $firstFolder = $parts[0];
                    break;
                }
            }

            if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);

            // Dateien extrahieren ohne doppelten Root-Ordner
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $fileName = $stat['name'];
                $relativePath = preg_replace('#^' . preg_quote($firstFolder . '/', '#') . '#', '', $fileName);
                if ($relativePath === '') continue;
                if (substr($fileName, -1) === '/') {
                    @mkdir($extractPath . '/' . $relativePath, 0755, true);
                } else {
                    file_put_contents($extractPath . '/' . $relativePath, $zip->getFromIndex($i));
                }
            }

            $zip->close();
            addMessage($messages, sprintf(__('template_uploaded'), $templateFolder), 'success');
        } else {
            $templateExistsError = __('failed_to_extract_zip');
        }
    }
} elseif (isset($_FILES['template_zip']) && $_FILES['template_zip']['error'] !== UPLOAD_ERR_OK) {
        addMessage($messages, __('upload_error'), 'error');
    }
}

/* ===============================
   Templates laden
================================ */
$templates = [];

foreach (scandir($templateDir) as $dir) {
    if ($dir === '.' || $dir === '..') continue;

    $path = $templateDir . '/' . $dir;
    if (!is_dir($path)) continue;

    $info = [
        'folder' => $dir,
        'name' => $dir,
        'description' => __('no_description'),
        'screenshot' => null
    ];

    if (file_exists("$path/info.json")) {
        $json = json_decode(file_get_contents("$path/info.json"), true);
        if ($json) {
            $info = array_merge($info, $json);
        }
    }

    if (file_exists("$path/screenshot.png")) {
        $info['screenshot'] = "screenshot.png";
    }

    $templates[] = $info;
}

// aktives Template nach oben
usort($templates, fn($a, $b) =>
    $a['folder'] === $activeTemplate ? -1 :
    ($b['folder'] === $activeTemplate ? 1 :
        strcasecmp($a['name'], $b['name']))
);

$pageTitle = __('template_manage');

ob_start();
?>

<h1><?= htmlspecialchars($pageTitle) ?></h1>

<label for="filter"><?= __('search_templates') ?>:</label>
<input id="filter" class="admin-search" type="search"
       placeholder="<?= __('search_templates_placeholder') ?>">
       
<form class="upload-form" method="post" enctype="multipart/form-data" id="uploadForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div id="dropZone" class="drop-zone">
        <p><?= __('upload_instruction') ?></p>
        <input type="file" name="template_zip" accept=".zip" required hidden>
    </div>
    <button type="submit"><?= __('upload') ?></button>
</form>

<dialog id="errorDialog" class="modal">
    <form method="dialog">
        <p id="dialogMessage"><?= htmlspecialchars($templateExistsError ?? '') ?></p>
        <menu>
            <button value="ok">OK</button>
        </menu>
    </form>
</dialog>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

<fieldset class="plugin-fieldset">
        <legend class="sr-only"><?= __('select template to activate') ?></legend>

<div class="template-list">
<?php foreach ($templates as $tpl): ?>
    <details class="entry-block template-block <?= $tpl['folder'] === $activeTemplate ? 'active' : '' ?>">
        <summary class="template-summary" aria-expanded="false">

            <svg class="toggle-arrow" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M5 3l5 5-5 5"/>
            </svg>

            <input type="radio"
                   name="template"
                   id="template_<?= htmlspecialchars($tpl['folder']) ?>"
                   value="<?= htmlspecialchars($tpl['folder']) ?>"
                   <?= $tpl['folder'] === $activeTemplate ? 'checked' : '' ?>>

            <label for="template_<?= htmlspecialchars($tpl['folder']) ?>">
                <span class="entry-name"><?= htmlspecialchars($tpl['name']) ?></span>
            </label>
            
            <?php if ($tpl['folder'] === $activeTemplate): ?>
                <span class="active-badge"><?= __('active') ?></span>
            <?php endif; ?>

            <?php if ($tpl['folder'] !== $activeTemplate): ?>
                <button class="maru-delete js-delete" aria-label="<?= htmlspecialchars(__('delete')) ?>" data-title="<?= htmlspecialchars(__('delete')) ?>" data-message="<?= htmlspecialchars(__('delete_confirm_template')) ?>"
    data-form="deleteTemplateForm"
    data-input="deleteTemplateInput"
    data-value="<?= htmlspecialchars($tpl['folder']) ?>">
    <?= getIcon('delete') ?>
</button>
            <?php endif; ?>

        </summary>

        <div class="maru-ext-details template-details">
            <?php if ($tpl['screenshot']): ?>
                <img class="template-screenshot"
                     src="../templates/<?= urlencode($tpl['folder']) ?>/screenshot.png"
                     alt="<?= __('preview') ?>">
            <?php endif; ?>

            <p>
                <strong><?= __('description') ?>:</strong>
                <?= htmlspecialchars($tpl['description']) ?>
            </p>

            <a class="edit-link"
               href="edit_template.php?template=<?= urlencode($tpl['folder']) ?>">
                <?= __('edit') ?>
            </a>
        </div>
    </details>
<?php endforeach; ?>
</div>
</fieldset>

<button type="submit"><?= __('activate_selected') ?></button>

</form>

<form method="post" id="deleteTemplateForm" hidden>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="delete_template" id="deleteTemplateInput">
</form>


<script>
/* aria-expanded */
document.querySelectorAll('details.template-block').forEach(d => {
    const s = d.querySelector('summary');
    d.addEventListener('toggle', () =>
        s.setAttribute('aria-expanded', d.open ? 'true' : 'false')
    );
});


</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('errorDialog');
    const message = dialog.querySelector('#dialogMessage').textContent.trim();

    if (message) {
        dialog.showModal(); // öffnet das Dialog, wenn Text vorhanden
    }
});
</script>

<?php
$content = ob_get_clean();
include '_layout.php';
