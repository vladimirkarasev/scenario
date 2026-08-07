<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Repositories\DirectoryItemRepository;

final readonly class DirectoryImportRowWriter
{
    public function __construct(private DirectoryItemRepository $items)
    {
    }

    /**
     * @param array<string, string|null> $row
     * @param Collection<string, array<string, mixed>> $fields
     * @param Collection<int, string> $mappedFieldKeys
     * @return array{externalKey: ?string, written: bool, created: bool}
     */
    public function write(
        DirectoryImport $import,
        array $row,
        Collection $fields,
        Collection $mappedFieldKeys,
        int $rowNumber,
    ): array {
        $externalKey = $this->externalKey($import, $row, $mappedFieldKeys, $rowNumber);
        $item = $this->resolveItem($import, $externalKey);

        if ($item === null) {
            return ['externalKey' => $externalKey, 'written' => false, 'created' => false];
        }

        $created = $item->wasRecentlyCreated;

        $item->forceFill([
            'external_key' => $externalKey,
            'search_text' => $this->searchText($row, $this->searchableKeys($fields)),
            'data_json' => $this->castValues($row, $fields),
        ])->save();

        $import->increment('imported_rows');

        return ['externalKey' => $externalKey, 'written' => true, 'created' => $created];
    }

    /**
     * @param array<string, string|null> $row
     * @param Collection<int, string> $mappedFieldKeys
     */
    public function externalKey(
        DirectoryImport $import,
        array $row,
        Collection $mappedFieldKeys,
        int $rowNumber,
    ): ?string {
        if ($import->external_key_field !== null) {
            return $row[DirectoryImportPayloadNormalizer::EXTERNAL_KEY] ?? null;
        }

        if ($import->match_by !== null) {
            return $row[$import->match_by] ?? null;
        }

        return $this->rowHash($row, $mappedFieldKeys, $rowNumber);
    }

    /**
     * @param array<string, string|null> $row
     * @param Collection<int, string> $mappedFieldKeys
     */
    public function rowHash(array $row, Collection $mappedFieldKeys, int $rowNumber): string
    {
        $filtered = array_intersect_key($row, array_flip($mappedFieldKeys->all()));
        ksort($filtered);

        $parts = ["row={$rowNumber}"];
        foreach ($filtered as $key => $value) {
            $parts[] = $key.'='.($value ?? '');
        }

        return md5(implode('&', $parts));
    }

    private function resolveItem(DirectoryImport $import, ?string $externalKey): ?DirectoryItem
    {
        if ($import->directory_version_id === null) {
            throw new DirectoryImportException('Import version is not initialized.');
        }

        if ($externalKey !== null) {
            $existingItem = $this->items->findByExternalKey($import->directory_version_id, $externalKey);

            if ($existingItem !== null) {
                return DirectoryImportOptions::fromImport($import)->updateExisting ? $existingItem : null;
            }
        }

        if (!DirectoryImportOptions::fromImport($import)->addNew) {
            return null;
        }

        return $this->items->create([
            'directory_version_id' => $import->directory_version_id,
            'search_text' => null,
        ]);
    }

    /**
     * @param Collection<string, array<string, mixed>> $fields
     * @return list<string>
     */
    private function searchableKeys(Collection $fields): array
    {
        return $fields
            ->filter(static fn(array $field): bool => ($field['searchable'] ?? false) === true)
            ->keys()
            ->values()
            ->all()
            |> array_values(...);
    }

    /**
     * @param array<string, string|null> $row
     * @param list<string> $searchableKeys
     */
    private function searchText(array $row, array $searchableKeys): string
    {
        if ($searchableKeys !== []) {
            $row = array_intersect_key($row, array_fill_keys($searchableKeys, true));
        }

        return collect($row)
            ->filter(static fn(mixed $value): bool => is_scalar($value) && filled((string)$value))
            ->map(static fn(mixed $value): string => Str::lower(trim((string)$value)))
            ->implode(' ');
    }

    /**
     * @param array<string, string|null> $row
     * @param Collection<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private function castValues(array $row, Collection $fields): array
    {
        return $fields
            ->mapWithKeys(static function (array $field, string $key) use ($row): array {
                $rawValue = $row[$key] ?? null;
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
