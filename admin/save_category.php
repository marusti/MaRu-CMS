<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$file = __DIR__ . '/../content/categories.json';
$categories = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

$id = $_POST['id'];
$name = $_POST['name'];

$found = false;
foreach ($categories as &$cat) {
    if ($cat['id'] === $id) {
        $cat['name'] = $name;
        $found = true;
        break;
    }
}

if (!$found) {
    // Wenn die Kategorie nicht gefunden wurde, dann fügen wir sie hinzu
    $categories[] = ['id' => $id, 'name' => $name];
}

$saveSuccess = file_put_contents($file, json_encode($categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($saveSuccess) {
    // Erfolgsmeldung, wenn die Kategorie erfolgreich gespeichert wurde
    header('Location: content_manager.php?success=category');
} else {
    // Fehlermeldung, wenn das Speichern fehlschlägt
    header('Location: content_manager.php?error=category');
}
exit;
