<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Workspace;
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
            ->withCount('users')
            ->orderBy('name')
            ->get();

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
            'slug' => $this->uniqueSlug($validated['slug'] ?? Str::slug($validated['name'])),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->ensureWorkspace($department);

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

        if (array_key_exists('name', $validated)) {
            $department->workspaces()->update(['name' => $department->name]);
        }
        if (array_key_exists('is_active', $validated)) {
            $department->workspaces()->update(['is_active' => $department->is_active]);
        }

        return response()->json(['data' => $department->loadCount('users')]);
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'department';
        $slug = $base;
        $suffix = 2;

        while (Department::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function ensureWorkspace(Department $department): void
    {
        if ($department->workspaces()->exists()) {
            return;
        }

        $base = $department->slug;
        $slug = $base;
        $suffix = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        Workspace::query()->create([
            'slug' => $slug,
            'name' => $department->name,
            'department_id' => $department->id,
            'is_active' => $department->is_active,
        ]);
    }
}
