<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\WorkItemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Department::query()
            ->where('is_active', true)
            ->with(['workspaces' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->withCount('users')
            ->orderBy('name');

        $requestDirectory = $request->query('purpose') === 'request' && $user->can('work.create');

        if (! $requestDirectory && ! $user->hasRole('system_admin') && ! $user->can('dashboard.management')) {
            $query->whereIn('id', $user->departments()->pluck('departments.id'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (Department $department) => $this->payload($department))->values(),
        ]);
    }

    public function show(Request $request, Department $department, WorkItemMetricsService $metrics): JsonResponse
    {
        abort_unless($department->is_active, 404);
        abort_unless($metrics->userCanAccessDepartment($request->user(), $department->id), 403);

        $department->load([
            'workspaces' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'users' => fn ($q) => $q->orderBy('name'),
        ]);
        $department->loadCount('users');

        return response()->json([
            'data' => $this->payload($department, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Department $department, bool $withMembers = false): array
    {
        $payload = [
            'id' => $department->id,
            'slug' => $department->slug,
            'name' => $department->name,
            'member_count' => (int) ($department->users_count ?? 0),
            'workspaces' => $department->workspaces->map(fn ($workspace) => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'department_id' => $workspace->department_id,
            ])->values(),
        ];

        if ($withMembers) {
            $payload['members'] = $department->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_supervisor' => (bool) $user->pivot?->is_supervisor,
            ])->values();
        }

        return $payload;
    }
}
