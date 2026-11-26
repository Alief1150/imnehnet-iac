<?php

namespace App\Services;

use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Support\EnvironmentWorkspace;
use DomainException;
use RuntimeException;
use Illuminate\Support\Facades\Log;
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
    public function reconcilePairedWorkspace(LabEnvironment $environment): void
    {
        $this->withDirectoryLock(function () use ($environment): void {
            $name = (string) $environment->container_name;
            EnvironmentWorkspace::assertValidContainerName($name);
            $inventory = $this->listWorkspaces();
            if (! $inventory->successful()) {
                throw new RuntimeException('Cannot inspect OpenTofu workspace inventory.');
            }
            $existing = $this->parseWorkspaceList($inventory->stdout);
            $candidates = array_unique([$this->workspaceFor($environment), $name]);
            $matches = [];
            foreach ($candidates as $workspace) {
                EnvironmentWorkspace::assertUsable($workspace, $name);
                if (! in_array($workspace, $existing, true)) {
                    continue;
                }
                if (! $this->run(['workspace', 'select', $workspace])->successful()) {
                    throw new RuntimeException('Cannot inspect candidate workspace.');
                }
                $result = $this->run(['state', 'pull']);
                if (! $result->successful()) {
                    throw new RuntimeException('Cannot read candidate workspace state.');
                }
                $state = json_decode($result->stdout, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($state) || ! isset($state['version'])) {
                    throw new RuntimeException('Unexpected OpenTofu state format.');
                }
                $count = 0;
                foreach ($state['resources'] ?? [] as $resource) {
                    if (($resource['mode'] ?? '') !== 'managed') {
                        continue;
                    }
                    foreach ($resource['instances'] ?? [] as $instance) {
                        $a = $instance['attributes'] ?? [];
                        if (($resource['type'] ?? '') !== 'incus_instance'
                            || ($a['name'] ?? '') !== $name
                            || rtrim((string) ($a['remote'] ?? ''), ':') !== rtrim((string) $environment->incus_remote, ':')
                            || ($a['project'] ?? 'default') !== ($environment->incus_project ?: 'default')) {
                            throw new RuntimeException('Workspace ownership cannot be verified; destroy refused.');
                        }
                        $count++;
                    }
                }
                if ($count > 1) {
                    throw new RuntimeException('Multiple managed instances found; destroy refused.');
                }
                if ($count === 1) {
                    $matches[] = $workspace;
                }
            }
            if (count($matches) > 1) {
                throw new RuntimeException('Duplicate OpenTofu ownership; destroy refused.');
            }
            if (count($matches) === 1 && $environment->tofu_workspace !== $matches[0]) {
                $environment->forceFill(['tofu_workspace' => $matches[0]])->save();
            }
        });
    }

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

        return $this->withDirectoryLock(function () use ($environment, $workspace, $sequence): mixed {
            // IMNEHNET_WORKSPACE_SELECTION_FIX_V1
            // Selecting and validating the workspace must happen inside
            // the same lock as every subsequent OpenTofu command.
            $inventory = $this->listWorkspaces();

            if (! $inventory->successful()) {
                throw new RuntimeException('Cannot inspect workspace inventory.');
            }

            $exists = in_array(
                $workspace,
                $this->parseWorkspaceList($inventory->stdout),
                true
            );

            // Never silently recreate a missing workspace belonging
            // to an environment that has already been provisioned.
            if (! $exists && $environment->provisioned_at !== null) {
                throw new RuntimeException(
                    'Expected provisioned workspace is missing: '.$workspace
                );
            }

            $selected = $this->selectOrCreateWorkspace($workspace);

            if (! $selected->successful()) {
                throw new RuntimeException(
                    'Failed to select OpenTofu workspace: '.$workspace
                );
            }

            $verified = $this->run(['workspace', 'show']);

            if (! $verified->successful()
                || trim($verified->stdout) !== $workspace) {
                throw new RuntimeException(
                    'OpenTofu workspace verification failed: '.$workspace
                );
            }

            return $sequence($this);
        });
    }

    /**
     * Create the workspace if it is not there yet, then select it.
     */
    public function selectOrCreateWorkspace(string $workspace): ProcessResult
    {
        $inventory = $this->listWorkspaces();

        if (! $inventory->successful()) {
            throw new RuntimeException(
                'Cannot inspect OpenTofu workspace inventory.'
            );
        }

        $exists = in_array(
            $workspace,
            $this->parseWorkspaceList($inventory->stdout),
            true
        );

        if ($exists) {
            return $this->run([
                'workspace', 'select', $workspace
            ]);
        }

        $created = $this->run([
            'workspace', 'new', $workspace
        ]);

        if ($created->successful()) {
            return $created;
        }

        // Return the actual fallback result.
        return $this->run([
            'workspace', 'select', $workspace
        ]);
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

    public function destroy(array $variables = []): ProcessResult
    {
        return $this->run(['destroy', '-auto-approve', '-input=false', '-no-color', ...$this->variableArgs($variables)]);
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

        return is_array($decoded) ? self::flattenOutputs($decoded) : [];
    }

    /**
     * Unwrap the envelope that "tofu output -json" puts around every value.
     *
     * The machine readable form is not a map of values, it is a map of
     * descriptors:
     *
     *     "container_ipv4": { "sensitive": false, "type": "string", "value": "10.20.0.171" }
     *
     * Treating that as the value made the address an array, and casting it to a
     * string raised "Array to string conversion". That exception was thrown
     * inside settle(), which is where a result becomes state, so the address was
     * never stored, the record stayed in PROVISIONING for ever, and the address
     * the portal showed was "Not observed yet" while the container had one.
     *
     * The sensitive flag is honoured rather than ignored: a marked value is
     * treated as absent rather than recorded, so nothing is kept that OpenTofu
     * says should not be.
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public static function flattenOutputs(array $decoded): array
    {
        $flat = [];

        foreach ($decoded as $name => $entry) {
            if (! is_array($entry) || ! array_key_exists('value', $entry)) {
                $flat[$name] = $entry;

                continue;
            }

            // A sensitive output has no readable value even with -json, and
            // keeping it would be exactly the leak the flag exists to prevent.
            if (($entry['sensitive'] ?? false) === true) {
                continue;
            }

            $flat[$name] = $entry['value'];
        }

        return $flat;
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

    /**
     * Remove an existing workspace without creating it.
     *
     * The caller must independently verify that its container is absent
     * before invoking this method. The entire sequence holds one lock.
     */
    /**
     * Destroy resources in an existing workspace without deleting it.
     *
     * Never creates a workspace. The caller must independently verify
     * container absence before cleaning up the workspace itself.
     */
    public function destroyWorkspaceResourcesIfPresent(
        LabEnvironment $environment
    ): void {
        $workspace = $this->workspaceFor($environment);

        EnvironmentWorkspace::assertValidContainerName(
            (string) $environment->container_name
        );

        EnvironmentWorkspace::assertUsable(
            $workspace,
            (string) $environment->container_name
        );

        $this->withDirectoryLock(function () use ($workspace, $environment): void {
            $inventory = $this->listWorkspaces();

            if (! $inventory->successful()) {
                throw new RuntimeException(
                    'Cannot inspect OpenTofu workspaces before destroy.'
                );
            }

            if (! in_array(
                $workspace,
                $this->parseWorkspaceList($inventory->stdout),
                true
            )) {
                throw new RuntimeException(
                    'OpenTofu workspace is missing; resource state cannot be verified.'
                );
            }

            $selected = $this->run(['workspace', 'select', $workspace]);

            if (! $selected->successful()) {
                throw new RuntimeException(
                    'Cannot select OpenTofu workspace for destroy.'
                );
            }


            // Verify state ownership while holding the lock.
            $pulled = $this->run(['state', 'pull']);

            if (! $pulled->successful()) {
                throw new RuntimeException(
                    'Cannot read state; destroy refused.'
                );
            }

            $state = json_decode(
                $pulled->stdout,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (! is_array($state) || ! isset($state['version'])
                || ! isset($state['resources'])
                || ! is_array($state['resources'])) {
                throw new RuntimeException(
                    'Invalid state; destroy refused.'
                );
            }

            $count = 0;

            foreach ($state['resources'] as $resource) {
                if (($resource['mode'] ?? '') !== 'managed') {
                    continue;
                }

                if (($resource['type'] ?? '') !== 'incus_instance') {
                    throw new RuntimeException(
                        'Unexpected managed resource; destroy refused.'
                    );
                }

                foreach ($resource['instances'] ?? [] as $instance) {
                    $a = $instance['attributes'] ?? [];

                    if (
                        ($a['name'] ?? '') !== (string) $environment->container_name
                        || rtrim((string) ($a['remote'] ?? ''), ':')
                            !== rtrim((string) $environment->incus_remote, ':')
                        || ($a['project'] ?? 'default')
                            !== ($environment->incus_project ?: 'default')
                    ) {
                        throw new RuntimeException(
                            'State ownership mismatch; destroy refused.'
                        );
                    }

                    $count++;
                }
            }

            // IMNEHNET_EMPTY_STATE_NOOP_V1
            // Empty rollback state has nothing to destroy.
            // The caller must independently verify Incus absence
            // before it removes this workspace.
            if ($count === 0 && $state['resources'] === []) {
                return;
            }

            if ($count !== 1) {
                throw new RuntimeException(
                    'Expected one owned instance; destroy refused.'
                );
            }

// OpenTofu requires container_image even during destroy.
            // Read from the validated environment record, never browser input.
            $image = trim((string) $environment->image);
            if ($image === '') {
                throw new RuntimeException('Cannot destroy: environment image is missing.');
            }
            $destroyVariables = ['container_image' => $image];
            $pool = trim((string) ($environment->storage_pool ?? ''));
            if ($pool !== '' && $pool !== 'default') {
                $destroyVariables['storage_pool'] = $pool;
            }
            $destroyed = $this->destroy($destroyVariables);

            if (! $destroyed->successful()) {
                // IMNEHNET_TOFU_DIAGNOSTIC_V2
                $detail = trim((string) $destroyed->stderr);

                if ($detail === '') {
                    $detail = trim((string) $destroyed->stdout);
                }

                Log::error('IMNEHNET_TOFU_DESTROY_FAILED', [
                    'environment_id' => $environment->getKey(),
                    'workspace' => $workspace,
                    'exit_code' => $destroyed->exitCode,
                    'output' => mb_substr($detail, 0, 1200),
                ]);

                throw new RuntimeException(
                    'OpenTofu resource destroy failed. See server log.'
                );
            }
        });
    }

    public function cleanupWorkspaceIfPresent(LabEnvironment $environment): void
    {
        $workspace = $this->workspaceFor($environment);

        EnvironmentWorkspace::assertValidContainerName(
            (string) $environment->container_name
        );

        EnvironmentWorkspace::assertUsable(
            $workspace,
            (string) $environment->container_name
        );

        $this->withDirectoryLock(function () use ($workspace): void {
            $before = $this->listWorkspaces();

            if (! $before->successful()) {
                throw new RuntimeException(
                    'Cannot inspect OpenTofu workspaces before cleanup.'
                );
            }

            $existing = $this->parseWorkspaceList($before->stdout);

            if (! in_array($workspace, $existing, true)) {
                return;
            }

            $deleted = $this->deleteWorkspace($workspace);

            if (! $deleted->successful()) {
                throw new RuntimeException(
                    'OpenTofu workspace cleanup failed.'
                );
            }

            $after = $this->listWorkspaces();

            if (! $after->successful()) {
                throw new RuntimeException(
                    'Cannot verify OpenTofu workspace cleanup.'
                );
            }

            if (in_array(
                $workspace,
                $this->parseWorkspaceList($after->stdout),
                true
            )) {
                throw new RuntimeException(
                    'OpenTofu workspace remains after cleanup.'
                );
            }
        });
    }

    /**
     * Read-only workspace existence check.
     *
     * Never creates a workspace. Inventory failures are not treated
     * as proof that a workspace is absent.
     */
    public function workspaceExists(string $workspace): bool
    {
        EnvironmentWorkspace::assertUsable(
            $workspace,
            'irrelevant-for-workspace-check'
        );

        return $this->withDirectoryLock(function () use ($workspace): bool {
            $result = $this->listWorkspaces();

            if (! $result->successful()) {
                throw new RuntimeException(
                    'Cannot verify OpenTofu workspace inventory.'
                );
            }

            return in_array(
                $workspace,
                $this->parseWorkspaceList($result->stdout),
                true
            );
        });
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
