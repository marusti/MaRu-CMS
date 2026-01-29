<?php

function parseCMS(string $md): string {

    // Gallery Plugin
    $md = preg_replace_callback('/\[gallery:(.*?)\]/', function($m){
        $images = explode(',', $m[1]);
        $html = '<div class="gallery">';
        foreach($images as $img){
            $img = trim($img);
            $html .= "<img src='/uploads/$img'>";
        }
        $html .= '</div>';
        return $html;
    }, $md);

    // YouTube Plugin
    $md = preg_replace('/\[youtube:(.*?)\]/',
        '<iframe src="https://www.youtube.com/embed/$1"></iframe>',
        $md
    );

    return $md;
}

