<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkTypeRequest;
use App\Http\Requests\Api\V1\Admin\UpdateWorkTypeRequest;
use App\Http\Resources\Api\V1\WorkTypeResource;
use App\Models\WorkItem;
use App\Models\WorkType;
use App\Services\BootstrapDefaultWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminWorkTypeController extends Controller
{
    public function __construct(
        private readonly BootstrapDefaultWorkflow $bootstrapWorkflow,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        $types = WorkType::query()
            ->with('workspace')
            ->withCount('workItems')
            ->orderBy('name')
            ->get();

        return WorkTypeResource::collection($types);
    }

    public function store(StoreWorkTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $data['slug'] ?? Str::snake(Str::slug($data['name'], '_'));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower((string) $slug)) ?: 'work_type';
        $slug = trim($slug, '_');

        if (WorkType::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'That slug is already in use.',
            ]);
        }

        $workType = DB::transaction(function () use ($data, $slug): WorkType {
            $workType = WorkType::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'reference_prefix' => strtoupper($data['reference_prefix']),
                'workspace_id' => $data['workspace_id'],
                'is_active' => $data['is_active'] ?? true,
                'form_schema' => $data['form_schema'] ?? null,
            ]);

            $this->bootstrapWorkflow->for($workType);

            return $workType;
        });

        $workType->load('workspace')->loadCount('workItems');

        return response()->json([
            'data' => WorkTypeResource::make($workType),
        ], 201);
    }

    public function update(UpdateWorkTypeRequest $request, WorkType $workType): JsonResponse
    {
        $data = $request->validated();
        $usageCount = WorkItem::query()->where('work_type_id', $workType->id)->count();

        if (array_key_exists('workspace_id', $data) && (int) $data['workspace_id'] !== (int) $workType->workspace_id && $usageCount > 0) {
            throw ValidationException::withMessages([
                'workspace_id' => 'Cannot move a work type that already has tasks.',
            ]);
        }

        if (array_key_exists('slug', $data) && $data['slug'] !== $workType->slug && $usageCount > 0) {
            throw ValidationException::withMessages([
                'slug' => 'Cannot change the slug after tasks have been created.',
            ]);
        }

        if (isset($data['reference_prefix'])) {
            $data['reference_prefix'] = strtoupper($data['reference_prefix']);
        }

        $workType->fill($data);
        $workType->save();
        $workType->load('workspace')->loadCount('workItems');

        return response()->json([
            'data' => WorkTypeResource::make($workType),
        ]);
    }

    public function destroy(Request $request, WorkType $workType): JsonResponse
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        $usageCount = WorkItem::query()->where('work_type_id', $workType->id)->count();
        if ($usageCount > 0) {
            throw ValidationException::withMessages([
                'work_type' => 'This work type has tasks. Deactivate it instead of deleting.',
            ]);
        }

        $workType->delete();

        return response()->json(['message' => 'Work type deleted.']);
    }
}
