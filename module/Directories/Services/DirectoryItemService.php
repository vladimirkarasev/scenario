<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryItemUpdateData;
use Module\Directories\DTO\DirectoryManualItemData;
use Module\Directories\Exceptions\DirectoryItemException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryRepository;

final class DirectoryItemService
{
    /** Sentinel id of the synthetic "Other" item (never persisted; negative to avoid colliding with real ids). */
    public const OTHER_ITEM_ID = -1;

    /** Default external_key used for the "Other" option when the version does not configure its own. */
    public const OTHER_EXTERNAL_KEY = '__other__';

    private const DEFAULT_OTHER_LABEL = 'Другой';

    public function __construct(
        private readonly DirectoryItemRepository $items,
        private readonly DirectoryRepository $directories,
        private readonly Container $container,
    ) {
    }

    /**
     * @param  array<string, string|list<string>>  $filters
     * @param  array<string, string>  $filtersTo
     * @return array<int, array<string, mixed>>
     */
    public function items(
        Directory $directory,
        ?string $versionId = null,
        array $filters = [],
        array $filtersTo = [],
        ?string $search = null,
        ?string $sortKey = null,
        string $sortDir = 'asc',
        bool $withOther = false,
    ): array {
        if ($versionId !== null && ctype_digit($versionId)) {
            $version = $this->directories->findVersion($directory, $versionId);
        } else {
            $version = $this->resolveViewVersion($directory);
        }

        if ($version === null) {
            return [];
        }

        $items = $this->payloadItems($version, $filters, $filtersTo, $search, $sortKey, $sortDir);

        // "Другой" is appended last as a synthetic option (opt-in via $withOther so the admin grid stays clean).
        if ($withOther) {
            $other = $this->otherItem($version);
            if ($other !== null) {
                $items[] = $other;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    /** @return array<string, mixed> */
    public function create(Directory $directory, DirectoryManualItemData $data): array
    {
        $version = $this->resolveEditableVersion($directory);
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($version->schema_json)->keyBy('key');
        $values = $this->normalizeValues($data->data, $fields);

        $this->validateValues($values, $fields, $version, $data->matchBy);

        $searchableKeys = $this->searchableKeys($fields);

        $item = $directory->getConnection()->transaction(
            function () use ($version, $values, $data, $searchableKeys): DirectoryItem {
                return $this->items->create([
                    'directory_version_id' => $version->id,
                    'parent_id' => $data->parentId,
                    'external_key' => $data->matchBy !== null ? ($values[$data->matchBy] ?? null) : null,
                    'search_text' => $this->buildSearchText($values, $searchableKeys),
                    'data_json' => $values,
                ]);
            },
        );

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payloadItem($item);
    }

    /** @return array<string, mixed> */
    public function update(Directory $directory, DirectoryItem $item, DirectoryItemUpdateData $data): array
    {
        $this->ensureItemBelongsToDirectory($directory, $item);

        $version = $item->version()->firstOrFail();
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($version->schema_json)->keyBy('key');
        $values = $this->normalizeValues($data->data, $fields);

        $this->validateValues($values, $fields, $version, $data->matchBy, $item);

        $externalKey = $data->externalKeyProvided
            ? $data->externalKey
            : ($data->matchBy !== null ? ($values[$data->matchBy] ?? null) : null);

        $this->items->update($item, [
            'parent_id' => $data->parentId,
            'external_key' => $externalKey,
            'search_text' => $this->buildSearchText($values, $this->searchableKeys($fields)),
            'data_json' => $values,
        ]);

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payloadItem($item->fresh() ?? $item);
    }

    public function delete(Directory $directory, DirectoryItem $item): void
    {
        $this->ensureItemBelongsToDirectory($directory, $item);

        $this->items->delete($item);

        DirectoryCache::forgetDirectory($directory->id);
    }

    /** @param  array<int, int>  $ids */
    public function bulkDelete(Directory $directory, array $ids): void
    {
        $this->items->bulkDeleteForDirectory($directory->id, $ids);

        DirectoryCache::forgetDirectory($directory->id);
    }

    /** @return array<string, mixed> */
    public function payloadItem(DirectoryItem $item): array
    {
        return [
            'id' => $item->id,
            'directory_version_id' => $item->directory_version_id,
            'parent_id' => $item->parent_id,
            'external_key' => $item->external_key,
            'search_text' => $item->search_text,
            'data' => $item->data_json ?? [],
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Rebuild search_text for every item belonging to this version.
     * Called after the schema changes so the indexed content reflects the new searchable fields.
     */
    public function rebuildSearchTextForVersion(DirectoryVersion $version): void
    {
        $searchableKeys = [];
        foreach ($version->schema_json as $f) {
            if (($f['searchable'] ?? false) === true && isset($f['key']) && is_string($f['key'])) {
                $searchableKeys[] = $f['key'];
            }
        }

        DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->each(function (DirectoryItem $item) use ($searchableKeys): void {
                $item->search_text = $this->buildSearchText($item->data_json ?? [], $searchableKeys);
                $item->save();
            });
    }

    /**
     * Synthetic "Other" item built from the version settings. Not persisted: it carries a sentinel id and a
     * stable external_key so downstream consumers (Actions/CRM mapping) can detect the fallback deterministically.
     *
     * @return array<string, mixed>|null
     */
    private function otherItem(DirectoryVersion $version): ?array
    {
        if ($version->allow_other !== true) {
            return null;
        }

        $label = is_string($version->other_label) && $version->other_label !== ''
            ? $version->other_label
            : self::DEFAULT_OTHER_LABEL;

        $externalKey = is_string($version->other_external_key) && $version->other_external_key !== ''
            ? $version->other_external_key
            : self::OTHER_EXTERNAL_KEY;

        // «Другой» — синтетический вариант без привязки к колонкам схемы. Фронт рендерит его
        // одной ячейкой (colspan) и берёт текст из label, поэтому колонки заполнять не нужно.
        $data = ['label' => $label];

        return [
            'id' => self::OTHER_ITEM_ID,
            'directory_version_id' => $version->id,
            'parent_id' => null,
            'external_key' => $externalKey,
            'search_text' => $label,
            'data' => $data,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @param  array<string, string|list<string>>  $filters  field_key => value(s) from
     * @param  array<string, string>  $filtersTo  field_key => value to (for 'between' operator)
     * @return array<int, array<string, mixed>>
     */
    private function payloadItems(
        DirectoryVersion $version,
        array $filters = [],
        array $filtersTo = [],
        ?string $search = null,
        ?string $sortKey = null,
        string $sortDir = 'asc',
    ): array {
        $typedFilters = [];
        if ($filters !== []) {
            /** @var array<string, array<string, mixed>> $schemaByKey */
            $schemaByKey = collect($version->schema_json)->keyBy('key')->toArray();
            foreach ($filters as $key => $value) {
                $schema = $schemaByKey[$key] ?? [];
                $type = isset($schema['type']) && is_string($schema['type']) ? $schema['type'] : 'string';
                $filterType = isset($schema['filter_type']) && is_string(
                    $schema['filter_type'],
                ) ? $schema['filter_type'] : $type;
                $operator = isset($schema['filter_operator']) && is_string(
                    $schema['filter_operator'],
                ) ? $schema['filter_operator'] : ($type === 'string' ? 'contains' : 'equals');

                if (is_array($value)) {
                    $typedFilters[$key] = [
                        'type' => 'list_multi',
                        'values' => $value,
                    ];
                } else {
                    $typedFilters[$key] = [
                        'type' => $filterType,
                        'operator' => $operator,
                        'value' => $value,
                        'value_to' => $filtersTo[$key] ?? null,
                    ];
                }
            }
        }

        $items = $this->items->latestForVersion($version, $typedFilters, $search, $sortKey, $sortDir);

        if ($items->isNotEmpty() && $typedFilters !== []) {
            $items = $this->withAncestors($version, $items);
        }

        return $items
            ->map(fn(DirectoryItem $item): array => $this->payloadItem($item))
            ->values()
            ->all();
    }

    /**
     * Expand a filtered collection to also include all ancestor items,
     * so the frontend can still render the tree correctly.
     *
     * @param  Collection<int, DirectoryItem>  $matched
     * @return Collection<int, DirectoryItem>
     */
    private function withAncestors(DirectoryVersion $version, Collection $matched): Collection
    {
        $included = $matched->keyBy('id');
        $pendingParentIds = $matched->pluck('parent_id')->filter()->unique()->values()->all();

        while ($pendingParentIds !== []) {
            $missing = array_values(array_filter($pendingParentIds, fn(mixed $id): bool => !isset($included[$id])));
            if ($missing === []) {
                break;
            }

            $ancestors = DirectoryItem::query()
                ->where('directory_version_id', $version->id)
                ->whereIn('id', $missing)
                ->get()
                ->keyBy('id');

            $included = $included->merge($ancestors);
            $pendingParentIds = $ancestors->pluck('parent_id')->filter()->unique()->values()->all();
        }

        return $included->values();
    }

    private function resolveViewVersion(Directory $directory): ?DirectoryVersion
    {
        return $this->directories->activeVersion($directory)
            ?? $this->directories->firstVersion($directory);
    }

    private function resolveEditableVersion(Directory $directory): DirectoryVersion
    {
        return $this->container->make(DirectoryService::class)->resolveEditableVersion($directory);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
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

            if (is_scalar($value)) {
                $trimmed = trim((string)$value);
                $normalized[$fieldKey] = $trimmed !== '' ? $trimmed : null;
            } else {
                $normalized[$fieldKey] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<string, array<string, mixed>>  $fields
     */
    private function validateValues(
        array $values,
        Collection $fields,
        DirectoryVersion $version,
        ?string $matchBy,
        ?DirectoryItem $ignoreItem = null,
    ): void {
        $rules = $fields
            ->mapWithKeys(static fn(array $field, string $fieldKey): array => [
                $fieldKey => is_array($field['rules'] ?? null) ? $field['rules'] : ['nullable', 'string'],
            ])
            ->all();

        $validator = Validator::make($values, $rules);

        if ($matchBy !== null) {
            $validator->after(
                function (\Illuminate\Validation\Validator $validator) use (
                    $values,
                    $version,
                    $matchBy,
                    $ignoreItem,
                ): void {
                    $rawKey = $values[$matchBy] ?? null;
                    $externalKey = is_string($rawKey) ? $rawKey : null;

                    if ($externalKey === null) {
                        return;
                    }

                    if ($this->items->externalKeyExists($version, $externalKey, $ignoreItem)) {
                        $validator->errors()->add(
                            $matchBy,
                            'Item with this match key already exists in current version.',
                        );
                    }
                },
            );
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function searchableKeys(Collection $fields): array
    {
        $keys = [];
        foreach ($fields as $key => $field) {
            if (($field['searchable'] ?? false) === true) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $searchableKeys
     */
    private function buildSearchText(array $values, array $searchableKeys = []): string
    {
        if ($searchableKeys !== []) {
            $values = array_intersect_key($values, array_fill_keys($searchableKeys, true));
        }

        return collect($values)
            ->filter(static fn(mixed $value): bool => is_scalar($value) && filled((string)$value))
            ->map(static fn(mixed $value): string => Str::lower(trim((string)$value)))
            ->implode(' ');
    }

    private function ensureItemBelongsToDirectory(Directory $directory, DirectoryItem $item): void
    {
        if (!$this->items->belongsToDirectory($item, $directory->id)) {
            throw DirectoryItemException::notBelongsToDirectory();
        }
    }
}
