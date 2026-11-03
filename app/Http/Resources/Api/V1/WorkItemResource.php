<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkItem */
class WorkItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'custom_fields' => $this->custom_fields,
            'confidential' => $this->confidential,
            'due_at' => $this->due_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'work_type' => $this->whenLoaded('workType', fn () => [
                'id' => $this->workType->id,
                'slug' => $this->workType->slug,
                'name' => $this->workType->name,
            ]),
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->id,
                'slug' => $this->workspace->slug,
                'name' => $this->workspace->name,
            ]),
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'slug' => $this->project->slug,
                'name' => $this->project->name,
            ]),
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'slug' => $this->status->slug,
                'label' => $this->status->label,
                'category' => $this->status->category,
            ]),
            'requester' => UserResource::make($this->whenLoaded('requester')),
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'available_transitions' => $this->when(
                isset($this->available_transitions),
                fn () => $this->available_transitions
            ),
        ];
    }
}
