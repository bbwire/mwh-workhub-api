<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'notes',
        'held_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'meeting_work_item');
    }
}
