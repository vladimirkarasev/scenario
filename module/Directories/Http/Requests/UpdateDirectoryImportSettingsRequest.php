<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDirectoryImportSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode'          => ['nullable', 'string', 'in:create,update,replace'],
            'chunk_size'    => ['nullable', 'integer', 'min:1', 'max:10000'],
            'match_by'      => ['nullable', 'string', 'max:255'],
            'fields_text'   => ['nullable', 'string'],
            'mapping_text'  => ['nullable', 'string'],
            'add_new'       => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'delete_unused' => ['nullable', 'boolean'],
        ];
    }
}
