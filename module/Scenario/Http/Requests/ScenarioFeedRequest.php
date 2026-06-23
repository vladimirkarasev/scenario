<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ScenarioFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'filter.parent_id' => ['nullable', 'string'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.status' => ['nullable', 'string', 'in:active,draft,archived'],
            'filter.exclude_scenario_id' => ['nullable', 'uuid'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
