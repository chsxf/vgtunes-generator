<?php

use Symfony\Component\Console\Application;

require_once('vendor/autoload.php');

$environments = require_once('environments.php');

$app = new Application('VGTunes Site Generator', '0.0.1');
$app->add(new GenerateCommand($environments));
$app->run();
