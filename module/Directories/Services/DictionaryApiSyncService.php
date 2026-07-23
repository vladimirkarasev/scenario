<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Schedule\Models\Schedule;
use Throwable;

final readonly class DictionaryApiSyncService
{
    public function __construct(
        private DirectoryImportRepository $imports,
        private DirectoryVersionRepository $versions,
        private ImportService $importService,
    ) {
    }

    public function queue(
        Directory $directory,
        ?int $userId = null,
        ?DirectoryImportOptions $options = null
    ): DirectoryImport {
        if ($directory->source_type !== 'api') {
            throw DictionaryApiSyncException::notApiDirectory();
        }

        $config = $directory->api_config_json ?? [];
        $proxyUuid = is_string($config['proxy_uuid'] ?? null) ? $config['proxy_uuid'] : null;

        if ($proxyUuid !== null) {
            return $this->queueProxySync($directory, $config, $userId, $options);
        }

        return $this->queueDirectApiSync($directory, $config, $userId, $options);
    }

    /**
     * Kicks off the (Temporal-orchestrated, async) import. The eventual completion/failure —
     * version activation, `Directory.sync_status/last_sync_at/next_sync_at` — is handled by
     * {@see \Module\Directories\Listeners\SyncDirectoryStatusOnImportFinished}, reacting to
     * {@see \Module\Directories\Events\DirectoryImportStatusUpdated} once the import actually finishes.
     */
    public function runImport(int $importId): void
    {
        $import = $this->imports->findOrFail($importId);
        $directory = $import->directory()->firstOrFail();

        try {
            $directory->forceFill(['sync_status' => 'processing', 'sync_error' => null])->save();

            $this->importService->start($importId);
        } catch (Throwable $exception) {
            $directory->forceFill([
                'sync_status' => 'failed',
                'sync_error' => Str::limit($exception->getMessage(), 65_535, ''),
                'next_sync_at' => $this->nextSyncAt($directory),
            ])->save();

            report($exception);
        }
    }

    /**
     * Queues due interval-based syncs. Directories with an enabled cron schedule (a
     * {@see \Module\Schedule\Models\Schedule} row, scope `directory-sync`) are skipped here —
     * they're triggered independently by their own native Temporal Schedule instead.
     */
    public function queueDue(): int
    {
        $count = 0;

        $scheduledDirectoryIds = Schedule::query()
            ->where('scope', DirectorySyncScheduleService::scope())
            ->where('enabled', true)
            ->whereNotNull('cron')
            ->pluck('subject_id');

        Directory::query()
            ->where('source_type', 'api')
            ->whereNotNull('api_config_json')
            ->where(function ($query): void {
                $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now());
            })
            ->whereNotIn('id', $scheduledDirectoryIds)
            ->each(function (Directory $directory) use (&$count): void {
                try {
                    $this->queue($directory);
                    $count++;
                } catch (Throwable) {
                }
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function queueProxySync(
        Directory $directory,
        array $config,
        ?int $userId,
        ?DirectoryImportOptions $options,
    ): DirectoryImport {
        $proxyUuid = is_string($config['proxy_uuid'] ?? null) ? $config['proxy_uuid'] : '';
        $proxyEndpoint = ProxyEndpoint::query()->where('uuid', $proxyUuid)->first();

        if ($proxyEndpoint === null) {
            throw DictionaryApiSyncException::proxyNotFound($proxyUuid);
        }

        $version = $this->versions->activeOrFirst($directory);
        if ($version === null) {
            $version = DB::transaction(fn() => $this->versions->createNext($directory, null));
        }
        $version->forceFill([
            'source_metadata_json' => ['source' => 'proxy', 'queued_at' => now()->toIso8601String()],
        ])->save();

        $fields = $version->schema_json !== [] ? $version->schema_json : [];

        $rawMapping = is_array($config['field_mapping'] ?? null) ? $config['field_mapping'] : [];
        $mappingJson = [];
        foreach ($rawMapping as $dirField => $proxyField) {
            if (is_string($proxyField) && $proxyField !== '' && is_string($dirField)) {
                $mappingJson[$proxyField] = $dirField;
            }
        }

        if ($mappingJson === []) {
            foreach ($fields as $field) {
                $key = is_string($field['key'] ?? null) ? $field['key'] : null;
                if ($key !== null && $key !== '') {
                    $mappingJson[$key] = $key;
                }
            }
        }

        $syncOptions = $options ?? new DirectoryImportOptions(addNew: true, updateExisting: true, deleteUnused: true);

        $import = $this->imports->create([
            'directory_id' => $directory->id,
            'directory_version_id' => $version->id,
            'uploaded_by' => $userId,
            'mode' => DirectoryImportMode::Replace->value,
            'status' => DirectoryImportStatus::Pending->value,
            'source_type' => DirectoryImportSourceType::Proxy->value,
            'file_disk' => 'proxy',
            'file_path' => 'proxy:'.$proxyEndpoint->id,
            'match_by' => $directory->match_by,
            'chunk_size' => 500,
            'mapping_json' => $mappingJson,
            'fields_json' => $fields,
            'remote_config_json' => [
                'proxy_endpoint_id' => $proxyEndpoint->id,
                'proxy_uuid' => $proxyUuid,
                ...$syncOptions->toArray(),
            ],
        ]);

        $directory->forceFill(['sync_status' => 'queued', 'sync_error' => null])->save();

        $this->runImport($import->id);

        return $import;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function queueDirectApiSync(
        Directory $directory,
        array $config,
        ?int $userId,
        ?DirectoryImportOptions $options,
    ): DirectoryImport {
        $version = $this->versions->activeOrFirst($directory);
        if ($version === null) {
            $version = DB::transaction(fn() => $this->versions->createNext($directory, null));
        }
        $version->forceFill([
            'source_metadata_json' => ['source' => 'api', 'queued_at' => now()->toIso8601String()],
        ])->save();

        $fields = $version->schema_json !== [] ? $version->schema_json : [];

        $syncOptions = $options ?? new DirectoryImportOptions(addNew: true, updateExisting: true, deleteUnused: true);

        $import = $this->imports->create([
            'directory_id' => $directory->id,
            'directory_version_id' => $version->id,
            'uploaded_by' => $userId,
            'mode' => DirectoryImportMode::Replace->value,
            'status' => DirectoryImportStatus::Pending->value,
            'source_type' => 'remote',
            'file_disk' => 'remote',
            'file_path' => is_string($config['endpoint'] ?? null) ? $config['endpoint'] : 'remote',
            'match_by' => $directory->match_by,
            'chunk_size' => 500,
            'mapping_json' => is_array($config['response_mapping'] ?? null)
                ? $config['response_mapping']
                : collect($fields)->pluck('key', 'key')->all(),
            'fields_json' => $fields,
            'remote_config_json' => [...$this->remoteConfig($config), ...$syncOptions->toArray()],
        ]);

        $directory->forceFill(['sync_status' => 'queued', 'sync_error' => null])->save();

        $this->runImport($import->id);

        return $import;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function remoteConfig(array $config): array
    {
        return [
            'url' => is_string($config['endpoint'] ?? null) ? $config['endpoint'] : '',
            'method' => is_string($config['method'] ?? null) ? $config['method'] : 'GET',
            'items_path' => is_string(data_get($config, 'response_mapping.items_path')) ? data_get(
                $config,
                'response_mapping.items_path'
            ) : 'data',
            'headers' => is_array($config['headers'] ?? null) ? $config['headers'] : [],
            'auth_type' => is_string($config['auth_type'] ?? null) ? $config['auth_type'] : 'none',
            'auth' => is_array($config['auth'] ?? null) ? $config['auth'] : [],
            'query' => is_array($config['query'] ?? null) ? $config['query'] : [],
            'body' => is_array($config['body'] ?? null) ? $config['body'] : [],
            'page_param' => is_string($config['page_param'] ?? null) ? $config['page_param'] : 'page',
            'per_page_param' => is_string($config['per_page_param'] ?? null) ? $config['per_page_param'] : 'per_page',
            'per_page' => is_int($config['per_page'] ?? null) ? $config['per_page'] : 500,
            'start_page' => 1,
        ];
    }

    public function nextSyncAt(Directory $directory): Carbon
    {
        $apiConfig = $directory->api_config_json ?? [];
        $refreshInterval = $apiConfig['refresh_interval'] ?? null;
        $seconds = max(60, is_int($refreshInterval) ? $refreshInterval : 3600);

        return now()->addSeconds($seconds);
    }
}
