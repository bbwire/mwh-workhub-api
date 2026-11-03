<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'form_schema' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
