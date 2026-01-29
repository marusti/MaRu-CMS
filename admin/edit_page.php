<?php
require_once __DIR__ . '/init.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// Einstellungen & Plugins
$settingsFile = __DIR__ . '/../config/settings.json';
$settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
$activePlugins = $settings['plugins'] ?? [];

// Kategorien laden
$categoriesFile = __DIR__ . '/../content/categories.json';
$categories = file_exists($categoriesFile) ? json_decode(file_get_contents($categoriesFile), true) : [];

// Seitenbasis
$pagesBaseDir = __DIR__ . '/../content/pages';
$id = isset($_GET['id']) ? basename(preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['id']))) : '';
$data = [
    'id'=>'',
    'title'=>'',
    'category'=>$categories[0]['id'] ?? '',
    'content'=>'',
    'meta_description'=>'',
    'meta_keywords'=>'',
    'default_image'=>'',
    'default_image_alt'=>'',
    'robots'=>'index, follow',
    'status'=>'draft'
];

// Seite laden, falls ID gesetzt
if ($id) {
    foreach (glob($pagesBaseDir . '/*/' . $id . '.md') as $mdFile) {
        $categoryDir = basename(dirname($mdFile));
        $jsonFile = $pagesBaseDir . '/' . $categoryDir . '/' . $id . '.json';
        if (file_exists($jsonFile)) {
            $meta = json_decode(file_get_contents($jsonFile), true);
            $data = [
                'id' => $meta['id'] ?? $id,
                'title' => $meta['title'] ?? '',
                'category' => $meta['category'] ?? $categoryDir,
                'meta_description' => $meta['meta_description'] ?? '',
                'meta_keywords' => $meta['meta_keywords'] ?? '',
                'default_image' => $meta['default_image'] ?? '',
                'default_image_alt' => $meta['default_image_alt'] ?? '',
                'robots' => $meta['robots'] ?? 'index, follow',
                'status' => $meta['status'] ?? 'draft',
                'content' => file_exists($mdFile) ? file_get_contents($mdFile) : ''
            ];
            break;
        }
    }
}

// Neue Seite: ID automatisch aus Titel generieren
if (!$id && empty($data['id'])) {
    $data['id'] = '';
}

$pageTitle = $id ? __('edit_page') : __('create_page');
ob_start();
?>

<h2><?= $pageTitle ?></h2>

<form method="post" action="save_page.php" enctype="multipart/form-data">
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="content"><?= __('content') ?></button>
        <button type="button" class="tab-btn" data-tab="seo"><?= __('seo') ?></button>
        <button type="button" class="tab-btn" data-tab="image"><?= __('default_image') ?></button>
    </div>

    <!-- TAB: Content -->
    <div id="tab-content" class="tab-content active">
        <label><?= __('title') ?>:
            <input type="text" id="page_title" name="title" value="<?= htmlspecialchars($data['title']) ?>" required>
        </label>

        <label><?= __('id') ?>:
            <input type="text" id="page_id" name="id" value="<?= htmlspecialchars($data['id']) ?>" required>
        </label>

        <label><?= __('category') ?>:
            <select name="category" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $data['category']===$cat['id']?'selected':'' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label><?= __('status') ?>:
            <select name="status" required>
                <option value="draft" <?= $data['status']==='draft'?'selected':'' ?>><?= __('Entwurf') ?></option>
                <option value="published" <?= $data['status']==='published'?'selected':'' ?>><?= __('Veröffentlicht') ?></option>
            </select>
        </label>

        <!-- Toolbar -->
        <!-- Toolbar -->
<div id="toolbar" class="md-toolbar">
    <button type="button" data-cmd="bold">B</button>
    <button type="button" data-cmd="italic">I</button>
    <button type="button" data-cmd="underline">U</button>
    <button type="button" data-cmd="h1">H1</button>
    <button type="button" data-cmd="h2">H2</button>
    <button type="button" data-cmd="h3">H3</button>
    <button type="button" data-cmd="h4">H4</button>
    <button type="button" data-cmd="h5">H5</button>
    <button type="button" data-cmd="h6">H6</button>

    <button type="button" data-cmd="ul">UL</button>
    <button type="button" data-cmd="ol">OL</button>
    <button type="button" data-cmd="quote">❝</button>
    <button type="button" data-cmd="code">{"</>"}</button>
    <button type="button" data-cmd="link">🔗</button>
    <button type="button" data-cmd="image">🖼</button>
    <button type="button" data-cmd="undo">↶</button>
    <button type="button" data-cmd="redo">↷</button>

    <!-- Neue Buttons -->
    <button type="button" data-cmd="div">DIV</button>
    <button type="button" data-cmd="p">P</button>
</div>


        <div id="editor-wrapper">
            <textarea id="editor" class="md-editor"><?= htmlspecialchars($data['content']) ?></textarea>
            <input type="hidden" id="content" name="content">

            <div id="live-preview-panel">
                <h3>Live Preview</h3>
                <iframe id="livePreviewFrame"></iframe>
            </div>
        </div>
    </div>

    <!-- TAB: SEO -->
    <div id="tab-seo" class="tab-content">
        <label><?= __('meta_description') ?>:
            <input type="text" name="meta_description" value="<?= htmlspecialchars($data['meta_description']) ?>">
        </label>

        <label><?= __('meta_keywords') ?>:
            <input type="text" name="meta_keywords" value="<?= htmlspecialchars($data['meta_keywords']) ?>">
        </label>

        <label><?= __('robots_directive') ?>:
            <select name="robots">
                <option value="index, follow" <?= $data['robots']==='index, follow'?'selected':'' ?>>index, follow</option>
                <option value="noindex, follow" <?= $data['robots']==='noindex, follow'?'selected':'' ?>>noindex, follow</option>
                <option value="index, nofollow" <?= $data['robots']==='index, nofollow'?'selected':'' ?>>index, nofollow</option>
                <option value="noindex, nofollow" <?= $data['robots']==='noindex, nofollow'?'selected':'' ?>>noindex, nofollow</option>
            </select>
        </label>
    </div>

    <!-- TAB: Default Image -->
    <div id="tab-image" class="tab-content">
        <label><?= __('default_image_url') ?>:
            <input type="text" id="default_image" name="default_image" value="<?= htmlspecialchars($data['default_image']) ?>">
            <button type="button" id="selectImageBtn">📁</button>

        </label>

        <label><?= __('default_image_alt_text') ?>:
            <input type="text" id="default_image_alt" name="default_image_alt" value="<?= htmlspecialchars($data['default_image_alt']) ?>">
        </label>

        <div style="margin-top:10px;">
            <img id="imagePreview" src="<?= htmlspecialchars($data['default_image']) ?>" style="max-width:100%; max-height:200px; <?= empty($data['default_image'])?'display:none;':'' ?>">
        </div>
    </div>

    <div class="button-row">
        <button type="submit" name="action" value="save"><?= __('save') ?></button>
        <button type="submit" name="action" value="save_close"><?= __('save_close') ?></button>
        <a href="content_manager.php"><?= __('cancel') ?></a>
    </div>
</form>

<!-- Dialog-Element für die Bildauswahl -->
<dialog id="imageDialog">
    <h1><?= __('select_image') ?></h1>
    <div id="imageDialogContent">
        <!-- Der Inhalt von uploads_list.php wird hier geladen -->
    </div>
    <button id="closeDialogBtn">Schließen</button>
</dialog>


<script>
document.addEventListener('DOMContentLoaded', ()=>{
    const ED = new CMS_Editor('editor');
    ED.enableLivePreview('livePreviewFrame');
    window.ED = ED;

    // Toolbar Buttons
document.querySelectorAll('#toolbar [data-cmd]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        const c = btn.dataset.cmd;
        switch(c){
            case 'bold': ED.wrap('[bold|', ']'); break;
            case 'italic': ED.wrap('[italic|', ']'); break;
            case 'underline': ED.wrap('[underline|', ']'); break;

            case 'h1': ED.wrap('[h1|', ']'); break;
            case 'h2': ED.wrap('[h2|', ']'); break;
            case 'h3': ED.wrap('[h3|', ']'); break;
            case 'h4': ED.wrap('[h4|', ']'); break;
            case 'h5': ED.wrap('[h5|', ']'); break;
            case 'h6': ED.wrap('[h6|', ']'); break;

            case 'ul': ED.wrap('[listunordered|\n- ', '\n]'); break;
            case 'ol': ED.wrap('[listordered|\n1. ', '\n]'); break;

            case 'quote': ED.wrap('[quote|', ']'); break;
            case 'code': ED.wrap('[codeblock|\n', '\n]'); break;

            case 'link': {
                // Beispiel-URL, kann statisch oder dynamisch gesetzt werden
                const url = 'https://example.com'; // Hier wird der Link eingefügt
                const linkTag = `[link|${url}|]`; // Der Link-Tag für den Markdown-Editor
                ED.insert(linkTag);  // Der Tag wird direkt in den Editor eingefügt
                break;
            }

            case 'image': {
                const url = prompt('Image URL:');
                const alt = prompt('Alt Text:');
                if(url) ED.wrap(`[image|${url}|`, (alt||'') + ']');
                break;
            }

            case 'undo': ED.undo(); break;
            case 'redo': ED.redo(); break;

            // Neue Befehle
            case 'div': ED.wrap('[div|', ']'); break;
            case 'p': ED.wrap('[p|', ']'); break;
        }
    });
});


    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-'+btn.dataset.tab).classList.add('active');
        });
    });

    // Image preview
    const imageInput = document.getElementById('default_image');
    const imagePreview = document.getElementById('imagePreview');
    function updateImagePreview(){
        const url = imageInput.value.trim();
        imagePreview.src = url;
        imagePreview.style.display = url?'block':'none';
    }
    imageInput.addEventListener('input', updateImagePreview);


    // Auto-ID aus Titel
    const titleInput = document.getElementById('page_title');
    const idInput = document.getElementById('page_id');
    idInput.dataset.locked = 'false';
    titleInput.addEventListener('input', ()=>{
        if(idInput.dataset.locked==='true') return;
        idInput.value = titleInput.value.toLowerCase()
            .replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss')
            .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    });
    idInput.addEventListener('input', ()=>idInput.dataset.locked='true');

    // Form submit: Markdown
    document.querySelector('form').addEventListener('submit', ()=>{
        document.getElementById('content').value = ED.getMarkdown();
    });
});

const baseUrl = '<?= htmlspecialchars($settings['base_url']) ?>';

document.addEventListener('DOMContentLoaded', () => {
    // Öffnen des Dialogs, wenn der Button geklickt wird
    document.getElementById('selectImageBtn').addEventListener('click', () => {
        const dialog = document.getElementById('imageDialog');
        dialog.showModal();  // Öffnet den Dialog
        loadImageList();  // Lädt den Inhalt der uploads_list.php in den Dialog
    });

    // Schließen des Dialogs, wenn der Schließen-Button geklickt wird
    document.getElementById('closeDialogBtn').addEventListener('click', () => {
        const dialog = document.getElementById('imageDialog');
        dialog.close();  // Schließt den Dialog
    });

    // Funktion zum Laden des Inhalts von uploads_list.php in den Dialog
    function loadImageList() {
        const dialogContent = document.getElementById('imageDialogContent');

        // Mit Fetch den Inhalt von uploads_list.php laden
        fetch(baseUrl + '/admin/uploads_list.php')  // Achte darauf, dass der Name korrekt ist!
            .then(response => {
                if (!response.ok) {
                    console.error('Fehler beim Laden der uploads_list.php:', response.statusText);
                    return;
                }
                return response.text();
            })
            .then(data => {
                dialogContent.innerHTML = data;  // Setzt den Inhalt in den Dialog

                // Hier sorgen wir dafür, dass nach dem Laden der Bilder, die Funktion selectImage verfügbar bleibt
                document.querySelectorAll('.image-grid img').forEach(img => {
                    img.addEventListener('click', function() {
                        selectImage(this.src);  // Aufruf der selectImage Funktion
                    });
                });
            })
            .catch(error => {
                console.error('Fehler beim Laden von uploads_list.php:', error);
            });
    }
});






</script>

<?php
$content = ob_get_clean();
include '_layout.php';
