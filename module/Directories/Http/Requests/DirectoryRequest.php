<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Models\Directory;
use Module\Projects\Models\Project;

final class DirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Directory|null $directory */
        $directory = $this->route('directory');
        $projectId = $directory !== null ? $directory->project_id : $this->currentProjectId();

        return [
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['string', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('directories', 'slug')
                    ->where(fn (Builder $query) => $query->where('project_id', $projectId))
                    ->ignore($directory?->id),
            ],
            'description' => ['nullable', 'string'],
            'source_type' => ['nullable', Rule::in(['manual', 'excel', 'api', 'external'])],
            'match_by' => ['nullable', 'string'],
            'api_config' => ['nullable', 'array'],
            'api_config.endpoint' => ['nullable', 'url'],
            'api_config.method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH'])],
            'api_config.headers' => ['nullable', 'array'],
            'api_config.auth_type' => ['nullable', Rule::in(['none', 'bearer', 'basic', 'api_key'])],
            'api_config.auth' => ['nullable', 'array'],
            'api_config.query' => ['nullable', 'array'],
            'api_config.body' => ['nullable', 'array'],
            'api_config.response_mapping' => ['nullable', 'array'],
            'api_config.refresh_interval' => ['nullable', 'integer', 'min:60'],
            'fields' => $this->isMethod('post') ? ['sometimes', 'array'] : ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'distinct'],
            'fields.*.name' => ['required', 'string'],
            'fields.*.type' => ['nullable', Rule::in(['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json'])],
            'fields.*.nullable' => ['nullable', 'boolean'],
            'fields.*.default' => ['nullable'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'fields.*.rules' => ['sometimes', 'array'],
            'fields.*.rules.*' => ['string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->str('slug')->toString() ?: $this->str('name')->toString()),
        ]);
    }

    private function currentProjectId(): string
    {
        $user = $this->user();

        /** @var string|null $projectId */
        $projectId = Project::query()
            ->where('sitekey', $user?->sitekey)
            ->where('host', $user?->host)
            ->where('is_active', true)
            ->value('id');

        if ($projectId === null) {
            throw DirectoryException::projectNotFound();
        }

        return $projectId;
    }
}
