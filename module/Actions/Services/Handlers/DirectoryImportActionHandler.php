<?php

declare(strict_types=1);

namespace Module\Actions\Services\Handlers;

use Module\Actions\Contracts\ActionHandlerInterface;
use Module\Actions\DTO\ActionResult;
use Module\Actions\Models\Action;
use Module\Actions\Services\ActionDataResolver;
use Module\Actions\Services\Handlers\Concerns\HasNoConfigFields;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\DTO\DirectoryImportOptions;
use Module\Directories\Enums\DirectoryImportMode;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Models\Directory;
use Module\Directories\Services\DictionaryApiSyncService;
use Module\Directories\Services\ImportService;
use Module\Proxy\Models\ProxyEndpoint;
use Throwable;

final readonly class DirectoryImportActionHandler implements ActionHandlerInterface
{
    use HasNoConfigFields;

    public function __construct(
        private ActionDataResolver $dataResolver,
        private ImportService $importService,
        private DictionaryApiSyncService $apiSync,
    ) {}

    /** @param  array<string, mixed>  $input */
    public function handle(Action $action, array $input = []): ActionResult
    {
        $resolvedConfig = $this->dataResolver->resolve(
            $action->config ?? [],
            $this->dataResolver->contextForAction($action, $input)
        );
        $config = is_array($resolvedConfig) ? $this->stringKeyed($resolvedConfig) : [];

        try {
            // Для proxy-источника конфиг (proxy, сопоставление, схема, ключ) собирается
            // из самого справочника в момент запуска — он всегда актуален.
            $import = $this->stringValue($config['source_type'] ?? null, '') === DirectoryImportSourceType::Proxy->value
                ? $this->apiSync->queue($this->directory($config))
                : $this->importService->queue($this->data($config));
        } catch (Throwable $exception) {
            return ActionResult::failed($exception->getMessage());
        }

        return ActionResult::success([
            'directory_import_id' => $import->id,
            'directory_id' => $import->directory_id,
            'directory_version_id' => $import->directory_version_id,
            'status' => $import->status,
            'source_type' => $import->source_type,
            'file_disk' => $import->file_disk,
            'file_path' => $import->file_path,
        ]);
    }

    /** @param  array<string, mixed>  $config */
    private function data(array $config): DirectoryImportData
    {
        $directory = $this->directory($config);
        $mode = DirectoryImportMode::from(
            $this->stringValue($config['mode'] ?? null, DirectoryImportMode::Replace->value)
        );
        $sourceType = DirectoryImportSourceType::from(
            $this->stringValue($config['source_type'] ?? null, DirectoryImportSourceType::File->value)
        );
        $remote = $this->remoteConfig($config, $sourceType);

        return new DirectoryImportData(
            directory: $directory,
            file: null,
            mode: $mode,
            sourceType: $sourceType,
            mapping: $this->stringMap($config['mapping'] ?? []),
            fields: $this->fields($config['columns'] ?? []),
            remote: $remote,
            matchBy: $this->nullableString($config['match_by'] ?? $directory->match_by),
            parentKeyField: $this->nullableString($config['parent_key_field'] ?? null),
            chunkSize: max(1, $this->intValue($config['chunk_size'] ?? 500, 500)),
            activate: $this->boolValue($config['activate'] ?? true),
            options: DirectoryImportOptions::fromInput($config, $mode->value),
            uploadedBy: $this->nullableInt($config['uploaded_by'] ?? null),
            versionId: $this->nullableInt($config['version_id'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $config */
    private function directory(array $config): Directory
    {
        $directoryId = $config['directory_id'] ?? null;

        if (! is_scalar($directoryId) || (string) $directoryId === '') {
            throw new \InvalidArgumentException('Directory import action requires `directory_id` in config.');
        }

        return Directory::query()->findOrFail((string) $directoryId);
    }

    /**
     * @param  array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function remoteConfig(array $config, DirectoryImportSourceType $sourceType): array
    {
        $remote = is_array($config['remote'] ?? null) ? $this->stringKeyed($config['remote']) : [];

        if ($sourceType === DirectoryImportSourceType::File) {
            $remote['file_disk'] = $this->stringValue($config['file_disk'] ?? ($remote['file_disk'] ?? null), 'local');
            $remote['file_path'] = $this->stringValue($config['file_path'] ?? ($remote['file_path'] ?? null), '');
        }

        if ($sourceType === DirectoryImportSourceType::Proxy) {
            $remote['proxy_endpoint_id'] = $this->proxyEndpointId($config, $remote);
        }

        return $remote;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $remote
     */
    private function proxyEndpointId(array $config, array $remote): ?int
    {
        $rawId = $config['proxy_endpoint_id'] ?? ($remote['proxy_endpoint_id'] ?? null);
        $id = $this->nullableInt($rawId);

        if ($id !== null) {
            return $id;
        }

        $uuid = $this->nullableString($config['proxy_endpoint_uuid'] ?? ($remote['proxy_endpoint_uuid'] ?? null));

        if ($uuid === null) {
            return null;
        }

        $value = ProxyEndpoint::query()->where('uuid', $uuid)->value('id');

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : null);
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_scalar($item)) {
                $result[(string) $key] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $field) {
            if (is_array($field)) {
                $result[] = $this->stringKeyed($field);
            }
        }

        return $result;
    }

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function stringKeyed(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function stringValue(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_scalar($value) && is_numeric($value) ? (int) $value : null;
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_scalar($value) && is_numeric($value) ? (int) $value : $default;
    }

    private function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
