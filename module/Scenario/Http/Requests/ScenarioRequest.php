<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Scenario\Models\Scenario;

final class ScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $scenario = $this->route('scenario');
        $scenarioId = $scenario instanceof Scenario ? $scenario->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'alias' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('scenarios', 'alias')->ignore($scenarioId),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['required', 'uuid', 'exists:categories,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['required', 'uuid', 'exists:user_groups,id'],
            'active_version_id' => [
                'nullable',
                'string',
                Rule::exists('scenario_versions', 'id')->where('scenario_id', $scenarioId),
            ],
        ];
    }
}
