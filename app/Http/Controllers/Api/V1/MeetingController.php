<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeetingRequest;
use App\Http\Resources\Api\V1\MeetingResource;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MeetingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $meetings = Meeting::query()
            ->with('creator')
            ->withCount('workItems')
            ->latest('held_at')
            ->paginate($request->integer('per_page', 20));

        return MeetingResource::collection($meetings);
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $meeting = Meeting::query()->create([
            'title' => $request->string('title'),
            'notes' => $request->input('notes'),
            'held_at' => $request->date('held_at'),
            'created_by_id' => $request->user()->id,
        ]);

        $meeting->load('creator');
        $meeting->loadCount('workItems');

        return response()->json([
            'data' => MeetingResource::make($meeting),
        ], 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $meeting->load(['creator', 'workItems.status', 'workItems.assignee']);

        return response()->json([
            'data' => MeetingResource::make($meeting),
            'action_items' => WorkItemResource::collection($meeting->workItems)->resolve(),
        ]);
    }

    public function update(StoreMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting->update($request->validated());
        $meeting->load('creator')->loadCount('workItems');

        return response()->json([
            'data' => MeetingResource::make($meeting),
        ]);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted.']);
    }
}
