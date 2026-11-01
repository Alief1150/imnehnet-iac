<?php

namespace App\Infrastructure\Health;

use App\Contracts\InfrastructureHealthProvider;
use App\Data\ServiceHealth;
use Illuminate\Support\Carbon;

final class LocalServiceHealthProvider implements InfrastructureHealthProvider
{
    /** @param list<string> $command */
    public function __construct(
        private readonly CommandRunner $commands,
        private readonly string $key,
        private readonly string $label,
        private readonly array $command,
    ) {}

    public function check(): ServiceHealth
    {
        $started = hrtime(true);
        $result = $this->commands->run($this->command);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        return new ServiceHealth(
            key: $this->key,
            label: $this->label,
            status: $result['successful'] ? 'healthy' : 'unknown',
            summary: $result['successful']
                ? sprintf('%s executable is available.', $this->label)
                : sprintf('%s executable could not be verified.', $this->label),
            source: implode(' ', $this->command),
            checkedAt: Carbon::now(),
            latencyMs: $latency,
            version: $result['successful'] ? $this->firstLine($result['stdout']) : null,
        );
    }

    private function firstLine(string $output): ?string
    {
        $line = trim(strtok($output, "\r\n") ?: '');

        return $line === '' ? null : $line;
    }
}
