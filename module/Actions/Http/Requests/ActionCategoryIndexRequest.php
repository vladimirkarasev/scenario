<?php

declare(strict_types=1);

namespace Module\Actions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ActionCategoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'filter.parent_id' => ['nullable', 'string'],
        ];
    }

    public function hasParentFilter(): bool
    {
        return $this->has('filter.parent_id');
    }

    public function parentId(): ?string
    {
        $parentId = $this->input('filter.parent_id');

        return is_string($parentId) && $parentId !== '' && $parentId !== 'null'
            ? $parentId
            : null;
    }
}
