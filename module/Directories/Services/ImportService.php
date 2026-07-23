<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Events\DirectoryImportStatusUpdated;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryImportRepository;
use Module\Directories\Repositories\DirectoryItemRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Directories\Services\Importing\DirectoryImportPayloadNormalizer;
use Module\Directories\Services\Importing\DirectoryImportRowProcessor;
use Module\Directories\Services\ImportSources\DirectoryImportSourceResolver;
use Module\Directories\Services\ImportSources\PagedDirectoryImportSource;
use Module\Directories\Temporal\RunDirectoryImportWorkflowInput;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final readonly class ImportService
{
    public function __construct(
        private DirectoryImport $directoryImportModel,
        private EventDispatcher $events,
        private DirectoryImportSourceResolver $sourceResolver,
        private DirectoryImportRepository $imports,
        private DirectoryItemRepository $items,
        private DirectoryVersionRepository $versions,
        private DirectoryImportPayloadNormalizer $normalizer,
        private DirectoryImportRowProcessor $rowProcessor,
        private Container $container,
    ) {
    }

    public function queue(DirectoryImportData $data): DirectoryImport
    {
        $source = $this->sourceResolver->forData($data);
        $sourcePayload = $source->buildPayload($data);
        $normalizedFields = $this->normalizer->normalizeFields($data->fields);
        $targetVersion = $this->targetVersionFor($data, $normalizedFields);

        $import = $this->imports->create([
            'directory_id' => $data->directory->id,
            'directory_version_id' => $targetVersion->id,
            'uploaded_by' => $data->uploadedBy,
            'mode' => $data->mode->value,
            'status' => DirectoryImportStatus::Pending->value,
            'source_type' => $source->type()->value,
            'file_disk' => $sourcePayload['file_disk'],
            'file_path' => $sourcePayload['file_path'],
            'match_by' => $data->matchBy,
            'parent_key_field' => $data->parentKeyField,
            'chunk_size' => $data->chunkSize,
            'mapping_json' => $this->normalizer->normalizeMapping($data->mapping),
            'fields_json' => $normalizedFields,
            'remote_config_json' => $this->remoteConfigFor($sourcePayload, $data),
            'processed_keys_json' => [],
        ]);

        $this->rememberImportSource($targetVersion, $import, $source->type()->value);

        $this->start($import->id);

        return $import;
    }

    public function start(int $directoryImportId): void
    {
        if (!$this->markAsProcessing($directoryImportId)) {
            return;
        }

        $import = $this->imports->findOrFail($directoryImportId);
        $source = $this->sourceResolver->forImport($import);

        $this->publishStatus($import);

        $starter = $this->container->make(RunDirectoryImportWorkflowStarterInterface::class);
        $input = new RunDirectoryImportWorkflowInput($import->id);

        if ($source instanceof PagedDirectoryImportSource) {
            $starter->startPaged($input);

            return;
        }

        $starter->start($input);
    }

    /**
     * Computes the Excel chunking plan for a Temporal-orchestrated import and marks it
     * ready for chunk processing. Called once by {@see \Module\Directories\Temporal\Activities\PrepareDirectoryImportActivity}
     * before any chunk activity runs.
     *
     * @return array{headingRow: int, firstDataRow: int, chunkSize: int, totalRows: int, chunkTimeoutSeconds: int}
     */
    public function prepareExcelImport(int $directoryImportId): array
    {
        $import = $this->imports->findOrFail($directoryImportId);

        $fullPath = Storage::disk($import->file_disk)->path($import->file_path);

        $reader = IOFactory::createReaderForFile($fullPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($fullPath);

        $headingRow = 1;

        return [
            'headingRow' => $headingRow,
            'firstDataRow' => $headingRow + 1,
            'chunkSize' => max(1, $import->chunk_size),
            'totalRows' => $spreadsheet->getActiveSheet()->getHighestDataRow(),
            'chunkTimeoutSeconds' => is_int($chunkTimeout = config('import.temporal_chunk_timeout_seconds', 120)) ? $chunkTimeout : 120,
        ];
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    public function importChunk(int $directoryImportId, Collection $rows, int $baseRowNumber): void
    {
        $this->waitBeforeChunkIfConfigured();

        $import = $this->imports->findWithVersionOrFail($directoryImportId);

        if ($import->status !== DirectoryImportStatus::Processing->value) {
            return;
        }

        $result = $this->rowProcessor->importRows($import, $rows, $baseRowNumber);

        $import->increment('processed_rows', $rows->count());
        $this->rememberProcessedKeys($import, $result['processed_keys']);

        if ($result['failed_rows'] > 0) {
            $this->recordFailedRows($import, $result['failed_rows'], $result['row_errors']);
        }

        $this->publishStatus($import->refresh());
    }

    public function complete(int $directoryImportId): bool
    {
        $import = $this->imports->find($directoryImportId);

        if ($import === null || $this->isFinished($import)) {
            return true;
        }

        $import = $this->imports->update($import, [
            'status' => DirectoryImportStatus::Completed->value,
            'finished_at' => now(),
        ]);

        if ($import->parent_key_field !== null && $import->directory_version_id !== null) {
            $this->resolveParentKeys($import);
        }

        if ($this->shouldDeleteUnused($import)) {
            $this->deleteUnusedItems($import);
        }

        DirectoryCache::forgetDirectory($import->directory_id);

        if ($this->shouldActivateVersion($import)) {
            $this->activateImportedVersion($import);
        }

        $this->publishStatus($import);

        return true;
    }

    public function fail(int $directoryImportId, Throwable $exception): void
    {
        $import = $this->imports->find($directoryImportId);

        if ($import === null) {
            return;
        }

        $failedRowsCount = $this->failedRowsCount($exception);

        $import = $this->imports->update($import, [
            'status' => DirectoryImportStatus::Failed->value,
            'error_message' => Str::limit($exception->getMessage(), 65_535, ''),
            'failed_rows' => $import->failed_rows + $failedRowsCount,
            'finished_at' => now(),
        ]);

        DirectoryCache::forgetDirectory($import->directory_id);

        $this->publishStatus($import);
    }

    private function publishStatus(DirectoryImport $import): void
    {
        $this->events->dispatch(DirectoryImportStatusUpdated::fromImport($import));
    }

    private function waitBeforeChunkIfConfigured(): void
    {
        $configured = config('import.chunk_delay_seconds', 0);
        $delay = is_int($configured) ? $configured : 0;

        if ($delay > 0) {
            sleep($delay);
        }
    }

    /**
     * @param  array<int, string|null>  $rowErrors
     */
    private function recordFailedRows(DirectoryImport $import, int $failedRows, array $rowErrors): void
    {
        $import->increment('failed_rows', $failedRows);

        $errorMessage = collect($rowErrors)
            ->filter()
            ->unique()
            ->take(10)
            ->implode(' ');

        if ($errorMessage === '') {
            return;
        }

        $this->imports->update($import, [
            'error_message' => Str::limit($errorMessage, 65_535, ''),
        ]);
    }

    /** @param  list<string>  $processedKeys */
    private function rememberProcessedKeys(DirectoryImport $import, array $processedKeys): void
    {
        if ($processedKeys === []) {
            return;
        }

        $this->directoryImportModel->getConnection()->transaction(function () use ($import, $processedKeys): void {
            $locked = $this->imports->lockForUpdateOrFail($import->id);
            $knownKeys = array_filter(
                $locked->processed_keys_json ?? [],
                static fn(string $key): bool => $key !== '',
            );

            $this->imports->update($locked, [
                'processed_keys_json' => array_values(array_unique([...$knownKeys, ...$processedKeys])),
            ]);
        });
    }

    private function shouldDeleteUnused(DirectoryImport $import): bool
    {
        return $import->directory_version_id !== null
            && DirectoryImportOptions::fromImport($import)->deleteUnused;
    }

    private function deleteUnusedItems(DirectoryImport $import): void
    {
        if ($import->directory_version_id === null) {
            return;
        }

        $processedKeys = array_values(
            array_filter(
                $import->processed_keys_json ?? [],
                static fn(string $key): bool => $key !== '',
            ),
        );

        if ($processedKeys === []) {
            return;
        }

        $this->items->deleteMissingExternalKeysForVersion($import->directory_version_id, $processedKeys);
    }

    private function shouldActivateVersion(DirectoryImport $import): bool
    {
        return $import->directory_version_id !== null
            && (bool)($import->remote_config_json['activate_on_success'] ?? true);
    }

    private function activateImportedVersion(DirectoryImport $import): void
    {
        $directory = $import->directory()->firstOrFail();
        $version = $import->version()->firstOrFail();

        DB::transaction(function () use ($directory, $version): void {
            $directory->versions()->lockForUpdate()->get();
            $this->versions->deactivateAll($directory);
            $this->versions->activate($version);
        });

        $this->container->make(DirectoryCacheService::class)->forgetDirectory($directory);
    }

    /** @param  array<int, array<string, mixed>>  $normalizedFields */
    private function targetVersionFor(DirectoryImportData $data, array $normalizedFields): DirectoryVersion
    {
        if ($data->versionId !== null) {
            $version = DirectoryVersion::query()->findOrFail($data->versionId);
            $version->forceFill(['schema_json' => $normalizedFields])->save();

            return $version;
        }

        return $this->createImportVersion($data, $normalizedFields);
    }

    /** @param  array<int, array<string, mixed>>  $normalizedFields */
    private function createImportVersion(DirectoryImportData $data, array $normalizedFields): DirectoryVersion
    {
        $sourceVersion = $this->versions->activeOrFirst($data->directory);

        return DB::transaction(function () use ($data, $sourceVersion, $normalizedFields) {
            $cloneSource = $sourceVersion;
            $version = $this->versions->createNext($data->directory, $cloneSource);
            $version->forceFill(['schema_json' => $normalizedFields])->save();

            if ($cloneSource !== null) {
                $this->versions->cloneItems($cloneSource, $version);
            }

            return $version;
        });
    }

    /**
     * @param  array<string, mixed>  $sourcePayload
     * @return array<string, mixed>
     */
    private function remoteConfigFor(array $sourcePayload, DirectoryImportData $data): array
    {
        $remoteConfig = [];
        $raw = $sourcePayload['remote_config_json'] ?? null;
        if (is_array($raw)) {
            foreach ($raw as $k => $v) {
                if (is_string($k)) {
                    $remoteConfig[$k] = $v;
                }
            }
        }
        $remoteConfig['activate_on_success'] = $data->activate;
        foreach ($data->options->toArray() as $k => $v) {
            $remoteConfig[$k] = $v;
        }

        return $remoteConfig;
    }

    private function rememberImportSource(DirectoryVersion $version, DirectoryImport $import, string $sourceType): void
    {
        $version->forceFill([
            'source_import_id' => $import->id,
            'source_metadata_json' => [
                'source' => $sourceType,
                'queued_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function markAsProcessing(int $directoryImportId): bool
    {
        return $this->directoryImportModel->getConnection()->transaction(function () use ($directoryImportId): bool {
            $import = $this->imports->lockForUpdateOrFail($directoryImportId);

            if ($import->status !== DirectoryImportStatus::Pending->value) {
                return false;
            }

            if ($import->directory_version_id === null) {
                throw new DirectoryImportException('Import has no target version.');
            }

            $this->imports->update($import, [
                'status' => DirectoryImportStatus::Processing->value,
                'started_at' => now(),
                'error_message' => null,
            ]);

            return true;
        });
    }

    private function isFinished(DirectoryImport $import): bool
    {
        return in_array($import->status, [
            DirectoryImportStatus::Completed->value,
            DirectoryImportStatus::Failed->value,
        ], true);
    }

    private function resolveParentKeys(DirectoryImport $import): void
    {
        $parentKeyField = $import->parent_key_field;
        $versionId = $import->directory_version_id;

        if (!is_string($parentKeyField) || $versionId === null) {
            return;
        }

        $keyToId = $this->items->externalKeyToIdMap($versionId);

        if ($keyToId->isEmpty()) {
            return;
        }

        $items = $this->items->forVersion($versionId);

        foreach ($items as $item) {
            $dataJson = is_array($item->data_json) ? $item->data_json : [];
            $parentKeyValue = $dataJson[$parentKeyField] ?? null;

            if ($parentKeyValue === null || $parentKeyValue === '') {
                continue;
            }

            $parentId = $keyToId->get(is_scalar($parentKeyValue) ? (string)$parentKeyValue : '');

            if ($parentId === null || $parentId === $item->id) {
                continue;
            }

            $item->forceFill(['parent_id' => $parentId])->save();
        }
    }

    private function failedRowsCount(Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            return max(1, count($exception->errors()));
        }

        return 1;
    }
}
