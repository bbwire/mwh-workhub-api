<?php

namespace App\Support;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;

class MyWorkQuery
{
    /**
     * Open work items where the user is assignee or requester.
     *
     * @return Builder<WorkItem>
     */
    public static function openFor(User $user): Builder
    {
        return WorkItem::query()
            ->visibleTo($user)
            ->where(function (Builder $q) use ($user): void {
                $q->where('assignee_id', $user->id)
                    ->orWhere('requester_id', $user->id);
            })
            ->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));
    }
}
