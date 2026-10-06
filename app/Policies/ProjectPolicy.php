<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $project->members()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user, Project $project): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        return $project->members()
            ->where('user_id', $user->id)
            ->whereIn('role', [UserRole::Manager, UserRole::Owner])
            ->exists();
    }
}
