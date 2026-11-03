<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'department_id',
        'owner_id',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('system_admin') || $user->can('dashboard.management') || $user->can('projects.manage')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $memberQuery) => $memberQuery->where('users.id', $user->id));
        });
    }

    public function isMember(User $user): bool
    {
        if ($this->owner_id === $user->id) {
            return true;
        }

        return $this->members()->where('users.id', $user->id)->exists();
    }
}
