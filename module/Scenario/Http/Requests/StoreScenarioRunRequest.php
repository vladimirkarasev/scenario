<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreScenarioRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'scenario_id' => ['nullable', 'uuid', 'exists:scenarios,id'],
            'scenario_version_id' => ['nullable', 'uuid', 'exists:scenario_versions,id'],
            'context' => ['nullable', 'array'],
            'user_data' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (!$this->filled('scenario_id') && !$this->filled('scenario_version_id')) {
                $v->errors()->add('scenario_id', 'Необходимо передать scenario_id или scenario_version_id.');
            }
        });
    }
}
