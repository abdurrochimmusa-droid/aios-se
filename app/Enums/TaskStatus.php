<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case WaitingApproval = 'waiting_approval';
    case Done = 'done';
    case Failed = 'failed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
}
