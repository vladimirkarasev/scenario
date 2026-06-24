<?php

declare(strict_types=1);

namespace Module\Projects\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\Models\Project;

final class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sitekey' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects')
                    ->where(fn(Builder $query) => $query->where('host', $this->input('host')))
                    ->ignore($project?->id),
            ],
            'host' => ['required', 'string', 'max:255'],
            'shared_secret' => ['required', 'string', 'min:32', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    #[\Override]
    public function attributes(): array
    {
        return [
            'sitekey' => 'sitekey',
            'shared_secret' => 'shared secret',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'sitekey.unique' => 'Project with this sitekey and host already exists.',
        ];
    }
}
