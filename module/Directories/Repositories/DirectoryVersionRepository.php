<?php

declare(strict_types=1);

namespace Module\Directories\Repositories;

use Illuminate\Support\Collection;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryItem;
use Module\Directories\Models\DirectoryVersion;

final class DirectoryVersionRepository
{
    /** @return Collection<int, DirectoryVersion> */
    public function orderedForDirectory(Directory $directory): Collection
    {
        return $directory->versions()
            ->withCount(['items', 'imports'])
            ->orderByDesc('version_number')
            ->get();
    }

    public function createNext(Directory $directory, ?DirectoryVersion $sourceVersion = null): DirectoryVersion
    {
        $nextNumber = $directory->versions()->withTrashed()->count() + 1;

        return $directory->versions()->create([
            'version_number' => $nextNumber,
            'status' => 'draft',
            'is_active' => false,
            'source_type' => $sourceVersion !== null ? $sourceVersion->source_type : ($directory->source_type ?? 'manual'),
            'schema_json' => $sourceVersion !== null ? $sourceVersion->schema_json : [],
        ]);
    }

    public function activeOrFirst(Directory $directory): ?DirectoryVersion
    {
        return $directory->versions()->where('is_active', true)->first()
            ?? $directory->versions()->first();
    }

    public function deactivateAll(Directory $directory): void
    {
        $directory->versions()->update(['is_active' => false]);
    }

    public function activate(DirectoryVersion $version): DirectoryVersion
    {
        $version->forceFill(['is_active' => true])->save();

        return $version;
    }

    public function updateItemParent(int $itemId, int $parentId): void
    {
        DirectoryItem::query()
            ->where('id', $itemId)
            ->update(['parent_id' => $parentId]);
    }

    public function itemsCount(DirectoryVersion $version): int
    {
        return $version->items()->count();
    }

    public function importsCount(DirectoryVersion $version): int
    {
        return $version->imports()->count();
    }

    public function cloneItems(DirectoryVersion $source, DirectoryVersion $target): void
    {
        $source->load('items');
        /** @var array<int, int> $idMap */
        $idMap = [];

        foreach ($source->items as $sourceItem) {
            $newItem = $target->items()->create([
                'external_key' => $sourceItem->external_key,
                'search_text' => $sourceItem->search_text,
                'data_json' => $sourceItem->data_json,
            ]);

            $idMap[$sourceItem->id] = $newItem->id;
        }

        foreach ($source->items->whereNotNull('parent_id') as $sourceItem) {
            $parentId = $sourceItem->parent_id;
            $newParentId = is_int($parentId) ? ($idMap[$parentId] ?? null) : null;

            if ($newParentId !== null) {
                $this->updateItemParent($idMap[$sourceItem->id], $newParentId);
            }
        }
    }
}
