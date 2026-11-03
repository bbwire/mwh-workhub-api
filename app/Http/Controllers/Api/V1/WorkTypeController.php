<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $types = WorkType::query()
            ->where('is_active', true)
            ->with('workspace:id,slug,name')
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'reference_prefix', 'workspace_id', 'form_schema']);

        return response()->json(['data' => $types]);
    }
}
