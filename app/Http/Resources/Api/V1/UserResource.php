<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employee_id' => $this->employee_id,
            'avatar_url' => $this->avatarUrl(),
            'is_active' => $this->is_active,
            'primary_department_id' => $this->primary_department_id,
            'is_supervisor' => $this->when(
                $this->relationLoaded('departments'),
                fn () => $this->departments->contains(fn ($department) => (bool) $department->pivot?->is_supervisor),
                false,
            ),
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'department_ids' => $this->when(
                $this->relationLoaded('departments'),
                fn () => $this->departments->pluck('id')->values(),
                [],
            ),
            'departments' => $this->when(
                $this->relationLoaded('departments'),
                fn () => $this->departments->map(fn ($department) => [
                    'id' => $department->id,
                    'slug' => $department->slug,
                    'name' => $department->name,
                ])->values(),
                [],
            ),
            'workspace_ids' => $this->when(
                $this->relationLoaded('workspaces'),
                fn () => $this->workspaces->pluck('id')->values(),
                [],
            ),
        ];
    }
}
