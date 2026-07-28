<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Collection;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryItemUpdateData;
use Module\Directories\DTO\DirectoryItemQuery;
use Module\Directories\DTO\DirectoryManualItemData;
use Module\Directories\Exceptions\DirectoryItemException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryRepository;
use Module\Directories\Services\Items\DirectoryItemValues;

final readonly class DirectoryItemService
{
    public const int OTHER_ITEM_ID = -1;

    public const string OTHER_EXTERNAL_KEY = '__other__';

    private const string DEFAULT_OTHER_LABEL = 'Другой';

    public function __construct(
        private DirectoryItemRepository $items,
        private DirectoryRepository $directories,
        private RelatedDirectoryFieldResolver $relatedFieldResolver,
        private DirectoryItemValues $values,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(Directory $directory, ?DirectoryItemQuery $query = null): array
    {
        $query ??= new DirectoryItemQuery();

        if ($query->versionId !== null && ctype_digit($query->versionId)) {
            $version = $this->directories->findVersion($directory, $query->versionId);
        } else {
            $version = $this->resolveViewVersion($directory);
        }

        if ($version === null) {
            return [];
        }

        $items = $this->payloadItems(
            $version,
            $query->filters,
            $query->filtersTo,
            $query->search,
            $query->sortKey,
            $query->sortDirection,
        );

        if ($query->withOther) {
            $other = $this->otherItem($version);
            if ($other !== null) {
                $items[] = $other;
            }
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public function create(Directory $directory, DirectoryManualItemData $data): array
    {
        $version = $this->resolveEditableVersion($directory);
        $matchBy = $data->matchBy ?? $directory->match_by;
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($version->schema_json)->keyBy('key');
        $values = $this->values->normalize($data->data, $fields);

        $this->values->validate($values, $fields, $version, $matchBy);

        $item = $directory->getConnection()->transaction(
            fn(): DirectoryItem => $this->items->create([
                'directory_version_id' => $version->id,
                'parent_id' => $data->parentId,
                'external_key' => $data->externalKey ?? ($matchBy !== null ? ($values[$matchBy] ?? null) : null),
                'search_text' => $this->values->searchText($values, $fields),
                'data_json' => $values,
            ]),
        );

        DirectoryCache::forgetDirectory($directory->id);

        return $this->withRelated([$this->payloadItem($item)], $version)[0];
    }

    /** @return array<string, mixed> */
    public function update(Directory $directory, DirectoryItem $item, DirectoryItemUpdateData $data): array
    {
        $this->ensureItemBelongsToDirectory($directory, $item);

        $version = $item->version()->firstOrFail();
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($version->schema_json)->keyBy('key');
        $values = $this->values->normalize($data->data, $fields);

        $this->values->validate($values, $fields, $version, $data->matchBy, $item);

        $externalKey = $data->externalKeyProvided
            ? $data->externalKey
            : ($data->matchBy !== null ? ($values[$data->matchBy] ?? null) : null);

        $this->items->update($item, [
            'parent_id' => $data->parentId,
            'external_key' => $externalKey,
            'search_text' => $this->values->searchText($values, $fields),
            'data_json' => $values,
        ]);

        DirectoryCache::forgetDirectory($directory->id);

        return $this->withRelated([$this->payloadItem($item->fresh() ?? $item)], $version)[0];
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

    public function rebuildSearchTextForVersion(DirectoryVersion $version): void
    {
        /** @var Collection<string, array<string, mixed>> $fields */
        $fields = collect($version->schema_json)->keyBy('key');

        DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->each(function (DirectoryItem $item) use ($fields): void {
                $item->search_text = $this->values->searchText($item->data_json ?? [], $fields);
                $item->save();
            });
    }

    /**
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

        $payloads = $items
            ->map(fn(DirectoryItem $item): array => $this->payloadItem($item))
            ->values()
            ->all();

        return $this->withRelated($payloads, $version);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function withRelated(array $items, DirectoryVersion $version): array
    {
        $flatRows = array_map($this->flattenPayloadItem(...), $items);

        $related = $this->relatedFieldResolver->resolve($flatRows, $version->schema_json);

        foreach ($items as $index => $item) {
            $items[$index]['related'] = $related[$index] ?? [];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function flattenPayloadItem(array $item): array
    {
        $row = [
            'id' => $item['id'],
            'external_key' => $item['external_key'],
        ];

        $data = $item['data'] ?? [];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }
        }

        return $row;
    }

    /**
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
        return $this->directories->resolveEditableVersion($directory);
    }

    private function ensureItemBelongsToDirectory(Directory $directory, DirectoryItem $item): void
    {
        if (!$this->items->belongsToDirectory($item, $directory->id)) {
            throw DirectoryItemException::notBelongsToDirectory();
        }
    }
}
