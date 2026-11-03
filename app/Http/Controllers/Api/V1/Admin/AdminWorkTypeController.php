<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateWorkTypeRequest;
use App\Http\Resources\Api\V1\WorkTypeResource;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminWorkTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('workflows.manage'), 403);

        $types = WorkType::query()
            ->with('workspace')
            ->orderBy('name')
            ->get();

        return WorkTypeResource::collection($types);
    }

    public function update(UpdateWorkTypeRequest $request, WorkType $workType): JsonResponse
    {
        $workType->fill($request->validated());
        $workType->save();
        $workType->load('workspace');

        return response()->json([
            'data' => WorkTypeResource::make($workType),
        ]);
    }
}
