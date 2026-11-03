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
