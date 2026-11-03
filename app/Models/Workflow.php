<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = ['work_type_id', 'name', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class);
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(WorkflowStatus::class)->orderBy('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->orderBy('slug');
    }
}
