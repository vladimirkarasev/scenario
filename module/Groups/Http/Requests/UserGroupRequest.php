<?php

declare(strict_types=1);

namespace Module\Groups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Groups\Models\UserGroup;

final class UserGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var UserGroup|null $group */
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('user_groups', 'slug')->ignore($group?->id),
            ],
            'ext_id' => ['nullable', 'string', 'max:255',
                Rule::unique('user_groups', 'ext_id')->ignore($group?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
