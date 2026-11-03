<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workItem = $this->route('workItem');

        return $workItem && $this->user()?->can('update', $workItem);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
