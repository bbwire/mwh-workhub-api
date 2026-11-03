<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\CreateWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkItemRequest;
use App\Http\Requests\Api\V1\UpdateWorkItemRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use App\Models\WorkType;
use App\Models\WorkflowTransition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkItem::class);

        $query = WorkItem::query()
            ->visibleTo($request->user())
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee']);

        if ($request->filled('workspace')) {
            $query->whereHas('workspace', fn ($q) => $q->where('slug', $request->string('workspace')));
        }

        if ($request->filled('work_type')) {
            $query->whereHas('workType', fn ($q) => $q->where('slug', $request->string('work_type')));
        }

        if ($request->boolean('mine')) {
            $query->where(function ($q) use ($request): void {
                $q->where('assignee_id', $request->user()->id)
                    ->orWhere('requester_id', $request->user()->id);
            });
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->integer('assignee_id'));
        }

        return WorkItemResource::collection(
            $query->latest()->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreWorkItemRequest $request, CreateWorkItem $createWorkItem): JsonResponse
    {
        $this->authorize('create', WorkItem::class);

        $workType = WorkType::query()
            ->where('slug', $request->string('work_type_slug'))
            ->where('is_active', true)
            ->firstOrFail();

        $workItem = $createWorkItem->execute($request->user(), $workType, $request->validated());

        return response()->json([
            'data' => WorkItemResource::make($this->withAvailableTransitions($request, $workItem)),
        ], 201);
    }

    public function show(Request $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $workItem);

        $workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee']);

        return response()->json([
            'data' => WorkItemResource::make($this->withAvailableTransitions($request, $workItem)),
        ]);
    }

    public function update(UpdateWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $workItem->fill($request->validated());
        $workItem->save();

        $workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee']);

        return response()->json([
            'data' => WorkItemResource::make($this->withAvailableTransitions($request, $workItem)),
        ]);
    }

    private function withAvailableTransitions(Request $request, WorkItem $workItem): WorkItem
    {
        $transitions = WorkflowTransition::query()
            ->where('from_status_id', $workItem->workflow_status_id)
            ->orderBy('slug')
            ->get()
            ->filter(function (WorkflowTransition $transition) use ($request): bool {
                if ($transition->required_permission === null) {
                    return true;
                }

                return $request->user()?->can($transition->required_permission) ?? false;
            })
            ->map(fn (WorkflowTransition $transition) => [
                'slug' => $transition->slug,
                'label' => $transition->label,
                'requires_comment' => $transition->requires_comment,
            ])
            ->values();

        $workItem->setAttribute('available_transitions', $transitions);

        return $workItem;
    }
}
