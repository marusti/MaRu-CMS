<?php
header('Content-Type: application/json');

$galleriesDir = __DIR__ . '/../uploads/gallery';

$galleries = [];

if (is_dir($galleriesDir)) {
    $dirs = scandir($galleriesDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $galleryPath = $galleriesDir . '/' . $dir;
        if (is_dir($galleryPath)) {
            // Name = Ordnername (kannst du hier anpassen)
            $galleries[] = [
                'id' => $dir,
                'name' => $dir
            ];
        }
    }
}

echo json_encode($galleries);