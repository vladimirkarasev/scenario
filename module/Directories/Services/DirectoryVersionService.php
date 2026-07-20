<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\Exceptions\DirectoryVersionException;
use Module\Directories\Jobs\RebuildDirectorySearchTextJob;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryVersionRepository;

final readonly class DirectoryVersionService
{
    public function __construct(
        private DirectoryVersionRepository $versions,
        private Container $container,
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

        $previousSearchableKeys = $this->searchableKeys($version->schema_json);

        $version->schema_json = $fields;
        $version->save();

        $directory->match_by = $matchBy;
        $directory->default_sort = $defaultSort;
        $directory->save();

        if ($previousSearchableKeys !== $this->searchableKeys($fields)) {
            RebuildDirectorySearchTextJob::dispatch($version->id);
        }

        DirectoryCache::forgetDirectory($directory->id);

        return $this->payload($version);
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

        DirectoryCache::forgetDirectory($directory->id);
        $this->container->make(DirectoryCacheService::class)->forgetDirectory($directory);

        return $this->payload($version->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(DirectoryVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'code' => $version->code,
            'status' => $version->status,
            'is_active' => (bool)$version->is_active,
            'source_type' => $version->source_type ?? 'manual',
            'sync_options' => $version->sync_options ?? [
                    'add_new' => true,
                    'update_existing' => true,
                    'delete_unused' => false,
                ],
            'allow_other' => (bool)$version->allow_other,
            'other_label' => $version->other_label,
            'other_external_key' => $version->other_external_key,
            'schema_json' => $this->schemaPayload($version),
            'items_count' => $version->items_count ?? $this->versions->itemsCount($version),
            'imports_count' => $version->imports_count ?? $this->versions->importsCount($version),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schemaPayload(DirectoryVersion $version): array
    {
        if ($version->schema_json !== []) {
            return $version->schema_json;
        }

        $import = $version->sourceImport()->first()
            ?? $version->imports()->latest()->first();

        return $import !== null ? $import->fields_json : [];
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
