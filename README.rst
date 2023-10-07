================
TYPO3 Exceptions
================

These official pages contain distilled community knowledge regarding exceptions
of the enterprise content management system TYPO3.

:Repository:  https://github.com/TYPO3-Documentation/TYPO3CMS-Exceptions
:Read online: https://docs.typo3.org/typo3cms/exceptions/main/en-us/

Creating exception code page skeletons for new exceptions
---------------------------------------------------------

This repository contains a `symfony/console` based php application providing a
basic toolchain to update TYPO3 core exception code lists and creating missing
exception code documentation files based on a template file.

That tool is a simplified extraction out of a dedicated repository and application,
which provided a web application simulating exception pages and creating pages
on browser request using api calls. That have been rather complex and the usage
of the web component was not used by any active contributors.

The exception code gathering checks every tag of the TYPO3 monorepository out,
using a temporary path. Then the core development script to check for duplicate
exception code is used with the argument to return found exception codes as json
and written to a `Build/Exceptions/exceptions-<tag>.json` file. Additionally,
all exception codes across all tags are merged into the global php array in
`Build/Exceptions/exceptions.php`.

The second step is, to check all merged exception codes if a corresponding
documentation page exists in `Documentation/Exceptions/<exception-code>.rst`,
and if not create one based on the `Build/app/Resources/Templates/default.rst`
template file.

The two main tasks are:

* Checkout TYPO3 core releases and collect exception codes per tag version,
  which are saved in json files and a merged php array file.
* Verify which exception code pages are missing and create missing pages based
  on a ReST template file.

The commands can be used through the `Build/Scripts/runTests.sh` script or
directly with `php Build/app/console.php`.

..  note::

    The command application can be used to manually update the exception code
    index files or create the documentation pages. However, the main usage is
    a scheduled GitHub Action to disentangle the need for human interaction.
    The `Build/Scripts/runTests.sh` provides additional commands and dispatches
    them to the command and even different PHP version can be used. A recent
    docker installation is required for the runTests.sh wrapper.

..  code-block:: shell

    # Install application dependencies:
    $ Build/Scripts/runTests.sh -s composerInstall
    # or
    $ composer install --working-dir=./Build/app

    # Create exception code collections for new release tags only
    $ Build/Scripts/runTests.sh -s updateExceptionCodesMissingTags
    # or
    $ php Build/app/console.php exception-codes:fetch

    # Create exception code collections for new release tags only and
    # and create a git commit:
    $ Build/Scripts/runTests.sh -s updateExceptionCodesMissingTags -c
    # or
    $ php Build/app/console.php exception-codes:fetch --auto-commit

    # Create missing exception code pages
    $ Build/Scripts/runTests.sh -s createMissingExceptionCodeFiles
    # or
    $ php Build/app/console.php exception-codes:generate-pages

    # Create missing exception code pages and create a commit
    $ Build/Scripts/runTests.sh -s createMissingExceptionCodeFiles -c
    # or
    $ php Build/app/console.php exception-codes:generate-pages --auto-commit

    # List all available commands aka testsuits (-s) and options
    $ Build/Scripts/runTests.sh -h

See `Build/app/DEVELOPMENT.rst` for further details about the tool itself
and how it is build for contributing or maintaining the application.
