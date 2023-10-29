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

namespace T3DOCS\ExceptionCodes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use T3DOCS\ExceptionCodes\Service\FetcherService;
use T3DOCS\ExceptionCodes\Service\FetchMode;

/**
 * Provides the exception code collection command. This command is used to collect the
 * exception code for TYPO3 releases and manage a merged overall state.
 *
 * Additionally, a git commit can be created if something has changed.
 *
 * Based on that state, documentation exception code pages can be created for new
 * exception codes.
 *
 * @see GeneratePagesCommand for documentation creation.
 */
final class FetchCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('exception-codes:fetch')
            ->setDescription('Fetch exception codes from TYPO3 core for tags.')
            ->addArgument(
                name: 'mode',
                mode: InputArgument::OPTIONAL,
                description: 'Verify mode - all to rebuild all tags, missing to only create missing tag collections.',
                default: 'missing',
            )
            ->addOption(
                name: 'exception-collections-path',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the exception code collection files.',
                default: $this->getDefaultExceptionCodeCollectionPath(),
            )
            ->addOption(
                name: 'repo-path',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Working path for TYPO3 core repository checkout.',
                default: $this->rootPath() . DIRECTORY_SEPARATOR . '/.Build/typo3-core-repo',
            )
            ->addOption(
                name: 'repo-clone-url',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'TYPO3 Core Repository clone url.',
                default: 'https://github.com/TYPO3/typo3.git',
            )
            ->addOption(
                name: 'auto-commit',
                shortcut: 'a',
                mode: InputOption::VALUE_NONE,
                description: 'If set, git commits are created if something has changed.',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootPath = $this->rootPath();
        if (!Path::isAbsolute($rootPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Could not determine absolute root path: %s',
                    $rootPath,
                ),
                1696777010
            );
        }
        $autoCommit = (bool)$input->getOption('auto-commit');
        $exceptionCollectionsPath = $input->getOption('exception-collections-path');
        $coreRepositoryPath = $input->getOption('repo-path');
        $coreRepositoryCloneUrl = $input->getOption('repo-clone-url');
        $mode = FetchMode::from((string)$input->getArgument('mode'));

        $exceptionCollectionsPath = Path::canonicalize(Path::makeAbsolute($exceptionCollectionsPath, $rootPath));
        $coreRepositoryPath = Path::canonicalize(Path::makeAbsolute($coreRepositoryPath, $rootPath));
        $output->writeln('Auto-commit: ' . ($autoCommit ? 'Y' : 'N'));
        $service = FetcherService::factory(
            output: $output,
            autoCommit: $autoCommit,
            corePath: $coreRepositoryPath,
            coreUrl: $coreRepositoryCloneUrl,
            exceptionsPath: $exceptionCollectionsPath,
            mode: $mode,
            rootPath: $rootPath,
        );
        $service->fetchExceptionCodes();

        return Command::SUCCESS;
    }

    protected function getDefaultExceptionCodeCollectionPath(): string|null
    {
        $rootPath = $this->rootPath();
        if ($rootPath !== ''
            && is_dir($rootPath . DIRECTORY_SEPARATOR . '/Build/Exceptions')
        ) {
            return $rootPath . DIRECTORY_SEPARATOR . '/Build/Exceptions';
        }

        return null;
    }

    protected function rootPath(): string
    {
        $coreRootPath = getenv('CORE_ROOT');
        if (!empty($coreRootPath) && is_dir($coreRootPath)) {
            $coreRootPath = rtrim($coreRootPath, DIRECTORY_SEPARATOR);
            $coreRootPath = Path::canonicalize($coreRootPath);
            if (Path::isAbsolute($coreRootPath)) {
                return $coreRootPath;
            }
        }
        $rootPath = rtrim(dirname(__DIR__, 4), DIRECTORY_SEPARATOR);
        $realRootPath = realpath($rootPath);
        if ($realRootPath !== false) {
            $realRootPath = rtrim($realRootPath, DIRECTORY_SEPARATOR);
            if (Path::isAbsolute($realRootPath)) {
                return $realRootPath;
            }
        }

        return $rootPath;
    }
}