<?php

namespace App\Data;

use DateTimeInterface;

final readonly class ResourceTrend
{
    /**
     * @param  list<float>  $samples
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $summary,
        public string $source,
        public DateTimeInterface $checkedAt,
        public array $samples = [],
        public ?float $latestPercent = null,
        public ?int $totalBytes = null,
    ) {}

    public function hasSamples(): bool
    {
        return $this->samples !== [];
    }

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
            'samples' => $this->samples,
            'latest_percent' => $this->latestPercent,
            'total_bytes' => $this->totalBytes,
        ];
    }
}
