<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Workflows\SyncWorkflowDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SyncWorkflowDefinitionRequest;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;

class AdminWorkflowDefinitionController extends Controller
{
    public function update(
        SyncWorkflowDefinitionRequest $request,
        Workflow $workflow,
        SyncWorkflowDefinition $syncWorkflowDefinition,
    ): JsonResponse {
        $workflow = $syncWorkflowDefinition->execute(
            $workflow,
            $request->validated('statuses'),
            $request->validated('transitions'),
        );

        return response()->json([
            'data' => WorkflowResource::make($workflow),
        ]);
    }
}
