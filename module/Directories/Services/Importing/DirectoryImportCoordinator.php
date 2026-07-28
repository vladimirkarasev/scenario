<?php

declare(strict_types=1);

namespace Module\Directories\Services\Importing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Module\Directories\Cache\DirectoryCache;
use Module\Directories\DTO\ExcelImportData;
use Module\Directories\DTO\DirectoryImportPlan;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\DTO\ProxyImportSourceConfig;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Enums\DirectoryImportStatus;
use Module\Directories\Models\DirectoryImport;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Directories\Repositories\DirectoryImportRepository;
use Module\Directories\Repositories\DirectoryVersionRepository;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Directories\Services\ImportSources\FileDirectoryImportSource;
use Module\Directories\Temporal\RunDirectoryImportWorkflowInput;
use Module\Directories\Temporal\RunDirectoryImportWorkflowStarterInterface;
use Throwable;

final readonly class DirectoryImportCoordinator
{
    public function __construct(
        private FileDirectoryImportSource $fileSource,
        private DirectoryImportRepository $imports,
        private DirectoryVersionRepository $versions,
        private DirectoryImportPayloadNormalizer $normalizer,
        private DirectoryImportCreator $creator,
        private DirectoryImportChunkProcessor $chunkProcessor,
        private DirectoryImportFinalizer $finalizer,
        private DirectoryImportState $state,
        private RunDirectoryImportWorkflowStarterInterface $workflowStarter,
    ) {
    }

    public function queue(ExcelImportData $data): DirectoryImport
    {
        $sourcePayload = $this->fileSource->buildPayload($data);
        $normalizedFields = $this->normalizer->normalizeFields($data->fields);
        $targetVersion = $this->targetVersionFor($data, $normalizedFields);

        $import = $this->creator->create(new DirectoryImportPlan(
            directory: $data->directory,
            version: $targetVersion,
            source: $this->fileSource->type(),
            mode: $data->mode,
            disk: $sourcePayload->disk,
            path: $sourcePayload->path,
            sourceConfig: $sourcePayload->config,
            mapping: $this->normalizer->normalizeMapping($data->mapping),
            fields: $normalizedFields,
            options: $data->options,
            matchBy: $data->matchBy,
            externalKeyField: null,
            parentKeyField: $data->parentKeyField,
            chunkSize: $data->chunkSize,
            activate: $data->activate,
            uploadedBy: $data->uploadedBy,
        ));

        $this->start($import->id);

        return $import;
    }

    public function queueProxy(
        Directory $directory,
        DirectoryVersion $version,
        ProxyEndpoint $endpoint,
        DirectoryImportOptions $options,
        ?int $userId,
    ): DirectoryImport {
        $fields = $version->schema_json;
        $mapping = $this->proxyMapping($directory, $fields);
        $config = new ProxyImportSourceConfig($endpoint->id, $endpoint->uuid);
        $externalKeyField = $this->proxyExternalKeyField($directory);

        $import = $this->creator->create(new DirectoryImportPlan(
            directory: $directory,
            version: $version,
            source: DirectoryImportSourceType::Proxy,
            mode: DirectoryImportMode::Replace,
            disk: 'proxy',
            path: 'proxy:'.$endpoint->id,
            sourceConfig: $config,
            mapping: $mapping,
            fields: $fields,
            options: $options,
            matchBy: $externalKeyField === null ? $directory->match_by : null,
            externalKeyField: $externalKeyField,
            parentKeyField: null,
            chunkSize: 500,
            activate: true,
            uploadedBy: $userId,
        ));

        $this->start($import->id);

        return $import;
    }

    public function start(int $directoryImportId): void
    {
        $import = $this->state->markProcessing($directoryImportId);

        if ($import === null) {
            return;
        }

        $this->workflowStarter->start(new RunDirectoryImportWorkflowInput($import->id));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{added: int, updated: int, failed: int}
     */
    public function importChunk(int $directoryImportId, Collection $rows, int $baseRowNumber): array
    {
        return $this->chunkProcessor->process($directoryImportId, $rows, $baseRowNumber);
    }

    /** @return int Количество удалённых элементов (deleteUnused) */
    public function complete(int $directoryImportId): int
    {
        $import = $this->imports->find($directoryImportId);

        if ($import === null || $this->isFinished($import)) {
            return 0;
        }

        $deleted = $this->finalizer->finalize($import);
        $this->state->markCompleted($import);

        return $deleted;
    }

    public function fail(int $directoryImportId, Throwable $exception): void
    {
        $import = $this->imports->find($directoryImportId);

        if ($import === null) {
            return;
        }

        DirectoryCache::forgetDirectory($import->directory_id);
        $this->state->markFailed($import, $exception);
    }

    public function publishStatus(DirectoryImport $import): void
    {
        $this->state->publish($import);
    }

    /** @param  array<int, array<string, mixed>>  $normalizedFields */
    private function targetVersionFor(ExcelImportData $data, array $normalizedFields): DirectoryVersion
    {
        if ($data->versionId !== null) {
            $version = DirectoryVersion::query()->findOrFail($data->versionId);
            $version->forceFill(['schema_json' => $normalizedFields])->save();

            return $version;
        }

        return $this->createImportVersion($data, $normalizedFields);
    }

    /** @param  array<int, array<string, mixed>>  $normalizedFields */
    private function createImportVersion(ExcelImportData $data, array $normalizedFields): DirectoryVersion
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
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, string>
     */
    private function proxyMapping(Directory $directory, array $fields): array
    {
        $configured = $directory->api_config_json['field_mapping'] ?? [];
        $mapping = [];

        if (is_array($configured)) {
            foreach ($configured as $directoryField => $proxyField) {
                if (is_string($directoryField) && is_string($proxyField) && $proxyField !== '') {
                    $mapping[$proxyField] = $directoryField;
                }
            }
        }

        if ($mapping !== []) {
            return $mapping;
        }

        foreach ($fields as $field) {
            $key = $field['key'] ?? null;

            if (is_string($key) && $key !== '') {
                $mapping[$key] = $key;
            }
        }

        return $mapping;
    }

    private function proxyExternalKeyField(Directory $directory): ?string
    {
        $configured = $directory->api_config_json['external_key_field'] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $matchBy = $directory->match_by;
        $mapping = $directory->api_config_json['field_mapping'] ?? null;
        $legacy = $matchBy !== null && is_array($mapping) ? ($mapping[$matchBy] ?? null) : null;

        return is_string($legacy) && $legacy !== '' ? $legacy : null;
    }

    private function isFinished(DirectoryImport $import): bool
    {
        return in_array($import->status, [
            DirectoryImportStatus::Completed->value,
            DirectoryImportStatus::Failed->value,
        ], true);
    }

}
