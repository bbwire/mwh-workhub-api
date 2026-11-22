<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogQuery
{
    /**
     * @return Builder<ActivityEvent>
     */
    public function visibleTo(User $user): Builder
    {
        $morph = (new WorkItem)->getMorphClass();

        return ActivityEvent::query()
            ->with(['causer', 'subject'])
            ->where('subject_type', $morph)
            ->when(
                ! $user->hasRole('system_admin') && ! $user->can('dashboard.management'),
                fn (Builder $query) => $query->whereIn(
                    'subject_id',
                    WorkItem::query()->visibleTo($user)->select('id'),
                ),
            )
            ->latest();
    }
}
