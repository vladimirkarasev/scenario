<?php

declare(strict_types=1);

namespace Module\Actions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Actions\Enums\ActionCredentialType;

final class ActionCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ActionCredentialType::values())],
            'config' => ['nullable', 'array'],
            'secrets' => ['nullable', 'array'],
        ];
    }
}
