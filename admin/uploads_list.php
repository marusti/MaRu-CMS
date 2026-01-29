<?php
require_once __DIR__ . '/init.php';

$uploadDir = __DIR__ . '/../uploads/media/images/';
$uploadUrl = '../uploads/media/images/';

// Alle Bilder im Verzeichnis sammeln
$images = glob($uploadDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
?>

<div class="image-grid">
<?php foreach ($images as $image): 
    $imageUrl = $uploadUrl . basename($image);
?>
    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= __('image_preview') ?>" onclick="selectImage('<?= htmlspecialchars($imageUrl) ?>')">
<?php endforeach; ?>
</div>

<script>
// Funktion, um das ausgewählte Bild in das Input-Feld zu setzen
function selectImage(url) {
    if (window.opener && typeof window.opener.setSelectedImage === 'function') {
        window.opener.setSelectedImage(url);
    }
    // Dialog schließen
    const dialog = document.getElementById('imageDialog');
    dialog.close();
}
</script>
