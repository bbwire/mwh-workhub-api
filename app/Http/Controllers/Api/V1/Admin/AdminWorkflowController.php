<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\Admin\UpdateWorkflowRequest;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AdminWorkflowController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        $workflows = Workflow::query()
            ->with('workType.workspace')
            ->withCount(['statuses', 'transitions'])
            ->orderBy('work_type_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return WorkflowResource::collection($workflows);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $workflow = DB::transaction(function () use ($request): Workflow {
            if ($request->boolean('is_default')) {
                Workflow::query()
                    ->where('work_type_id', $request->integer('work_type_id'))
                    ->update(['is_default' => false]);
            }

            return Workflow::query()->create([
                'work_type_id' => $request->integer('work_type_id'),
                'name' => $request->string('name'),
                'is_default' => $request->boolean('is_default'),
            ]);
        });

        $workflow->load('workType.workspace')->loadCount(['statuses', 'transitions']);

        return response()->json([
            'data' => WorkflowResource::make($workflow),
        ], 201);
    }

    public function show(Request $request, Workflow $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        $workflow->load([
            'workType.workspace',
            'statuses',
            'transitions.fromStatus',
            'transitions.toStatus',
        ]);

        return response()->json([
            'data' => WorkflowResource::make($workflow),
        ]);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): JsonResponse
    {
        DB::transaction(function () use ($request, $workflow): void {
            if ($request->boolean('is_default')) {
                Workflow::query()
                    ->where('work_type_id', $workflow->work_type_id)
                    ->whereKeyNot($workflow->id)
                    ->update(['is_default' => false]);
            }

            $workflow->fill($request->validated());
            $workflow->save();
        });

        $workflow->load([
            'workType.workspace',
            'statuses',
            'transitions.fromStatus',
            'transitions.toStatus',
        ]);

        return response()->json([
            'data' => WorkflowResource::make($workflow),
        ]);
    }

    public function destroy(Request $request, Workflow $workflow): JsonResponse
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        if ($workflow->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default workflow for a work type.',
            ], 422);
        }

        if ($workflow->statuses()->whereHas('workItems')->exists()) {
            return response()->json([
                'message' => 'Cannot delete a workflow that has work items on its statuses.',
            ], 422);
        }

        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted.']);
    }
}
