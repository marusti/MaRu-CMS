<?php
require __DIR__.'/../vendor/Parsedown.php';

$md = file_get_contents('php://input');

$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);

echo $Parsedown->text($md);

