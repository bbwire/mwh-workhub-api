<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Project $project): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole('system_admin') || $user->can('dashboard.management') || $user->can('projects.manage')) {
            return true;
        }

        return $project->isMember($user);
    }

    public function create(User $user): bool
    {
        return $user->can('projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->can('projects.manage') || $user->hasRole('system_admin')) {
            return true;
        }

        return $project->owner_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.manage') || $user->hasRole('system_admin');
    }

    public function manageTasks(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
