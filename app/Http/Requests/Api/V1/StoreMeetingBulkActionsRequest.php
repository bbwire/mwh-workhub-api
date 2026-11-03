<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingBulkActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meetings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actions' => ['required', 'array', 'min:1', 'max:50'],
            'actions.*.title' => ['required', 'string', 'max:255'],
            'actions.*.description' => ['nullable', 'string', 'max:10000'],
            'actions.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'actions.*.due_at' => ['nullable', 'date'],
            'actions.*.work_type_slug' => ['required', 'string'],
        ];
    }
}
