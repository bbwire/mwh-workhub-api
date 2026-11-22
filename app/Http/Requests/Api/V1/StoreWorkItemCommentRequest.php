<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CommentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkItemCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['sometimes', 'string', Rule::enum(CommentVisibility::class)],
            'mentioned_user_ids' => ['sometimes', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function visibility(): CommentVisibility
    {
        $value = $this->input('visibility', CommentVisibility::Public->value);

        return CommentVisibility::from((string) $value);
    }
}
