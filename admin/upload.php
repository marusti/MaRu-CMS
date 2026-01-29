<?php
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
    $name = basename($_FILES['files']['name'][$index]);
    if (is_uploaded_file($tmpName)) {
        move_uploaded_file($tmpName, $uploadDir . $name);
    }
}

header('Location: filemanager.php');
exit;
