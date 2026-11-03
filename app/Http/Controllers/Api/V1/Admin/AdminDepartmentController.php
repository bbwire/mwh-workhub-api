<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminDepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'is_active']);

        return response()->json(['data' => $departments]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:departments,slug'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department = Department::query()->create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $department], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('departments', 'slug')->ignore($department->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department->update($validated);

        return response()->json(['data' => $department]);
    }
}
