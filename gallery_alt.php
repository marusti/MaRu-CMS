<?php
// session starten falls nötig
session_start();

require_once __DIR__ . '/core/gallery_functions.php';

$albums = loadGalleries();
$currentAlbum = $_GET['album'] ?? null;

if ($currentAlbum && !isset($albums[$currentAlbum])) {
    $currentAlbum = null; // ungültiges Album, ignoriere
}

$pageTitle = 'Galerie';

ob_start();
?>

<h1>Alben</h1>
<ul>
<?php foreach ($albums as $key => $album): ?>
    <li><a href="?album=<?= urlencode($key) ?>"><?= htmlspecialchars($album['title']) ?></a></li>
<?php endforeach; ?>
</ul>

<?php if ($currentAlbum): ?>
    <h2>Bilder in Album: <?= htmlspecialchars($albums[$currentAlbum]['title']) ?></h2>
    <div style="display:flex; flex-wrap: wrap; gap: 10px;">
    <?php
        $images = getGalleryImages($currentAlbum);
        foreach ($images as $img):
    ?>
        <div style="width:150px;">
            <a href="<?= htmlspecialchars($img['path']) ?>" target="_blank">
                <img src="<?= htmlspecialchars($img['thumb']) ?>" alt="" style="width:100%; border:1px solid #ccc;">
            </a>
        </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>Bitte wähle ein Album aus der Liste oben aus.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';

