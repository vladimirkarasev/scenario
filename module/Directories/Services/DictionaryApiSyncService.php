<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Exceptions\DictionaryApiSyncException;
use Module\Directories\Jobs\SyncDictionaryFromApiJob;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Repositories\DirectoryImportRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Proxy\Models\ProxyEndpoint;
use Throwable;

final readonly class DictionaryApiSyncService
{
    public function __construct(
        private Dispatcher $dispatcher,
        private DirectoryImportRepository $imports,
        private DirectoryVersionRepository $versions,
        private ImportService $importService,
        private DirectoryVersionService $versionService,
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

    public function runImport(int $importId): void
    {
        $import = $this->imports->findOrFail($importId);
        $directory = $import->directory()->firstOrFail();

        try {
            $directory->forceFill(['sync_status' => 'processing', 'sync_error' => null])->save();

            $this->importService->start($importId);

            $completed = $import->fresh();

            if ($completed?->status !== DirectoryImportStatus::Completed->value) {
                throw new \RuntimeException($completed?->error_message ?: 'Sync finished with errors.');
            }

            $version = $completed->version()->firstOrFail();
            $this->versionService->activate($directory, $version);

            $directory->forceFill([
                'last_sync_at' => now(),
                'next_sync_at' => $this->nextSyncAt($directory),
                'sync_status' => 'success',
                'sync_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $directory->forceFill([
                'sync_status' => 'failed',
                'sync_error' => Str::limit($exception->getMessage(), 65_535, ''),
                'next_sync_at' => $this->nextSyncAt($directory),
            ])->save();

            report($exception);
        }
    }

    public function queueDue(): int
    {
        $count = 0;

        Directory::query()
            ->where('source_type', 'api')
            ->whereNotNull('api_config_json')
            ->where(function ($query): void {
                $query->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now());
            })
            ->each(function (Directory $directory) use (&$count): void {
                // Справочники с cron-расписанием (через модуль Actions) синхронизируются
                // командой actions:run-scheduled — пропускаем их в интервальном планировщике.
                $config = $directory->api_config_json ?? [];
                if (($config['schedule_mode'] ?? null) === 'cron') {
                    return;
                }

                try {
                    $this->queue($directory);
                    $count++;
                } catch (Throwable) {
                    // Skip individual failures so others still run
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

        // field_mapping: { dir_field: proxy_field } → invert to mapping_json: { proxy_field: dir_field }
        $rawMapping = is_array($config['field_mapping'] ?? null) ? $config['field_mapping'] : [];
        $mappingJson = [];
        foreach ($rawMapping as $dirField => $proxyField) {
            if (is_string($proxyField) && $proxyField !== '' && is_string($dirField)) {
                $mappingJson[$proxyField] = $dirField;
            }
        }

        // Fallback: если пользователь не настроил mapping — используем 1:1 по ключам полей справочника.
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

        $this->dispatcher->dispatch(new SyncDictionaryFromApiJob($import->id));

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

        $this->dispatcher->dispatch(new SyncDictionaryFromApiJob($import->id));

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

    private function nextSyncAt(Directory $directory): Carbon
    {
        $apiConfig = $directory->api_config_json ?? [];
        $refreshInterval = $apiConfig['refresh_interval'] ?? null;
        $seconds = max(60, is_int($refreshInterval) ? $refreshInterval : 3600);

        return now()->addSeconds($seconds);
    }
}
