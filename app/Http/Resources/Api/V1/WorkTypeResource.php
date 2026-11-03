<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkType */
class WorkTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'reference_prefix' => $this->reference_prefix,
            'workspace_id' => $this->workspace_id,
            'form_schema' => $this->form_schema,
            'is_active' => $this->is_active,
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id' => $this->workspace->id,
                'slug' => $this->workspace->slug,
                'name' => $this->workspace->name,
            ]),
        ];
    }
}
