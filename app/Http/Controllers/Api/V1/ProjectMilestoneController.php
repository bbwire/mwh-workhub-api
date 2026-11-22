<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProjectMilestoneResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
        ]);

        $sortOrder = ((int) $project->milestones()->max('sort_order')) + 10;

        $milestone = $project->milestones()->create([
            'title' => $data['title'],
            'due_at' => $data['due_at'] ?? null,
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'data' => ProjectMilestoneResource::make($milestone),
        ], 201);
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($milestone->project_id === $project->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('title', $data)) {
            $milestone->title = $data['title'];
        }
        if (array_key_exists('due_at', $data)) {
            $milestone->due_at = $data['due_at'];
        }
        if (array_key_exists('completed', $data)) {
            $milestone->completed_at = $data['completed'] ? ($milestone->completed_at ?? now()) : null;
        }

        $milestone->save();

        return response()->json([
            'data' => ProjectMilestoneResource::make($milestone),
        ]);
    }

    public function destroy(Request $request, Project $project, ProjectMilestone $milestone): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($milestone->project_id === $project->id, 404);

        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted.']);
    }
}
