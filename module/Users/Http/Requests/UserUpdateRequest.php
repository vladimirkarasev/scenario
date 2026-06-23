<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'fio' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'login' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'login')->ignore($user->id),
            ],
            'external_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'external_id')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
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
