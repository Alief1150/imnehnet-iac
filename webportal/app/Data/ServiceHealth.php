<?php

namespace App\Data;

use DateTimeInterface;

final readonly class ServiceHealth
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $summary,
        public string $source,
        public DateTimeInterface $checkedAt,
        public ?int $latencyMs = null,
        public ?string $version = null,
        public string $group = 'infrastructure',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'summary' => $this->summary,
            'source' => $this->source,
            'group' => $this->group,
            'checked_at' => $this->checkedAt->format(DateTimeInterface::ATOM),
            'latency_ms' => $this->latencyMs,
            'version' => $this->version,
        ];
    }
}
