<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;

final class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $projectId = $this->container->make(CurrentProject::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'fio' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->where('project_id', $projectId),
            ],
            'login' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'login')->where('project_id', $projectId),
            ],
            'external_id' => [
                'nullable', 'string', 'max:255',
                Rule::unique('users', 'external_id')->where('project_id', $projectId),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'string',
                'uuid',
                Rule::exists('user_groups', 'id')->where('site_id', $projectId),
            ],
        ];
    }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fio' => $this->filled('fio') ? $this->input('fio') : null,
            'external_id' => $this->filled('external_id') ? $this->input('external_id') : null,
        ]);
    }
}
