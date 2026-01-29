<?php

// core/gallery_functions.php

$galleryDir = __DIR__ . '/../uploads/gallery/';

// Initialverzeichnisse (nur das Root-Gallery-Verzeichnis)
if (!is_dir($galleryDir)) mkdir($galleryDir, 0755, true);

function loadMetadata($album) {
  $metaFile = __DIR__ . '/../uploads/gallery/' . $album . '/metadata.json';

  if (!file_exists($metaFile)) return [];

  $json = file_get_contents($metaFile);
  $metadata = json_decode($json, true) ?: [];

  return $metadata;
}

function saveMetadata($album, $metadata) {
    $albumDir = __DIR__ . '/../uploads/gallery/' . $album;
    if (!is_dir($albumDir)) {
        mkdir($albumDir, 0755, true);    }

    $metaFile = $albumDir . '/metadata.json';


    return file_put_contents($metaFile, json_encode($metadata, JSON_PRETTY_PRINT)) !== false;
}

// Thumbnail erstellen (GD)
function createThumbnail($srcPath, $destPath, $maxWidth, $maxHeight) {
    $info = getimagesize($srcPath);
    if (!$info) return false;

    list($width, $height) = $info;
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($srcPath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($srcPath);
            break;
        default:
            return false;
    }

    // Prüfen ob Bild geladen wurde
    if (!$image) {
        return false;
    }

    $scale = min($maxWidth / $width, $maxHeight / $height);
    if ($scale >= 1) {
        return copy($srcPath, $destPath);
    }

    $newWidth = (int)($width * $scale);
    $newHeight = (int)($height * $scale);

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // Transparenz erhalten für PNG/GIF
    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    if (!imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
        imagedestroy($image);
        imagedestroy($thumb);
        return false;
    }

    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($thumb, $destPath, 85);
            break;
        case 'image/png':
            $result = imagepng($thumb, $destPath);
            break;
        case 'image/gif':
            $result = imagegif($thumb, $destPath);
            break;
        default:
            $result = false;
    }

    imagedestroy($image);
    imagedestroy($thumb);

    return $result;
}


// Bilder aus dem Gallery-Root-Ordner (ohne Albumzuordnung) laden
function getGalleryImages() {
    global $galleryDir;
    $thumbDir = $galleryDir . 'thumbnails/';
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

    $files = array_diff(scandir($galleryDir), ['.', '..', 'thumbnails', 'gallery.json']);
    $images = [];
    foreach ($files as $file) {
        $path = $galleryDir . $file;
        if (is_file($path) && preg_match('/\.(jpe?g|png|gif)$/i', $file)) {
            $images[] = [
                'filename' => $file,
                'path' => 'uploads/gallery/' . $file,
                'thumb' => 'uploads/gallery/thumbnails/' . $file
            ];
        }
    }
    return $images;
}

// Galerieübersicht (falls verwendet)
function loadGalleries() {
    $file = __DIR__ . '/../uploads/gallery/gallery.json';
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveGalleries($galleries) {
    $file = __DIR__ . '/../uploads/gallery/gallery.json';
    $json = json_encode($galleries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $fp = fopen($file, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

// Albumverzeichnisliste
function getAlbums() {
    global $galleryDir;
    $dirs = array_filter(glob($galleryDir . '*'), 'is_dir');
    $albums = [];

    foreach ($dirs as $dir) {
        $name = basename($dir);
        if ($name === 'thumbnails') continue;
        $albums[] = $name;
    }
    return $albums;
}

// Bilder innerhalb eines Albums
function getImagesInAlbum($album) {
    global $galleryDir;
    $albumPath = $galleryDir . $album . '/';
    $thumbPath = $albumPath . 'thumbnails/';

    if (!is_dir($albumPath)) return [];

    $files = array_diff(scandir($albumPath), ['.', '..', 'thumbnails']);
    $images = [];

    foreach ($files as $file) {
        if (preg_match('/\.(jpe?g|png|gif)$/i', $file)) {
            $images[] = [
                'filename' => $file,
                'path' => "uploads/gallery/$album/$file",
                'thumb' => "uploads/gallery/$album/thumbnails/$file"
            ];
        }
    }

    return $images;
}

// Neues Album erstellen
function createAlbum($name) {
    global $galleryDir;
    $albumPath = $galleryDir . $name . '/';
    $thumbPath = $albumPath . 'thumbnails/';
    if (!is_dir($albumPath)) mkdir($albumPath, 0755, true);
    if (!is_dir($thumbPath)) mkdir($thumbPath, 0755, true);
    return is_dir($albumPath) && is_dir($thumbPath);
}

// (Optional) Migration alter metadata.json
function migrateMetadata() {
    $oldMetaFile = __DIR__ . '/../uploads/gallery/metadata.json';
    if (!file_exists($oldMetaFile)) return;

    $allMeta = json_decode(file_get_contents($oldMetaFile), true);
    if (!is_array($allMeta)) return;

    foreach ($allMeta as $album => $meta) {
        $albumMetaFile = __DIR__ . "/../uploads/gallery/$album/metadata.json";
        if (!is_dir(__DIR__ . "/../uploads/gallery/$album")) continue;
        file_put_contents($albumMetaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // unlink($oldMetaFile); // Optional: alte Datei löschen
}

// Ganz oben oder in gallery_functions.php
function deleteFolder($folder, $depth = 0, $maxDepth = 20) {
    if ($depth > $maxDepth) {
        throw new Exception('Maximale Rekursionstiefe erreicht. Mögliche Endlosschleife!');
    }

    foreach (glob($folder . '*') as $file) {
        if (is_dir($file)) {
            deleteFolder($file . DIRECTORY_SEPARATOR, $depth + 1, $maxDepth);
        } else {
            unlink($file);
        }
    }
    rmdir($folder);
}

