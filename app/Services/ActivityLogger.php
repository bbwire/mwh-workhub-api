<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function log(Model $subject, string $event, ?User $causer = null, ?array $properties = null): ActivityEvent
    {
        return ActivityEvent::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'causer_id' => $causer?->id,
            'event' => $event,
            'properties' => $properties,
        ]);
    }
}
