<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workflows.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\WorkType $workType */
        $workType = $this->route('workType');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('work_types', 'slug')->ignore($workType->id),
            ],
            'reference_prefix' => ['sometimes', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'workspace_id' => ['sometimes', 'integer', 'exists:workspaces,id'],
            'is_active' => ['sometimes', 'boolean'],
            'form_schema' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
