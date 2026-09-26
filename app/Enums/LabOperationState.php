<?php

namespace App\Enums;

enum LabOperationState: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Provisioning = 'provisioning';
    case Starting = 'starting';
    case Stopping = 'stopping';
    case Resetting = 'resetting';
    case Deleting = 'deleting';
    case Reconciling = 'reconciling';
    case Failed = 'failed';
}
