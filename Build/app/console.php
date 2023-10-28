#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

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

// @todo Consider to integrate a simplyfied `symfony/di` container into the application.

$application = new Application('T3DOCS Exception Pages Console', '1.0.0');
$application->addCommands(array_values([
    new FetchCommand(),
    new GeneratePagesCommand(),
    // instantiate new command classes directly here
]));
$application->run();
