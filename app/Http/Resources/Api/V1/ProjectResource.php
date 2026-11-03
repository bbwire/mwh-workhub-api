<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Project */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status?->value,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'slug' => $this->department->slug,
                'name' => $this->department->name,
            ]),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'milestones' => ProjectMilestoneResource::collection($this->whenLoaded('milestones')),
            'tasks_count' => $this->when(isset($this->tasks_count), fn () => $this->tasks_count),
        ];
    }
}
