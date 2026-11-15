<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssigneeResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($request->user()?->can('work.create'), 403);
        abort_unless($workspace->is_active, 404);

        $members = $workspace->members()
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'data' => AssigneeResource::collection($members),
        ]);
    }
}
