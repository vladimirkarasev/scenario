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
use Module\Directories\Repositories\DirectoryRepository;
use Module\Projects\Models\Project;
use Module\Directories\Presenters\DirectoryImportPresenter;
use Module\Directories\Presenters\DirectoryPresenter;

final readonly class DirectoryService
{
    public function __construct(
        private DirectoryRepository $directories,
        private DirectoryAccess $access,
        private DirectoryApiConfigurationValidator $apiConfiguration,
        private DirectorySyncScheduleService $syncSchedules,
        private DirectoryImportPresenter $importsPresenter,
        private DirectoryPresenter $presenter,
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
        $this->apiConfiguration->validate($data);

        $directory = $this->directories->create([
            'project_id' => $data->projectId,
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'source_type' => $data->sourceType,
            'match_by' => $data->matchBy,
            'cache_ttl_seconds' => $data->cacheTtlSeconds,
            'api_config_json' => $data->apiConfig,
            'next_sync_at' => $data->sourceType === 'api' ? now() : null,
        ]);

        $directory->categories()->sync($this->categoryPivot($data->categoryIds, $data->projectId));

        $this->directories->createInitialVersion($directory, $data->fields, $data->sourceType);

        if ($directory->source_type === 'api') {
            $this->syncSchedules->ensureDefault($directory);
        }

        DirectoryCache::forgetList($data->projectId);

        return $this->payload($this->directories->freshForPayload($directory));
    }

    /** @return array<string, mixed> */
    public function update(DirectoryData $data, Directory $directory): array
    {
        $this->ensureManageAccess($data->canManageDirectories);
        $this->apiConfiguration->validate($data);

        $directory = $this->directories->update($directory, [
            'name' => $data->name,
            'slug' => $data->slug,
            'description' => $data->description,
            'match_by' => $data->matchBy,
            'cache_ttl_seconds' => $data->cacheTtlSeconds,
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
        return $this->presenter->listing($directory);
    }

    /** @return array<string, mixed> */
    public function payload(Directory $directory): array
    {
        return $this->presenter->detail($directory);
    }

    /** @return array<string, mixed> */
    public function importPayload(DirectoryImport $import): array
    {
        return $this->importsPresenter->present($import);
    }

    public function resolveEditableVersion(Directory $directory): DirectoryVersion
    {
        return $this->directories->resolveEditableVersion($directory);
    }

    public function currentProjectForUser(?User $user): Project
    {
        return $this->access->projectFor($user);
    }

    public function ensureProjectAccess(Directory $directory, Project $project): void
    {
        $this->access->ensureDirectoryInProject($directory, $project);
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
