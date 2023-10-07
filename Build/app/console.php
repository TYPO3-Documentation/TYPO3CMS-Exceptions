#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace T3DOCS\ExceptionCodes;

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    echo 'Composer autoload information missing. Please run "composer install"' . PHP_EOL;
    exit(1);
}
require __DIR__.'/vendor/autoload.php';

define('COMMAND_ROOT', dirname(__FILE__));

use Symfony\Component\Console\Application;
use T3DOCS\ExceptionCodes\Command\FetchCommand;
use T3DOCS\ExceptionCodes\Command\GeneratePagesCommand;

$application = new Application('T3DOCS Exception Pages Console', '1.0.0');
$application->addCommands(array_values([
    new FetchCommand(),
    new GeneratePagesCommand(),
]));
$application->run();
