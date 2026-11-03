<?php

namespace App\Policies;

use App\Enums\CommentVisibility;
use App\Models\Comment;
use App\Models\User;
use App\Models\WorkItem;

class CommentPolicy
{
    public function viewAny(User $user, WorkItem $workItem): bool
    {
        return app(WorkItemPolicy::class)->view($user, $workItem);
    }

    public function create(User $user, WorkItem $workItem): bool
    {
        if (! app(WorkItemPolicy::class)->view($user, $workItem)) {
            return false;
        }

        return true;
    }

    public function createWithVisibility(User $user, WorkItem $workItem, CommentVisibility $visibility): bool
    {
        if (! $this->create($user, $workItem)) {
            return false;
        }

        if ($visibility === CommentVisibility::Internal) {
            return $workItem->requester_id !== $user->id
                || $user->workspaces()->where('workspaces.id', $workItem->workspace_id)->exists();
        }

        return true;
    }
}
