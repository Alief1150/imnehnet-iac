<?php

namespace App\Contracts;

use App\Data\MutationResult;
use App\Models\LabOperation;

interface LabMutationProvider
{
    public function execute(LabOperation $operation): MutationResult;
}
