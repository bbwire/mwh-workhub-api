<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Workspace::query()->where('is_active', true)->orderBy('name');

        if (! $user->hasRole('system_admin') && ! $user->can('dashboard.management')) {
            $workspaceIds = $user->workspaces()->pluck('workspaces.id');
            $supervisedDepartmentIds = $user->supervisedDepartmentIds();

            $query->where(function ($inner) use ($workspaceIds, $supervisedDepartmentIds): void {
                $scoped = false;

                if ($workspaceIds->isNotEmpty()) {
                    $inner->whereIn('id', $workspaceIds);
                    $scoped = true;
                }

                if ($supervisedDepartmentIds->isNotEmpty()) {
                    if ($scoped) {
                        $inner->orWhereIn('department_id', $supervisedDepartmentIds);
                    } else {
                        $inner->whereIn('department_id', $supervisedDepartmentIds);
                    }
                    $scoped = true;
                }

                if (! $scoped) {
                    $inner->whereRaw('1 = 0');
                }
            });
        }

        $workspaces = $query->get(['id', 'slug', 'name', 'department_id']);

        return response()->json([
            'data' => $workspaces->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'slug' => $workspace->slug,
                'name' => $workspace->name,
                'department_id' => $workspace->department_id,
            ]),
        ]);
    }
}
