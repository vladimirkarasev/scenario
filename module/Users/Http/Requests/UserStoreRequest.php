<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UserStoreRequest extends FormRequest
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
            'fio' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'login' => ['nullable', 'string', 'max:255', 'unique:users,login'],
            'external_id' => ['nullable', 'string', 'max:255', 'unique:users,external_id'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['string', 'exists:user_groups,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'login' => $this->filled('login') ? $this->input('login') : null,
            'external_id' => $this->filled('external_id') ? $this->input('external_id') : null,
        ]);
    }
}
