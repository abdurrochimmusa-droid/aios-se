<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Running = 'running';
    case Paused = 'paused';
    case Done = 'done';
}
