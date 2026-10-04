<?php

namespace App\Services\GuidedCtf;

/**
 * The result of asking whether an objective is proven.
 *
 * Carries the evidence, not just the boolean. An operator looking at a completed
 * lab needs to be able to answer "why does this say completed" months later, when
 * the victim's logs have rotated, so the accepted line is kept with the verdict.
 *
 * A rejected objective also carries its reason, and that reason is shown to the
 * student. An unexplained failure would tell them nothing about what to try, and
 * would be indistinguishable from the validator being broken.
 */
final readonly class ObjectiveVerdict
{
    /**
     * @param  array<string, string>|null  $evidence
     */
    private function __construct(
        public bool $proven,
        public ?array $evidence,
        public ?string $reason,
    ) {}

    /**
     * @param  array<string, string>  $evidence
     */
    public static function proven(array $evidence): self
    {
        return new self(true, $evidence, null);
    }

    public static function unproven(string $reason): self
    {
        return new self(false, null, $reason);
    }

    /**
     * The evidence as stored in the progress record.
     *
     * @return array<string, string>|null
     */
    public function evidencePayload(): ?array
    {
        if (! $this->proven || $this->evidence === null) {
            return null;
        }

        return $this->evidence + [
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}