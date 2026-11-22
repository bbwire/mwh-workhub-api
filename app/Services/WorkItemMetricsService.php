<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WorkItemMetricsService
{
    /**
     * @return array{from: string|null, to: string|null}
     */
    public function resolvePeriod(?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        return [
            'from' => $fromDate->toIso8601String(),
            'to' => $toDate->toIso8601String(),
            'from_carbon' => $fromDate,
            'to_carbon' => $toDate,
        ];
    }

    public function scopedQuery(User $user, ?int $departmentId = null): Builder
    {
        $query = WorkItem::query()->visibleTo($user);

        if ($departmentId !== null) {
            $query->where('department_id', $departmentId);
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    public function countSnapshot(Builder $base, Carbon $from, Carbon $to): array
    {
        $inPeriod = (clone $base)->whereBetween('created_at', [$from, $to]);

        $open = (clone $inPeriod)->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));

        $completedInPeriod = (clone $base)->where(function (Builder $q) use ($from, $to): void {
            $q->whereBetween('closed_at', [$from, $to])
                ->orWhere(function (Builder $inner) use ($from, $to): void {
                    $inner->whereNull('closed_at')
                        ->whereBetween('resolved_at', [$from, $to]);
                });
        });

        return [
            'total' => (clone $inPeriod)->count(),
            'awaiting_action' => (clone $open)->where(function (Builder $q): void {
                $q->whereNull('assignee_id')
                    ->orWhereHas('status', fn (Builder $s) => $s->where('category', 'todo'));
            })->count(),
            'in_progress' => (clone $open)->whereHas('status', fn (Builder $s) => $s->where('category', 'in_progress'))->count(),
            'completed' => $completedInPeriod->count(),
            'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'unassigned' => (clone $open)->whereNull('assignee_id')->count(),
            'urgent_open' => (clone $open)->whereIn('priority', ['urgent', 'high'])->count(),
        ];
    }

    /**
     * @return Collection<int, array{department_id: int|null, department_name: string|null, total: int, open: int, overdue: int}>
     */
    public function departmentSummaries(Builder $base, Carbon $from, Carbon $to): Collection
    {
        $rows = (clone $base)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('department_id, COUNT(*) as total')
            ->groupBy('department_id')
            ->get();

        $departmentNames = Department::query()
            ->whereIn('id', $rows->pluck('department_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($base, $from, $to, $departmentNames) {
            $deptId = $row->department_id ? (int) $row->department_id : null;
            $deptQuery = $deptId
                ? (clone $base)->where('department_id', $deptId)->whereBetween('created_at', [$from, $to])
                : (clone $base)->whereNull('department_id')->whereBetween('created_at', [$from, $to]);

            $open = (clone $deptQuery)->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));

            return [
                'department_id' => $deptId,
                'department_name' => $deptId ? ($departmentNames[$deptId] ?? 'Unknown') : 'Unassigned',
                'total' => (int) $row->total,
                'open' => $open->count(),
                'overdue' => (clone $open)->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            ];
        })->values();
    }

    /**
     * @return array<int, array{key: string, label: string, value: int}>
     */
    public function statusBreakdown(Builder $base, Carbon $from, Carbon $to): array
    {
        $rows = (clone $base)
            ->whereBetween('work_items.created_at', [$from, $to])
            ->join('workflow_statuses', 'workflow_statuses.id', '=', 'work_items.workflow_status_id')
            ->selectRaw('workflow_statuses.category as category, COUNT(work_items.id) as total')
            ->groupBy('workflow_statuses.category')
            ->pluck('total', 'category');

        $labels = [
            'todo' => 'Assigned',
            'in_progress' => 'In Progress',
            'done' => 'Completed',
        ];

        return collect($labels)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'value' => (int) ($rows[$key] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, value: int}>
     */
    public function priorityBreakdown(Builder $base, Carbon $from, Carbon $to): array
    {
        $open = (clone $base)
            ->whereBetween('work_items.created_at', [$from, $to])
            ->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));

        $rows = (clone $open)
            ->selectRaw('work_items.priority as priority, COUNT(work_items.id) as total')
            ->groupBy('work_items.priority')
            ->pluck('total', 'priority');

        $order = [
            'urgent' => 'Urgent',
            'high' => 'High',
            'normal' => 'Medium',
            'low' => 'Low',
        ];

        return collect($order)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'value' => (int) ($rows[$key] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{assignee_id: int|null, assignee_name: string, value: int}>
     */
    public function assigneeWorkload(Builder $base, Carbon $from, Carbon $to, int $limit = 8): array
    {
        $rows = (clone $base)
            ->whereBetween('work_items.created_at', [$from, $to])
            ->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false))
            ->selectRaw('work_items.assignee_id as assignee_id, COUNT(work_items.id) as total')
            ->groupBy('work_items.assignee_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $names = User::query()
            ->whereIn('id', $rows->pluck('assignee_id')->filter())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($names): array {
            $assigneeId = $row->assignee_id ? (int) $row->assignee_id : null;

            return [
                'assignee_id' => $assigneeId,
                'assignee_name' => $assigneeId ? ($names[$assigneeId] ?? 'Unknown') : 'Unassigned',
                'value' => (int) $row->total,
            ];
        })->values()->all();
    }

    public function userCanAccessDepartment(User $user, int $departmentId): bool
    {
        if ($user->hasRole('system_admin') || $user->can('dashboard.management')) {
            return true;
        }

        if ($user->supervisedDepartmentIds()->contains($departmentId)) {
            return true;
        }

        return $user->departments()->where('departments.id', $departmentId)->exists();
    }
}
