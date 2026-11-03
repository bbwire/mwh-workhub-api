<?php

namespace App\Models;

use App\Enums\WorkItemPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkItem extends Model
{
    protected $fillable = [
        'reference_number',
        'work_type_id',
        'workspace_id',
        'project_id',
        'workflow_status_id',
        'title',
        'description',
        'priority',
        'requester_id',
        'assignee_id',
        'department_id',
        'due_at',
        'resolved_at',
        'closed_at',
        'custom_fields',
        'confidential',
    ];

    protected function casts(): array
    {
        return [
            'priority' => WorkItemPriority::class,
            'custom_fields' => 'array',
            'confidential' => 'boolean',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'workflow_status_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkItemAssignment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function activityEvents(): MorphMany
    {
        return $this->morphMany(ActivityEvent::class, 'subject');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('system_admin') || $user->can('dashboard.management')) {
            return $query;
        }

        $workspaceIds = $user->workspaces()->pluck('workspaces.id');
        $supervisedDepartmentIds = $user->supervisedDepartmentIds();

        return $query->where(function (Builder $inner) use ($user, $workspaceIds, $supervisedDepartmentIds): void {
            $inner->where('requester_id', $user->id)
                ->orWhere('assignee_id', $user->id);

            if ($workspaceIds->isNotEmpty()) {
                $inner->orWhereIn('workspace_id', $workspaceIds);
            }

            if ($supervisedDepartmentIds->isNotEmpty()) {
                $inner->orWhereIn('department_id', $supervisedDepartmentIds);
            }

            $inner->orWhereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('owner_id', $user->id)
                    ->orWhereHas('members', fn (Builder $memberQuery) => $memberQuery->where('users.id', $user->id));
            });
        });
    }
}
