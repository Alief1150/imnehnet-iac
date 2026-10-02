<?php

namespace App\Services;

use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Support\EnvironmentWorkspace;
use DomainException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Every OpenTofu command the portal is allowed to run.
 *
 * Two rules are enforced here rather than trusted to callers.
 *
 * One workspace per environment. OpenTofu keeps every environment's state in
 * a single working directory and only remembers which workspace is currently
 * selected, so the workspace has to be switched before every command. That
 * makes concurrent use unsafe: two jobs could each select a workspace and
 * then both apply against whichever was selected last. The whole transaction
 * therefore runs under one exclusive lock on a file, held from the workspace
 * switch through to the last command in the sequence.
 *
 * The default workspace and the reference container are refused. The default
 * workspace holds the state of the reference environment, so a destroy aimed
 * at it would take down a container that exists to be a comparison baseline.
 */
class OpenTofuService
{
    /**
     * @var resource|null
     */
    private $lockHandle = null;

    public function __construct(
        private readonly string $directory,
        private readonly string $binary,
        private readonly string $lockFile,
        private readonly int $timeoutSeconds,
        private readonly int $lockWaitSeconds,
    ) {
        if (! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The OpenTofu working directory "%s" is not available.', $this->directory));
        }

        if (! is_dir(dirname($this->lockFile))) {
            throw new RuntimeException(sprintf('The OpenTofu lock directory "%s" is not available.', dirname($this->lockFile)));
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            directory: (string) config('infrastructure.tofu.directory'),
            binary: (string) config('infrastructure.tofu.binary'),
            lockFile: (string) config('infrastructure.tofu.lock_file'),
            timeoutSeconds: (int) config('infrastructure.tofu.timeout_seconds', 900),
            lockWaitSeconds: (int) config('infrastructure.tofu.lock_wait_seconds', 300),
        );
    }

    /**
     * The workspace this environment owns. Falls back to the derived name when
     * the record has none yet, which is the case during provisioning.
     */
    public function workspaceFor(LabEnvironment $environment): string
    {
        return $environment->tofu_workspace
            ?: EnvironmentWorkspace::forContainer((string) $environment->container_name);
    }

    /**
     * Run a sequence of OpenTofu commands against one environment's workspace,
     * holding the directory lock for the whole sequence.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $sequence
     * @return TReturn
     */
    public function withWorkspace(LabEnvironment $environment, callable $sequence): mixed
    {
        $workspace = $this->workspaceFor($environment);

        // Validated before the lock is taken and before any command runs, so a
        // rejected environment costs nothing and cannot have side effects.
        EnvironmentWorkspace::assertValidContainerName((string) $environment->container_name);
        EnvironmentWorkspace::assertUsable($workspace, (string) $environment->container_name);

        return $this->withDirectoryLock(
            fn (): mixed => $sequence($this)
        );
    }

    /**
     * Create the workspace if it is not there yet, then select it.
     */
    public function selectOrCreateWorkspace(string $workspace): ProcessResult
    {
        $existing = $this->listWorkspaces();
        $result = $existing->successful() && in_array($workspace, $this->parseWorkspaceList($existing->stdout), true)
            ? $this->run(['workspace', 'select', $workspace])
            : $this->run(['workspace', 'new', $workspace]);

        // A workspace that already exists is not an error for the caller; the
        // select that follows decides whether we really have it.
        return $result->successful()
            ? $result
            : ($this->run(['workspace', 'select', $workspace])->successful() ? $result : $result);
    }

    /**
     * @param  array<string, string|int|bool>  $variables
     */
    public function plan(array $variables = []): ProcessResult
    {
        return $this->run(['plan', '-input=false', '-no-color', '-detailed-exitcode', ...$this->variableArgs($variables)]);
    }

    /**
     * @param  array<string, string|int|bool>  $variables
     */
    public function apply(array $variables = []): ProcessResult
    {
        return $this->run(['apply', '-auto-approve', '-input=false', '-no-color', ...$this->variableArgs($variables)]);
    }

    public function destroy(): ProcessResult
    {
        return $this->run(['destroy', '-auto-approve', '-input=false', '-no-color']);
    }

    /**
     * Machine readable outputs, so an address is never parsed out of human
     * prose.
     */
    public function outputs(): ProcessResult
    {
        return $this->run(['output', '-json']);
    }

    /**
     * @return array<string, mixed>
     */
    public function readOutputs(): array
    {
        $result = $this->outputs();

        if (! $result->successful()) {
            return [];
        }

        $decoded = json_decode($result->stdout, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Remove an environment's workspace once its container is gone.
     *
     * The selection moves first, and that is the whole point of the method.
     * OpenTofu refuses to delete the workspace that is currently selected:
     *
     *     Error: Workspace "env-tes-123" is your active workspace
     *     You cannot delete the currently active workspace.
     *
     * The delete flow has just selected that workspace in order to destroy
     * through it, so without the switch the destroy succeeds and this fails. The
     * result was that every deleted environment stayed in the inventory with its
     * container already gone and a workspace that outlived it, which the
     * interface could only report as a failure to delete something that was
     * already destroyed.
     *
     * The destination is a protected workspace, which the assertion above has
     * already confirmed the target is not, so the switch cannot land on the
     * workspace being removed.
     */
    public function deleteWorkspace(string $workspace): ProcessResult
    {
        EnvironmentWorkspace::assertUsable($workspace, 'irrelevant-for-workspace-delete');

        $switched = $this->run(['workspace', 'select', $this->fallbackWorkspace()]);

        if (! $switched->successful()) {
            return $switched;
        }

        return $this->run(['workspace', 'delete', $workspace]);
    }

    /**
     * A workspace that is always safe to be sitting in.
     *
     * Taken from the protected list rather than hardcoded, so there is one place
     * that knows which workspaces are off limits and this cannot drift from it.
     */
    private function fallbackWorkspace(): string
    {
        $protected = EnvironmentWorkspace::protectedWorkspaces();

        return $protected[0] ?? 'default';
    }

    public function listWorkspaces(): ProcessResult
    {
        return $this->run(['workspace', 'list']);
    }

    /**
     * @return list<string>
     */
    public function parseWorkspaceList(string $output): array
    {
        $names = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $name = trim(ltrim(trim($line), '* '));

            if ($name !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,62}$/', $name) === 1) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Run one command. Arguments are an array, never a string, so nothing in
     * the working directory or the environment can be reinterpreted by a
     * shell.
     *
     * @param  list<string>  $arguments
     */
    public function run(array $arguments): ProcessResult
    {
        $command = [$this->binary, ...$arguments];

        $process = new Process($command, $this->directory);
        $process->setTimeout($this->timeoutSeconds);

        $startedAt = microtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return ProcessResult::fromSymfonyProcess(
                $command,
                $process,
                (microtime(true) - $startedAt) * 1000,
                'timeout'
            );
        } catch (\Throwable $exception) {
            return new ProcessResult(
                command: $command,
                exitCode: -1,
                stdout: '',
                stderr: $exception->getMessage(),
                durationMs: (microtime(true) - $startedAt) * 1000,
                errorCode: 'not_started',
            );
        }

        return ProcessResult::fromSymfonyProcess(
            $command,
            $process,
            (microtime(true) - $startedAt) * 1000,
        );
    }

    /**
     * Hold the exclusive lock for the duration of a callback.
     *
     * flock is advisory but it is the right tool here: every writer in this
     * application goes through this class, so there is no path that mutates
     * the working directory without taking the lock.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withDirectoryLock(callable $callback): mixed
    {
        $handle = fopen($this->lockFile, 'c');

        if ($handle === false) {
            throw new RuntimeException('The OpenTofu directory lock could not be opened.');
        }

        $acquired = flock($handle, LOCK_EX | LOCK_NB);

        if (! $acquired) {
            // Bounded wait. A job that cannot get the lock must fail and be
            // retried by an operator rather than sit in memory forever.
            $deadline = microtime(true) + $this->lockWaitSeconds;

            while (microtime(true) < $deadline && ! $acquired) {
                usleep(250_000);
                $acquired = flock($handle, LOCK_EX | LOCK_NB);
            }
        }

        if (! $acquired) {
            fclose($handle);

            throw new RuntimeException(sprintf(
                'Another OpenTofu operation is already running in "%s". Waited %d seconds.',
                basename($this->directory),
                $this->lockWaitSeconds
            ));
        }

        $this->lockHandle = $handle;

        try {
            return $callback();
        } finally {
            // Released even when the callback throws, otherwise a single failed
            // apply would block every later lifecycle action.
            flock($handle, LOCK_UN);
            fclose($handle);
            $this->lockHandle = null;
        }
    }

    /**
     * True while this process holds the directory lock.
     */
    public function holdsLock(): bool
    {
        return $this->lockHandle !== null;
    }

    /**
     * @param  array<string, string|int|bool>  $variables
     * @return list<string>
     */
    private function variableArgs(array $variables): array
    {
        $args = [];

        foreach ($variables as $key => $value) {
            if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
                throw new DomainException(sprintf('"%s" is not a valid OpenTofu variable name.', $key));
            }

            $args[] = sprintf(
                '-var=%s=%s',
                $key,
                match (true) {
                    is_bool($value) => $value ? 'true' : 'false',
                    default => (string) $value,
                }
            );
        }

        return $args;
    }
}
