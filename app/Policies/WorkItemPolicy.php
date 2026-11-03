<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work.view.team') || $user->can('work.create');
    }

    public function view(User $user, WorkItem $workItem): bool
    {
        if ($user->hasRole('system_admin') || $user->can('dashboard.management')) {
            return true;
        }

        if ($workItem->requester_id === $user->id || $workItem->assignee_id === $user->id) {
            return true;
        }

        if ($user->workspaces()->where('workspaces.id', $workItem->workspace_id)->exists()) {
            return $user->can('work.view.team');
        }

        if ($workItem->department_id && $user->supervisedDepartmentIds()->contains($workItem->department_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('work.create');
    }

    public function update(User $user, WorkItem $workItem): bool
    {
        if ($user->hasRole('system_admin')) {
            return true;
        }

        if ($workItem->assignee_id === $user->id) {
            return $user->can('work.update.assigned');
        }

        if ($workItem->department_id && $user->supervisedDepartmentIds()->contains($workItem->department_id)) {
            return $user->can('work.assign');
        }

        return false;
    }

    public function assign(User $user, WorkItem $workItem): bool
    {
        return $user->can('work.assign') && $this->view($user, $workItem);
    }

    public function transition(User $user, WorkItem $workItem): bool
    {
        return $this->update($user, $workItem) || $workItem->requester_id === $user->id;
    }
}
