====================================
TYPO3 Exceptions Command Application
====================================

Introduction
------------

This console application is based on `symfony/console` and provides some
commands needed for the work in this repository. Therefore, this application
is considered as a `build related` application and placed in the Build folder.

Dependencies for this application are managed by using the well-known `composer`
php dependency manager.

Development
-----------

The main entry point is the `Build/app/console.php` script. This bootstraps the
composer autoloader, creates a symfony console application instance and register
the commands.

In this file, the available commands are registered:

..  code-block:: php

    <?php

    // ... bootstrap code, e.g. composer autoloading

    $application = new Application('T3DOCS Exception Pages Console', '1.0.0');
    $application->addCommands(array_values([
        new FetchCommand(),
        new GeneratePagesCommand(),
        // instantiate new command classes directly here
    ]));
    $application->run();

..  note::

    As a simple command application no symfony dependency injection container
    or similar are available. Still, a service injection design should be kept
    to make a later transition to it easier without greater code refactorings.

..  note::

    Add more detailed development information.

Code quality
------------

Currently, no code quality tools are setup. For example, no php-cs-fixer or
phpstan is configured for the application.

..  note::

    Code quality tool chain along with automatic testing will be added. Due to
    fact that the recreation is broken the application has been tested manually
    to get it running for now. Mainly due to capacity reasons and maintainer.

Testing
-------

..  note::

    Add information how to test this application manually or running automatic
    tests - after they have been implemented.
