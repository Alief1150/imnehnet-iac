<?php

namespace App\Drivers;

use App\Contracts\LifecycleDriver;
use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Services\AnsibleService;
use App\Services\OpenTofuService;

/**
 * The real driver: OpenTofu owns the container, Ansible configures it.
 *
 * Each method runs its whole sequence inside one workspace lock, because
 * OpenTofu can only have one workspace selected in a given working directory
 * at a time. Anything that switched workspace, applied and switched back
 * without holding the lock would let a second job apply against the wrong
 * environment.
 */
final class LiveLifecycleDriver implements LifecycleDriver
{
    public function __construct(
        private readonly OpenTofuService $tofu,
        private readonly AnsibleService $ansible,
    ) {}

    public function provision(LabEnvironment $environment): ProcessResult
    {
        return $this->tofu->withWorkspace($environment, function (OpenTofuService $tofu) use ($environment): ProcessResult {
            $workspace = $tofu->workspaceFor($environment);
            $selected = $tofu->selectOrCreateWorkspace($workspace);

            if (! $selected->successful()) {
                return $selected;
            }

            $variables = $this->variables($environment);
            $planned = $tofu->plan($variables);

            // Exit code 2 from a detailed plan means "changes present", which
            // is the expected case here, so it is not treated as a failure.
            if (! $planned->successful() && ! $this->isChangesPresent($planned)) {
                return $planned;
            }

            $applied = $tofu->apply($variables);

            if (! $applied->successful()) {
                return $applied;
            }

            $outputs = $tofu->readOutputs();

            return new ProcessResult(
                command: $applied->command,
                exitCode: $applied->exitCode,
                stdout: json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $applied->stdout,
                stderr: $applied->stderr,
                durationMs: $applied->durationMs,
            );
        });
    }

    public function start(LabEnvironment $environment): ProcessResult
    {
        return $this->converge($environment, true);
    }

    public function stop(LabEnvironment $environment): ProcessResult
    {
        return $this->converge($environment, false);
    }

    public function reset(LabEnvironment $environment): ProcessResult
    {
        // Destroy and recreate. The container comes back with a new identity,
        // so any address recorded earlier is expected to change and is
        // re-read from the outputs afterwards.
        return $this->tofu->withWorkspace($environment, function (OpenTofuService $tofu) use ($environment): ProcessResult {
            $workspace = $tofu->workspaceFor($environment);
            $selected = $tofu->selectOrCreateWorkspace($workspace);

            if (! $selected->successful()) {
                return $selected;
            }

            $destroyed = $tofu->destroy();

            if (! $destroyed->successful()) {
                return $destroyed;
            }

            return $this->provisionWithinLock($tofu, $environment);
        });
    }

    public function destroy(LabEnvironment $environment): ProcessResult
    {
        return $this->tofu->withWorkspace($environment, function (OpenTofuService $tofu) use ($environment): ProcessResult {
            $workspace = $tofu->workspaceFor($environment);
            $selected = $tofu->selectOrCreateWorkspace($workspace);

            if (! $selected->successful()) {
                return $selected;
            }

            $destroyed = $tofu->destroy();

            if (! $destroyed->successful()) {
                return $destroyed;
            }

            // The workspace only goes once the container is gone. Removing it
            // first would lose the record of what had to be destroyed.
            return $tofu->deleteWorkspace($workspace);
        });
    }

    /**
     * Read-only. Switches workspace under the lock so the outputs belong to
     * this environment, then changes nothing.
     */
    public function observe(LabEnvironment $environment): ProcessResult
    {
        return $this->tofu->withWorkspace($environment, function (OpenTofuService $tofu) use ($environment): ProcessResult {
            $selected = $tofu->selectOrCreateWorkspace($tofu->workspaceFor($environment));

            if (! $selected->successful()) {
                return $selected;
            }

            $outputs = $tofu->readOutputs();

            return new ProcessResult(
                command: $outputs->command,
                exitCode: $outputs->exitCode,
                stdout: json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $outputs->stdout,
                stderr: $outputs->stderr,
                durationMs: $outputs->durationMs,
            );
        });
    }

    public function configure(LabEnvironment $environment): ProcessResult
    {
        return $this->ansible->bootstrap($environment);
    }

    private function converge(LabEnvironment $environment, bool $desiredRunning): ProcessResult
    {
        return $this->tofu->withWorkspace($environment, function (OpenTofuService $tofu) use ($environment, $desiredRunning): ProcessResult {
            $selected = $tofu->selectOrCreateWorkspace($tofu->workspaceFor($environment));

            if (! $selected->successful()) {
                return $selected;
            }

            $variables = $this->variables($environment, $desiredRunning);
            $planned = $tofu->plan($variables);

            if (! $planned->successful() && ! $this->isChangesPresent($planned)) {
                return $planned;
            }

            return $tofu->apply($variables);
        });
    }

    /**
     * The apply half of provisioning, reused by reset while the lock is already
     * held. Taking the lock again here would deadlock against this process.
     */
    private function provisionWithinLock(OpenTofuService $tofu, LabEnvironment $environment): ProcessResult
    {
        $variables = $this->variables($environment);
        $planned = $tofu->plan($variables);

        if (! $planned->successful() && ! $this->isChangesPresent($planned)) {
            return $planned;
        }

        $applied = $tofu->apply($variables);

        if (! $applied->successful()) {
            return $applied;
        }

        $outputs = $tofu->readOutputs();

        return new ProcessResult(
            command: $applied->command,
            exitCode: $applied->exitCode,
            stdout: json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $applied->stdout,
            stderr: $applied->stderr,
            durationMs: $applied->durationMs,
        );
    }

    /**
     * @return array<string, string|int|bool>
     */
    private function variables(LabEnvironment $environment, ?bool $desiredRunning = null): array
    {
        $variables = [
            'container_name' => (string) $environment->container_name,
            'container_image' => (string) $environment->image,
            'cpu_limit' => (int) $environment->cpu_limit,
            'memory_limit' => $this->memoryArgument($environment),
        ];

        if ($desiredRunning !== null) {
            $variables['desired_running'] = $desiredRunning;
        }

        return $variables;
    }

    /**
     * The record stores megabytes because that is what an operator picks in a
     * form; OpenTofu passes a size string through to Incus unchanged. The
     * conversion happens once, here, so no other layer has to know which one
     * of the two units is in play.
     */
    private function memoryArgument(LabEnvironment $environment): string
    {
        $megabytes = (int) $environment->memory_limit_mb;

        return $megabytes > 0 && $megabytes % 1024 === 0
            ? sprintf('%dGiB', intdiv($megabytes, 1024))
            : sprintf('%dMiB', $megabytes);
    }

    private function isChangesPresent(ProcessResult $result): bool
    {
        // OpenTofu reports "changes present" as exit code 2 when detailed exit
        // codes are enabled, which is not an error.
        return $result->exitCode === 2;
    }
}
