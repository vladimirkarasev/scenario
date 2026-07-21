<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Repositories\DirectoryItemRepository;

final readonly class DirectoryImportRowProcessor
{
    public function __construct(
        private DirectoryImport $directoryImportModel,
        private DirectoryItemRepository $items,
        private DirectoryImportPayloadNormalizer $normalizer,
    ) {
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{failed_rows: int, row_errors: array<int, string>, processed_keys: list<string>}
     *
     * @throws \Throwable
     */
    public function importRows(DirectoryImport $import, Collection $rows, int $baseRowNumber): array
    {
        /** @var Collection<string, string> $mapping */
        $mapping = collect($import->mapping_json);
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($import->fields_json)->keyBy('key');
        /** @var Collection<int, string> $mappedFieldKeys */
        $mappedFieldKeys = $mapping->values()->filter()->unique()->values();
        $failedRows = 0;
        $rowErrors = [];
        $processedKeys = [];

        foreach ($rows->values() as $index => $row) {
            $rowNumber = $baseRowNumber + $index;
            $prepared = $this->normalizer->prepareRow($this->normalizer->normalizeIncomingRow($row), $mapping, $fields);

            try {
                $externalKey = $this->validateAndPersistRow($prepared, $fields, $mappedFieldKeys, $rowNumber, $import);
                if ($externalKey !== null) {
                    $processedKeys[] = $externalKey;
                }
            } catch (ValidationException $exception) {
                $failedRows++;
                $rowErrors[] = collect($exception->errors())
                    ->flatten()
                    ->implode(' ');
            } catch (UniqueConstraintViolationException) {
            }
        }

        return [
            'failed_rows' => $failedRows,
            'row_errors' => $rowErrors,
            'processed_keys' => array_values(array_unique($processedKeys)),
        ];
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  Collection<int, string>  $mappedFieldKeys
     *
     * @throws \Throwable
     */
    private function validateAndPersistRow(
        array $prepared,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
        DirectoryImport $import,
    ): ?string {
        return $this->directoryImportModel->getConnection()->transaction(
            function () use ($prepared, $fields, $mappedFieldKeys, $rowNumber, $import): ?string {
                $this->validateRow($prepared, $fields, $mappedFieldKeys, $rowNumber, $import);

                return $this->persistRow($import, $prepared, $fields, $mappedFieldKeys, $rowNumber);
            },
        );
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  Collection<int, string>  $mappedFieldKeys
     */
    private function validateRow(
        array $prepared,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
        DirectoryImport $import,
    ): void {
        $rules = $fields
            ->mapWithKeys(static function (array $field, string $fieldKey) use ($mappedFieldKeys): array {
                $fieldRules = is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'];
                $baseRules = collect($fieldRules)
                    ->reject(static fn(mixed $rule): bool => $rule === 'required')
                    ->values()
                    ->all();

                if (!$mappedFieldKeys->contains($fieldKey)) {
                    return [$fieldKey => ['nullable', 'string']];
                }

                return [$fieldKey => $baseRules !== [] ? $baseRules : ['nullable', 'string']];
            })
            ->all();

        $validator = Validator::make($prepared, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                "row_{$rowNumber}" => $validator->errors()->all(),
            ]);
        }
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<string, array<string, mixed>>  $fields
     * @param  Collection<int, string>  $mappedFieldKeys
     */
    private function persistRow(
        DirectoryImport $import,
        array $prepared,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
    ): ?string {
        $searchableKeys = [];
        foreach ($fields as $key => $field) {
            if (($field['searchable'] ?? false) === true) {
                $searchableKeys[] = $key;
            }
        }

        $externalKey = $this->resolveExternalKey($import, $prepared, $mappedFieldKeys, $rowNumber);

        $item = $this->resolveItem($import, $prepared, $externalKey);

        if ($item === null) {
            return $externalKey;
        }

        $item->forceFill([
            'external_key' => $externalKey,
            'search_text' => $this->buildSearchText($prepared, $searchableKeys),
            'data_json' => $this->castRowData($prepared, $fields),
        ])->save();

        $import->increment('imported_rows');

        return $externalKey;
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<int, string>  $mappedFieldKeys
     */
    private function resolveExternalKey(
        DirectoryImport $import,
        array $prepared,
        Collection $mappedFieldKeys,
        int $rowNumber
    ): ?string {
        if ($import->match_by !== null) {
            return $prepared[$import->match_by] ?? null;
        }

        return $this->computeRowHash($prepared, $mappedFieldKeys, $rowNumber);
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<int, string>  $mappedFieldKeys
     */
    private function computeRowHash(array $prepared, Collection $mappedFieldKeys, int $rowNumber): string
    {
        $filtered = array_intersect_key($prepared, array_flip($mappedFieldKeys->all()));
        ksort($filtered);
        $parts = ["row={$rowNumber}"];
        foreach ($filtered as $key => $value) {
            $parts[] = $key.'='.($value ?? '');
        }

        return md5(implode('&', $parts));
    }

    /**
     * @param  array<string, string|null>  $prepared
     */
    private function resolveItem(DirectoryImport $import, array $prepared, ?string $externalKey): ?DirectoryItem
    {
        if ($import->directory_version_id === null) {
            throw new DirectoryImportException('Import version is not initialized.');
        }

        if ($externalKey !== null) {
            $existingItem = $this->items->findByExternalKey($import->directory_version_id, $externalKey);

            if ($existingItem !== null) {
                return $this->updatesExisting($import) ? $existingItem : null;
            }
        }

        if (!$this->addsNew($import)) {
            return null;
        }

        return $this->items->create([
            'directory_version_id' => $import->directory_version_id,
            'search_text' => null,
        ]);
    }

    private function addsNew(DirectoryImport $import): bool
    {
        return DirectoryImportOptions::fromImport($import)->addNew;
    }

    private function updatesExisting(DirectoryImport $import): bool
    {
        return DirectoryImportOptions::fromImport($import)->updateExisting;
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  array<int, string>  $searchableKeys
     */
    private function buildSearchText(array $prepared, array $searchableKeys = []): string
    {
        if ($searchableKeys !== []) {
            $prepared = array_intersect_key($prepared, array_fill_keys($searchableKeys, true));
        }

        return collect($prepared)
            ->filter(static fn(mixed $value): bool => is_scalar($value) && filled((string)$value))
            ->map(static fn(mixed $value): string => Str::lower(trim((string)$value)))
            ->implode(' ');
    }

    /**
     * @param  array<string, string|null>  $prepared
     * @param  Collection<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function castRowData(array $prepared, Collection $fields): array
    {
        return $fields
            ->mapWithKeys(static function (array $field, string $key) use ($prepared): array {
                $rawValue = $prepared[$key] ?? null;
                $value = $rawValue ?? (is_scalar($field['default'] ?? null) ? (string)$field['default'] : null);

                if ($value === null || $value === '') {
                    return [$key => $value];
                }

                return [
                    $key => match ($field['type'] ?? 'string') {
                        'integer' => is_numeric($value) ? (int)$value : 0,
                        'float' => is_numeric($value) ? (float)$value : 0.0,
                        'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool)$value,
                        'json' => json_decode($value, true),
                        default => $value,
                    },
                ];
            })
            ->all();
    }
}
