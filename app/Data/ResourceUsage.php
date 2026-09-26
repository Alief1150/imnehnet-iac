<?php

namespace App\Data;

use DateTimeInterface;

final readonly class ResourceUsage
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $summary,
        public string $source,
        public DateTimeInterface $checkedAt,
        public ?float $usedPercent = null,
        public ?int $usedBytes = null,
        public ?int $totalBytes = null,
        public ?int $count = null,
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
            'checked_at' => $this->checkedAt->format(DateTimeInterface::ATOM),
            'used_percent' => $this->usedPercent,
            'used_bytes' => $this->usedBytes,
            'total_bytes' => $this->totalBytes,
            'count' => $this->count,
        ];
    }
}
