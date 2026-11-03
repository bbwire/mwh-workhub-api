<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemEscalatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class EscalateOverdueWorkItemsToSupervisors implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        WorkItem::query()
            ->whereNotNull('department_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->subDay())
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->with('department')
            ->chunkById(50, function ($items): void {
                foreach ($items as $workItem) {
                    $supervisors = $this->supervisorsForDepartment((int) $workItem->department_id);

                    foreach ($supervisors as $supervisor) {
                        if ($this->alreadyNotifiedToday($supervisor, 'work_item.escalated', $workItem->id)) {
                            continue;
                        }

                        $supervisor->notify(new WorkItemEscalatedNotification($workItem));
                    }
                }
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function supervisorsForDepartment(int $departmentId)
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('departments', function ($q) use ($departmentId): void {
                $q->where('departments.id', $departmentId)
                    ->where('department_user.is_supervisor', true);
            })
            ->get();
    }

    private function alreadyNotifiedToday(User $user, string $type, int $workItemId): bool
    {
        return $user->notifications()
            ->whereDate('created_at', Carbon::today())
            ->where('data->type', $type)
            ->where('data->work_item_id', $workItemId)
            ->exists();
    }
}
