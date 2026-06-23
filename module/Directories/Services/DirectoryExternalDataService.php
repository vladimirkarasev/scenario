<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Directories\Exceptions\DirectoryExternalException;
use Module\Directories\Models\Directory;
use Module\Directories\Models\DirectoryVersion;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\ProxyContextFactory;
use Module\Proxy\Services\ProxyExecutor;

final readonly class DirectoryExternalDataService
{
    public function __construct(
        private ProxyExecutor $proxyExecutor,
        private ProxyContextFactory $contextFactory,
    ) {
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function activeData(Directory $directory, array $query = []): array
    {
        $version = $this->resolveVersion($directory);
        $endpoint = $this->resolveEndpoint($directory);

        $fieldMapping = $this->fieldMapping($directory);

        $response = $this->proxyExecutor->executeLogged(
            $endpoint,
            $this->contextFactory->forQuery($endpoint, $query),
            $query,
            ['caller' => 'directory-external', 'directory_id' => $directory->id],
        );

        return [
            'dictionary' => $this->dictionaryPayload($directory, $version),
            'data' => $this->applyMapping($response->body['data'] ?? [], $fieldMapping),
            'meta' => is_array($response->body['meta'] ?? null) ? $response->body['meta'] : [],
        ];
    }

    private function resolveVersion(Directory $directory): DirectoryVersion
    {
        return $directory->activeVersion()->first()
            ?? throw DirectoryExternalException::versionNotFound();
    }

    private function resolveEndpoint(Directory $directory): ProxyEndpoint
    {
        $config = $directory->api_config_json ?? [];
        $proxyUuid = is_string($config['proxy_uuid'] ?? null) ? $config['proxy_uuid'] : null;

        if ($proxyUuid === null) {
            throw DirectoryExternalException::proxyNotConfigured();
        }

        return ProxyEndpoint::query()->where('uuid', $proxyUuid)->first()
            ?? throw DirectoryExternalException::proxyNotFound($proxyUuid);
    }

    /** @return array<string, string> */
    private function fieldMapping(Directory $directory): array
    {
        $mapping = ($directory->api_config_json ?? [])['field_mapping'] ?? null;

        $result = [];

        if (is_array($mapping)) {
            foreach ($mapping as $k => $v) {
                if (is_string($k) && is_string($v) && $v !== '') {
                    $result[$k] = $v;
                }
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function dictionaryPayload(Directory $directory, DirectoryVersion $version): array
    {
        return [
            'id' => $directory->id,
            'code' => $directory->slug,
            'name' => $directory->name,
            'active_version_id' => $version->id,
            'active_version_number' => $version->version_number,
        ];
    }

    /**
     * @param  array<string, string>  $fieldMapping
     * @return array<int, array<string, mixed>>
     */
    private function applyMapping(mixed $items, array $fieldMapping): array
    {
        if (!is_array($items)) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $result */
        $result = array_map(function (mixed $item) use ($fieldMapping): array {
            $item = is_array($item) ? $item : [];
            $row = ['id' => $item['id'] ?? null];

            foreach ($fieldMapping as $dirField => $proxyField) {
                if ($proxyField !== '') {
                    $row[$dirField] = $item[$proxyField] ?? null;
                }
            }

            return $row;
        }, $items);

        return $result;
    }
}
