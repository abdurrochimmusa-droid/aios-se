<?php

namespace App\Enums;

enum CommandStatus: string
{
    case Preview = 'preview';
    case Confirmed = 'confirmed';
    case Executed = 'executed';
    case Cancelled = 'cancelled';
}
