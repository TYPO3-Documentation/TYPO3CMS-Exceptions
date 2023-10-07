<?php

declare(strict_types=1);

namespace T3DOCS\ExceptionCodes\Git;

use CzProject\GitPhp\Commit;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\InvalidArgumentException;
use CzProject\GitPhp\RunnerResult;
use CzProject\GitPhp\Runners\CliRunner;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Provides a git repository wrapper based on the "czproject/git-php" library.
 *
 * @see https://github.com/czproject/git-php
 */
class Repository
{
    protected CustomGit $git;
    protected CliRunner $runner;
    protected CustomGitRepository|null $repository = null;

    /**
     * @param OutputInterface $output
     * @param non-empty-string $path
     * @param string|null $uri
     */
    public function __construct(
        public readonly OutputInterface $output,
        public readonly string $path,
        public readonly string|null $uri = null,
        public string|null $userName = null,
        public string|null $userEmail = null,
    ) {
        $this->runner = new CliRunner(
            gitBinary: $this->determineGitBinary(),
        );
        $this->git = new CustomGit(
            runner: $this->runner,
        );
    }

    public function isReady(bool $throwException = false): bool
    {
        try {
            return $this->repository() instanceof CustomGitRepository
                && $this->isValid();
        } catch (GitException $e) {
            if ($throwException === true) {
                throw $e;
            }
        }

        return false;
    }

    public function checkout(string $revision, array|null $gitOptions = null): self
    {
        if (empty($gitOptions)) {
            $this->repository()->checkout($revision);
            return $this;
        }

        if (!is_string($revision)) {
            throw new InvalidArgumentException('Branch name must be string.');
        }
        if ($revision === '') {
            throw new InvalidArgumentException('Branch name cannot be empty.');
        }
        if ($revision[0] === '-') {
            throw new InvalidArgumentException('Branch name cannot be option name.');
        }
        $this->run($gitOptions, 'checkout', $revision);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->repository()->getTags() ?? [];
    }

    public function addFile(string ...$files): self
    {
        $this->repository()->addFile($files);

        return $this;
    }

    public function renameFile(string $old, string $new): self
    {
        $this->repository()->renameFile(
            file: $old,
            to: $new,
        );

        return $this;
    }

    public function removeFile(string ...$files): self
    {
        $this->repository()->removeFile($files);

        return $this;
    }

    public function addAllChanges(): self
    {
        $this->repository()->addAllChanges();

        return $this;
    }

    public function getCurrentBranchName(): string
    {
        return $this->repository()->getCurrentBranchName();
    }

    public function commit(string $message, array|null $options = null): Commit
    {
        return $this->repository()->commit(
            message: $message,
            options: $options,
        )->getLastCommit();
    }

    public function commitRaw(array|null $options = null): self
    {
        $this->run('commit', $options);
        return $this;
    }

    public function push(string|array|null $remote, array|null $options = null): self
    {
        $this->repository()->push(
            remote: $remote,
            options: $options,
        );

        return $this;
    }

    public function pull(string|array|null $remote, array|null $options = []): Commit
    {
        return $this->repository()->pull(
            remote: $remote,
            options: $options,
        )->getLastCommit();
    }

    public function pullRebase(string|array|null $remote, array|null $options = []): Commit
    {
        return $this->repository()->pull(
            remote: $remote,
            options: array_merge(
                $options ?? [],
                [
                    '--rebase',
                ]
            ),
        )->getLastCommit();
    }

    /**
     * @param string $reference
     * @param bool $hard
     * @return Commit
     * @throws GitException
     *
     * @todo Move this to a GitRepository extending class.
     */
    public function reset(string $reference, bool $hard = false): Commit
    {
        $options = [];
        if ($hard === true){
            $options[] = '--hard';
        }
        $this->run(
            'reset',
            $options,
            $reference
        );
        return $this->repository()->getLastCommit();
    }

    public function fetch(string|array|null $remote = null, array|null $options = null): self
    {
        $this->repository()->fetch($remote, $options);

        return $this;
    }

    public function getLastCommit(): Commit
    {
        return $this->repository()->getLastCommit();
    }

    public function hasChanges(): bool
    {
        return $this->repository()->hasChanges();
    }

    protected function repository(): CustomGitRepository
    {
        if ($this->repository instanceof CustomGitRepository) {
            return $this->repository;
        }

        if ($this->isValid()) {
            return $this->repository = $this->git->open(
                directory: $this->path
            );
        }

        if (!empty($this->uri)) {
            $this->repository = $this->git->cloneRepository(
                url: $this->uri,
                directory: $this->path,
                params: [],
            );
            if ($this->repository instanceof CustomGitRepository
                && $this->isValid()
            ) {
                return $this->repository;
            }
        }

        throw new GitException(
            sprintf(
                'Could not open or clone repository "%s".',
                $this->path,
            ),
            1696734684,
            null
        );
    }

    public function userName(): string|null
    {
        return $this->repository->userName();
    }

    public function userEmail(): string|null
    {
        return $this->repository->userEmail();
    }

    protected function determineGitBinary(): string
    {
        return 'git';
    }

    protected function isValid(): bool
    {
        try {
            return ($this->repository ?? $this->git->open($this->path))->getCurrentBranchName() !== '';
        } catch (GitException) {}

        return false;
    }

    /**
     * Runs command.
     * @param  mixed ...$args
     * @return RunnerResult
     * @throws GitException
     *
     * @todo Temporary copy&paste from GitRepository until custom methods has been added to a extended GitRepository.
     */
    protected function run(...$args): RunnerResult
    {
        $result = $this->runner->run($this->repository()->getRepositoryPath(), $args);

        if (!$result->isOk()) {
            throw new GitException("Command '{$result->getCommand()}' failed (exit-code {$result->getExitCode()}).", $result->getExitCode(), NULL, $result);
        }

        return $result;
    }
}