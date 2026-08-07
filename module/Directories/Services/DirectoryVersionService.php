<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Facades\DB;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryCacheRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Directories\Temporal\RebuildDirectorySearchTextWorkflowStarterInterface;
use Module\Directories\Presenters\DirectoryVersionPresenter;

final readonly class DirectoryVersionService
{
    public function __construct(
        private DirectoryVersionRepository $versions,
        private DirectoryCacheRepository $dataCache,
        private DirectorySyncScheduleService $syncSchedules,
        private RebuildDirectorySearchTextWorkflowStarterInterface $searchTextRebuilder,
        private DirectoryVersionPresenter $presenter,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Directory $directory): array
    {
        return $this->versions
            ->orderedForDirectory($directory)
            ->map(fn(DirectoryVersion $version): array => $this->payload($version))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function create(Directory $directory, bool $clone): array
    {
        $newVersion = DB::transaction(function () use ($directory, $clone): DirectoryVersion {
            $sourceVersion = $clone ? $this->versions->activeOrFirst($directory) : null;
            $newVersion = $this->versions->createNext($directory, $sourceVersion);

            if ($sourceVersion !== null) {
                $this->versions->cloneItems($sourceVersion, $newVersion);
            }

            return $newVersion;
        });

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payload($newVersion->refresh());
    }

    public function delete(Directory $directory, DirectoryVersion $version): void
    {
        if ($version->directory_id !== $directory->id) {
            throw DirectoryVersionException::notBelongsToDirectory();
        }

        if ((bool)$version->is_active) {
            throw DirectoryVersionException::cannotDeleteActive();
        }

        $version->delete();

        DirectoryCache::forgetDirectory($directory->id);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function updateSchema(
        Directory $directory,
        DirectoryVersion $version,
        array $fields,
        ?string $matchBy,
        ?string $defaultSort = null,
    ): array {
        if ($version->directory_id !== $directory->id) {
            throw DirectoryVersionException::notBelongsToDirectory();
        }

        $this->validateRelatedDirectoryFields($fields, $directory);

        $previousSearchableKeys = $this->searchableKeys($version->schema_json);

        $version->schema_json = $fields;
        $version->save();

        $directory->match_by = $matchBy;
        $directory->default_sort = $defaultSort;
        $directory->save();

        if ($previousSearchableKeys !== $this->searchableKeys($fields)) {
            $this->searchTextRebuilder->start($version->id);
        }

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payload($version);
    }

    /** @param  array<int, array<string, mixed>>  $fields */
    private function validateRelatedDirectoryFields(array $fields, Directory $directory): void
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? null) !== 'related_directory') {
                continue;
            }

            $relatedId = $field['related_directory_id'] ?? null;

            if (!is_string($relatedId) || $relatedId === '') {
                continue;
            }

            if ($relatedId === $directory->id) {
                throw DirectoryVersionException::relatedDirectorySelfReference();
            }

            $related = Directory::query()->find($relatedId);

            if (!$related instanceof Directory || $related->project_id !== $directory->project_id) {
                throw DirectoryVersionException::relatedDirectoryNotFound();
            }
        }
    }

    /**
     * @param  array{add_new: bool, update_existing: bool, delete_unused: bool}|null  $syncOptions
     * @return array<string, mixed>
     */
    public function updateSettings(
        Directory $directory,
        DirectoryVersion $version,
        string $sourceType,
        ?array $syncOptions = null,
        ?bool $allowOther = null,
        ?string $otherLabel = null,
        ?string $otherExternalKey = null,
    ): array {
        if ($version->directory_id !== $directory->id) {
            throw DirectoryVersionException::notBelongsToDirectory();
        }

        $version->source_type = $sourceType;
        if ($syncOptions !== null) {
            $version->sync_options = $syncOptions;
        }
        if ($allowOther !== null) {
            $version->allow_other = $allowOther;
        }
        if ($otherLabel !== null) {
            $version->other_label = $otherLabel !== '' ? $otherLabel : null;
        }
        if ($otherExternalKey !== null) {
            $version->other_external_key = $otherExternalKey !== '' ? $otherExternalKey : null;
        }
        $version->save();

        if ((bool)$version->is_active) {
            $directory->source_type = $sourceType;
            $directory->next_sync_at = $sourceType === 'api' ? now() : null;
            $directory->save();

            if ($sourceType === 'api') {
                $this->syncSchedules->ensureDefault($directory);
            }
        }

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payload($version);
    }

    /** @return array<string, mixed> */
    public function updateCode(Directory $directory, DirectoryVersion $version, ?string $code): array
    {
        if ($version->directory_id !== $directory->id) {
            throw DirectoryVersionException::notBelongsToDirectory();
        }

        $version->code = $code !== '' ? $code : null;
        $version->save();

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payload($version);
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(Directory $directory, DirectoryVersion $version): array
    {
        if ($version->directory_id !== $directory->id) {
            throw DirectoryVersionException::notBelongsToDirectory();
        }

        DB::transaction(function () use ($directory, $version): void {
            $directory->versions()->lockForUpdate()->get();

            $this->versions->deactivateAll($directory);
            $this->versions->activate($version);
        });

        $directory->source_type = $version->source_type;
        $directory->next_sync_at = $version->source_type === 'api' ? now() : null;
        $directory->save();

        if ($version->source_type === 'api') {
            $this->syncSchedules->ensureDefault($directory);
        }

        DirectoryCache::forgetDirectory($directory->id);
        $this->dataCache->forgetDirectory($directory);

        return $this->payload($version->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(DirectoryVersion $version): array
    {
        return $this->presenter->present($version);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function searchableKeys(array $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            if (($field['searchable'] ?? false) === true && is_string($field['key'] ?? null)) {
                $keys[] = $field['key'];
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }
}
