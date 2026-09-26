<?php

namespace App\Infrastructure\Mutation;

use App\Contracts\LabMutationProvider;
use App\Data\MutationResult;
use App\Models\LabOperation;

final class UnavailableLabMutationProvider implements LabMutationProvider
{
    public function execute(LabOperation $operation): MutationResult
    {
        return new MutationResult(
            successful: false,
            errorCode: 'not_configured',
            message: 'Live infrastructure mutation is disabled in Milestone 1.',
        );
    }
}
