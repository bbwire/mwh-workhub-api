<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $facilitiesDept = Department::query()->updateOrCreate(
            ['slug' => 'facilities-maintenance'],
            ['name' => 'Facilities and Maintenance', 'is_active' => true],
        );

        $ictDept = Department::query()->updateOrCreate(
            ['slug' => 'ict'],
            ['name' => 'ICT', 'is_active' => true],
        );

        $facilitiesWorkspace = Workspace::query()->updateOrCreate(
            ['slug' => 'facilities'],
            ['name' => 'Facilities and Maintenance', 'department_id' => $facilitiesDept->id, 'is_active' => true],
        );

        $ictWorkspace = Workspace::query()->updateOrCreate(
            ['slug' => 'ict'],
            ['name' => 'ICT', 'department_id' => $ictDept->id, 'is_active' => true],
        );

        Workspace::query()->updateOrCreate(
            ['slug' => 'administration'],
            ['name' => 'Administration', 'department_id' => null, 'is_active' => true],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@mswnh.test'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );
        $admin->syncRoles(['system_admin']);

        $management = User::query()->updateOrCreate(
            ['email' => 'management@mswnh.test'],
            [
                'name' => 'Hospital Management',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );
        $management->syncRoles(['hospital_management']);

        $facilitiesSupervisor = User::query()->updateOrCreate(
            ['email' => 'facilities.supervisor@mswnh.test'],
            [
                'name' => 'Facilities Supervisor',
                'password' => Hash::make('password'),
                'is_active' => true,
                'primary_department_id' => $facilitiesDept->id,
            ],
        );
        $facilitiesSupervisor->syncRoles(['department_supervisor']);
        $facilitiesSupervisor->departments()->sync([
            $facilitiesDept->id => ['is_supervisor' => true, 'is_primary' => true],
        ]);
        $facilitiesSupervisor->workspaces()->sync([$facilitiesWorkspace->id]);

        $facilitiesOfficer = User::query()->updateOrCreate(
            ['email' => 'facilities.officer@mswnh.test'],
            [
                'name' => 'Facilities Officer',
                'password' => Hash::make('password'),
                'is_active' => true,
                'primary_department_id' => $facilitiesDept->id,
            ],
        );
        $facilitiesOfficer->syncRoles(['staff']);
        $facilitiesOfficer->departments()->sync([
            $facilitiesDept->id => ['is_supervisor' => false, 'is_primary' => true],
        ]);
        $facilitiesOfficer->workspaces()->sync([$facilitiesWorkspace->id]);

        $ictOfficer = User::query()->updateOrCreate(
            ['email' => 'ict.officer@mswnh.test'],
            [
                'name' => 'ICT Officer',
                'password' => Hash::make('password'),
                'is_active' => true,
                'primary_department_id' => $ictDept->id,
            ],
        );
        $ictOfficer->syncRoles(['staff']);
        $ictOfficer->departments()->sync([
            $ictDept->id => ['is_supervisor' => false, 'is_primary' => true],
        ]);
        $ictOfficer->workspaces()->sync([$ictWorkspace->id]);

        $requester = User::query()->updateOrCreate(
            ['email' => 'staff@mswnh.test'],
            [
                'name' => 'General Staff',
                'password' => Hash::make('password'),
                'is_active' => true,
                'primary_department_id' => $facilitiesDept->id,
            ],
        );
        $requester->syncRoles(['staff']);
        $requester->departments()->sync([
            $facilitiesDept->id => ['is_supervisor' => false, 'is_primary' => true],
        ]);
    }
}
