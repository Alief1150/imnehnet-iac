<?php

namespace App\Data;

use DateTimeInterface;

final readonly class SecuritySummary
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $summary,
        public string $source,
        public DateTimeInterface $checkedAt,
        public int $alertCount = 0,
        public int $criticalCount = 0,
        public int $warningCount = 0,
        public ?DateTimeInterface $lastAlertAt = null,
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
            'alert_count' => $this->alertCount,
            'critical_count' => $this->criticalCount,
            'warning_count' => $this->warningCount,
            'last_alert_at' => $this->lastAlertAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
