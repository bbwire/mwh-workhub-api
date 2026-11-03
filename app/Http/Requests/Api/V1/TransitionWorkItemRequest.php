<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransitionWorkItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workItem = $this->route('workItem');

        return $workItem && $this->user()?->can('transition', $workItem);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'string', 'max:100'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
