<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeetingRequest;
use App\Http\Resources\Api\V1\MeetingResource;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
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
            ->withCount([
                'workItems as action_items_count',
                'workItems as open_actions_count' => fn (Builder $query) => $query
                    ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false)),
                'workItems as overdue_actions_count' => fn (Builder $query) => $query
                    ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false))
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now()),
            ])
            ->latest('held_at')
            ->paginate(min($request->integer('per_page', 20), 100));

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
        $meeting->loadCount([
            'workItems as action_items_count',
            'workItems as open_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false)),
            'workItems as overdue_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false))
                ->whereNotNull('due_at')
                ->where('due_at', '<', now()),
        ]);

        return response()->json([
            'data' => MeetingResource::make($meeting),
        ], 201);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if (! $user->can('meetings.manage')) {
            $canSeeLinkedWork = $meeting->workItems()
                ->visibleTo($user)
                ->exists();
            abort_unless($canSeeLinkedWork, 403);
        }

        $meeting->load('creator');
        $meeting->loadCount([
            'workItems as action_items_count',
            'workItems as open_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false)),
            'workItems as overdue_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false))
                ->whereNotNull('due_at')
                ->where('due_at', '<', now()),
        ]);

        $actions = $meeting->workItems()
            ->with(['workType', 'workspace', 'status', 'assignee', 'requester'])
            ->latest()
            ->get();

        return response()->json([
            'data' => MeetingResource::make($meeting),
            'action_items' => WorkItemResource::collection($actions)->resolve(),
        ]);
    }

    public function update(StoreMeetingRequest $request, Meeting $meeting): JsonResponse
    {
        $meeting->update($request->validated());
        $meeting->load('creator');
        $meeting->loadCount([
            'workItems as action_items_count',
            'workItems as open_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false)),
            'workItems as overdue_actions_count' => fn (Builder $query) => $query
                ->whereHas('status', fn (Builder $status) => $status->where('is_terminal', false))
                ->whereNotNull('due_at')
                ->where('due_at', '<', now()),
        ]);

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
