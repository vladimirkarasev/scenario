<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;
use Module\Scenario\Enums\BlockFieldType;
use Module\Scenario\Models\ScenarioFieldPreset;
use Module\Scenario\Rules\SafeFieldPresetSnapshot;

final class ScenarioFieldPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ScenarioFieldPreset|null $preset */
        $preset = $this->route('fieldPreset');
        $projectId = $this->container->make(CurrentProject::class)->id();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('scenario_field_presets', 'name')
                    ->where('project_id', $projectId)
                    ->ignore($preset?->id),
            ],
            'field' => ['required', 'array', 'max:100', new SafeFieldPresetSnapshot()],
            'field.id' => ['sometimes', 'string', 'max:255'],
            'field.type' => ['required', Rule::enum(BlockFieldType::class)],
        ];
    }
}
