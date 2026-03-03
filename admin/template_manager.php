<?php
session_start();

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

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
$message = '';
$messageType = 'success';

/* ===============================
   POST
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        $message = __('invalid_csrf_token');
        $messageType = 'error';
    }

    // Template aktivieren
    elseif (isset($_POST['template'])) {
        $tpl = basename($_POST['template']);
        $settings['template'] = $tpl;
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $activeTemplate = $tpl;
        $message = sprintf(__('template_activated'), $tpl);
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

            $message = sprintf(__('template_deleted'), $tpl);
        }
    }
    
    // Template hochladen
    elseif (isset($_FILES['template_zip']) && $_FILES['template_zip']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $templateDir;
        $zipFile = $_FILES['template_zip']['tmp_name'];
        $zipName = basename($_FILES['template_zip']['name']);

        // Überprüfen ob die Datei eine ZIP-Datei ist
        if (pathinfo($zipName, PATHINFO_EXTENSION) !== 'zip') {
            $message = __('invalid_zip_file');
            $messageType = 'error';
        } else {
            // Entpacken der ZIP-Datei
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === true) {
                $extractPath = $uploadDir . '/' . pathinfo($zipName, PATHINFO_FILENAME);
                if (!file_exists($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }
                $zip->extractTo($extractPath);
                $zip->close();

                // Rückmeldung nach erfolgreichem Upload
                $message = sprintf(__('template_uploaded'), $zipName);
                $messageType = 'success';
            } else {
                $message = __('failed_to_extract_zip');
                $messageType = 'error';
            }
        }
    } elseif ($_FILES['template_zip']['error'] !== UPLOAD_ERR_OK) {
        $message = __('upload_error');
        $messageType = 'error';
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

<?php if ($message): ?>
    <div class="message <?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<label for="templateSearch"><?= __('search_templates') ?>:</label>
<input id="templateSearch" class="admin-search" type="search"
       placeholder="<?= __('search_templates_placeholder') ?>">
       
<form class="upload-form" method="post" enctype="multipart/form-data" id="uploadForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div id="dropZone" class="drop-zone">
        <p><?= __('upload_instruction') ?></p>
        <input type="file" name="template_zip" accept=".zip" required hidden>
    </div>
    <button type="submit"><?= __('upload') ?></button>
</form>

<form method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

<fieldset class="plugin-fieldset">
        <legend class="sr-only"><?= __('select template to activate') ?></legend>

<div class="template-list">
<?php foreach ($templates as $tpl): ?>
    <details class="template-block <?= $tpl['folder'] === $activeTemplate ? 'active' : '' ?>">
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
                <strong><?= htmlspecialchars($tpl['name']) ?></strong>
                
            </label>
            
            <?php if ($tpl['folder'] === $activeTemplate): ?>
                    <span class="active-badge"><?= __('active') ?></span>
                <?php endif; ?>

            <?php if ($tpl['folder'] !== $activeTemplate): ?>
                <button type="button"
        class="maru-delete delete-template"
        data-title="<?= htmlspecialchars(__('delete'), ENT_QUOTES, 'UTF-8') ?>"
        data-message="<?= htmlspecialchars(__('delete_confirm_template'), ENT_QUOTES, 'UTF-8') ?>"
        data-template="<?= htmlspecialchars($tpl['folder'], ENT_QUOTES, 'UTF-8') ?>"
        title="<?= __('delete') ?>">
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

/* Suche */
document.getElementById('templateSearch').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('.template-block').forEach(b => {
        const name = b.querySelector('summary strong')?.textContent.toLowerCase() || '';
        b.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>

<?php
$content = ob_get_clean();
include '_layout.php';
