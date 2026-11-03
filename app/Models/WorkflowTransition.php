<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = [
        'workflow_id',
        'from_status_id',
        'to_status_id',
        'slug',
        'label',
        'required_permission',
        'requires_comment',
    ];

    protected function casts(): array
    {
        return [
            'requires_comment' => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(WorkflowStatus::class, 'to_status_id');
    }
}
