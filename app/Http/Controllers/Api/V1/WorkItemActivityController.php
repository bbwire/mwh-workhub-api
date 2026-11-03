<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityEventResource;
use App\Models\ActivityEvent;
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkItemActivityController extends Controller
{
    public function index(Request $request, WorkItem $workItem): AnonymousResourceCollection
    {
        $this->authorize('view', $workItem);

        $events = ActivityEvent::query()
            ->where('subject_type', $workItem->getMorphClass())
            ->where('subject_id', $workItem->id)
            ->with('causer')
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ActivityEventResource::collection($events);
    }
}
