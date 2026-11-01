<?php

namespace App\Services;

use App\Data\ProcessResult;
use App\Models\LabEnvironment;
use App\Support\EnvironmentWorkspace;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Configures a container that already exists.
 *
 * OpenTofu decides whether the container exists and what its address is;
 * Ansible decides what runs inside it. Keeping the two apart means a change to
 * the student account does not require an infrastructure apply, and a change
 * to the container shape does not require a configuration run.
 *
 * The container name is passed as an extra variable rather than edited into
 * the playbook, and it is validated first because it becomes a shell argument
 * on the compute plane.
 */
class AnsibleService
{
    public function __construct(
        private readonly string $directory,
        private readonly string $binary,
        private readonly string $playbook,
        private readonly string $inventoryGroup,
        private readonly int $timeoutSeconds,
        private readonly string $pingBinary = 'ansible',
    ) {
        if (! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('The Ansible directory "%s" is not available.', $this->directory));
        }
    }

    public static function fromConfig(): self
    {
        return new self(
            directory: (string) config('infrastructure.ansible.directory'),
            binary: (string) config('infrastructure.ansible.binary'),
            playbook: (string) config('infrastructure.ansible.playbook'),
            inventoryGroup: (string) config('infrastructure.ansible.inventory_group', 'lab_env'),
            timeoutSeconds: (int) config('infrastructure.ansible.timeout_seconds', 600),
            pingBinary: (string) config('infrastructure.ansible.ping_binary', 'ansible'),
        );
    }

    public function bootstrap(LabEnvironment $environment): ProcessResult
    {
        $container = (string) $environment->container_name;

        EnvironmentWorkspace::assertValidContainerName($container);
        EnvironmentWorkspace::assertUsable(
            $environment->tofu_workspace ?: EnvironmentWorkspace::forContainer($container),
            $container
        );

        return $this->run([
            $this->playbook,
            '--limit', $this->inventoryGroup,
            '-e', 'container_name='.$container,
            '--forks', '1',
        ]);
    }

    /**
     * Read-only reachability check for the infrastructure health view.
     */
    /**
     * Reachability, run as an ad-hoc module rather than a playbook.
     *
     * Using ansible-playbook for this produces a usage error, which would be
     * reported as "cannot reach the compute plane" when the real problem is
     * that the wrong binary was invoked.
     */
    public function ping(): ProcessResult
    {
        $process = new Process(
            [$this->pingBinary, 'all', '-m', 'ping', '-o'],
            $this->directory
        );
        $process->setTimeout(30);

        $startedAt = microtime(true);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            return new ProcessResult(
                command: [$this->pingBinary, 'all', '-m', 'ping', '-o'],
                exitCode: -1,
                stdout: '',
                stderr: $exception->getMessage(),
                durationMs: (microtime(true) - $startedAt) * 1000,
                errorCode: 'not_started',
            );
        }

        return ProcessResult::fromSymfonyProcess(
            [$this->pingBinary, 'all', '-m', 'ping', '-o'],
            $process,
            (microtime(true) - $startedAt) * 1000,
        );
    }

    public function version(): ProcessResult
    {
        return $this->run(['--version'], timeoutSeconds: 15);
    }

    /**
     * @param  list<string>  $arguments
     */
    public function run(array $arguments, ?int $timeoutSeconds = null): ProcessResult
    {
        $command = [$this->binary, ...$arguments];

        $process = new Process($command, $this->directory);
        $process->setTimeout($timeoutSeconds ?? $this->timeoutSeconds);

        $startedAt = microtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return ProcessResult::fromSymfonyProcess($command, $process, (microtime(true) - $startedAt) * 1000, 'timeout');
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

        return ProcessResult::fromSymfonyProcess($command, $process, (microtime(true) - $startedAt) * 1000);
    }
}
