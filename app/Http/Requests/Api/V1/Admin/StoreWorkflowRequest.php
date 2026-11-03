<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
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
            'work_type_id' => ['required', 'integer', 'exists:work_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
