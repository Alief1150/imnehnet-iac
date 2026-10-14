<?php

namespace App\Data;

use DateTimeInterface;

final readonly class MonitoringBackend
{
    public function __construct(
        public string $key,
        public string $label,
        public string $url,
        public string $description,
        public string $scope,
        public string $status,
        public DateTimeInterface $checkedAt,
        public ?int $latencyMs = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'url' => $this->url,
            'description' => $this->description,
            'scope' => $this->scope,
            'status' => $this->status,
            'checked_at' => $this->checkedAt->format(DateTimeInterface::ATOM),
            'latency_ms' => $this->latencyMs,
        ];
    }
}
