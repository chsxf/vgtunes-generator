<?php

use Symfony\Component\Console\Application;

require_once('vendor/autoload.php');

$app = new Application('VGTunes Site Generator', '0.0.1');
$app->add(new GenerateCommand());
$app->run();
