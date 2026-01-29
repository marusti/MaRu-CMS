<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

function isValidTemplateName(string $name): bool {
    return preg_match('/^[a-zA-Z0-9_-]+$/', $name);
}

$templateBaseDir = realpath(__DIR__ . '/../templates');
$error = '';
$message = '';
$template = $_GET['template'] ?? '';
$file = $_GET['file'] ?? '';

if (!$template || !isValidTemplateName($template)) {
    $error = "Ungültiger Template-Name.";
} else {
    $templatePath = realpath($templateBaseDir . '/' . $template);

    if (!$templatePath || strpos($templatePath, $templateBaseDir) !== 0 || !is_dir($templatePath) || !is_readable($templatePath)) {
        $error = "Das Template-Verzeichnis <code>/templates/{$template}</code> konnte nicht gelesen werden. "
               . "Stelle sicher, dass der Ordner existiert und für den Webserver lesbar ist.";
    } else {
        // Dateien rekursiv sammeln (php, css, js)
        $allowedExtensions = ['php', 'css', 'js'];
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($templatePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && in_array(strtolower($fileinfo->getExtension()), $allowedExtensions)) {
                // Pfad relativ zum Template-Ordner
                $relativePath = substr($fileinfo->getPathname(), strlen($templatePath) + 1);
                $files[] = $relativePath;
            }
        }

        if (empty($files)) {
            $error = "Keine editierbaren Dateien im Template-Ordner gefunden.";
        } else {
            if (!$file || !in_array($file, $files)) {
                $file = in_array('index.php', $files) ? 'index.php' : $files[0];
            }

            $filePath = $templatePath . '/' . $file;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
                if (file_put_contents($filePath, $_POST['content']) !== false) {
                    $message = "Datei erfolgreich gespeichert.";
                } else {
                    $error = "Fehler beim Speichern der Datei.";
                }
            }

            if (file_exists($filePath)) {
                $contentFile = file_get_contents($filePath);
            } else {
                $error = "Die Datei konnte nicht gefunden werden.";
                $contentFile = '';
            }
        }
    }
}

ob_start();
?>

<h2>Template bearbeiten: <?= htmlspecialchars($template) ?></h2>

<?php if ($error): ?>
    <div class="error" style="border:1px solid #f00; background:#fee; padding:1em; border-radius:5px; margin-bottom:1em;">
        <?= $error ?>
    </div>
<?php else: ?>

    <form method="get" style="margin-bottom:1em;">
        <input type="hidden" name="template" value="<?= htmlspecialchars($template) ?>" />
        <label for="file-select" style="font-weight:bold; display:block; margin-bottom:0.5em;">Datei auswählen:</label>
        <select name="file" id="file-select" onchange="this.form.submit()" style="font-family: monospace; font-size: 1em;">
            <?php foreach ($files as $f): ?>
                <option value="<?= htmlspecialchars($f) ?>" <?= ($f === $file) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (!empty($file)): ?>
        <?php if ($message): ?>
            <p style="color: green; font-weight: bold; margin-bottom: 1em;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form method="post" id="edit-form" autocomplete="off">
            <textarea name="content" id="content" rows="25" style="width:100%; font-family: monospace; font-size: 14px; line-height: 1.4; padding: 1em; border: 1px solid #ccc; border-radius: 4px; resize: vertical;"><?= htmlspecialchars($contentFile) ?></textarea><br><br>
            <button type="submit" style="padding:0.5em 1.5em; font-size:1em; cursor:pointer; background:#007bff; color:#fff; border:none; border-radius:4px;">Speichern</button>
            <a href="template_manager.php" id="cancel-btn" style="margin-left:1em; padding:0.5em 1.5em; font-size:1em; background:#6c757d; color:#fff; text-decoration:none; border-radius:4px; cursor:pointer;">Abbrechen</a>
        </form>

        <script>
            (() => {
                const form = document.getElementById('edit-form');
                const textarea = document.getElementById('content');
                const cancelBtn = document.getElementById('cancel-btn');
                let originalContent = textarea.value;

                window.addEventListener('beforeunload', (e) => {
                    if (textarea.value !== originalContent) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });

                cancelBtn.addEventListener('click', (e) => {
                    if (textarea.value !== originalContent) {
                        if (!confirm('Änderungen werden nicht gespeichert. Wirklich abbrechen?')) {
                            e.preventDefault();
                        }
                    }
                });
            })();
        </script>
    <?php else: ?>
        <p>Keine Dateien zum Bearbeiten vorhanden.</p>
    <?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
include '_layout.php';
