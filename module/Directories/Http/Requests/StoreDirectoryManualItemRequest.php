<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDirectoryManualItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
            'data.*' => ['nullable'],
            'match_by' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:directory_items,id'],
        ];
    }
}
