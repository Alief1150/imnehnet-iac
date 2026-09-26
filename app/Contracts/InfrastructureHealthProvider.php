<?php

namespace App\Contracts;

use App\Data\ServiceHealth;

interface InfrastructureHealthProvider
{
    public function check(): ServiceHealth;
}
