<?php

namespace Database\Seeders;

use App\Actions\WorkItems\CreateWorkItem;
use App\Enums\ProjectStatus;
use App\Models\Comment;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkflowStatus;
use App\Models\WorkType;
use App\Services\ActivityLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (WorkItem::query()->where('title', 'Fix laboratory printer')->exists()) {
            return;
        }

        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $facilitiesOfficer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $ictOfficer = User::query()->where('email', 'ict.officer@mswnh.test')->firstOrFail();
        $management = User::query()->where('email', 'management@mswnh.test')->firstOrFail();

        $facilities = Department::query()->where('slug', 'facilities-maintenance')->firstOrFail();
        $ict = Department::query()->where('slug', 'ict')->firstOrFail();
        $maintenance = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();
        $support = WorkType::query()->where('slug', 'ict_support')->firstOrFail();

        $create = app(CreateWorkItem::class);
        $activity = app(ActivityLogger::class);

        $printer = $this->open($create, $staff, $maintenance, [
            'title' => 'Fix laboratory printer',
            'description' => 'Printer in Lab 3 is offline and showing error code E03. Check connectivity, restart, and replace toner if needed.',
            'priority' => 'high',
            'due_at' => now()->setTime(17, 0),
            'custom_fields' => ['location' => 'Laboratory 3', 'safety_hazard' => false],
        ], $facilities, 'in_progress', $facilitiesOfficer, now()->subDays(2)->setTime(9, 15));

        $this->comment($printer, $facilitiesOfficer, 'I have checked the printer. It is connected but keeps throwing E03. I will restart it and clear the queue.', now()->subHours(3));
        $activity->log($printer, 'work_item.transitioned', $facilitiesOfficer, [
            'from' => 'assigned',
            'from_status_label' => 'Assigned',
            'to' => 'in_progress',
            'to_status_label' => 'In Progress',
            'comment' => 'Checked the printer — connected but still throwing E03.',
        ])->forceFill([
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ])->save();

        $this->open($create, $staff, $maintenance, [
            'title' => 'Ward 3 air conditioning not cooling',
            'description' => 'The split unit in Ward 3 bay B is running but the room stays warm. Patients have complained since yesterday evening.',
            'priority' => 'urgent',
            'due_at' => now()->subDays(1)->setTime(17, 0),
            'custom_fields' => ['location' => 'Ward 3, bay B', 'safety_hazard' => false],
        ], $facilities, 'in_progress', $facilitiesOfficer, now()->subDays(3)->setTime(14, 20));

        $this->open($create, $staff, $maintenance, [
            'title' => 'Replace corridor light bulbs',
            'description' => 'Three fittings along the postnatal corridor are out. Night staff are using a torch on the last stretch.',
            'priority' => 'normal',
            'due_at' => now()->addDays(3)->setTime(17, 0),
            'custom_fields' => ['location' => 'Postnatal corridor', 'safety_hazard' => false],
        ], $facilities, 'assigned', $facilitiesOfficer, now()->subDays(1)->setTime(11, 5));

        $this->open($create, $staff, $maintenance, [
            'title' => 'Leaking tap in the staff washroom',
            'description' => 'The cold tap by the door drips continuously. The floor stays wet through the shift.',
            'priority' => 'low',
            'due_at' => now()->addDays(5)->setTime(17, 0),
            'custom_fields' => ['location' => 'Level 2 staff washroom', 'safety_hazard' => false],
        ], $facilities, 'submitted', null, now()->subHours(6));

        $generator = $this->open($create, $staff, $maintenance, [
            'title' => 'Monthly generator service',
            'description' => 'Complete the monthly inspection, oil check, and test run for the standby generator.',
            'priority' => 'normal',
            'due_at' => now()->subDay()->setTime(17, 0),
            'custom_fields' => ['location' => 'Plant room', 'safety_hazard' => false],
        ], $facilities, 'closed', $facilitiesOfficer, now()->subDays(6)->setTime(8, 0));
        $generator->forceFill([
            'resolved_at' => now()->subDays(1)->setTime(15, 40),
            'closed_at' => now()->subDays(1)->setTime(16, 10),
        ])->save();

        $this->open($create, $supervisor, $maintenance, [
            'title' => 'Inspect oxygen manifold in Theatre 2',
            'description' => 'Confirm the alarm panel and outlet pressures before the afternoon list.',
            'priority' => 'high',
            'due_at' => now()->addDay()->setTime(17, 0),
            'custom_fields' => ['location' => 'Theatre 2', 'safety_hazard' => false],
        ], $facilities, 'assigned', $staff, now()->subHours(5));

        $switch = $this->open($create, $staff, $support, [
            'title' => 'Network switch replacement – West Wing',
            'description' => 'The access switch on West Wing level 1 drops ports overnight. Replace it during the agreed window.',
            'priority' => 'high',
            'due_at' => now()->addDays(8)->setTime(17, 0),
            'custom_fields' => ['category' => 'network'],
        ], $ict, 'assigned', $ictOfficer, now()->subDays(4)->setTime(10, 0));

        $queue = $this->open($create, $staff, $support, [
            'title' => 'Printer queue issue – ED',
            'description' => 'Emergency department discharge summaries sit in the queue and never print.',
            'priority' => 'high',
            'due_at' => now()->subDays(2)->setTime(17, 0),
            'custom_fields' => ['category' => 'printer'],
        ], $ict, 'in_progress', $ictOfficer, now()->subDays(4)->setTime(16, 30));
        $this->comment($queue, $ictOfficer, 'The queue service was restarted. Two jobs from this morning are still stuck; I am clearing them one by one.', now()->subHours(2));

        $this->open($create, $staff, $support, [
            'title' => 'MFA rollout – Phase 2',
            'description' => 'Enrol the remaining ward clerks and confirm the backup codes are stored with the supervisor.',
            'priority' => 'normal',
            'due_at' => now()->addDays(5)->setTime(17, 0),
            'custom_fields' => ['category' => 'access'],
        ], $ict, 'in_progress', $ictOfficer, now()->subDays(5)->setTime(9, 0));

        $patch = $this->open($create, $staff, $support, [
            'title' => 'Monthly patch management',
            'description' => 'Apply this month’s workstation patches on the clinical admin PCs after hours.',
            'priority' => 'low',
            'due_at' => now()->subDays(2)->setTime(17, 0),
            'custom_fields' => ['category' => 'software'],
        ], $ict, 'closed', $ictOfficer, now()->subDays(8)->setTime(8, 30));
        $patch->forceFill([
            'resolved_at' => now()->subDays(2)->setTime(18, 5),
            'closed_at' => now()->subDays(2)->setTime(18, 20),
        ])->save();

        $this->open($create, $staff, $support, [
            'title' => 'Clinical app update coordination',
            'description' => 'Coordinate the maternity app update with the ward in-charges and confirm the downtime window.',
            'priority' => 'normal',
            'due_at' => now()->addDays(12)->setTime(17, 0),
            'custom_fields' => ['category' => 'software'],
        ], $ict, 'assigned', $ictOfficer, now()->subDays(2)->setTime(13, 45));

        $this->notify($staff, [
            [
                'type' => 'work_item.assigned',
                'work_item_id' => $switch->id,
                'reference_number' => $switch->reference_number,
                'title' => $switch->title,
                'assigned_by' => $supervisor->name,
            ],
            [
                'type' => 'work_item.overdue',
                'work_item_id' => $queue->id,
                'reference_number' => $queue->reference_number,
                'title' => $queue->title,
            ],
            [
                'type' => 'work_item.due_soon',
                'work_item_id' => $printer->id,
                'reference_number' => $printer->reference_number,
                'title' => $printer->title,
            ],
        ]);

        if (! Meeting::query()->where('title', 'Monday operations review')->exists()) {
            $meeting = Meeting::query()->create([
                'title' => 'Monday operations review',
                'notes' => 'Facilities and ICT follow-ups from the weekly ops huddle.',
                'held_at' => now()->subDays(1)->setTime(8, 30),
                'created_by_id' => $management->id,
            ]);

            $signage = $this->open($create, $management, $maintenance, [
                'title' => 'Replace faded ward directional signs',
                'description' => 'Action from Monday ops review — West Wing corridor signs are unreadable.',
                'priority' => 'normal',
                'due_at' => now()->addDays(5)->setTime(17, 0),
                'custom_fields' => ['location' => 'Meeting action', 'safety_hazard' => false],
                'assignee_id' => $facilitiesOfficer->id,
            ], $facilities, 'assigned', $facilitiesOfficer, now()->subDays(1)->setTime(9, 0));

            $wifi = $this->open($create, $management, $support, [
                'title' => 'Survey Maternity Wi-Fi dead spots',
                'description' => 'Action from Monday ops review — midwives report dropouts near Bay 4.',
                'priority' => 'high',
                'due_at' => now()->subDay()->setTime(17, 0),
                'custom_fields' => ['category' => 'network'],
                'assignee_id' => $ictOfficer->id,
            ], $ict, 'in_progress', $ictOfficer, now()->subDays(1)->setTime(9, 15));

            $meeting->workItems()->attach([$signage->id, $wifi->id]);
        }

        if (! Project::query()->where('slug', 'oxygen-manifold-upgrade')->exists()) {
            $project = Project::query()->create([
                'name' => 'Oxygen manifold upgrade',
                'slug' => 'oxygen-manifold-upgrade',
                'description' => 'Replace Theatre 2 oxygen manifold and certify the backup cylinder bank.',
                'status' => ProjectStatus::Active,
                'department_id' => $facilities->id,
                'owner_id' => $supervisor->id,
                'starts_at' => now()->subWeeks(2),
                'ends_at' => now()->addWeeks(6),
            ]);

            $project->members()->sync([
                $supervisor->id => ['role' => 'lead'],
                $facilitiesOfficer->id => ['role' => 'member'],
            ]);

            $project->milestones()->createMany([
                [
                    'title' => 'Site survey complete',
                    'due_at' => now()->subWeek(),
                    'completed_at' => now()->subDays(5),
                    'sort_order' => 10,
                ],
                [
                    'title' => 'Parts on site',
                    'due_at' => now()->addDays(10),
                    'completed_at' => null,
                    'sort_order' => 20,
                ],
                [
                    'title' => 'Commissioning & sign-off',
                    'due_at' => now()->addWeeks(5),
                    'completed_at' => null,
                    'sort_order' => 30,
                ],
            ]);

            $projectType = WorkType::query()->where('slug', 'project_task')->firstOrFail();

            $this->open($create, $supervisor, $projectType, [
                'title' => 'Isolate Theatre 2 oxygen line for works',
                'description' => 'Coordinate with theatre in-charge before isolation.',
                'priority' => 'high',
                'due_at' => now()->addDays(3)->setTime(17, 0),
                'project_id' => $project->id,
            ], $facilities, 'in_progress', $facilitiesOfficer, now()->subDays(3)->setTime(11, 0));

            $this->open($create, $supervisor, $projectType, [
                'title' => 'Order certified manifold kit',
                'description' => 'Use the approved supplier list and confirm lead time.',
                'priority' => 'normal',
                'due_at' => now()->addDays(7)->setTime(17, 0),
                'project_id' => $project->id,
            ], $facilities, 'backlog', $facilitiesOfficer, now()->subDays(2)->setTime(14, 0));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function open(
        CreateWorkItem $create,
        User $requester,
        WorkType $type,
        array $data,
        Department $department,
        string $statusSlug,
        ?User $assignee,
        Carbon $createdAt,
    ): WorkItem {
        $item = $create->execute($requester, $type, $data);
        $status = WorkflowStatus::query()
            ->where('slug', $statusSlug)
            ->whereHas('workflow', fn ($query) => $query->where('work_type_id', $type->id)->where('is_default', true))
            ->firstOrFail();

        $item->forceFill([
            'workflow_status_id' => $status->id,
            'assignee_id' => $assignee?->id,
            'department_id' => $department->id,
            'created_at' => $createdAt,
            'updated_at' => now(),
        ])->save();

        $item->activityEvents()
            ->where('event', 'work_item.created')
            ->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        return $item->fresh();
    }

    private function comment(WorkItem $item, User $author, string $body, Carbon $at): void
    {
        $comment = Comment::query()->create([
            'work_item_id' => $item->id,
            'user_id' => $author->id,
            'body' => $body,
            'visibility' => 'public',
        ]);
        $comment->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        app(ActivityLogger::class)
            ->log($item, 'work_item.comment_created', $author, ['comment_id' => $comment->id])
            ->forceFill(['created_at' => $at, 'updated_at' => $at])
            ->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function notify(User $user, array $items): void
    {
        foreach ($items as $index => $data) {
            $at = now()->subHours(6 - ($index * 2));
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\DemoNotification',
                'data' => $data,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }
}
