<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrganizationSetting */
class OrganizationSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->short_name,
            'tagline' => $this->tagline,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'report_footer' => $this->report_footer,
            'logo_url' => $this->logoUrl(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
