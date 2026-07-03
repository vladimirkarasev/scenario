<?php

declare(strict_types=1);

namespace Module\Categories\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;

final class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');
        $projectId = $this->container->make(CurrentProject::class)->id();

        return [
            'parent_id' => [
                'nullable',
                'uuid',
                'exists:categories,id',
                Rule::notIn([$category?->id]),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'required',
                'uuid',
                Rule::exists('user_groups', 'id')->where('site_id', $projectId),
            ],
            'inherit_to_descendants' => ['nullable', 'boolean'],
            'is_workspace' => ['nullable', 'boolean'],
        ];
    }
}
