<?php

declare(strict_types=1);

namespace T3DOCS\ExceptionCodes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use T3DOCS\ExceptionCodes\Git\Repository;
use T3DOCS\ExceptionCodes\ErrorCodes;

class GeneratePagesCommand extends Command
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
//            $fullCommitMessage = implode("\n", [
//                $firstLine,
//                '',
//                'New exception codes has been found in TYPO3 releases, but the',
//                'corresponding documentation pages are missing.',
//                'This change contains automatically created documentation pages',
//                'for exception codes which are missing.',
//                '',
//                'List of items:',
//            ]);
//            $fullCommitMessage .= implode("\n", $commitLines);
//            $fullCommitMessage .= implode("\n", [
//                '',
//                '```',
//                ' Total..: ' . $countTotal,
//                ' Added..: ' . $countAdded,
//                ' Skipped: ' . $countSkipped,
//                ' Failed.: ' . $countFailed,
//                '```',
//                '',
//                sprintf(' Checking all codes took %s seconds.', $duration),
//                '',
//                'Used command(s):',
//                '',
//                '```shell',
//                '> Build/Scripts/runTests.sh -s createMissingExceptionCodeFiles -c',
//                '```',
//                '',
//            ]);
//            file_put_contents(
//                Path::join($rootRepository->path, '.Build/commit-message.txt'),
//                $fullCommitMessage,
//            );
//            $rootRepository->commitRaw([
//                //'--author="TYPO3 Documentation Team <documentation@typo3.org>"',
//                '-F' => Path::join($rootRepository->path, '.Build/commit-message.txt'),
//            ]);
//            unlink(Path::join($rootRepository->path, '.Build/commit-message.txt'));
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