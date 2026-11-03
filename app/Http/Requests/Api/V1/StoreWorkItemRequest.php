<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('work.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'work_type_slug' => ['required', 'string', Rule::exists('work_types', 'slug')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
            'confidential' => ['nullable', 'boolean'],
        ];
    }
}
