<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Proxy\Services\CredentialCatalog;

final class ProxyConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'credential_type' => ['required', 'string', Rule::in(CredentialCatalog::all())],
            'values' => ['nullable', 'array'],
        ];
    }
}
