<?php

declare(strict_types=1);

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