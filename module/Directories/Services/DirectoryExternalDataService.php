<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Module\Directories\Exceptions\DirectoryExternalException;
use Module\Directories\DTO\DirectoryPage;
use Module\Directories\DTO\DirectoryPagination;
use Module\Directories\DTO\DirectoryQuery;
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
     * @throws \Throwable
     */
    public function activeData(Directory $directory, ?DirectoryQuery $query = null): DirectoryPage
    {
        $query ??= new DirectoryQuery();
        $version = $this->resolveVersion($directory);
        $endpoint = $this->resolveEndpoint($directory);

        $fieldMapping = $this->fieldMapping($directory);

        $response = $this->proxyExecutor->executeLogged(
            $endpoint,
            $this->contextFactory->forQuery($endpoint, $query->all()),
            $query->all(),
            ['caller' => 'directory-external', 'directory_id' => $directory->id],
        );

        $pagination = [];
        $rawPagination = $response->body['meta'] ?? null;

        if (is_array($rawPagination)) {
            foreach ($rawPagination as $key => $value) {
                if (is_string($key)) {
                    $pagination[$key] = $value;
                }
            }
        }

        return new DirectoryPage(
            dictionary: $this->dictionaryPayload($directory, $version),
            items: array_values($this->applyMapping($response->body['data'] ?? [], $fieldMapping)),
            pagination: DirectoryPagination::fromArray($pagination),
        );
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
