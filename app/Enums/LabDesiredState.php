<?php

namespace App\Enums;

enum LabDesiredState: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Deleted = 'deleted';
}
