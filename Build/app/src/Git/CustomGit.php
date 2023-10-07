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

namespace T3DOCS\ExceptionCodes\Git;

use CzProject\GitPhp\Git;

final class CustomGit extends Git
{
    /**
     * @param  string $directory
     * @return CustomGitRepository
     */
    public function open($directory): CustomGitRepository
    {
        return new CustomGitRepository(
            repository: $directory,
            runner: $this->runner,
            defaultUserName: 'TYPO3 Documentation Team',
            defaultUserEmail: 'documentation@typo3.org',
        );
    }
}