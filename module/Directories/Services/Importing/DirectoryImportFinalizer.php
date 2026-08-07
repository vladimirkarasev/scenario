<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Facades\DB;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryCacheRepository;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;

final readonly class DirectoryImportFinalizer
{
    public function __construct(
        private DirectoryItemRepository $items,
        private DirectoryVersionRepository $versions,
        private DirectoryCacheRepository $cache,
    ) {
    }

    public function finalize(DirectoryImport $import): int
    {
        $this->resolveParentKeys($import);
        $deleted = $this->deleteUnusedItems($import);
        $this->activateVersion($import);

        DirectoryCache::forgetDirectory($import->directory_id);

        return $deleted;
    }

    private function resolveParentKeys(DirectoryImport $import): void
    {
        if ($import->parent_key_field === null || $import->directory_version_id === null) {
            return;
        }

        $keyToId = $this->items->externalKeyToIdMap($import->directory_version_id);

        foreach ($this->items->forVersion($import->directory_version_id) as $item) {
            $value = $item->data_json[$import->parent_key_field] ?? null;
            $parentId = is_scalar($value) ? $keyToId->get((string)$value) : null;

            if ($parentId !== null && $parentId !== $item->id) {
                $item->forceFill(['parent_id' => $parentId])->save();
            }
        }
    }

    private function deleteUnusedItems(DirectoryImport $import): int
    {
        if (
            $import->directory_version_id === null
            || !DirectoryImportOptions::fromImport($import)->deleteUnused
        ) {
            return 0;
        }

        $keys = array_values(array_filter(
            $import->processed_keys_json ?? [],
            static fn(string $key): bool => $key !== '',
        ));

        if ($keys === []) {
            return 0;
        }

        return $this->items->deleteMissingExternalKeysForVersion($import->directory_version_id, $keys);
    }

    private function activateVersion(DirectoryImport $import): void
    {
        if (
            $import->directory_version_id === null
            || !(bool)($import->source_config_json['activate_on_success'] ?? true)
        ) {
            return;
        }

        $directory = $import->directory()->firstOrFail();
        $version = $import->version()->firstOrFail();

        DB::transaction(function () use ($directory, $version): void {
            $directory->versions()->lockForUpdate()->get();
            $this->versions->deactivateAll($directory);
            $this->versions->activate($version);
        });

        $this->cache->forgetDirectory($directory);
    }
}
