<?php

declare(strict_types=1);

namespace Module\Actions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Actions\Enums\ActionType;
use Module\Actions\Models\Action;

final class ActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $action = $this->route('action');
        $ignoreId = $action instanceof Action ? $action->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('actions', 'slug')->ignore($ignoreId)],
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('actions', 'code')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(ActionType::values())],
            'is_active' => ['required', 'boolean'],
            'config' => ['nullable', 'array'],
            'schema' => ['nullable', 'array'],
            'ui_schema' => ['nullable', 'array'],
            'input_fields' => ['nullable', 'array'],
            'input_fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'input_fields.*.label' => ['nullable', 'string'],
            'input_fields.*.type' => ['required', Rule::in(['string', 'number', 'boolean', 'uuid', 'email'])],
            'input_fields.*.required' => ['nullable', 'boolean'],
            'input_fields.*.default' => ['nullable'],
            'default_backoff' => ['nullable', 'array'],
            'default_backoff.*' => ['integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['required', 'uuid', 'exists:categories,id'],
        ];
    }
}
