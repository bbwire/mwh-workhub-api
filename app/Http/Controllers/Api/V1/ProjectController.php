<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with(['owner', 'department'])
            ->withCount('workItems as tasks_count')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $slug = $this->uniqueSlug($request->string('name'));

        $project = Project::query()->create([
            'name' => $request->string('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
            'status' => $request->enum('status', ProjectStatus::class) ?? ProjectStatus::Active,
            'department_id' => $request->input('department_id'),
            'owner_id' => $request->user()->id,
            'starts_at' => $request->date('starts_at'),
            'ends_at' => $request->date('ends_at'),
        ]);

        $memberIds = collect($request->input('member_ids', []))
            ->push($request->user()->id)
            ->unique()
            ->values();

        $sync = [];
        foreach ($memberIds as $memberId) {
            $sync[$memberId] = [
                'role' => (int) $memberId === $request->user()->id ? 'lead' : 'member',
            ];
        }
        $project->members()->sync($sync);

        foreach ($request->input('milestones', []) as $index => $milestone) {
            $project->milestones()->create([
                'title' => $milestone['title'],
                'due_at' => $milestone['due_at'] ?? null,
                'sort_order' => $index * 10,
            ]);
        }

        $project->load(['owner', 'department', 'members', 'milestones'])->loadCount('workItems as tasks_count');

        return response()->json([
            'data' => ProjectResource::make($project),
        ], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['owner', 'department', 'members', 'milestones'])
            ->loadCount('workItems as tasks_count');

        $tasks = $project->workItems()
            ->visibleTo($request->user())
            ->with(['workType', 'status', 'assignee'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => ProjectResource::make($project),
            'tasks' => WorkItemResource::collection($tasks)->resolve(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->fill($request->safe()->except(['member_ids']));
        $project->save();

        if ($request->has('member_ids')) {
            $memberIds = collect($request->input('member_ids', []))
                ->push($project->owner_id)
                ->unique()
                ->values();

            $sync = [];
            foreach ($memberIds as $memberId) {
                $sync[$memberId] = [
                    'role' => (int) $memberId === $project->owner_id ? 'lead' : 'member',
                ];
            }
            $project->members()->sync($sync);
        }

        $project->load(['owner', 'department', 'members', 'milestones'])->loadCount('workItems as tasks_count');

        return response()->json([
            'data' => ProjectResource::make($project),
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'project';
        }

        $slug = $base;
        $suffix = 1;

        while (Project::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
