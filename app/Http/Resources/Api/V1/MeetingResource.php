<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Meeting */
class MeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'notes' => $this->notes,
            'minutes' => $this->minutes,
            'held_at' => $this->held_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'action_items_count' => $this->when(isset($this->work_items_count) || isset($this->action_items_count), function () {
                return (int) ($this->action_items_count ?? $this->work_items_count ?? 0);
            }),
            'open_actions_count' => $this->when(isset($this->open_actions_count), fn () => (int) $this->open_actions_count),
            'overdue_actions_count' => $this->when(isset($this->overdue_actions_count), fn () => (int) $this->overdue_actions_count),
        ];
    }
}
