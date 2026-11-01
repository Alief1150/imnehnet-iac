<?php

namespace App\Infrastructure\Health;

use App\Contracts\InfrastructureHealthProvider;
use App\Data\ServiceHealth;
use Illuminate\Support\Carbon;

final class UnavailableHealthProvider implements InfrastructureHealthProvider
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $status,
        private readonly string $summary,
    ) {}

    public function check(): ServiceHealth
    {
        return new ServiceHealth(
            key: $this->key,
            label: $this->label,
            status: $this->status,
            summary: $this->summary,
            source: 'portal configuration',
            checkedAt: Carbon::now(),
        );
    }
}
