<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workItem = $this->route('workItem');

        return $workItem && $this->user()?->can('assign', $workItem);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
