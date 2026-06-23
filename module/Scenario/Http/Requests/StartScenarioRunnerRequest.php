<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StartScenarioRunnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'scenario_id' => ['nullable', 'uuid'],
            'version_id' => ['nullable', 'uuid'],
            'alias' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
            'user_data' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (!$this->filled('scenario_id') && !$this->filled('version_id') && !$this->filled('alias')) {
                $v->errors()->add(
                    'scenario_id',
                    'Необходимо передать один из параметров: scenario_id, version_id или alias.'
                );
            }
        });
    }
}
