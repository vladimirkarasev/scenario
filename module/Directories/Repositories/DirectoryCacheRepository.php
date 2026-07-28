<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryPagination;
use Module\Directories\DTO\DirectoryQuery;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;

final class DirectoryCacheRepository
{
    private const int DEFAULT_TTL_SECONDS = 3600;

    /**
     */
    public function activeData(Directory $directory, ?DirectoryQuery $query = null): DirectoryPage
    {
        $query ??= new DirectoryQuery();
        $version = $directory->activeVersion()->first();

        if ($version === null) {
            throw DirectoryVersionException::notFound();
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($this->rememberVersionRows($directory, $version));

        $rows = $this->filterRows($rows, $query->filters);
        $rows = $this->sortRows($rows, $query->sort, $query->direction);
        $paginator = new LengthAwarePaginator(
            items: $rows->forPage($query->page, $query->perPage)->values(),
            total: $rows->count(),
            perPage: $query->perPage,
            currentPage: $query->page,
        );

        return new DirectoryPage(
            dictionary: [
                'id' => $directory->id,
                'code' => $directory->slug,
                'name' => $directory->name,
                'active_version_id' => $version->id,
                'active_version_number' => $version->version_number,
            ],
            items: array_values($paginator->items()),
            pagination: new DirectoryPagination(
                currentPage: $paginator->currentPage(),
                perPage: $paginator->perPage(),
                total: $paginator->total(),
                lastPage: $paginator->lastPage(),
            ),
        );
    }

    /**
     * Сырые (нерезолвленные related-поля) строки активной версии — используется для
     * подстановки в связанные поля других справочников, без пагинации/фильтров/сортировки.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeRows(Directory $directory): array
    {
        $version = $directory->activeVersion()->first();

        if ($version === null) {
            return [];
        }

        return $this->rememberVersionRows($directory, $version);
    }

    /** Прогревает кеш справочника, только если он ещё не прогрет (не спамим релоад раньше TTL). */
    public function warmup(Directory $directory): int
    {
        $version = $directory->activeVersion()->first();

        if ($version === null) {
            return 0;
        }

        $key = $this->versionKey($directory->slug ?? '', $version->id);
        $store = $this->store($directory);

        if ($store->has($key)) {
            return 0;
        }

        $rows = $this->loadVersionRows($version);
        $store->put($key, $rows, $this->ttlFor($directory));

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
            Cache::forget(
                $this->versionKey(
                    $directory->slug ?? '',
                    is_int($versionId) ? $versionId : (is_numeric($versionId) ? (int)$versionId : 0)
                )
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function rememberVersionRows(Directory $directory, DirectoryVersion $version): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = $this->store($directory)->remember(
            $this->versionKey($directory->slug ?? '', $version->id),
            $this->ttlFor($directory),
            fn(): array => $this->loadVersionRows($version),
        );

        return $result;
    }

    private function ttlFor(Directory $directory): int
    {
        return $directory->cache_ttl_seconds ?? self::DEFAULT_TTL_SECONDS;
    }

    private function store(Directory $directory): CacheRepository
    {
        $store = Cache::getStore();

        if (method_exists($store, 'tags')) {
            return Cache::tags($this->tag($directory->slug ?? ''));
        }

        return Cache::store();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadVersionRows(DirectoryVersion $version): array
    {
        return $version->items()
            ->orderBy('id')
            ->get()
            ->map(static fn(DirectoryItem $item): array => [
                'id' => $item->id,
                'external_key' => $item->external_key,
                ...(is_array($item->data_json) ? $item->data_json : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterRows(Collection $rows, array $filters): Collection
    {
        return $rows->filter(static function (array $row) use ($filters): bool {
            foreach ($filters as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $rowValue = data_get($row, (string)$key);
                if (
                    (is_scalar($rowValue) ? (string)$rowValue : '') !== (is_scalar($value) ? (string)$value : '')
                ) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, mixed $sort, string $direction): Collection
    {
        if (!is_string($sort) || $sort === '') {
            return $rows;
        }

        $sorted = $rows->sortBy(static fn(array $row): mixed => data_get($row, $sort));

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
