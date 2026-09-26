<?php

namespace App\Enums;

enum LabActualState: string
{
    case Absent = 'absent';
    case Created = 'created';
    case Running = 'running';
    case Stopped = 'stopped';
    case Missing = 'missing';
    case Unknown = 'unknown';
    case Error = 'error';
}
