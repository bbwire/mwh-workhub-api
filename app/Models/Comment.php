<?php

namespace App\Models;

use App\Enums\CommentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'work_item_id',
        'user_id',
        'body',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => CommentVisibility::class,
        ];
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleToUser(Builder $query, User $user, WorkItem $workItem): Builder
    {
        if ($user->hasRole('system_admin') || $user->can('dashboard.management')) {
            return $query;
        }

        $canSeeInternal = $workItem->assignee_id === $user->id
            || ($workItem->department_id && $user->supervisedDepartmentIds()->contains($workItem->department_id))
            || (
                $user->can('work.view.team')
                && $user->workspaces()->where('workspaces.id', $workItem->workspace_id)->exists()
            );

        if ($canSeeInternal) {
            return $query;
        }

        return $query->where('visibility', CommentVisibility::Public);
    }
}
