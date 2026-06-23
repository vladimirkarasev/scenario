<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;

final class DirectoryCacheService
{
    private const TTL_SECONDS = 3600;

    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function activeData(Directory $directory, array $query = []): array
    {
        $version = $directory->activeVersion()->first();

        if ($version === null) {
            throw DirectoryVersionException::notFound();
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($this->rememberVersionRows($directory, $version));

        $filterRaw = $query['filter'] ?? [];
        /** @var array<string, mixed> $filters */
        $filters = is_array($filterRaw) ? $filterRaw : [];
        $rows = $this->filterRows($rows, $filters);
        $sortRaw = $query['sort'] ?? null;
        $direction = $query['direction'] ?? null;
        $rows = $this->sortRows($rows, $sortRaw, is_string($direction) ? $direction : 'asc');

        $perPageRaw = $query['per_page'] ?? null;
        $perPage = min(100, max(1, is_int($perPageRaw) ? $perPageRaw : (is_numeric($perPageRaw) ? (int) $perPageRaw : 50)));
        $pageRaw = $query['page'] ?? null;
        $page = max(1, is_int($pageRaw) ? $pageRaw : (is_numeric($pageRaw) ? (int) $pageRaw : 1));
        $paginator = new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
        );

        return [
            'dictionary' => [
                'id' => $directory->id,
                'code' => $directory->slug,
                'name' => $directory->name,
                'active_version_id' => $version->id,
                'active_version_number' => $version->version_number,
            ],
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function warmup(Directory $directory): int
    {
        $version = $directory->activeVersion()->first();

        if ($version === null) {
            return 0;
        }

        $rows = $this->loadVersionRows($version);
        $this->store()->put($this->versionKey($directory->slug ?? '', $version->id), $rows, self::TTL_SECONDS);

        return count($rows);
    }

    public function forgetDirectory(Directory $directory): void
    {
        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            Cache::tags($this->tag($directory->slug ?? ''))->flush();

            return;
        }

        foreach ($directory->versions()->pluck('id') as $versionId) {
            Cache::forget($this->versionKey($directory->slug ?? '', is_int($versionId) ? $versionId : (is_numeric($versionId) ? (int) $versionId : 0)));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function rememberVersionRows(Directory $directory, DirectoryVersion $version): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = $this->store()->remember(
            $this->versionKey($directory->slug ?? '', $version->id),
            self::TTL_SECONDS,
            fn (): array => $this->loadVersionRows($version),
        );

        return $result;
    }

    private function store(): CacheRepository
    {
        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            return Cache::tags('directories');
        }

        return Cache::store();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadVersionRows(DirectoryVersion $version): array
    {
        return $version->items()
            ->orderBy('id')
            ->get()
            ->map(static fn (DirectoryItem $item): array => [
                'id' => $item->id,
                'external_key' => $item->external_key,
                ...(is_array($item->data_json) ? $item->data_json : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>> $rows
     * @param  array<string, mixed>                  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $rows, array $filters): Collection
    {
        return $rows->filter(static function (array $row) use ($filters): bool {
            foreach ($filters as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $rowValue = data_get($row, (string) $key);
                if (
                    (is_scalar($rowValue) ? (string) $rowValue : '') !== (is_scalar($value) ? (string) $value : '')
                ) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>> $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, mixed $sort, string $direction): Collection
    {
        if (! is_string($sort) || $sort === '') {
            return $rows;
        }

        $sorted = $rows->sortBy(static fn (array $row): mixed => data_get($row, $sort));

        return strtolower($direction) === 'desc' ? $sorted->reverse()->values() : $sorted->values();
    }

    private function versionKey(string $code, int $versionId): string
    {
        return "directories.data.{$code}.version.{$versionId}";
    }

    private function tag(string $code): string
    {
        return "directory.{$code}";
    }
}
