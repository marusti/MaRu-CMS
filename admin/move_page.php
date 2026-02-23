<?php

require_once __DIR__ . '/init.php';

if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pagesDir = __DIR__ . '/../content/pages';

$id       = $_GET['id']       ?? '';
$category = $_GET['category'] ?? '';
$dir      = $_GET['dir']      ?? '';

if ($id === '' || !in_array($dir, ['up', 'down'], true)) {
    header('Location: content_manager.php?error=page');
    exit;
}

$categoryDir = $category !== ''
    ? $pagesDir . '/' . $category
    : $pagesDir;

if (!is_dir($categoryDir)) {
    header('Location: content_manager.php?error=page');
    exit;
}

$files = glob($categoryDir . '/*.json');

$pages = [];

foreach ($files as $file) {

    $meta = json_decode(file_get_contents($file), true);

    $pages[] = [

        'id'    => basename($file, '.json'),

        'order' => isset($meta['order']) ? (int)$meta['order'] : 9999

    ];
}

usort($pages, fn($a, $b) => $a['order'] <=> $b['order']);

$ids = array_column($pages, 'id');

$index = array_search($id, $ids, true);

if ($index === false) {
    header('Location: content_manager.php?error=page');
    exit;
}

if ($dir === 'up' && $index > 0) {

    [$pages[$index - 1], $pages[$index]] =
    [$pages[$index], $pages[$index - 1]];

}

elseif ($dir === 'down' && $index < count($pages) - 1) {

    [$pages[$index + 1], $pages[$index]] =
    [$pages[$index], $pages[$index + 1]];
}

foreach ($pages as $order => $page) {

    $filePath = $categoryDir . '/' . $page['id'] . '.json';

    $meta = json_decode(file_get_contents($filePath), true);

    $meta['order'] = $order;

    file_put_contents(
        $filePath,
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

header('Location: content_manager.php');

exit;
