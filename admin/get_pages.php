<?php
// Fehler-Reporting ausschalten, damit keine Warnungen ausgegeben werden
error_reporting(0);
ini_set('display_errors', 0);

// JSON Content-Type Header
header('Content-Type: application/json');

$pagesDir = __DIR__ . '/../content/pages';
$pages = [];

foreach (glob($pagesDir . '/*/*.php') as $file) {
    $id = basename($file, '.php');
    $category = basename(dirname($file));
    $pageTitle = '';

    // Ausgabe puffern, um HTML zu vermeiden
    ob_start();
    include $file;
    ob_end_clean();

    $pages[] = [
        'id' => $id,
        'category' => $category,
        'title' => $pageTitle ?: $id,
        'url' => "index.php?page=$category/$id"
    ];
}

// JSON ausgeben, keine weiteren Ausgaben erlauben
echo json_encode($pages);
exit;
