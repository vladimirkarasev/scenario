<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Users\Models\User;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryData;
use Module\Directories\Exceptions\DirectoryException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryRepository;
use Module\Projects\Models\Project;
use Module\Projects\Repositories\ProjectRepository;

final readonly class DirectoryService
{
    public function __construct(
        private DirectoryVersionService $versionService,
        private DirectoryRepository $directories,
        private DirectoryItemRepository $items,
        private ProjectRepository $projects,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function items(Project $project): array
    {
        return DirectoryCache::rememberList($project->id, fn(): array => $this->directories
            ->listForProject($project)
            ->map(fn(Directory $directory): array => $this->listPayload($directory))
            ->values()
            ->all());
    }

    /**
     * @param  string[]  $categoryIds
     * @return array{items: array<int, array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    public function paginate(
        Project $project,
        int $page,
        int $perPage,
        array $categoryIds = [],
        bool $uncategorized = false
    ): array {
        $paginator = $this->directories->paginateForProject($project, $page, $perPage, $categoryIds, $uncategorized);

        return [
            'items' => array_map($this->listPayload(...), $paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function find(Directory $directory): array
    {
        return DirectoryCache::rememberDetail($directory->id, function () use ($directory): array {
            $this->directories->loadDetail($directory);

            return $this->payload($directory);
        });
    }

    /** @return array<string, mixed> */
    public function create(DirectoryData $data): array
    {
        $this->ensureManageAccess($data->canManageDirectories);

        $directory = $this->directories->create([
            'project_id' => $data->projectId,
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'source_type' => $data->sourceType,
            'match_by' => $data->matchBy,
            'api_config_json' => $data->apiConfig,
            'next_sync_at' => $data->sourceType === 'api' ? now() : null,
        ]);

        $directory->categories()->sync($this->categoryPivot($data->categoryIds, $data->projectId));

        $this->directories->createInitialVersion($directory, $data->fields, $data->sourceType);

        DirectoryCache::forgetList($data->projectId);

        return $this->payload($this->directories->freshForPayload($directory));
    }

    /** @return array<string, mixed> */
    public function update(DirectoryData $data, Directory $directory): array
    {
        $this->ensureManageAccess($data->canManageDirectories);

        $directory = $this->directories->update($directory, [
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'match_by' => $data->matchBy,
            'api_config_json' => $data->apiConfig,
        ]);

        $directory->categories()->sync($this->categoryPivot($data->categoryIds, (string)$directory->project_id));

        DirectoryCache::forgetDirectory($directory->id);
        DirectoryCache::forgetList((string)$directory->project_id);

        return $this->find($directory->fresh() ?? $directory);
    }

    /** @return array<string, mixed> */
    public function listPayload(Directory $directory): array
    {
        /** @var DirectoryVersion|null $activeVersion */
        $activeVersion = $directory->relationLoaded('activeVersion') ? $directory->activeVersion : null;
        /** @var DirectoryVersion|null $latestVersion */
        $latestVersion = $directory->latestVersion;
        $displayVersion = $activeVersion ?? $latestVersion;

        return [
            'id' => $directory->id,
            'project_id' => $directory->project_id,
            'category_ids' => $directory->relationLoaded('categories')
                ? $directory->categories->pluck('id')->values()->all()
                : [],
            'name' => $directory->name,
            'slug' => $directory->slug,
            'description' => $directory->description,
            'source_type' => $directory->source_type ?? 'manual',
            'match_by' => $directory->match_by,
            'default_sort' => $directory->default_sort,
            'last_sync_at' => $directory->last_sync_at?->toIso8601String(),
            'next_sync_at' => $directory->next_sync_at?->toIso8601String(),
            'sync_status' => $directory->sync_status ?? 'idle',
            'sync_error' => $directory->sync_error,
            'versions_count' => $directory->versions_count ?? 0,
            'active_version' => $activeVersion !== null ? $this->versionService->payload($activeVersion) : null,
            'latest_version' => $displayVersion !== null ? [
                'id' => $displayVersion->id,
                'version_number' => $displayVersion->version_number,
                'status' => $displayVersion->status,
                'is_active' => (bool)$displayVersion->is_active,
                'schema_json' => $this->versionService->payload($displayVersion)['schema_json'],
                'created_at' => $displayVersion->created_at?->toIso8601String(),
            ] : null,
            'sample_items' => [],
            'imports' => $directory->imports
                ->map(fn(DirectoryImport $import): array => $this->importPayload($import))
                ->values()
                ->all(),
            'import_settings' => $directory->import_settings_json ?? [],
            'created_at' => $directory->created_at?->toIso8601String(),
            'updated_at' => $directory->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function payload(Directory $directory): array
    {
        /** @var DirectoryVersion|null $activeVersion */
        $activeVersion = $directory->relationLoaded('activeVersion')
            ? $directory->activeVersion
            : $this->directories->activeVersion($directory);

        /** @var DirectoryVersion|null $latestVersion */
        $latestVersion = $directory->latestVersion;
        $displayVersion = $activeVersion ?? $latestVersion;

        return [
            'id' => $directory->id,
            'project_id' => $directory->project_id,
            'category_ids' => $directory->relationLoaded('categories')
                ? $directory->categories->pluck('id')->values()->all()
                : $directory->categories()->pluck('categories.id')->values()->all(),
            'name' => $directory->name,
            'slug' => $directory->slug,
            'description' => $directory->description,
            'source_type' => $directory->source_type ?? 'manual',
            'match_by' => $directory->match_by,
            'default_sort' => $directory->default_sort,
            'last_sync_at' => $directory->last_sync_at?->toIso8601String(),
            'next_sync_at' => $directory->next_sync_at?->toIso8601String(),
            'sync_status' => $directory->sync_status ?? 'idle',
            'sync_error' => $directory->sync_error,
            'api_config' => $directory->api_config_json ?? [],
            'versions_count' => $directory->versions_count ?? $this->directories->versionsCount($directory),
            'active_version' => $activeVersion !== null ? $this->versionService->payload($activeVersion) : null,
            'latest_version' => $displayVersion !== null ? [
                'id' => $displayVersion->id,
                'version_number' => $displayVersion->version_number,
                'status' => $displayVersion->status,
                'is_active' => (bool)$displayVersion->is_active,
                'schema_json' => $this->versionService->payload($displayVersion)['schema_json'],
                'created_at' => $displayVersion->created_at?->toIso8601String(),
            ] : null,
            'sample_items' => $displayVersion !== null ? $this->sampleItems($displayVersion) : [],
            'versions' => $directory->relationLoaded('versions')
                ? $directory->versions->map(fn(DirectoryVersion $v): array => $this->versionService->payload($v)
                )->values()->all()
                : [],
            'imports' => $directory->imports
                ->map(fn(DirectoryImport $import): array => $this->importPayload($import))
                ->values()
                ->all(),
            'import_settings' => $directory->import_settings_json ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function importPayload(DirectoryImport $import): array
    {
        $import->loadMissing('version');

        return [
            'id' => $import->id,
            'directory_id' => $import->directory_id,
            'directory_version_id' => $import->directory_version_id,
            'version_number' => $import->version?->version_number,
            'mode' => $import->mode,
            'status' => $import->status,
            'source_type' => $import->source_type,
            'source_label' => $import->source_type === 'remote' ? 'Remote API' : 'Excel',
            'match_by' => $import->match_by,
            'parent_key_field' => $import->parent_key_field,
            'chunk_size' => $import->chunk_size,
            'remote_url' => data_get($import->remote_config_json, 'url'),
            'processed_rows' => $import->processed_rows,
            'imported_rows' => $import->imported_rows,
            'failed_rows' => $import->failed_rows,
            'error_message' => $import->error_message,
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }

    public function resolveEditableVersion(Directory $directory): DirectoryVersion
    {
        return $this->directories->resolveEditableVersion($directory);
    }

    public function currentProjectForUser(?User $user): Project
    {
        $project = $this->projects->activeBySitekeyAndHost($user?->sitekey, $user?->host);

        if ($project === null) {
            throw DirectoryException::projectNotFound();
        }

        return $project;
    }

    public function ensureProjectAccess(Directory $directory, Project $project): void
    {
        if ($directory->project_id !== $project->id) {
            throw DirectoryException::notInProject();
        }
    }

    /** @param array<string, mixed> $settings */
    public function updateImportSettings(Directory $directory, array $settings): void
    {
        $existing = is_array($directory->import_settings_json) ? $directory->import_settings_json : [];
        $directory->forceFill([
            'import_settings_json' => array_merge(
                $existing,
                array_filter($settings, static fn(mixed $v): bool => $v !== null),
            ),
        ])->save();
        DirectoryCache::forgetDirectory($directory->id);
    }

    /** @return array<int, array<string, mixed>> */
    private function sampleItems(DirectoryVersion $version): array
    {
        return $this->items
            ->sampleForVersion($version)
            ->map(static fn($item): array => [
                'id' => $item->id,
                'external_key' => $item->external_key,
                'parent_id' => $item->parent_id,
                'data' => $item->data_json ?? [],
                'created_at' => $item->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  string[]  $categoryIds
     * @return array<string, array<string, string|null>>
     */
    private function categoryPivot(array $categoryIds, string $projectId): array
    {
        $pivot = [];
        foreach ($categoryIds as $id) {
            $pivot[$id] = ['project_id' => $projectId];
        }

        return $pivot;
    }

    private function ensureManageAccess(bool $canManageDirectories): void
    {
        if (!$canManageDirectories) {
            throw DirectoryException::forbidden();
        }
    }
}
