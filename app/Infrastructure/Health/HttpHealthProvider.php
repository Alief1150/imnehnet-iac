<?php

namespace App\Infrastructure\Health;

use App\Contracts\InfrastructureHealthProvider;
use App\Data\ServiceHealth;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpHealthProvider implements InfrastructureHealthProvider
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly ?string $url,
        private readonly int $timeoutSeconds = 3,
        private readonly ?Closure $validator = null,
        private readonly bool $verifyTls = true,
        private readonly string $group = 'infrastructure',
    ) {}

    public function check(): ServiceHealth
    {
        if ($this->url === null || $this->url === '') {
            return $this->health('not_configured', 'Health endpoint is not configured.', null);
        }

        $started = hrtime(true);

        try {
            $request = Http::timeout($this->timeoutSeconds)->acceptJson();

            if (! $this->verifyTls) {
                $request = $request->withOptions(['verify' => false]);
            }

            $response = $request->get($this->url);
            $latency = (int) ((hrtime(true) - $started) / 1_000_000);

            if ($response->successful()) {
                if ($this->validator && ! ($this->validator)($response)) {
                    return $this->health('unknown', 'Health endpoint returned an invalid response.', $latency);
                }

                return $this->health('healthy', 'Health endpoint responded successfully.', $latency);
            }

            return $this->health('offline', sprintf('Health endpoint returned HTTP %d.', $response->status()), $latency);
        } catch (Throwable) {
            $latency = (int) ((hrtime(true) - $started) / 1_000_000);

            return $this->health('offline', 'Health endpoint could not be reached.', $latency);
        }
    }

    private function health(string $status, string $summary, ?int $latency): ServiceHealth
    {
        return new ServiceHealth(
            key: $this->key,
            label: $this->label,
            status: $status,
            summary: $summary,
            source: $this->url ?? 'not configured',
            checkedAt: Carbon::now(),
            latencyMs: $latency,
            group: $this->group,
        );
    }
}
