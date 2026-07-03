<?php

declare(strict_types=1);

namespace Module\Groups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Groups\Models\UserGroup;
use Module\Projects\CurrentProject;

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
        $projectId = $this->container->make(CurrentProject::class)->id();
        $presence = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'slug' => [
                $presence,
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('user_groups', 'slug')
                    ->where('site_id', $projectId)
                    ->ignore($group?->id),
            ],
            'ext_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('user_groups', 'ext_id')
                    ->where('site_id', $projectId)
                    ->ignore($group?->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
