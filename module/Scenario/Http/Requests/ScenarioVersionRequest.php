<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScenarioVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $scenario = $this->route('scenario');
        $scenarioId = is_object($scenario) && isset($scenario->id) ? (string) $scenario->id : (string) $scenario;
        $version = $this->route('version');
        $versionId = is_object($version) && isset($version->id) ? (string) $version->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('scenario_versions', 'name')
                    ->where(fn ($q) => $q->where('scenario_id', $scenarioId))
                    ->ignore($versionId),
            ],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'archived'])],
            'schema_json' => ['required', 'array'],
            'input_fields' => ['nullable', 'array'],
            'input_fields.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'input_fields.*.label' => ['nullable', 'string', 'max:255'],
            'input_fields.*.type' => ['required', 'string', Rule::in(['datetime', 'json', 'text', 'boolean'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Название версии обязательно.',
            'name.unique' => 'Версия с таким названием уже существует в этом сценарии.',
        ];
    }
}
