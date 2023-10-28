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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use T3DOCS\ExceptionCodes\Git\Repository;
use T3DOCS\ExceptionCodes\ErrorCodes;

/**
 * This command uses the merged exception code lists and is able to create missing
 * documentation exception code pages for new exception codes based on a configurable
 * template file.
 *
 * Additionally, a git commit can be created if something has changed.
 *
 * @see GeneratePagesCommand for maintaining the exception code lists for TYPO3 releases.
 */
final class GeneratePagesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('exception-codes:generate-pages')
            ->setDescription('Generate missing restfiles for exception codes based on a template.')
            ->addOption(
                name: 'exception-collections-path',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the exception code collection files.',
                default: $this->getDefaultExceptionCodeCollectionPath(),
            )
            ->addOption(
                name: 'exception-documentation-path',
                shortcut: null,
                mode: InputOption::VALUE_REQUIRED,
                description: 'Path to the exception code collection files.',
                default: $this->getDefaultExceptionDocumentationPath(),
            )
            ->addOption(
                name: 'auto-commit',
                shortcut: 'a',
                mode: InputOption::VALUE_NONE,
                description: 'If set, git commits are created if something has changed.',
            );
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
        $exceptionCollectionsPath = Path::canonicalize(Path::makeAbsolute($exceptionCollectionsPath, $rootPath));
        $exceptionDocumentationPath = $input->getOption('exception-documentation-path');
        $exceptionDocumentationPath = Path::canonicalize(Path::makeAbsolute($exceptionDocumentationPath, $rootPath));
        $rootRepository = new Repository(
            output: $output,
            path: $rootPath,
            uri: null,
        );

        $errorCodes = $this->getTotalErrorCodes($exceptionCollectionsPath);
        $templateContent = $this->getDocumentationFileTemplateContent();
        if (empty($templateContent)) {
            throw new \RuntimeException(
                'Could not load documentation template file. Empty content.',
                1696785071
            );
        }
        $commitLines = [];
        $start = microtime(true);
        $countTotal = $errorCodes->total();
        $countSkipped = 0;
        $countAdded = 0;
        $countFailed = 0;
        foreach ($errorCodes->codes() as $value) {
            $targetRestFile = Path::join($exceptionDocumentationPath, sprintf('%s.rst', $value));
            if (file_exists($targetRestFile)) {
                $output->writeln(sprintf(
                    'Skipped exception code "%s". ReST file exists already.',
                    $value
                ));
                $countSkipped++;
                continue;
            }
            if (!$this->generateExceptionDocumentationFile(file: $targetRestFile, code: $value, template: $templateContent)) {
                $countFailed++;
                $output->writeln(sprintf(
                    'Failed to create documentation ReST file for exception code "%s".',
                    $value
                ));
                continue;
            }

            $countAdded++;
            $output->writeln(sprintf(
                'Successfully created documentation ReST file for exception code "%s".',
                $value
            ));
            $rootRepository->addFile($targetRestFile);
            $commitLines[] = sprintf(' * Created exception page for "%s".',  $value);
        }
        $duration = microtime(true) - $start;

        if ($countAdded > 0
            && $autoCommit === true
            && $rootRepository->hasChanges()
        ) {
            $firstLine = '[TASK] Created missing exception code page(s)';
            $rootRepository->commit($firstLine);
        } else {
            $output->writeln(
                $autoCommit === true
                    ? 'No auto commit for root repository needed'
                    : 'Auto commit disabled. Check for changes and commit them manually.'
            );
        }

        return Command::SUCCESS;
    }

    protected function generateExceptionDocumentationFile(string $file, int $code, string $template): bool
    {
        $content = $this->replaceTemplatePlaceholder($template, $code);
        if (!empty($content)) {
            return file_put_contents($file, $content) !== false;
        }
        return false;
    }

    protected function getDefaultExceptionCodeCollectionPath(): string|null
    {
        $rootPath = $this->rootPath();
        if ($rootPath !== ''
            && is_dir(Path::canonicalize(Path::join($rootPath, 'Build/Exceptions')))
        ) {
            return Path::canonicalize(Path::join($rootPath, 'Build/Exceptions'));
        }

        return null;
    }

    protected function getDefaultExceptionDocumentationPath(): string|null
    {
        $rootPath = $this->rootPath();
        if ($rootPath !== ''
            && is_dir(Path::canonicalize(Path::join($rootPath, 'Documentation/Exceptions')))
        ) {
            return Path::canonicalize(Path::join($rootPath, 'Documentation/Exceptions'));
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

    protected function replaceTemplatePlaceholder(
        string $templateContent,
        int $code,
    ): string
    {
        $exception = sprintf('TYPO3 Exception %s', $code);
        $topicLine = str_pad('', strlen($exception), '=', STR_PAD_RIGHT);
        $replaces = [
            '[[[Exception]]]' => $exception,
            '[[[TopicLine]]]' => $topicLine,
        ];
        return str_replace(array_keys($replaces), array_values($replaces), $templateContent);
    }

    protected function getTotalErrorCodes(string $exceptionsPath): ErrorCodes
    {
        $codes = [];
        $filename = $this->getTotalErrorCodeFileName(
            exceptionsPath: $exceptionsPath,
        );
        if (file_exists($filename)) {
            $codes = include $filename;
            if (!is_array($codes)) {
                $codes = [];
            }
            $codes = $codes['exceptions'] ?? [];
        }
        return new ErrorCodes(
            codes: $codes,
        );
    }

    protected function getTotalErrorCodeFileName(string $exceptionsPath): string
    {
        return Path::join($exceptionsPath, 'exceptions.php');
    }

    protected function getDocumentationFileTemplateContent(): string
    {
        $filename = Path::join(COMMAND_ROOT, 'Resources/Templates/default.rst');
        return file_exists($filename)
            ? (string)file_get_contents($filename)
            : ''
        ;
    }
}