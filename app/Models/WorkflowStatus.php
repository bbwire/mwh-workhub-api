<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStatus extends Model
{
    protected $fillable = [
        'workflow_id',
        'slug',
        'label',
        'sort_order',
        'category',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'workflow_status_id');
    }
}
