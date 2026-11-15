<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityEventResource;
use App\Models\ActivityEvent;
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $morph = (new WorkItem)->getMorphClass();

        $events = ActivityEvent::query()
            ->with(['causer', 'subject'])
            ->where('subject_type', $morph)
            ->when(
                ! $user->hasRole('system_admin') && ! $user->can('dashboard.management'),
                fn ($query) => $query->whereIn(
                    'subject_id',
                    WorkItem::query()->visibleTo($user)->select('id'),
                ),
            )
            ->latest()
            ->paginate(min($request->integer('per_page', 30), 100));

        return ActivityEventResource::collection($events);
    }
}
