<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTypeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:work_types,slug'],
            'reference_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'is_active' => ['sometimes', 'boolean'],
            'form_schema' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
