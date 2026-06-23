<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ContinueScenarioRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'input' => ['nullable', 'array'],
            'selected_target_node_id' => ['nullable', 'string'],
        ];
    }
}
