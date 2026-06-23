<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\Directories\Enums\DirectoryImportMode;

final class UpsertDirectoryImportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'frequency' => ['required', Rule::in(['daily'])],
            'run_at' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone'],
            'mode' => ['required', Rule::in(DirectoryImportMode::values())],
            'add_new' => ['nullable', 'boolean'],
            'update_existing' => ['nullable', 'boolean'],
            'delete_unused' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array', 'min:1'],
            'mapping.*' => ['required', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'distinct'],
            'fields.*.name' => ['required', 'string'],
            'fields.*.rules' => ['sometimes', 'array'],
            'fields.*.rules.*' => ['string'],
            'match_by' => ['nullable', 'string'],
            'chunk_size' => ['nullable', 'integer', 'min:100', 'max:2000'],
            'remote' => ['required', 'array'],
            'remote.url' => ['required', 'url'],
            'remote.items_path' => ['nullable', 'string'],
            'remote.page_param' => ['nullable', 'string'],
            'remote.per_page_param' => ['nullable', 'string'],
            'remote.per_page' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'remote.per_page_path' => ['nullable', 'string'],
            'remote.start_page' => ['nullable', 'integer', 'min:1'],
            'remote.headers' => ['nullable', 'array'],
            'remote.headers.*' => ['nullable', 'string'],
            'remote.query' => ['nullable', 'array'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $fields = $this->input('fields');
                $fieldKeys = collect(is_array($fields) ? $fields : [])
                    ->pluck('key')
                    ->filter()
                    ->values();

                $mapping = $this->input('mapping');
                $mappingTargets = collect(is_array($mapping) ? $mapping : [])->values();
                $unknownTargets = $mappingTargets->diff($fieldKeys)->values();

                if ($unknownTargets->isNotEmpty()) {
                    $validator->errors()->add(
                        'mapping',
                        'Every mapping target must exist in fields.'
                    );
                }

                $matchBy = $this->input('match_by');
                $matchBy = is_string($matchBy) ? $matchBy : null;

                if ($matchBy !== null && ! $fieldKeys->contains($matchBy)) {
                    $validator->errors()->add(
                        'match_by',
                        'The match_by field must reference one of the declared fields.'
                    );
                }
            },
        ];
    }
}
