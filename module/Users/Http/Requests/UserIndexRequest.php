<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;

final class UserIndexRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $projectId = $this->container->make(CurrentProject::class)->id();

        return [
            'filter' => ['sometimes', 'array'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.group_ids' => ['sometimes', 'array'],
            'filter.group_ids.*' => [
                'string',
                'uuid',
                Rule::exists('user_groups', 'id')->where('site_id', $projectId),
            ],
            'filter.role_ids' => ['sometimes', 'array'],
            'filter.role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('guard_name', 'web'),
            ],
            'page.size' => ['sometimes', 'integer', 'between:1,100'],
            'page.number' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
