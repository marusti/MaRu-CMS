<?php

require_once __DIR__ . '/init.php'; 



$jsonPath = realpath(__DIR__ . '/../config/allowed_filetypes.json');
$allowedImageTypes = [];

if (file_exists($jsonPath)) {
    $jsonContent = file_get_contents($jsonPath);
    $typesData = json_decode($jsonContent, true);
    if (isset($typesData['images']['mime_types'])) {
        $allowedImageTypes = $typesData['images']['mime_types'];
    } else {
        die('Ungültiges Format in allowed_filetypes.json');
    }
} else {
    die('allowed_filetypes.json nicht gefunden');
}

// Session prüfen - nur Admins
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../core/gallery_functions.php';

$galleryDir = __DIR__ . '/../uploads/gallery/';
$albums = getAlbums();

$error = '';
$success = '';

// Hilfsfunktion, um AJAX-Requests zu erkennen
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Neues Album anlegen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_album'])) {

    // Debug-Log vor der Prüfung
    file_put_contents(__DIR__ . '/csrf_debug.log', date('c') . " SESSION_TOKEN: " . ($_SESSION['csrf_token'] ?? 'NULL') . " POST_TOKEN: " . ($_POST['csrf_token'] ?? 'NULL') . PHP_EOL, FILE_APPEND);


    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    // Sicherer Vergleich mit hash_equals
    if (empty($postToken) || !hash_equals($sessionToken, $postToken)) {
    if (isAjaxRequest()) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    } else {
        die('Ungültiger CSRF-Token');
    }
    exit;
}

    $newAlbum = $_POST['new_album'];

    if (preg_match('/^[a-zA-Z0-9_-]+$/', $newAlbum)) {
        createAlbum($newAlbum);

        if (isAjaxRequest()) {
            echo json_encode([
                'success' => true,
                'message' => 'Album erfolgreich erstellt.',
            ]);
        } else {
            header("Location: gallery_manager.php");
        }
        exit;
    } else {
        $error = "Ungültiger Albumname.";

        if (isAjaxRequest()) {
            echo json_encode([
                'success' => false,
                'message' => $error,
            ]);
            exit;
        } else {
            die($error);
        }
    }
}

// Album löschen (normaler POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album']) && !isAjaxRequest()) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Ungültiger CSRF-Token');
    }
    $albumToDelete = basename($_POST['delete_album']);
    $albumPath = $galleryDir . $albumToDelete . '/';

    if (is_dir($albumPath)) {
        deleteFolder($albumPath);
        header('Location: gallery_manager.php');
        exit;
    } else {
        $error = 'Album nicht gefunden.';
    }
}

// Album löschen (AJAX kompatibel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_album']) && isAjaxRequest()) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger CSRF-Token',
        ]);
        exit;
    }

    $album = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['delete_album']);

    $albumDir = __DIR__ . '/../uploads/gallery/' . $album;
    if (is_dir($albumDir)) {
        try {
            deleteFolder($albumDir);
            echo json_encode(['success' => true, 'message' => 'Album erfolgreich gelöscht']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Album nicht gefunden']);
    }
    exit;
}

// Bild Upload (AJAX kompatibel)

// Sauberer Bild-Upload (AJAX-kompatibel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'], $_FILES['image'], $_POST['album'])) {
    // Alles puffern, um ungewollte Ausgaben zu vermeiden
    ob_start();
    
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => ''];

    try {
        // CSRF-Check
        if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Ungültiger CSRF-Token');
        }

        $currentAlbum = basename($_POST['album']);
        $file = $_FILES['image'];
        $allowed = $allowedImageTypes ?? ['image/jpeg', 'image/png', 'image/gif']; // Fallback

        // Upload-Fehler prüfen
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Fehler beim Upload.');
        }

        // MIME-Type prüfen
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowed)) {
            throw new Exception('Nur JPG, PNG oder GIF-Dateien sind erlaubt.');
        }

        // Sicherer Dateiname
        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($file['name']));
        $targetDir = rtrim($galleryDir, '/') . '/' . $currentAlbum . '/';
        $thumbDir  = $targetDir . "thumbnails/";
        $targetFile = $targetDir . $safeName;
        $thumbFile  = $thumbDir . $safeName;

        // Ordner erstellen, falls nicht vorhanden
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            throw new Exception('Konnte Datei nicht speichern.');
        }

        // Thumbnail erstellen
        if (function_exists('createThumbnail')) {
            createThumbnail($targetFile, $thumbFile, $maxWidth ?? 200, $maxHeight ?? 200);
        }

        // Metadaten aktualisieren
        if (function_exists('loadMetadata') && function_exists('saveMetadata')) {
            $meta = loadMetadata($currentAlbum) ?? [];
            $meta[$safeName] = ['title' => '', 'alt' => ''];
            saveMetadata($currentAlbum, $meta);
        }

        $response['success'] = true;
        $response['message'] = 'Bild erfolgreich hochgeladen.';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }


// Vor dem JSON
header('Content-Type: application/json; charset=utf-8');
ob_clean(); // alles vorherige im Puffer löschen
$response = ['success' => true, 'test' => 'ok'];
    echo json_encode($response);
    exit;
}



// Bild löschen (AJAX kompatibel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['album'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => ''];

   if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger CSRF-Token',
        ]);
        exit;
    }

    $currentAlbum = basename($_POST['album']);
    $delFile = basename($_POST['delete']);
    $filePath = $galleryDir . $currentAlbum . '/' . $delFile;
    $thumbPath = $galleryDir . $currentAlbum . '/thumbnails/' . $delFile;

    if (file_exists($filePath)) unlink($filePath);
    if (file_exists($thumbPath)) unlink($thumbPath);

    $meta = loadMetadata($currentAlbum);
    unset($meta[$delFile]);
    saveMetadata($currentAlbum, $meta);

    $response['success'] = true;
    $response['message'] = 'Bild gelöscht.';

    echo json_encode($response);
    exit;
}

// Metadaten speichern (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meta']) && isset($_POST['album'])) {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => ''];

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger CSRF-Token',
        ]);
        exit;
    }

    $currentAlbum = basename($_POST['album']);
    $meta = loadMetadata($currentAlbum);

    $titles = $_POST['title'] ?? [];
    $alts = $_POST['alt'] ?? [];

    if (!is_array($titles)) $titles = [];
    if (!is_array($alts)) $alts = [];

    foreach ($titles as $filename => $title) {
        $filename = basename($filename);
        if (!isset($meta[$filename])) {
            $meta[$filename] = ['title' => '', 'alt' => ''];
        }
        $meta[$filename]['title'] = strip_tags(trim($title));
        $meta[$filename]['alt'] = strip_tags(trim($alts[$filename] ?? ''));
    }

    if (saveMetadata($currentAlbum, $meta)) {
        $response['success'] = true;
        $response['message'] = 'Metadaten gespeichert.';
    } else {
        $response['message'] = 'Fehler beim Speichern der Metadaten.';
    }

    echo json_encode($response);
    exit;
}

// Pfad zur Settings-Datei
$settingsFile = realpath(__DIR__ . '/../config/settings.json');

// Einstellungen laden
$settings = ['thumb_width' => 150, 'thumb_height' => 150]; // default

if (file_exists($settingsFile)) {
    $json = file_get_contents($settingsFile);
    $loadedSettings = json_decode($json, true);
    if (is_array($loadedSettings)) {
        $settings = array_merge($settings, $loadedSettings);
    }
}

// Formular abgeschickt?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $width = isset($_POST['thumb_width']) ? (int)$_POST['thumb_width'] : 0;
    $height = isset($_POST['thumb_height']) ? (int)$_POST['thumb_height'] : 0;

    // Validierung
    if ($width < 1 || $height < 1) {
        $error = "Bitte gültige Werte größer 0 eingeben.";
    } else {
        $settings['thumb_width'] = $width;
        $settings['thumb_height'] = $height;

        if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
            $success = "Thumbnail-Größe erfolgreich gespeichert: {$width} x {$height} px";
        } else {
            $error = "Fehler beim Speichern der Einstellungen.";
        }
    }
}

$pageTitle = 'Galerie-Verwaltung';
ob_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

?>

<h1><?= __('manage_galleries') ?></h1>

<!-- Fehler/Erfolg -->
<?php if ($error): ?><div style="color:red;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green;"><?= htmlspecialchars($success) ?></div><?php endif; ?>


<form method="post">
    <label for="thumb_width">Thumbnail Breite (px):</label>
    <input type="number" id="thumb_width" name="thumb_width" value="<?= htmlspecialchars($settings['thumb_width']) ?>" min="1" required>

    <label for="thumb_height">Thumbnail Höhe (px):</label>
    <input type="number" id="thumb_height" name="thumb_height" value="<?= htmlspecialchars($settings['thumb_height']) ?>" min="1" required>

    <button type="submit">Speichern</button>
</form>


<!-- Neues Album -->
<form method="post" style="margin-bottom:2em;">
    <label for="new_album">Neues Album erstellen:</label><br>
    <input type="text" name="new_album" id="new_album" required pattern="[a-zA-Z0-9_\-]+">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">


    <button type="submit">Anlegen</button>
</form>



<div class="album-grid">
  <?php foreach ($albums as $album):
    $images = getImagesInAlbum($album);
    $cover = count($images) > 0 ? $images[0]['thumb'] : 'placeholder.png';
    $imageCount = count($images);
  ?>
  <div class="album-tile" tabindex="0" role="button" data-album="<?= htmlspecialchars($album) ?>">
  <div class="album-cover">
    <img src="../<?= htmlspecialchars($cover) ?>" alt="Cover von Album <?= htmlspecialchars($album) ?>">
    <div class="album-info">
      <h3><?= htmlspecialchars($album) ?></h3>
      <span><?= $imageCount ?> Bild<?= $imageCount === 1 ? '' : 'er' ?></span>
    </div>
  </div>

  <!-- Album löschen Formular -->
<button type="button" class="delete-album-btn" data-album="<?= htmlspecialchars($album) ?>">
  Album löschen
</button>

</div>

  <?php endforeach; ?>
</div>

<!-- Album-Dialog -->
<dialog id="albumModal" role="dialog" aria-modal="true" aria-labelledby="albumModalTitle" tabindex="-1">
  <div class="modal">
    <button id="modalClose" aria-label="Schließen">&times;</button>
    <h2 id="albumModalTitle"></h2>
    <div id="albumModalContent">
      <!-- Album-Bilder + Upload + Metadaten via JS -->
    </div>
  </div>
</dialog>

<!-- Delete-Dialog -->
<dialog id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="confirmText" tabindex="-1">
  <div class="modal">
    <div class="modal-content">
      <p id="deleteModalText"></p>
      <div class="modal-buttons">
        <button id="confirmDelete" class="confirm">Ja, löschen</button>
        <button id="cancelDelete" type="button">Abbrechen</button>
      </div>
    </div>
  </div>
</dialog>

<script>
function getCsrfToken() {
    return document.getElementById('csrf_token_global')?.value || '';
}

// Album öffnen
document.querySelectorAll('.album-tile').forEach(tile => {
    tile.addEventListener('click', async () => {
        const album = tile.dataset.album;

        const modal = document.getElementById('albumModal');
        const modalTitle = document.getElementById('albumModalTitle');
        const modalContent = document.getElementById('albumModalContent');

        modalTitle.textContent = `Album: ${album}`;
        modalContent.innerHTML = '<p>Lade Inhalte...</p>';

        // Dialog öffnen
        if (!modal.open) modal.showModal();

        try {
            const response = await fetch(`get_album.php?album=${encodeURIComponent(album)}`);
            if (!response.ok) throw new Error('Fehler beim Laden');
            const data = await response.json();

            let html = `
                <form id="uploadForm" enctype="multipart/form-data" style="margin-bottom:1em;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
                    <input type="hidden" name="album" value="${album}">

                    <div id="dropZone" class="drop-zone">
                        Ziehe Bilder hierher oder klicke zum Auswählen
                    </div>

                    <input type="file" name="image" id="fileInput" accept="image/*" style="display:none;" multiple>
                    <button type="submit" style="margin-top:10px;">Bild hochladen</button>
                </form>
            `;

            html += '<div class="images-list" style="display:flex; flex-wrap:wrap; gap:10px;">';
            if (data.images.length === 0) {
                html += '<p>Keine Bilder im Album.</p>';
            } else {
                data.images.forEach(img => {
                    html += `
                        <div style="width:140px; text-align:center; position:relative; border:1px solid #ccc; padding:5px; border-radius:5px;">
                            <img src="../${img.thumb}" alt="${img.alt || ''}" style="width:100%; border-radius:5px; object-fit:cover;">
                            <div style="font-size: 0.8em; margin-top: 4px; color: #555;">
                                <strong>${img.filename}</strong><br>
                                ${img.dimensions.width}×${img.dimensions.height} px<br>
                                ${img.filesize_kb} KB
                            </div>
                            <input type="text" name="title[${img.filename}]" placeholder="Titel" value="${img.title || ''}" style="width:100%; margin-top:5px;">
                            <input type="text" name="alt[${img.filename}]" placeholder="Alt-Text" value="${img.alt || ''}" style="width:100%; margin-top:5px;">
                            <button type="button" class="delete-image" data-filename="${img.filename}" style="margin-top:5px; color:red;">Bild löschen</button>
                        </div>
                    `;
                });
            }
            html += '</div>';
            html += `<button id="saveMeta" type="button" style="margin-top:1em;">Metadaten speichern</button>`;

            modalContent.innerHTML = html;

            const uploadForm = document.getElementById('uploadForm');
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');

            // Upload Formular Handler
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(uploadForm);

                try {
                    const res = await fetch('gallery_manager.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message);
                    if (data.success) {
                        // Reload Album-Inhalte
                        tile.click();
                    }
                } catch (err) {
                    console.error(err);
                    alert('Fehler beim Upload.');
                }
            });

            // Drag & Drop Events
            if (dropZone && fileInput) {
                dropZone.addEventListener('click', () => fileInput.click());
                dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropZone.style.borderColor = '#000';
                    dropZone.style.color = '#000';
                });
                dropZone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    dropZone.style.borderColor = '#aaa';
                    dropZone.style.color = '#666';
                });
                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropZone.style.borderColor = '#aaa';
                    dropZone.style.color = '#666';
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        uploadForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                });
            }

            // Bild löschen Handler
            modalContent.querySelectorAll('.delete-image').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const filename = btn.dataset.filename;
                    if (!confirm(`Bild "${filename}" wirklich löschen?`)) return;

                    const formData = new FormData();
                    formData.append('delete', filename);
                    formData.append('album', album);
                    formData.append('csrf_token', getCsrfToken());

                    try {
                        const res = await fetch('gallery_manager.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) tile.click(); // Inhalte neu laden
                    } catch (err) {
                        console.error(err);
                        alert('Fehler beim Löschen.');
                    }
                });
            });

            // Metadaten speichern Handler
            document.getElementById('saveMeta').addEventListener('click', async () => {
                const formData = new FormData();
                formData.append('save_meta', '1');
                formData.append('album', album);
                formData.append('csrf_token', getCsrfToken());

                // Alle Input-Felder sammeln
                modalContent.querySelectorAll('input[name^="title"], input[name^="alt"]').forEach(input => {
                    formData.append(input.name, input.value);
                });

                try {
                    const res = await fetch('gallery_manager.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    alert(data.message);
                } catch (err) {
                    console.error(err);
                    alert('Fehler beim Speichern der Metadaten.');
                }
            });

        } catch (err) {
            console.error(err);
            alert('Fehler beim Laden des Albums');
        }

    }); // Ende tile.addEventListener
}); // Ende forEach

// Album Modal schließen
const albumModal = document.getElementById('albumModal');
document.getElementById('modalClose').addEventListener('click', () => albumModal.close());

// Overlay-Klick schließen
albumModal.addEventListener('click', e => {
    if (e.target === albumModal) albumModal.close();
});

// Escape schließen
window.addEventListener('keydown', e => {
    if (e.key === 'Escape' && albumModal.open) albumModal.close();
});

// Delete Modal
document.addEventListener('DOMContentLoaded', () => {
    const deleteButtons = document.querySelectorAll('.delete-album-btn');
    const deleteModal = document.getElementById('deleteModal');
    const deleteModalText = document.getElementById('deleteModalText');
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    const cancelDeleteBtn = document.getElementById('cancelDelete');

    let albumToDelete = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            albumToDelete = button.dataset.album;
            deleteModalText.textContent = `Album "${albumToDelete}" wirklich löschen?`;
            if (!deleteModal.open) deleteModal.showModal();
            confirmDeleteBtn.focus();
        });
    });

    cancelDeleteBtn.addEventListener('click', () => {
        albumToDelete = null;
        deleteModal.close();
    });

    deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) deleteModal.close();
    });

    confirmDeleteBtn.addEventListener('click', async () => {
        if (!albumToDelete) return;
        const formData = new FormData();
        formData.append('delete_album', albumToDelete);
        formData.append('csrf_token', getCsrfToken());

        try {
            const response = await fetch('gallery_manager.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const json = await response.json();
            alert(json.message);

            if (json.success) {
                const albumTile = document.querySelector(`.album-tile[data-album="${albumToDelete}"]`);
                if (albumTile) albumTile.remove();
            }
        } catch (e) {
            alert('Fehler beim Löschen des Albums');
            console.error(e);
        } finally {
            albumToDelete = null;
            deleteModal.close();
        }
    });
});
</script>


<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';