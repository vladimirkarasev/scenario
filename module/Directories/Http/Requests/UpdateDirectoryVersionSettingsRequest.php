<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDirectoryVersionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source_type'                    => ['required', Rule::in(['manual', 'excel', 'api', 'external'])],
            'sync_options'                   => ['sometimes', 'array'],
            'sync_options.add_new'           => ['sometimes', 'boolean'],
            'sync_options.update_existing'   => ['sometimes', 'boolean'],
            'sync_options.delete_unused'     => ['sometimes', 'boolean'],
            'allow_other'                    => ['sometimes', 'boolean'],
            'other_label'                    => ['nullable', 'string', 'max:100'],
            'other_external_key'             => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\.]+$/'],
        ];
    }
}
