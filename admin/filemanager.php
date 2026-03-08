<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php'; 

$pageHasDialog = true;

// Signal, dass diese Seite Filter braucht
$pageHasFilter = true;

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_files');
$uploadBase = realpath(__DIR__ . '/../uploads');
$mediaDir   = $uploadBase . '/media';
$systemDir  = $uploadBase . '/system';

$jsonPath = realpath(__DIR__ . '/../config/allowed_filetypes.json');
$allowedTypes = [];
$allowedExts = [];
$acceptedExtAttr = '';

if (file_exists($jsonPath)) {
    $jsonContent = file_get_contents($jsonPath);
    $typesData = json_decode($jsonContent, true);

    if (
        isset($typesData['images']['mime_types']) &&
        isset($typesData['general_extra']['mime_types'])
    ) {
        $allowedTypes = array_merge(
            $typesData['images']['mime_types'],
            $typesData['general_extra']['mime_types']
        );

        $allowedExts = array_merge(
            $typesData['images']['extensions'],
            $typesData['general_extra']['extensions']
        );

        $acceptedExtAttr = implode(',', array_map(fn($ext) => '.' . $ext, $allowedExts));
    } else {
        die('Ungültiges Format in allowed_filetypes.json');
    }
} else {
    die('allowed_filetypes.json nicht gefunden');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$selectMode = isset($_GET['select']) && $_GET['select'] == 1;

// Alt-Texte laden aus /uploads/.alt_texts.json
$altTextsPath = $systemDir . '/alt_texts.json';
$altTexts = [];

if (file_exists($altTextsPath)) {
    $altTexts = json_decode(file_get_contents($altTextsPath), true);
    if (!is_array($altTexts)) {
        $altTexts = [];
    }
}

// Bildunterschriften laden
$captionsPath = $systemDir . '/captions.json';
$captions = [];

if (file_exists($captionsPath)) {
    $captions = json_decode(file_get_contents($captionsPath), true);
    if (!is_array($captions)) {
        $captions = [];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	

    // CSRF prüfen
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Ungültiges CSRF-Token');
    }

    /*
    |---------------------------------------------
    | Dateien löschen
    |---------------------------------------------
    */
    if (!$selectMode && !empty($_POST['delete_files']) && is_array($_POST['delete_files'])) {


        foreach ($_POST['delete_files'] as $relativePath) {

            $relativePath = str_replace(['..', "\0"], '', $relativePath);
            $filePath = realpath($uploadBase . $relativePath);

            if ($filePath && str_starts_with($filePath, realpath($mediaDir)) && is_file($filePath)) {

                $fileName = basename($filePath);

                if (unlink($filePath)) {
                    addMessage($messages, sprintf(__('file_deleted'), $fileName), 'success');
                } else {
                    addMessage($messages, sprintf(__('file_delete_failed'), $fileName), 'error');
                }

                if (isset($altTexts[$fileName])) unset($altTexts[$fileName]);
                if (isset($captions[$fileName])) unset($captions[$fileName]);

            } else {
                addMessage($messages, sprintf(__('file_delete_failed'), $relativePath), 'error');
            }
        }

        file_put_contents(
            $altTextsPath,
            json_encode($altTexts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        file_put_contents(
            $captionsPath,
            json_encode($captions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /*
    |---------------------------------------------
    | Dateien hochladen
    |---------------------------------------------
    */
    if (isset($_FILES['files'])) {

    foreach ($_FILES['files']['tmp_name'] as $i => $tmp) {

        $name = basename($_FILES['files']['name'][$i]);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        // Gefährliche Extensions direkt blockieren
        if (preg_match('/\.(php|phtml|exe|sh|bat)$/i', $name)) {
            addMessage($messages, sprintf(__('invalid_filename'), $name), 'error');
            continue;
        }

        // Doppelte gefährliche Extensions prüfen (z.B. test.php.txt)
        if (preg_match('/\.(php|phtml|exe|sh|bat)\.[a-z0-9]+$/i', $name)) {
            addMessage($messages, sprintf(__('invalid_filename'), $name), 'error');
            continue;
        }

        // Extension erlaubt?
        if (!in_array($ext, $allowedExts)) {
            addMessage($messages, sprintf(__('invalid_filetype'), $name), 'error');
            continue;
        }

        // Upload gültig?
        if (empty($tmp) || !is_uploaded_file($tmp)) {
            addMessage($messages, sprintf(__('upload_error'), $name), 'error');
            continue;
        }

        // MIME-Type prüfen
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmp);

        if (!in_array($mimeType, $allowedTypes)) {
            addMessage($messages, sprintf(__('invalid_filetype'), $name), 'error');
            continue;
        }

        // Mime-Type vs Extension Map (zusätzliche Kontrolle)
        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'zip'  => 'application/zip',
            'rar'  => 'application/x-rar-compressed',
        ];

        if (isset($mimeMap[$ext]) && $mimeMap[$ext] !== $mimeType) {
            addMessage($messages, sprintf(__('invalid_filetype'), $name), 'error');
            continue;
        }
        
// **GIF-Header prüfen**
        if ($ext === 'gif') {
            $header = file_get_contents($tmp, false, null, 0, 6);
            if (!in_array($header, ["GIF87a", "GIF89a"])) {
                addMessage($messages, sprintf(__('invalid_filetype'), $name), 'error');
                continue;
            }
        }

        // Zielordner bestimmen
        $imageExts = ['jpg','jpeg','png','webp','gif'];
        $docExts   = ['pdf','docx','txt'];
        $videoExts = ['mp4','webm'];
        $audioExts = ['mp3','wav'];

        if (in_array($ext, $imageExts)) {
            $targetDir = $mediaDir . '/images';
        } elseif (in_array($ext, $docExts)) {
            $targetDir = $mediaDir . '/documents';
        } elseif (in_array($ext, $videoExts)) {
            $targetDir = $mediaDir . '/videos';
        } elseif (in_array($ext, $audioExts)) {
            $targetDir = $mediaDir . '/audio';
        } else {
            $targetDir = $mediaDir . '/other';
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $target = $targetDir . '/' . $name;

        // Datei existiert schon?
        if (file_exists($target)) {
            addMessage($messages, sprintf(__('file_exists'), $name), 'error');
            continue;
        }

        // Datei verschieben
        if (move_uploaded_file($tmp, $target)) {
            addMessage($messages, sprintf(__('file_uploaded'), $name), 'success');
        } else {
            addMessage($messages, sprintf(__('upload_error'), $name), 'error');
        }
    }
}
}


ob_start();
?>
<h1><?= $selectMode ? 'Bild auswählen' : __('manage_files') ?></h1>



<!-- Filter für Dateinamen -->
<label for="filter"><?= __('search_files') ?>:</label>
<input type="text" id="filter" class="admin-search" placeholder="<?= htmlspecialchars(__('search_files_placeholder')) ?>" />


<h2><?= __('upload_files') ?></h2>

<form method="post" enctype="multipart/form-data" class="upload-form" id="uploadForm">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div id="dropZone" class="drop-zone" tabindex="0" role="button"
         aria-label="<?= __('upload_instruction') ?>">
        <p><?= __('upload_instruction') ?></p>
        <input type="file" name="files[]" multiple accept="<?= htmlspecialchars($acceptedExtAttr) ?>" hidden aria-label="Choose a file" required>
    </div>

    <button type="submit"><?= __('upload_files') ?></button>
</form>




<h2><?= $selectMode ? 'Verfügbare Bilder' : __('existing_files') ?></h2>

<div class="file-list">
<?php
// Alle Dateien aus dem Verzeichnis holen, ohne nach Ordnern zu gruppieren
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($mediaDir, FilesystemIterator::SKIP_DOTS)
);

$files = [];
foreach ($rii as $file) {
    if ($file->isFile()) {
        $files[] = $file->getPathname(); // Alle Dateien in einem Array sammeln
}
}

// Alphabetisch sortieren
sort($files);

// Jetzt die Dateien in der alphabetischen Reihenfolge anzeigen
foreach ($files as $filePath):
    $fileName = basename($filePath);
    $relativePath = str_replace(realpath($uploadBase), '', $filePath);
    $relativePath = str_replace('\\','/',$relativePath); // Windows-Slashes fix
    $fileUrl = $baseUrl . '/uploads' . $relativePath;

    $fileType = mime_content_type($filePath);

    if (strpos($fileType, 'image/') === 0) {
        $fileTypeAttr = 'image';
    } elseif (pathinfo($fileName, PATHINFO_EXTENSION) === 'txt') {
        $fileTypeAttr = 'text';
    } else {
        $fileTypeAttr = 'other';
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $iconMap = [
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'webp' => 'image',
        'txt'  => 'txt',
        'pdf'  => 'pdf',
        'zip'  => 'zip',
        'rar'  => 'zip',
    ];

    $iconName = $iconMap[$ext] ?? 'file';
    $iconSvg  = getIcon($iconName);

    $thumb = '<div class="file-icon preview-trigger" style="cursor:pointer;">' . $iconSvg . '</div>';

    // Alt + Caption nur für Bilder
    $dataAlt = $iconName === 'image' ? htmlspecialchars($altTexts[$fileName] ?? '') : '';
    $dataCaption = $iconName === 'image' ? htmlspecialchars($captions[$fileName] ?? '') : '';

?>
    <div class="entry-block file-item"
         data-type="<?= in_array($iconName, ['txt', 'pdf', 'zip']) ? 'text' : $iconName ?>" 
         data-url="<?= htmlspecialchars($fileUrl) ?>"
         data-alt="<?= $dataAlt ?>"
         data-caption="<?= $dataCaption ?>"
         tabindex="0"
         role="group"
         aria-label="Datei <?= htmlspecialchars($fileName) ?>">

        <div class="preview-trigger"
             role="button"
             tabindex="0"
             aria-label="Vorschau für <?= htmlspecialchars($fileName) ?> öffnen">
            <?= $thumb ?>
        </div>

        <div class="entry-name filename"><?= htmlspecialchars($fileName) ?></div>
        <?php if (!$selectMode): ?>
            <button type="button" class="maru-delete js-delete"
        data-file="<?= htmlspecialchars($relativePath) ?>"
data-input="deleteFileInput"
        data-form="deleteFileForm" 
        data-title="<?= __('delete') ?>"
        data-message="<?= htmlspecialchars(__('delete_confirm_file')) ?>"
        data-value="<?= htmlspecialchars($relativePath) ?>"
        aria-label="<?= __('delete_file') ?> <?= htmlspecialchars($fileName) ?>">
    <?= getIcon('delete') ?>
</button>
        <?php endif; ?>
    </div>

<?php endforeach; ?>
</div>




<form id="deleteFileForm" method="post" hidden>
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="delete_files[]" id="deleteFileInput">
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropZone  = document.getElementById('dropZone');
    const fileInput = dropZone.querySelector('input[type="file"]');
    const form      = document.getElementById('uploadForm');

    // Klick → Filepicker
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    // Drag-Over Styling
    dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    // Drop → Upload
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            form.submit();
        }
    });

    // Auswahl über Filepicker → Upload
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            form.submit();
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('imagePreviewDialog');
    const previewImg = document.getElementById('previewImage');
    const previewText = document.getElementById('previewText');
 //   const closeBtn = document.getElementById('modalClose');
    const fileNameEl = document.getElementById('fileName');
    const fileSizeEl = document.getElementById('fileSize');
    const fileDimensionsEl = document.getElementById('fileDimensions');
    const imageMeta = document.getElementById('imageMeta');
    const altTextInput = document.getElementById('altTextInput');
    const captionInput = document.getElementById('captionInput');
    const saveMetaBtn = document.getElementById('saveMetaBtn');
    const metaStatus = document.getElementById('metaStatus');

    let currentFile = null;

    document.querySelectorAll('.file-item .preview-trigger').forEach(trigger => {
    trigger.addEventListener('click', async (e) => {
        e.stopPropagation(); // verhindert, dass Checkbox geklickt wird
        const item = trigger.closest('.file-item');

        const file = item.querySelector('.filename').textContent;
        const type = item.dataset.type;
        const url = item.dataset.url;

        currentFile = file;
        fileNameEl.textContent = file;
        fileSizeEl.textContent = '';
        fileDimensionsEl.textContent = ''; // Initialisierung, um Dimensionen zu leeren
        metaStatus.textContent = '';

        // Vorschau zurücksetzen
        previewImg.style.display = 'none';
        previewText.style.display = 'none';
        document.getElementById('imageMeta').style.display = 'none'; // Verstecke das Metadatenformular

        if (type === 'image') {
            previewImg.src = url;
            previewImg.alt = item.dataset.alt || '';
            previewImg.style.display = 'block';

            altTextInput.value = item.dataset.alt || '';
            captionInput.value = item.dataset.caption || '';
            altTextInput.dataset.filename = file;
            captionInput.dataset.filename = file;

            // Bildgröße und Dimensionen anzeigen
            document.getElementById('imageMeta').style.display = 'flex'; // Zeige das Metadatenformular

            fetch(url, { method: 'HEAD' }).then(res => {
                const size = res.headers.get('content-length');
                fileSizeEl.textContent = size ? (size / 1024).toFixed(1) + ' KB' : 'n/a';
            });

            previewImg.onload = () => {
                fileDimensionsEl.textContent = previewImg.naturalWidth + ' x ' + previewImg.naturalHeight; // Nur für Bilder
            };
        } else if (type === 'text') {
            try {
                const res = await fetch(url);
                const text = await res.text();
                previewText.textContent = text;
                previewText.style.display = 'block';
previewText.style.whiteSpace = 'normal'; // Standard Textumbruch setzen
                fileSizeEl.textContent = text.length + ' Bytes';
                fileDimensionsEl.textContent = '–'; // Für .txt-Dateien keine Dimensionen anzeigen
            } catch {
                previewText.textContent = 'Fehler beim Laden der Datei';
            }

            // Keine Metadaten und Dimensionen für Textdateien anzeigen
            document.getElementById('imageMeta').style.display = 'none';
        } else {
            fileSizeEl.textContent = '–';
            fileDimensionsEl.textContent = '–';
        }

        // Dialog öffnen und Fokus setzen für Screenreader
        dialog.showModal();
        dialog.focus();
    });
});

    // Alt + Caption speichern
    saveMetaBtn.addEventListener('click', async () => {
        const file = altTextInput.dataset.filename;
        if (!file) return;

        const alt = altTextInput.value.trim();
        const caption = captionInput.value.trim();

        const fileItem = [...document.querySelectorAll('.file-item')]
            .find(f => f.querySelector('.filename').textContent === file);
        const img = fileItem?.querySelector('img');

        // vorherige Werte sichern für Fehlerfall
        const previousAlt = img?.alt || '';
        const previousCaption = img?.dataset.caption || '';

        if (img) {
            // UI sofort aktualisieren
            img.alt = alt;
            img.dataset.caption = caption;

            // data-Attribute im file-item aktualisieren
            fileItem.dataset.alt = alt;
            fileItem.dataset.caption = caption;
        }

        metaStatus.textContent = 'Speichern…';
        metaStatus.className = 'alt-status saving';

        try {
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;

            // Alt-Text speichern
            const altRes = await fetch('save_alt_text.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ filename: file, altText: alt, csrf_token: csrfToken })
            });
            if (!altRes.ok) throw new Error('Netzwerkfehler beim Alt-Text speichern');
            const altData = await altRes.json();
            if (!altData.success) throw new Error(altData.msg || 'Fehler beim Speichern Alt-Text');

            // Caption speichern
            const captionRes = await fetch('save_caption.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ filename: file, caption: caption, csrf_token: csrfToken })
            });
            if (!captionRes.ok) throw new Error('Netzwerkfehler beim Caption speichern');
            const captionData = await captionRes.json();
            if (!captionData.success) throw new Error(captionData.msg || 'Fehler beim Speichern Caption');

            // Erfolgsanzeige
            metaStatus.textContent = 'Gespeichert ✓';
            metaStatus.className = 'alt-status success';
            setTimeout(() => {
                metaStatus.textContent = '';
                metaStatus.className = 'alt-status';
            }, 2000);

        } catch (err) {
            console.error(err);

            // Preview zurücksetzen bei Fehler
            if (img) {
                img.alt = previousAlt;
                img.dataset.caption = previousCaption;
            }

            metaStatus.textContent = 'Fehler beim Speichern';
            metaStatus.className = 'alt-status error';
        }
    });
});

</script>

<?php
$content = ob_get_clean();
include '_layout.php';
