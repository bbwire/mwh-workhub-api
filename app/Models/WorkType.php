<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'reference_prefix',
        'workspace_id',
        'form_schema',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'form_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function defaultWorkflow(): HasOne
    {
        return $this->hasOne(Workflow::class)->where('is_default', true);
    }
}
