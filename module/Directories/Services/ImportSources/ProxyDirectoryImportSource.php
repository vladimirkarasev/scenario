<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Support\Collection;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\ProxyContextFactory;
use Module\Proxy\Services\ProxyExecutor;

final readonly class ProxyDirectoryImportSource implements PagedDirectoryImportSource
{
    public function __construct(
        private ProxyExecutor $proxyExecutor,
        private ProxyContextFactory $contextFactory,
    ) {
    }

    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::Proxy;
    }

    /** @return array<string, mixed> */
    public function buildPayload(DirectoryImportData $data): array
    {
        $proxyEndpointId = $data->remote['proxy_endpoint_id'] ?? null;

        return [
            'file_disk' => 'proxy',
            'file_path' => is_int($proxyEndpointId) ? "proxy:{$proxyEndpointId}" : 'proxy',
            'remote_config_json' => [
                'proxy_endpoint_id' => $proxyEndpointId,
            ],
        ];
    }

    /** @return array{rows: Collection<int, array<string, mixed>>, hasMore: bool} */
    public function fetchPage(DirectoryImport $import, int $page): array
    {
        $config = $import->remote_config_json;
        $proxyEndpointId = $config['proxy_endpoint_id'] ?? null;

        if (!is_int($proxyEndpointId)) {
            throw new DirectoryImportException('Proxy endpoint ID is missing from import config.');
        }

        $endpoint = ProxyEndpoint::query()->find($proxyEndpointId);

        if ($endpoint === null) {
            throw new DirectoryImportException("Proxy endpoint [{$proxyEndpointId}] not found.");
        }

        $perPage = max(1, $import->chunk_size ?? 500);
        $query = ['page' => $page, 'per_page' => $perPage];

        $response = $this->proxyExecutor->executeLogged(
            $endpoint,
            $this->contextFactory->forQuery($endpoint, $query),
            $query,
            ['caller' => 'directory-import', 'directory_import_id' => $import->id],
        );

        $items = $this->extractItems($response->body);

        if ($items === []) {
            /** @var Collection<int, array<string, mixed>> $empty */
            $empty = collect();

            return ['rows' => $empty, 'hasMore' => false];
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($items)->map(static fn(mixed $item): array => is_array($item) ? $item : []);

        return ['rows' => $rows, 'hasMore' => $rows->count() >= $perPage];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, mixed>
     */
    private function extractItems(array $body): array
    {
        foreach (['items', 'data'] as $key) {
            $value = $body[$key] ?? null;
            if (is_array($value)) {
                return array_values($value);
            }
        }

        return [];
    }
}
