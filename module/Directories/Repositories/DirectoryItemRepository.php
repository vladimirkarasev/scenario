<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;

final class DirectoryItemRepository
{
    /**
     * @param  array<string, array<string, mixed>> $filters
     * @return Collection<int, DirectoryItem>
     */
    public function latestForVersion(
        DirectoryVersion $version,
        array $filters = [],
        ?string $search = null,
        ?string $sortKey = null,
        string $sortDir = 'asc',
    ): Collection {
        $query = DirectoryItem::query()
            ->where('directory_version_id', $version->id);

        if ($search !== null && $search !== '') {
            $query->whereLike('search_text', '%'.$search.'%');
        }

        foreach ($filters as $key => $filter) {
            if (! preg_match('/^[a-z0-9_]+$/i', $key)) {
                continue;
            }

            $this->applyFilter($query, $key, $filter);
        }

        $dir = $sortDir === 'desc' ? 'desc' : 'asc';
        if ($sortKey !== null && preg_match('/^[a-z0-9_-]+$/i', $sortKey)) {
            $query->orderByRaw("data_json->>? {$dir}", [$sortKey]);
        } else {
            $query->latest();
        }

        return $query->get();
    }

    /** @return Collection<int, DirectoryItem> */
    public function sampleForVersion(DirectoryVersion $version, int $limit = 10): Collection
    {
        return $version->items()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DirectoryItem
    {
        return DirectoryItem::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(DirectoryItem $item, array $attributes): DirectoryItem
    {
        $item->fill($attributes);
        $item->save();

        return $item;
    }

    public function delete(DirectoryItem $item): void
    {
        $item->delete();
    }

    public function externalKeyExists(DirectoryVersion $version, string $externalKey, ?DirectoryItem $ignoreItem = null): bool
    {
        $query = DirectoryItem::query()
            ->where('directory_version_id', $version->id)
            ->where('external_key', $externalKey);

        if ($ignoreItem !== null) {
            $query->whereKeyNot($ignoreItem->id);
        }

        return $query->exists();
    }

    public function externalKeyExistsForVersionId(int $versionId, string $externalKey): bool
    {
        return DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->where('external_key', $externalKey)
            ->exists();
    }

    public function findByExternalKey(int $versionId, string $externalKey): ?DirectoryItem
    {
        return DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->where('external_key', $externalKey)
            ->first();
    }

    public function deleteForVersion(int $versionId): void
    {
        DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->delete();
    }

    /** @param list<string> $keepExternalKeys */
    public function deleteMissingExternalKeysForVersion(int $versionId, array $keepExternalKeys): int
    {
        if ($keepExternalKeys === []) {
            return 0;
        }

        $deleted = DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->whereNotNull('external_key')
            ->whereNotIn('external_key', $keepExternalKeys)
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /** @param array<int, int> $ids */
    public function bulkDeleteForDirectory(string $directoryId, array $ids): void
    {
        DirectoryItem::query()
            ->whereIn('id', $ids)
            ->whereHas('version', static fn ($q) => $q->where('directory_id', $directoryId))
            ->delete();
    }

    /** @return Collection<string, int> */
    public function externalKeyToIdMap(int $versionId): Collection
    {
        /** @var Collection<string, int> $result */
        $result = DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->whereNotNull('external_key')
            ->pluck('id', 'external_key');

        return $result;
    }

    /** @return Collection<int, DirectoryItem> */
    public function forVersion(int $versionId): Collection
    {
        return DirectoryItem::query()
            ->where('directory_version_id', $versionId)
            ->get();
    }

    public function belongsToDirectory(DirectoryItem $item, string $directoryId): bool
    {
        return $item->version()
            ->where('directory_id', $directoryId)
            ->exists();
    }

    /**
     * @param Builder<DirectoryItem> $query
     * @param array<string, mixed>   $filter
     */
    private function applyFilter(Builder $query, string $key, array $filter): void
    {
        $type = isset($filter['type']) && is_string($filter['type']) ? $filter['type'] : 'string';

        if ($type === 'list_multi') {
            /** @var list<string> $values */
            $values = isset($filter['values']) && is_array($filter['values']) ? $filter['values'] : [];
            if ($values !== []) {
                $this->applyMultiListFilter($query, $key, $values);
            }

            return;
        }

        $operator = isset($filter['operator']) && is_string($filter['operator']) ? $filter['operator'] : 'contains';
        $value = isset($filter['value']) && is_string($filter['value']) ? $filter['value'] : '';
        $valueTo = isset($filter['value_to']) && is_string($filter['value_to']) ? $filter['value_to'] : null;

        if ($value === '') {
            return;
        }

        match ($type) {
            'integer' => $this->applyIntegerFilter($query, $key, $operator, $value, $valueTo),
            'boolean' => $this->applyBooleanFilter($query, $key, $value),
            'date' => $this->applyDateFilter($query, $key, $operator, $value, $valueTo, 'date'),
            'datetime' => $this->applyDateFilter($query, $key, $operator, $value, $valueTo, 'timestamp'),
            'list' => $this->applyListFilter($query, $key, $value),
            default => $this->applyStringFilter($query, $key, $operator, $value),
        };
    }

    /**
     * @param Builder<DirectoryItem> $query
     * @param list<string>           $values
     */
    private function applyMultiListFilter(Builder $query, string $key, array $values): void
    {
        $query->where(function (Builder $q) use ($key, $values): void {
            foreach ($values as $value) {
                if ($value === '') {
                    continue;
                }
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);
                $q->orWhere(function (Builder $inner) use ($key, $value, $escaped): void {
                    $inner->whereRaw('data_json->>? LIKE ?', [$key, '%"'.$escaped.'"%'])
                        ->orWhereRaw('data_json->>? = ?', [$key, $value]);
                });
            }
        });
    }

    /** @param Builder<DirectoryItem> $query */
    private function applyStringFilter(Builder $query, string $key, string $operator, string $value): void
    {
        match ($operator) {
            'equals' => $query->whereRaw('data_json->>? ILIKE ?', [$key, $value]),
            'starts_with' => $query->whereRaw('data_json->>? ILIKE ?', [$key, $value.'%']),
            default => $query->whereRaw('data_json->>? ILIKE ?', [$key, '%'.$value.'%']),
        };
    }

    /** @param Builder<DirectoryItem> $query */
    private function applyIntegerFilter(Builder $query, string $key, string $operator, string $value, ?string $valueTo): void
    {
        if (! is_numeric($value)) {
            return;
        }

        match ($operator) {
            'gt' => $query->whereRaw('(data_json->>?)::bigint > ?', [$key, (int) $value]),
            'lt' => $query->whereRaw('(data_json->>?)::bigint < ?', [$key, (int) $value]),
            'between' => $valueTo !== null && is_numeric($valueTo)
                ? $query->whereRaw('(data_json->>?)::bigint >= ?', [$key, (int) $value])
                    ->whereRaw('(data_json->>?)::bigint <= ?', [$key, (int) $valueTo])
                : $query->whereRaw('(data_json->>?)::bigint = ?', [$key, (int) $value]),
            default => $query->whereRaw('(data_json->>?)::bigint = ?', [$key, (int) $value]),
        };
    }

    /** @param Builder<DirectoryItem> $query */
    private function applyBooleanFilter(Builder $query, string $key, string $value): void
    {
        $boolVal = in_array($value, ['true', '1'], true) ? 'true' : 'false';
        $query->whereRaw('data_json->>? = ?', [$key, $boolVal]);
    }

    /** @param Builder<DirectoryItem> $query */
    private function applyListFilter(Builder $query, string $key, string $value): void
    {
        // Match both new JSON-array format '["a","b"]' and legacy plain string 'a'.
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);
        $query->where(function (Builder $q) use ($key, $value, $escaped): void {
            $q->whereRaw('data_json->>? LIKE ?', [$key, '%"'.$escaped.'"%'])
                ->orWhereRaw('data_json->>? = ?', [$key, $value]);
        });
    }

    /** @param Builder<DirectoryItem> $query */
    private function applyDateFilter(Builder $query, string $key, string $operator, string $value, ?string $valueTo, string $castType): void
    {
        $cast = $castType === 'timestamp' ? 'timestamp' : 'date';

        if ($operator === 'before') {
            $query->whereRaw("(data_json->>?)::{$cast} < ?", [$key, $value]);
        } elseif ($operator === 'after') {
            $query->whereRaw("(data_json->>?)::{$cast} > ?", [$key, $value]);
        } elseif ($operator === 'between' && $valueTo !== null) {
            $query->whereRaw("(data_json->>?)::{$cast} >= ?", [$key, $value])
                ->whereRaw("(data_json->>?)::{$cast} <= ?", [$key, $valueTo]);
        } else {
            $query->whereRaw("(data_json->>?)::{$cast} = ?", [$key, $value]);
        }
    }
}
