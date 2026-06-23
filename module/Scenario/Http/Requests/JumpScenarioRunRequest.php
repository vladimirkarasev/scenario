<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class JumpScenarioRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'string'],
        ];
    }
}
