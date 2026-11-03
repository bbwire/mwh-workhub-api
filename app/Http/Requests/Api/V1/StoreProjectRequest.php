<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Project::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'milestones' => ['sometimes', 'array'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.due_at' => ['nullable', 'date'],
        ];
    }
}
