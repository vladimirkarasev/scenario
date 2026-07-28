<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;

final class StoreDirectoryImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source_type' => ['required', Rule::in([DirectoryImportSourceType::File->value])],
            'file' => ['nullable', 'file', 'mimes:xlsx,csv,ods,xls'],
            'files' => ['nullable', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:xlsx,csv,ods,xls'],
            'mode' => ['required', Rule::in(DirectoryImportMode::values())],
            'add_new' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'delete_unused' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['required', 'string'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.key' => ['required', 'string', 'distinct'],
            'columns.*.name' => ['required', 'string'],
            'columns.*.type' => ['nullable', 'string'],
            'columns.*.nullable' => ['nullable', 'boolean'],
            'columns.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'columns.*.rules' => ['sometimes', 'array'],
            'columns.*.rules.*' => ['string'],
            'match_by' => ['nullable', 'string'],
            'parent_key_field' => ['nullable', 'string'],
            'version_id' => ['nullable', 'integer', 'exists:directory_versions,id'],
            'chunk_size' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'activate' => ['nullable', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $fields = $this->input('columns');
                $fieldKeys = collect(is_array($fields) ? $fields : [])
                    ->pluck('key')
                    ->filter()
                    ->values();

                $sourceType = $this->input('source_type');

                if (
                    $sourceType === DirectoryImportSourceType::File->value
                    && !$this->hasFile('file')
                    && !$this->hasFile('files')
                ) {
                    $validator->errors()->add('files', 'Нужен хотя бы один файл для импорта из Excel.');
                }

                $mapping = $this->input('mapping');
                $mappingTargets = collect(is_array($mapping) ? $mapping : [])->values();

                $unknownTargets = $mappingTargets
                    ->diff($fieldKeys)
                    ->values();

                if ($unknownTargets->isNotEmpty()) {
                    $validator->errors()->add(
                        'mapping',
                        'Every mapping target must exist in fields.'
                    );
                }

                $matchBy = $this->input('match_by');
                $matchBy = is_string($matchBy) ? $matchBy : null;

                if ($matchBy !== null && !$fieldKeys->contains($matchBy)) {
                    $validator->errors()->add(
                        'match_by',
                        'The match_by field must reference one of the declared fields.'
                    );
                }

                $updatesExisting = $this->boolean('update_existing')
                    || $this->input('mode') === DirectoryImportMode::Update->value
                    || $this->input('mode') === DirectoryImportMode::Replace->value;
                $deletesUnused = $this->boolean('delete_unused')
                    || $this->input('mode') === DirectoryImportMode::Replace->value;

                if (($updatesExisting || $deletesUnused)
                    && $matchBy === null
                    && $sourceType !== DirectoryImportSourceType::File->value
                ) {
                    $validator->errors()->add(
                        'match_by',
                        'The match_by field is required for update mode.'
                    );
                }

                $parentKeyField = $this->input('parent_key_field');
                $parentKeyField = is_string($parentKeyField) ? $parentKeyField : null;

                if ($parentKeyField !== null && !$fieldKeys->contains($parentKeyField)) {
                    $validator->errors()->add(
                        'parent_key_field',
                        'The parent_key_field must reference one of the declared fields.'
                    );
                }
            },
        ];
    }
}
