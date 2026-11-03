<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workflow */
class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'work_type_id' => $this->work_type_id,
            'work_type' => $this->whenLoaded('workType', fn () => WorkTypeResource::make($this->workType)),
            'statuses' => WorkflowStatusResource::collection($this->whenLoaded('statuses')),
            'transitions' => WorkflowTransitionResource::collection($this->whenLoaded('transitions')),
            'statuses_count' => $this->when(isset($this->statuses_count), fn () => $this->statuses_count),
            'transitions_count' => $this->when(isset($this->transitions_count), fn () => $this->transitions_count),
        ];
    }
}
