<?php
require_once __DIR__ . '/init.php';

$uploadDir = __DIR__ . '/../uploads/media/images/';
$uploadUrl = rtrim($settings['base_url'], '/') . '/uploads/media/images/';

// Allowed filetypes laden
$allowedFiletypesFile = __DIR__ . '/../config/allowed_filetypes.json';
$allowedFiletypes = file_exists($allowedFiletypesFile) 
    ? json_decode(file_get_contents($allowedFiletypesFile), true)
    : [];
$imageExtensions = $allowedFiletypes['images']['extensions'] ?? [];

// Glob Pattern bauen
$pattern = $uploadDir . '*.{'.implode(',', $imageExtensions).'}';
$images = glob($pattern, GLOB_BRACE);
?>

<div class="image-grid">
<?php foreach ($images as $image):
    $filename = basename($image);
    $imageUrl = $uploadUrl . $filename;
?>
<img src="<?= htmlspecialchars($imageUrl) ?>" 
     data-file="<?= htmlspecialchars($filename) ?>" 
     alt="<?= __('image_preview') ?>">
<?php endforeach; ?>
</div>