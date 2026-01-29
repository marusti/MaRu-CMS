<?php
function load_page($path) {
    $file = __DIR__ . '/../content/' . $path . '.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
}
function save_page($path, $data) {
    $file = __DIR__ . '/../content/' . $path . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
