<?php

namespace App\Infrastructure\Health;

use App\Contracts\InfrastructureHealthProvider;
use App\Data\ServiceHealth;
use Illuminate\Support\Carbon;

final class IncusHealthProvider implements InfrastructureHealthProvider
{
    public function __construct(
        private readonly CommandRunner $commands,
        private readonly string $remote = 'lab-env:',
    ) {}

    public function check(): ServiceHealth
    {
        $started = hrtime(true);
        $result = $this->commands->run(['incus', 'list', $this->remote, '--format=json']);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        if (! $result['successful']) {
            return $this->health('unknown', 'Incus status could not be verified.', $latency);
        }

        $instances = json_decode($result['stdout'], true);

        if (! is_array($instances) || ! $this->validInstances($instances)) {
            return $this->health('unknown', 'Incus returned an invalid response.', $latency);
        }

        $running = count(array_filter(
            $instances,
            fn (mixed $instance): bool => is_array($instance)
                && strcasecmp((string) ($instance['status'] ?? ''), 'running') === 0,
        ));
        $total = count($instances);

        return $this->health(
            'healthy',
            sprintf('%d container(s) observed; %d running.', $total, $running),
            $latency,
        );
    }

    /** @param array<mixed> $instances */
    private function validInstances(array $instances): bool
    {
        foreach ($instances as $instance) {
            if (! is_array($instance)
                || ! is_string($instance['name'] ?? null)
                || $instance['name'] === ''
                || ! is_string($instance['status'] ?? null)
                || $instance['status'] === '') {
                return false;
            }
        }

        return true;
    }

    private function health(string $status, string $summary, int $latency): ServiceHealth
    {
        return new ServiceHealth(
            key: 'incus',
            label: 'Incus',
            status: $status,
            summary: $summary,
            source: sprintf('incus list %s --format=json', $this->remote),
            checkedAt: Carbon::now(),
            latencyMs: $latency,
        );
    }
}
