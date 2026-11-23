<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminRoleController extends Controller
{
    /** @var list<string> */
    private const PROTECTED_ROLES = [
        'super_admin',
        'hospital_management',
        'department_supervisor',
        'staff',
        'reviewer',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $userCounts = DB::table('model_has_roles')
            ->where('model_type', (new User)->getMorphClass())
            ->selectRaw('role_id, COUNT(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id');

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($userCounts) {
                $role->users_count = (int) ($userCounts[$role->id] ?? 0);

                return $this->payload($role);
            });

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => [
                'name' => $permission->name,
                'label' => $this->permissionLabel($permission->name),
                'group' => Str::before($permission->name, '.') ?: $permission->name,
            ])
            ->values();

        return response()->json([
            'data' => $roles,
            'meta' => [
                'permissions' => $permissions,
                'protected_roles' => self::PROTECTED_ROLES,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($validated['permissions']);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role->load('permissions');
        $role->users_count = 0;

        return response()->json([
            'data' => $this->payload($role),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($role->guard_name === 'web', 404);

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
            'permissions' => ['sometimes', 'array', 'min:1'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
        ]);

        if (array_key_exists('name', $validated) && $validated['name'] !== $role->name) {
            if ($this->isProtected($role->name)) {
                throw ValidationException::withMessages([
                    'name' => ['Built-in roles cannot be renamed.'],
                ]);
            }
            $role->name = $validated['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $validated)) {
            $permissions = $validated['permissions'];

            if ($role->name === 'super_admin') {
                foreach (['admin.access', 'roles.manage', 'users.manage'] as $required) {
                    if (! in_array($required, $permissions, true)) {
                        throw ValidationException::withMessages([
                            'permissions' => ["Super admin must keep {$required}."],
                        ]);
                    }
                }
            }

            $role->syncPermissions($permissions);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $role = $role->fresh();
        $role->load('permissions');
        $role->users_count = $this->usersCountFor($role);

        return response()->json([
            'data' => $this->payload($role),
        ]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless($role->guard_name === 'web', 404);

        if ($this->isProtected($role->name)) {
            throw ValidationException::withMessages([
                'role' => ['Built-in roles cannot be deleted.'],
            ]);
        }

        if ($this->usersCountFor($role) > 0) {
            throw ValidationException::withMessages([
                'role' => ['Reassign users before deleting this role.'],
            ]);
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'data' => ['id' => $role->id],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('roles.manage') || $user->can('admin.access')), 403);
    }

    private function isProtected(string $name): bool
    {
        return in_array($name, self::PROTECTED_ROLES, true);
    }

    private function usersCountFor(Role $role): int
    {
        return (int) DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', (new User)->getMorphClass())
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => Str::of($role->name)->replace('_', ' ')->title()->toString(),
            'is_protected' => $this->isProtected($role->name),
            'users_count' => (int) ($role->users_count ?? 0),
            'permissions' => $role->permissions->pluck('name')->sort()->values(),
        ];
    }

    private function permissionLabel(string $name): string
    {
        return Str::of($name)
            ->replace(['.', '_'], [' · ', ' '])
            ->title()
            ->toString();
    }
}
