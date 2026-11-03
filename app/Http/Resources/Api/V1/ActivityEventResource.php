<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActivityEvent */
class ActivityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'properties' => $this->properties,
            'created_at' => $this->created_at?->toIso8601String(),
            'causer' => $this->whenLoaded('causer', fn () => $this->causer ? [
                'id' => $this->causer->id,
                'name' => $this->causer->name,
            ] : null),
        ];
    }
}
