<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'admin.access',
            'users.manage',
            'roles.manage',
            'settings.manage',
            'workflows.manage',
            'dashboard.management',
            'dashboard.department',
            'reports.hospital',
            'reports.department',
            'work.assign',
            'work.create',
            'work.update.assigned',
            'work.view.team',
            'approvals.act',
            'meetings.manage',
            'projects.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
            );
        }

        $matrix = [
            'system_admin' => $permissions,
            'hospital_management' => [
                'dashboard.management',
                'dashboard.department',
                'reports.hospital',
                'reports.department',
                'work.create',
                'work.view.team',
                'meetings.manage',
            ],
            'department_supervisor' => [
                'dashboard.department',
                'reports.department',
                'work.assign',
                'work.create',
                'work.update.assigned',
                'work.view.team',
                'approvals.act',
                'meetings.manage',
                'projects.manage',
            ],
            'staff' => [
                'work.create',
                'work.update.assigned',
                'work.view.team',
            ],
            'reviewer' => [
                'reports.department',
                'work.view.team',
            ],
        ];

        foreach ($matrix as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
            );
            $role->syncPermissions($rolePermissions);
        }
    }
}
