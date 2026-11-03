<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkflowTransition */
class WorkflowTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'label' => $this->label,
            'from_status_id' => $this->from_status_id,
            'to_status_id' => $this->to_status_id,
            'from_status_slug' => $this->whenLoaded('fromStatus', fn () => $this->fromStatus->slug),
            'to_status_slug' => $this->whenLoaded('toStatus', fn () => $this->toStatus->slug),
            'required_permission' => $this->required_permission,
            'requires_comment' => $this->requires_comment,
        ];
    }
}
