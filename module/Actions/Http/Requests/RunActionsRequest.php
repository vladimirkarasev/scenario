<?php

declare(strict_types=1);

namespace Module\Actions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['parallel', 'sequential'])],

            'actions' => ['required', 'array', 'min:1'],
            'actions.*' => ['required', 'uuid', 'exists:actions,id'],

            'before' => ['nullable', 'array'],
            'before.*' => ['required', 'uuid', 'exists:actions,id'],

            'after' => ['nullable', 'array'],
            'after.*' => ['required', 'uuid', 'exists:actions,id'],

            'on_error' => ['nullable', 'array'],
            'on_error.*' => ['required', 'uuid', 'exists:actions,id'],

            'input' => ['nullable', 'array'],

            'schedule' => ['nullable', 'array'],
            'schedule.cron' => ['required_with:schedule', 'string', 'max:120'],
            'schedule.timezone' => ['nullable', 'string', 'timezone'],
        ];
    }
}
