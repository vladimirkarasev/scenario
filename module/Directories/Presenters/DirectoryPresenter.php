<?php

declare(strict_types=1);

namespace Module\Directories\Presenters;

use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryRepository;

final readonly class DirectoryPresenter
{
    public function __construct(
        private DirectoryRepository $directories,
        private DirectoryItemRepository $items,
        private DirectoryVersionPresenter $versions,
        private DirectoryImportPresenter $imports,
    ) {
    }

    /** @return array<string, mixed> */
    public function listing(Directory $directory): array
    {
        $active = $directory->relationLoaded('activeVersion') ? $directory->activeVersion : null;
        $display = $active ?? $directory->latestVersion;

        return [
            ...$this->base($directory),
            'category_ids' => $directory->relationLoaded('categories')
                ? $directory->categories->pluck('id')->values()->all()
                : [],
            'versions_count' => $directory->versions_count ?? 0,
            'active_version' => $active instanceof DirectoryVersion ? $this->versions->present($active) : null,
            'latest_version' => $this->versionSummary($display),
            'sample_items' => [],
            'imports' => $directory->imports
                ->map(fn(DirectoryImport $import): array => $this->imports->present($import))
                ->values()->all(),
            'import_settings' => $directory->import_settings_json ?? [],
            'created_at' => $directory->created_at?->toIso8601String(),
            'updated_at' => $directory->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Directory $directory): array
    {
        $active = $directory->relationLoaded('activeVersion')
            ? $directory->activeVersion
            : $this->directories->activeVersion($directory);
        $display = $active ?? $directory->latestVersion;

        return [
            ...$this->base($directory),
            'category_ids' => $directory->relationLoaded('categories')
                ? $directory->categories->pluck('id')->values()->all()
                : $directory->categories()->pluck('categories.id')->values()->all(),
            'api_config' => $directory->api_config_json ?? [],
            'versions_count' => $directory->versions_count ?? $this->directories->versionsCount($directory),
            'active_version' => $active instanceof DirectoryVersion ? $this->versions->present($active) : null,
            'latest_version' => $this->versionSummary($display),
            'sample_items' => $display instanceof DirectoryVersion ? $this->sampleItems($display) : [],
            'versions' => $directory->relationLoaded('versions')
                ? $directory->versions->map(fn(DirectoryVersion $version): array => $this->versions->present($version))
                    ->values()->all()
                : [],
            'imports' => $directory->imports
                ->map(fn(DirectoryImport $import): array => $this->imports->present($import))
                ->values()->all(),
            'import_settings' => $directory->import_settings_json ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function base(Directory $directory): array
    {
        return [
            'id' => $directory->id,
            'project_id' => $directory->project_id,
            'name' => $directory->name,
            'slug' => $directory->slug,
            'description' => $directory->description,
            'source_type' => $directory->source_type ?? 'manual',
            'match_by' => $directory->match_by,
            'default_sort' => $directory->default_sort,
            'cache_ttl_seconds' => $directory->cache_ttl_seconds,
            'last_sync_at' => $directory->last_sync_at?->toIso8601String(),
            'next_sync_at' => $directory->next_sync_at?->toIso8601String(),
            'sync_status' => $directory->sync_status ?? 'idle',
            'sync_error' => $directory->sync_error,
        ];
    }

    /** @return array<string, mixed>|null */
    private function versionSummary(?DirectoryVersion $version): ?array
    {
        if ($version === null) {
            return null;
        }

        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'status' => $version->status,
            'is_active' => (bool)$version->is_active,
            'schema_json' => $this->versions->present($version)['schema_json'],
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleItems(DirectoryVersion $version): array
    {
        return $this->items->sampleForVersion($version)->map(static fn($item): array => [
            'id' => $item->id,
            'external_key' => $item->external_key,
            'parent_id' => $item->parent_id,
            'data' => $item->data_json ?? [],
            'created_at' => $item->created_at?->toIso8601String(),
        ])->values()->all();
    }
}
