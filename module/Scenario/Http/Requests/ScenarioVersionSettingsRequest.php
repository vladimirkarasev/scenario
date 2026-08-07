<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Scenario\Models\Scenario;
use Module\Scenario\Models\ScenarioVersion;

final class ScenarioVersionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $scenario = $this->route('scenario');
        $version = $this->route('version');
        $scenarioId = $scenario instanceof Scenario ? $scenario->id : '';
        $versionId = $version instanceof ScenarioVersion ? $version->id : null;

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('scenario_versions', 'name')
                    ->where(fn(Builder $query) => $query->where('scenario_id', $scenarioId))
                    ->ignore($versionId),
            ],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'archived'])],
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    public function messages(): array
    {
        return ['name.unique' => 'Версия с таким названием уже существует в этом сценарии.'];
    }
}
