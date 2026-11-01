<?php

namespace App\Data;

final readonly class MutationResult
{
    public function __construct(
        public bool $successful,
        public ?string $errorCode = null,
        public ?string $message = null,
    ) {}
}
