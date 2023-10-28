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

namespace T3DOCS\ExceptionCodes\Service;

use CzProject\GitPhp\GitException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use T3DOCS\ExceptionCodes\Git\Repository;
use T3DOCS\ExceptionCodes\ErrorCodes;

class FetcherService
{
    public function __construct(
        protected readonly OutputInterface $output,
        protected readonly Repository $rootRepository,
        protected readonly Repository $coreRepository,
        protected readonly bool $autoCommit,
        protected readonly string $exceptionsPath,
        protected readonly FetchMode $mode,
        protected readonly Filesystem $filesystem,
    ) {
    }

    public static function factory(
        OutputInterface $output,
        bool $autoCommit,
        string $corePath,
        string $coreUrl,
        string $exceptionsPath,
        FetchMode $mode,
        string $rootPath,
    ): self {
        $corePath = rtrim($corePath, DIRECTORY_SEPARATOR);
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $exceptionsPath = rtrim($exceptionsPath, DIRECTORY_SEPARATOR);
        $rootRepository = new Repository(
            output: $output,
            path: $rootPath,
            uri: null,
        );
        $coreRepository = new Repository(
            output: $output,
            path: $corePath,
            uri: $coreUrl,
        );
        return new self(
            output: $output,
            rootRepository: $rootRepository,
            coreRepository: $coreRepository,
            autoCommit: $autoCommit,
            exceptionsPath: $exceptionsPath,
            mode: $mode,
            filesystem: new Filesystem(),
        );
    }

    public function userName(): string|null
    {
        return $this->rootRepository->userName();
    }

    public function userEmail(): string|null
    {
        return $this->rootRepository->userEmail();
    }

    public function setUserName(string|null $name = null): self
    {
        $this->rootRepository->setRepositoryUserName($name);
        return $this;
    }

    public function setUserEmail(string|null $email = null): self
    {
        $this->rootRepository->setRepositoryUserEmail($email);
        return $this;
    }

    public function fetchExceptionCodes(): void
    {
        $currentTotalErrorCodes = $this->getTotalErrorCodes();
        $newTotalErrorCodes = new ErrorCodes(
            codes: ($this->mode === FetchMode::All ? [] : $currentTotalErrorCodes->codes()),
        );
        $rootRepository = $this->rootRepository;
        $this->output->write('Preparing root repository ... ');
        if (!$rootRepository->isReady()) {
            $this->output->writeln('failed!');
            throw new \RuntimeException(
                'Root repository must be a cloned git repository, but it isn\'t',
                1696713096
            );
        }
        $this->output->writeln('done.');
        $currentDirectory = getcwd();
        $coreRepository = $this->coreRepository;
        $this->output->write('Preparing core repository ... ');
        if (!$coreRepository->isReady()) {
            $this->output->writeln('failed!');
            throw new \RuntimeException('Failed to open or clone core repository.', 1696710143);
        }
        $this->output->writeln('done.');
        chdir($coreRepository->path);

        $coreRepository->fetch('origin', ['--prune']);
        $coreRepository->fetch(null, ['--all']);

        $this->output->write('Reset repository to origin/main ...');
        try {
            $coreRepository->reset('origin/main', true);
            $this->output->writeln('done');
        } catch (GitException $e) {
            $this->output->writeln('failed! ' . $e->getMessage());
            throw $e;
        }
        $coreRepository->checkout('main');
        $currentBranchName = $coreRepository->getCurrentBranchName();
        if ($currentBranchName !== 'main') {
            throw new \RuntimeException(
                sprintf('Could not set core repository to main branch, current "%s"', $currentBranchName),
                1696788988
            );
        }
        $coreRepository->pull(null, ['--rebase']);
        $this->output->write('Read all tags from repository ... ');
        $tags = $coreRepository->getTags();
        $countTotal = count($tags);
        $this->output->writeln(sprintf('done. Found %s tags.', $countTotal));

        $countAdded = 0;
        $countFailed = 0;
        $countSkipped = 0;
        $durationTotal = 0;
        usort($tags, 'version_compare');
        foreach ($tags as $tag) {
            $exceptionJsonFilePath = $this->exceptionJsonFilePathForTag($tag);
            $fileExists = file_exists($exceptionJsonFilePath);
            if ($this->mode === FetchMode::Missing && $fileExists) {
                $this->output->writeln(
                    messages: sprintf(
                        'Exception codes of TYPO3 %s already fetched.',
                        $tag,
                    ),
                );
                $countSkipped++;
                continue;
            }

            try {
                $start = microtime(true);

                $coreRepository->checkout($tag, ['-c' => 'advice.detachedHead=false']);
                $exceptionCodesJson = [];
                exec(sprintf('%s -p', $this->determineDuplicateExceptionCodeCheckScriptToUse()), $exceptionCodesJson);
                $exceptionCodesJson = implode("\n", $exceptionCodesJson);
                $exceptionCodes = \json_decode($exceptionCodesJson, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($exceptionCodes) || $exceptionCodes === []) {
                    $countFailed++;
                    $this->output->writeln(sprintf('Failed to fetch exception codes for tag "%s"', $tag));
                    continue;
                }
                file_put_contents($exceptionJsonFilePath, $exceptionCodesJson);
                $newTotalErrorCodes->merge(new ErrorCodes(
                    codes: $exceptionCodes['exceptions'] ?? [],
                ));

                $duration = microtime(true) - $start;
                $durationTotal += $duration;
                $countAdded++;

                if ($this->autoCommit === true) {
                    $rootRepository->addFile($exceptionJsonFilePath);
                }
                $this->output->writeln(
                    sprintf(
                        'Fetching %s exception codes of TYPO3 %s took %s seconds.',
                        $exceptionCodes['total'], $tag, number_format($duration, 2)
                    ),
                );

            } catch (\Exception $e) {
                $countFailed++;
                $this->output->writeln(
                    sprintf(
                        'Fetching the exception codes of TYPO3 %s failed (%s)!',
                        $tag,
                        $e->getMessage(),
                    )
                );
            }
        }

        $this->output->writeln(
            sprintf(
                'Fetching the exception codes of %s(%s) TYPO3 releases took %s seconds.',
                ($countTotal - $countSkipped),
                $countTotal,
                number_format($durationTotal, 2)
            )
        );

        if ($this->mode === FetchMode::All || $countAdded > 0) {
            $this->writeTotalErrorCodes($newTotalErrorCodes);
            if ($this->autoCommit === true) {
                $rootRepository->addFile($this->getTotalErrorCodeFileName());
            }
        }

        if ($this->autoCommit === true
            && $this->rootRepository->hasChanges()
            && $countAdded > 0
        ) {
            $firstLine = ($this->mode === FetchMode::Missing)
                ? '[TASK] Added missing release exception codes'
                : '[TASK] Rebuild exception codes for all releases';
            $rootRepository->commit($firstLine);

        } else {
            $this->output->writeln(
                $this->autoCommit === true
                    ? 'No auto commit for root repository needed'
                    : 'Auto commit disabled. Check for changes and commit them manually.'
            );
        }

        $this->output->writeln(
            sprintf(
                'Root repository is %s',
                $rootRepository->hasChanges()
                    ? 'dirty'
                    : 'clean'
            )
        );

        chdir($currentDirectory);

        if ($this->autoCommit === true && $rootRepository->hasChanges()) {
            throw new \RuntimeException(
                'Changes left in repository after committing exception code release collections.',
                1696777795
            );
        }
    }

    protected function exceptionJsonFilePathForTag(string $tag): string
    {
        return Path::canonicalize(Path::join($this->exceptionsPath,sprintf('exceptions-%s.json', $tag)));
    }

    protected function determineDuplicateExceptionCodeCheckScriptToUse(): string
    {
        $repoBasePath = $this->coreRepository->path;
        $rootPath = $this->rootRepository->path;
        if (file_exists($repoBasePath . DIRECTORY_SEPARATOR . 'Build/Scripts/duplicateExceptionCodeCheck.sh')) {
            return $repoBasePath . DIRECTORY_SEPARATOR . 'Build/Scripts/duplicateExceptionCodeCheck.sh';
        }
        if (file_exists($rootPath . DIRECTORY_SEPARATOR . 'Build/Scripts/app/Resources/Scripts/duplicateExceptionCodeCheck.sh')) {
            return $rootPath . DIRECTORY_SEPARATOR . 'Build/Scripts/app/Resources/Scripts/duplicateExceptionCodeCheck.sh';
        }

        throw new \RuntimeException('Could not determine duplicateExceptionCodeCheck.sh path to use.', 1696711733);
    }

    protected function getTotalErrorCodes(): ErrorCodes
    {
        $codes = [];
        $filename = $this->getTotalErrorCodeFileName();
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

    protected function writeTotalErrorCodes(ErrorCodes $codes): void
    {
        file_put_contents(
            $this->getTotalErrorCodeFileName(),
            sprintf(
                "<?php\nreturn %s;",
                var_export($codes->export(), true)
            )
        );
    }

    protected function getTotalErrorCodeFileName(): string
    {
        return Path::join($this->exceptionsPath, 'exceptions.php');
    }
}