<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\CreateWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeetingBulkActionsRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Meeting;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MeetingBulkActionsController extends Controller
{
    public function store(
        StoreMeetingBulkActionsRequest $request,
        Meeting $meeting,
        CreateWorkItem $createWorkItem,
    ): JsonResponse {
        $actor = $request->user();
        $created = [];

        DB::transaction(function () use ($request, $meeting, $createWorkItem, $actor, &$created): void {
            foreach ($request->input('actions') as $action) {
                $workType = WorkType::query()
                    ->where('slug', $action['work_type_slug'])
                    ->where('is_active', true)
                    ->firstOrFail();

                $payload = [
                    'title' => $action['title'],
                    'description' => $action['description'] ?? null,
                    'due_at' => $action['due_at'] ?? null,
                    'custom_fields' => match ($workType->slug) {
                        'maintenance_request' => ['location' => 'Meeting action'],
                        'ict_support' => ['category' => 'other'],
                        default => [],
                    },
                ];

                $workItem = $createWorkItem->execute($actor, $workType, $payload);

                if (! empty($action['assignee_id'])) {
                    $workItem->assignee_id = (int) $action['assignee_id'];
                    $workItem->save();
                }

                $meeting->workItems()->attach($workItem->id);
                $workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee']);
                $created[] = $workItem;
            }
        });

        return response()->json([
            'data' => WorkItemResource::collection(collect($created))->resolve(),
        ], 201);
    }
}
