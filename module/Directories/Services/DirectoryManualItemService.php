<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryManualItemData;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryRepository;

final class DirectoryManualItemService
{
    public function __construct(
        private readonly DirectoryItemRepository $items,
        private readonly DirectoryRepository $directories,
    ) {}

    /** @return array<string, mixed> */
    public function create(Directory $directory, DirectoryManualItemData $data): array
    {
        $matchBy = $data->matchBy ?? $directory->match_by;

        $version = $this->resolveEditableVersion($directory);
        $fields = collect($version->schema_json)->keyBy('key');
        $values = $this->normalizeValues($data->data, $fields);

        $this->validateValues($values, $fields, $version, $matchBy);

        $searchableKeys = [];
        foreach ($fields as $key => $field) {
            if (($field['searchable'] ?? false) === true && is_string($key)) {
                $searchableKeys[] = $key;
            }
        }

        $externalKey = $data->externalKey ?? ($matchBy !== null ? ($values[$matchBy] ?? null) : null);

        $item = $directory->getConnection()->transaction(function () use ($version, $values, $data, $externalKey, $searchableKeys): DirectoryItem {
            return $this->items->create([
                'directory_version_id' => $version->id,
                'parent_id' => $data->parentId,
                'external_key' => $externalKey,
                'search_text' => $this->buildSearchText($values, $searchableKeys),
                'data_json' => $values,
            ]);
        });

        DirectoryCache::forgetDirectory($directory->id);

        return [
            'id' => $item->id,
            'directory_version_id' => $item->directory_version_id,
            'parent_id' => $item->parent_id,
            'external_key' => $item->external_key,
            'search_text' => $item->search_text,
            'data' => $item->data_json ?? [],
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    private function resolveEditableVersion(Directory $directory): DirectoryVersion
    {
        $version = $this->directories->activeVersion($directory)
            ?? $this->directories->firstVersion($directory);

        if ($version === null) {
            throw ValidationException::withMessages([
                'directory' => ['Создайте хотя бы одну версию справочника перед добавлением элементов.'],
            ]);
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>                     $values
     * @param  Collection<string, array<string, mixed>> $fields
     * @return array<string, string|null>
     */
    private function normalizeValues(array $values, Collection $fields): array
    {
        $normalized = [];

        foreach ($fields as $fieldKey => $field) {
            $value = $values[$fieldKey] ?? null;

            if ($value === null) {
                $normalized[$fieldKey] = null;

                continue;
            }

            $normalized[$fieldKey] = is_scalar($value)
                ? trim((string) $value) ?: null
                : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null);
        }

        return $normalized;
    }

    /**
     * @param array<string, string|null>               $values
     * @param Collection<string, array<string, mixed>> $fields
     */
    private function validateValues(array $values, Collection $fields, DirectoryVersion $version, ?string $matchBy): void
    {
        /** @var array<string, array<int, string>> $rules */
        $rules = $fields
            ->mapWithKeys(static fn (array $field, string $fieldKey): array => [
                $fieldKey => $field['rules'] ?? ['nullable', 'string'],
            ])
            ->all();

        $validator = Validator::make($values, $rules);

        if ($matchBy !== null) {
            $validator->after(function (\Illuminate\Validation\Validator $validator) use ($values, $version, $matchBy): void {
                $externalKey = $values[$matchBy] ?? null;

                if ($externalKey === null) {
                    return;
                }

                if ($this->items->externalKeyExists($version, $externalKey)) {
                    $validator->errors()->add($matchBy, 'Элемент с таким ключом уже существует в этой версии.');
                }
            });
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @param array<string, string|null> $values
     * @param array<int, string>         $searchableKeys
     */
    private function buildSearchText(array $values, array $searchableKeys = []): string
    {
        if ($searchableKeys !== []) {
            $values = array_intersect_key($values, array_fill_keys($searchableKeys, true));
        }

        return collect($values)
            ->filter(static fn (mixed $value): bool => is_scalar($value) && filled((string) $value))
            ->map(static fn (mixed $value): string => Str::lower(trim((string) $value)))
            ->implode(' ');
    }
}
