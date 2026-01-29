<?php
$uploadDir = __DIR__ . '/../uploads/';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $filename = basename($_POST['filename']);
    $filePath = $uploadDir . $filename;

    if (file_exists($filePath)) {
        unlink($filePath);
    }
}
header('Location: filemanager.php');
exit;

