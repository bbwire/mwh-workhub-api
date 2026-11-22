<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityEventResource;
use App\Services\ActivityLogQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request, ActivityLogQuery $query): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $events = $query->visibleTo($user)
            ->paginate(min($request->integer('per_page', 30), 100));

        return ActivityEventResource::collection($events);
    }
}
