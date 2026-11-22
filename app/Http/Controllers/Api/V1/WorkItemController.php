<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\CreateWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkItemRequest;
use App\Http\Requests\Api\V1\UpdateWorkItemRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use App\Models\WorkType;
use App\Services\WorkItemTransitionCatalog;
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

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn ($q) => $q->where('slug', $request->string('status')));
        }

        $category = $request->string('category')->toString();
        if (in_array($category, ['todo', 'in_progress', 'done'], true)) {
            $query->whereHas('status', fn ($q) => $q->where('category', $category));

            if ($category === 'in_progress') {
                $query->where(function ($inner): void {
                    $inner->whereNull('due_at')->orWhere('due_at', '>=', now());
                });
            }
        }

        if ($request->filled('priority')) {
            $priorities = collect(explode(',', $request->string('priority')->toString()))
                ->map(fn (string $value) => trim($value))
                ->filter(fn (string $value) => in_array($value, ['low', 'normal', 'high', 'urgent'], true))
                ->values();

            if ($priorities->isNotEmpty()) {
                $query->whereIn('priority', $priorities->all());
            }
        }

        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('reference_number', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $due = $request->string('due')->toString();
        if ($due === 'overdue') {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereHas('status', fn ($q) => $q->where('is_terminal', false));
        } elseif ($due === 'today') {
            $query->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]);
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

        $workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee', 'project', 'meetings']);

        return response()->json([
            'data' => WorkItemResource::make($this->withAvailableTransitions($request, $workItem)),
        ]);
    }

    public function update(UpdateWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $workItem->fill($request->validated());
        $workItem->save();

        $workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee', 'project', 'meetings']);

        return response()->json([
            'data' => WorkItemResource::make($this->withAvailableTransitions($request, $workItem)),
        ]);
    }

    private function withAvailableTransitions(Request $request, WorkItem $workItem): WorkItem
    {
        return app(WorkItemTransitionCatalog::class)->forWorkItem($workItem, $request->user());
    }
}
