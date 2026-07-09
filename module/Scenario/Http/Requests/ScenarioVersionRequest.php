<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;

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
        $scenarioId = match (true) {
            $scenario instanceof Scenario => $scenario->id,
            is_string($scenario) => $scenario,
            default => '',
        };
        $version = $this->route('version');
        $versionId = match (true) {
            $version instanceof ScenarioVersion => $version->id,
            is_string($version) => $version,
            default => null,
        };

        return [
            // Имя опционально: если не передано — репозиторий генерирует «vN».
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('scenario_versions', 'name')
                    ->where(fn(Builder $q) => $q->where('scenario_id', $scenarioId))
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
    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Название версии обязательно.',
            'name.unique' => 'Версия с таким названием уже существует в этом сценарии.',
        ];
    }
}
