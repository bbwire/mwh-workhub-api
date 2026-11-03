<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workflows.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $transitions = $this->input('transitions', []);

        foreach ($transitions as $index => $transition) {
            if (! is_array($transition)) {
                continue;
            }

            if (array_key_exists('required_permission', $transition) && $transition['required_permission'] === '') {
                $transitions[$index]['required_permission'] = null;
            }
        }

        $this->merge(['transitions' => $transitions]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*.slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'statuses.*.label' => ['required', 'string', 'max:255'],
            'statuses.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'statuses.*.category' => ['required', 'string', Rule::in(['todo', 'in_progress', 'done'])],
            'statuses.*.is_terminal' => ['sometimes', 'boolean'],
            'transitions' => ['present', 'array'],
            'transitions.*.slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'transitions.*.from_status_slug' => ['required', 'string', 'max:64'],
            'transitions.*.to_status_slug' => ['required', 'string', 'max:64'],
            'transitions.*.label' => ['required', 'string', 'max:255'],
            'transitions.*.required_permission' => ['nullable', 'string', 'max:64'],
            'transitions.*.requires_comment' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $statusSlugs = collect($this->input('statuses', []))->pluck('slug')->filter()->values();
            $transitionSlugs = collect($this->input('transitions', []))->pluck('slug')->filter();

            if ($statusSlugs->duplicates()->isNotEmpty()) {
                $validator->errors()->add('statuses', 'Status slugs must be unique.');
            }

            if ($transitionSlugs->duplicates()->isNotEmpty()) {
                $validator->errors()->add('transitions', 'Transition slugs must be unique.');
            }

            foreach ($this->input('transitions', []) as $index => $transition) {
                if (! is_array($transition)) {
                    continue;
                }

                $from = $transition['from_status_slug'] ?? null;
                $to = $transition['to_status_slug'] ?? null;

                if ($from !== null && ! $statusSlugs->contains($from)) {
                    $validator->errors()->add(
                        "transitions.{$index}.from_status_slug",
                        "Unknown status slug \"{$from}\"."
                    );
                }

                if ($to !== null && ! $statusSlugs->contains($to)) {
                    $validator->errors()->add(
                        "transitions.{$index}.to_status_slug",
                        "Unknown status slug \"{$to}\"."
                    );
                }
            }
        });
    }
}
