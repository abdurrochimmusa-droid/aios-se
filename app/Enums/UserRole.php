<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Approver = 'approver';
    case Viewer = 'viewer';
}
