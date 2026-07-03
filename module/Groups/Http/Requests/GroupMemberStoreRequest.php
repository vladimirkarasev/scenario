<?php

declare(strict_types=1);

namespace Module\Groups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;

final class GroupMemberStoreRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('project_id', $projectId),
            ],
        ];
    }
}
