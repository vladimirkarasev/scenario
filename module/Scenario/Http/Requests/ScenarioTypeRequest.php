<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Scenario\Enums\ScenarioType;

final class ScenarioTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ScenarioType::class)],
        ];
    }

    public function scenarioType(): ScenarioType
    {
        return ScenarioType::from($this->string('type')->toString());
    }
}
