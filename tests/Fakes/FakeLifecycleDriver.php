<?php

namespace Tests\Fakes;

use App\Contracts\LifecycleDriver;
use App\Data\ProcessResult;
use App\Models\LabEnvironment;

/**
 * Records what the lifecycle asked for and answers with a scripted result.
 *
 * The orchestration is the part worth testing: which action is allowed from
 * which state, what the record looks like afterwards, and what ends up in the
 * audit trail. None of that needs a hypervisor, so the suite must not pay for
 * one. Destructive live work stays in the explicit smoke test, not here.
 */
class FakeLifecycleDriver implements LifecycleDriver
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, ProcessResult> */
    private array $scripted = [];

    private ProcessResult $default;

    private ?\Throwable $throwFor = null;

    public function __construct(?ProcessResult $default = null)
    {
        $this->default = $default ?? new ProcessResult(
            command: ['tofu', 'apply'],
            exitCode: 0,
            stdout: '',
            stderr: '',
            durationMs: 12.0,
        );
    }

    /**
     * @param  array<string, mixed>  $outputs
     */
    public function willReturnFor(string $method, ProcessResult $result, array $outputs = []): self
    {
        $this->scripted[$method] = $result;

        if ($outputs !== []) {
            $this->scripted['outputs#'.$method] = new ProcessResult(
                command: ['tofu', 'output', '-json'],
                exitCode: 0,
                stdout: json_encode($outputs) ?: '{}',
                stderr: '',
                durationMs: 3.0,
            );
        }

        return $this;
    }

    /**
     * Make one method blow up, so the job's own error handling is what gets
     * exercised rather than the failure being swallowed by the double.
     */
    public function willThrow(string $method, \Throwable $exception): self
    {
        $this->throwFor = $exception;
        $this->throwForMethod = $method;

        return $this;
    }

    private ?string $throwForMethod = null;

    public function provision(LabEnvironment $environment): ProcessResult
    {
        return $this->record('provision', $environment);
    }

    public function start(LabEnvironment $environment): ProcessResult
    {
        return $this->record('start', $environment);
    }

    public function stop(LabEnvironment $environment): ProcessResult
    {
        return $this->record('stop', $environment);
    }

    public function reset(LabEnvironment $environment): ProcessResult
    {
        return $this->record('reset', $environment);
    }

    public function destroy(LabEnvironment $environment): ProcessResult
    {
        return $this->record('destroy', $environment);
    }

    public function observe(LabEnvironment $environment): ProcessResult
    {
        return $this->record('observe', $environment);
    }

    public function configure(LabEnvironment $environment): ProcessResult
    {
        return $this->record('configure', $environment);
    }

    private function record(string $method, LabEnvironment $environment): ProcessResult
    {
        $this->calls[] = $method.':'.$environment->container_name;

        if ($this->throwFor !== null && $method === $this->throwForMethod) {
            throw $this->throwFor;
        }

        return $this->scripted[$method] ?? $this->scripted['outputs#'.$method] ?? $this->default;
    }
}
