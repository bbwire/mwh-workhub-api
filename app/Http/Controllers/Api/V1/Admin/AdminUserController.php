<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $users = User::query()
            ->with(['roles', 'permissions', 'departments', 'workspaces'])
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        $user->load(['roles', 'permissions', 'departments', 'workspaces']);

        return response()->json([
            'data' => UserResource::make($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->can('users.manage'), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        if (array_key_exists('name', $validated)) {
            $user->name = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }
        if (array_key_exists('employee_id', $validated)) {
            $user->employee_id = $validated['employee_id'];
        }
        if (array_key_exists('is_active', $validated)) {
            $user->is_active = $validated['is_active'];
        }

        $user->save();

        if (array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles']);
        }

        $user->load(['roles', 'permissions', 'departments', 'workspaces']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }
}
