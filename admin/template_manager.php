<?php
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sprachdatei und Funktionen laden
require_once __DIR__ . '/init.php';

// Einstellungen laden
$settingsFile = __DIR__ . '/../config/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$baseUrl = rtrim($settings['base_url'] ?? '', '/');

// Aktuelles Template aus den Einstellungen laden
$template = $settings['template'] ?? 'default';
$templatesDir = __DIR__ . '/../templates';
$activeTemplateFile = __DIR__ . '/../config/active_template.json';
$activeTemplate = $settings['template'] ?? 'default';

$message = null;

function normalize_path(string $path): string {
    $path = str_replace('\\', '/', $path);            // Backslashes → Slashes
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    return implode('/', $parts);
}

/**
 * Sichere Extraktion einer ZipArchive-Instanz nach $extractPath
 * @return array [ok(bool), message(string)]
 */
function secure_zip_extract(ZipArchive $zip, string $extractPath, array $opts = []): array {
    $maxFiles = $opts['max_files'] ?? 2000;          // sinnvolle Obergrenze
    $maxTotal = $opts['max_total_bytes'] ?? 200 * 1024 * 1024; // 200 MB
    $perFileMax = $opts['per_file_max_bytes'] ?? 50 * 1024 * 1024; // 50 MB

    // Zielverzeichnis erstellen
    if (!is_dir($extractPath) && !mkdir($extractPath, 0755, true)) {
        return [false, 'Zielverzeichnis konnte nicht erstellt werden.'];
    }
    $rootReal = realpath($extractPath);
    if ($rootReal === false) {
        return [false, 'Zielverzeichnis ist ungültig.'];
    }

    // Vorab-Zählung & Prüfung
    $numFiles = $zip->numFiles;
    if ($numFiles > $maxFiles) {
        return [false, 'ZIP enthält zu viele Einträge.'];
    }

    $total = 0;
    for ($i = 0; $i < $numFiles; $i++) {
        $nameRaw = $zip->getNameIndex($i);
        if ($nameRaw === false) return [false, 'Fehler beim Lesen der ZIP-Einträge.'];

        // Grundlegende Checks
        if (strpos($nameRaw, "\0") !== false) return [false, 'ZIP enthält Null-Bytes im Pfad.'];

        $name = normalize_path($nameRaw);
        if ($name === '') continue;                  // leere Einträge ignorieren
        if (str_starts_with($name, '/')) return [false, 'Absolute Pfade in ZIP nicht erlaubt.'];
        if (preg_match('/^[A-Za-z]:/', $name) === 1) return [false, 'Laufwerksangaben nicht erlaubt.'];
        if (strpos($name, '../') !== false) return [false, 'Pfad enthält unzulässige Navigation.'];

        $stat = $zip->statIndex($i);
        if ($stat === false) return [false, 'Fehlerhafte ZIP-Metadaten.'];

        // Symlink blocken (Unix: 0xA000)
        if (isset($stat['external_attributes'])) {
            $mode = ($stat['external_attributes'] >> 16) & 0xF000;
            if ($mode === 0xA000) {
                return [false, 'Symbolische Links sind im ZIP nicht erlaubt.'];
            }
        }

        $size = (int)($stat['size'] ?? 0);
        if ($size > $perFileMax) {
            return [false, 'Eine Datei im ZIP überschreitet die erlaubte Größe.'];
        }
        $total += $size;
        if ($total > $maxTotal) {
            return [false, 'Gesamtgröße des ZIP überschreitet das Limit.'];
        }
    }

    // Tatsächliche Extraktion Datei für Datei
    for ($i = 0; $i < $numFiles; $i++) {
        $name = normalize_path($zip->getNameIndex($i));
        if ($name === '') continue;

        $isDir = str_ends_with($name, '/');
        $dest   = $rootReal . DIRECTORY_SEPARATOR . $name;
        $destDir = $isDir ? $dest : dirname($dest);

        // Zielverzeichnis anlegen
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            return [false, 'Zielverzeichnis konnte nicht erstellt werden.'];
        }

        // Sicherheits-Prefix-Check nach Verzeichnisanlage
        $destDirReal = realpath($destDir);
        if ($destDirReal === false || strpos($destDirReal, $rootReal) !== 0) {
            return [false, 'Pfadvalidierung fehlgeschlagen.'];
        }

        if ($isDir) continue;

        // Stream-basiertes Extrahieren
        $read = $zip->getStream($zip->getNameIndex($i));
        if ($read === false) return [false, 'Dateistream aus ZIP konnte nicht geöffnet werden.'];

        $write = @fopen($dest, 'wb');
        if ($write === false) { fclose($read); return [false, 'Zieldatei konnte nicht geschrieben werden.']; }

        // Kopieren in Blöcken (RAM-sparend, schützt zusätzlich gegen Bomben)
        $bytes = 0;
        while (!feof($read)) {
            $buf = fread($read, 1024 * 1024);
            if ($buf === false) { fclose($read); fclose($write); return [false, 'Fehler beim Lesen aus ZIP.']; }
            $bytes += strlen($buf);
            if ($bytes > $perFileMax) { fclose($read); fclose($write); return [false, 'Datei überschreitet Größenlimit beim Entpacken.']; }
            if (fwrite($write, $buf) === false) { fclose($read); fclose($write); return [false, 'Fehler beim Schreiben der Zieldatei.']; }
        }
        fclose($read);
        fclose($write);

        @chmod($dest, 0644); // sichere Standardrechte
    }

    return [true, 'ZIP erfolgreich extrahiert.'];
}


// Template löschen, aktivieren und hochladen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('Ungültiger CSRF-Token. Aktion abgebrochen.');
    }

    if (isset($_POST['delete_template'])) {
        $toDelete = basename($_POST['delete_template']);
        $deletePath = $templatesDir . '/' . $toDelete;
        if (is_dir($deletePath) && $toDelete !== $activeTemplate) {
            function rrmdir($dir) {
                foreach (scandir($dir) as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $dir . '/' . $item;
                    is_dir($path) ? rrmdir($path) : unlink($path);
                }
                rmdir($dir);
            }
            rrmdir($deletePath);
            $message = sprintf(__('template_deleted'), $toDelete);
        }
    } elseif (isset($_POST['template'])) {
        $newTemplate = basename($_POST['template']);
        file_put_contents($activeTemplateFile, json_encode(['active' => $newTemplate], JSON_PRETTY_PRINT));

        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            $settings['template'] = $newTemplate;
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $activeTemplate = $newTemplate;
        $message = sprintf(__('template_activated'), $newTemplate);
    } } elseif (isset($_FILES['template_zip'])) {
    $zipName = $_FILES['template_zip']['name'] ?? '';
    $zipTmp  = $_FILES['template_zip']['tmp_name'] ?? '';
    $zipSize = $_FILES['template_zip']['size'] ?? 0;
    $maxUpload = 220 * 1024 * 1024; // 220 MB Upload-Grenze

    if ($zipSize <= 0 || $zipSize > $maxUpload) {
        $message = __('zip_too_large_or_empty');
    } elseif (strtolower(pathinfo($zipName, PATHINFO_EXTENSION)) === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($zipTmp) === TRUE) {
            $folderName  = basename($zipName, '.zip');
            $folderName  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $folderName); // konservativ
            $extractPath = $templatesDir . '/' . $folderName;

            if (!is_dir($extractPath)) {
                // Sichere Extraktion mit Limits
                [$ok, $msg] = secure_zip_extract($zip, $extractPath, [
                    'max_files' => 3000,
                    'max_total_bytes' => 180 * 1024 * 1024,  // 180 MB inhaltlich
                    'per_file_max_bytes' => 40 * 1024 * 1024 // 40 MB pro Datei
                ]);
                $zip->close();

                if ($ok) {
                    $message = sprintf(__('template_installed'), $folderName);
                } else {
                    // Aufräumen bei Fehler
                    if (is_dir($extractPath)) {
                        // rekursiv löschen
                        $it = new RecursiveDirectoryIterator($extractPath, FilesystemIterator::SKIP_DOTS);
                        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                        foreach ($ri as $file) {
                            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                        }
                        @rmdir($extractPath);
                    }
                    $message = htmlspecialchars($msg);
                }
            } else {
                $message = sprintf(__('template_exists'), $folderName);
                $zip->close();
            }
        } else {
            $message = __('zip_open_error');
        }
    } else {
        $message = __('zip_only');
    }
}



// Templates einlesen
$templates = [];
if (is_dir($templatesDir)) {
    foreach (scandir($templatesDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $templatePath = $templatesDir . '/' . $entry;
        if (is_dir($templatePath)) {
            $infoFile = $templatePath . '/info.json';
            $screenshot = file_exists($templatePath . '/screenshot.png') ? 'screenshot.png' : null;
            $info = ['name' => $entry, 'description' => __('no_description')];
            if (file_exists($infoFile)) {
                $info = json_decode(file_get_contents($infoFile), true);
            }
            $info['folder'] = $entry;
            $info['screenshot'] = $screenshot;
            $templates[] = $info;
        }
    }
}

usort($templates, function($a, $b) use ($activeTemplate) {
    if ($a['folder'] === $activeTemplate) return -1;
    if ($b['folder'] === $activeTemplate) return 1;
    return strcasecmp($a['name'], $b['name']);
});

$pageTitle = __('template_manage');
ob_start();
?>

<h1><?= __('template_manage') ?></h1>

<?php if ($message): ?>
    <div class="message success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Suchfeld -->
<label for="templateSearch"><?= __('search_templates') ?>:</label>
<input id="templateSearch" class="admin-search" type="search" placeholder="<?= __('search_templates_placeholder') ?>">

<form class="upload-form" method="post" enctype="multipart/form-data" id="uploadForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div id="dropZone" class="drop-zone">
        <p><?= __('drag_or_click_zip') ?></p>
        <input type="file" name="template_zip" accept=".zip" required hidden>
    </div>
    <button type="submit"><?= __('upload') ?></button>
</form>

<form method="post" class="templates-list">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <?php foreach ($templates as $tpl): ?>
        <div class="template-card <?= $tpl['folder'] === $activeTemplate ? 'active' : '' ?>">
            <?php if ($tpl['screenshot']): ?>
    <img class="screenshot screenshot-thumb" src="../templates/<?= urlencode($tpl['folder']) ?>/screenshot.png" alt="<?= __('preview') ?>" data-full="../templates/<?= urlencode($tpl['folder']) ?>/screenshot.png">
<?php endif; ?>

            <div class="info">
                <label class="template-header">
                    <input type="radio" name="template" value="<?= htmlspecialchars($tpl['folder']) ?>" <?= $tpl['folder'] === $activeTemplate ? 'checked' : '' ?>>
                    <strong><?= htmlspecialchars($tpl['name']) ?></strong>
                    <?php if ($tpl['folder'] === $activeTemplate): ?>
                        <span class="active-badge"><?= __('active') ?></span>
                    <?php endif; ?>
                </label>
                <details>
                    <summary><?= __('show_details') ?></summary>
                    <p><?= htmlspecialchars($tpl['description']) ?></p>
                </details>
                <div class="actions">
                    <a href="edit_template.php?template=<?= urlencode($tpl['folder']) ?>" class="edit-link"><?= __('edit') ?></a>
                    <?php if ($tpl['folder'] !== $activeTemplate): ?>
                        <button type="submit" name="delete_template" value="<?= htmlspecialchars($tpl['folder']) ?>" class="delete-button" onclick="return confirm('<?= __('confirm_delete_template') ?>')"><?= __('delete') ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <button type="submit" class="activate-button"><?= __('activate_selected') ?></button>
</form>

<!-- Screenshot-Modal -->

<dialog id="screenshotDialog">
<div class="modal">
  <button id="closeDialog" aria-label="Schließen">×</button>
  <img id="dialogImg" src="" alt="Vorschau">
</div>

</dialog>




<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('templateSearch');

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        document.querySelectorAll('.templates-list > .template-card').forEach(card => {
            const nameElem = card.querySelector('.template-header strong');
            const name = nameElem ? nameElem.textContent.toLowerCase() : '';
            const descElem = card.querySelector('details p');
            const description = descElem ? descElem.textContent.toLowerCase() : '';
            const match = name.includes(query) || description.includes(query);
            card.style.display = match ? '' : 'none';
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('screenshotDialog');
    const dialogImg = document.getElementById('dialogImg');
    const closeBtn = document.getElementById('closeDialog');

    // Klick auf Screenshot → Dialog öffnen
    document.querySelectorAll('.template-card img.screenshot').forEach(img => {
        img.addEventListener('click', () => {
            dialogImg.src = img.dataset.full || img.src;
            dialog.showModal();
        });
    });

    // Klick auf Close-Button → schließen
    closeBtn.addEventListener('click', () => {
        dialog.close();
        dialogImg.src = '';
    });

    // Klick außerhalb des Inhalts → schließen
    dialog.addEventListener('click', (e) => {
        const rect = dialog.getBoundingClientRect();
        if (
            e.clientX < rect.left || e.clientX > rect.right ||
            e.clientY < rect.top || e.clientY > rect.bottom
        ) {
            dialog.close();
            dialogImg.src = '';
        }
    });
});


</script>

<?php
$content = ob_get_clean();
include '_layout.php';
