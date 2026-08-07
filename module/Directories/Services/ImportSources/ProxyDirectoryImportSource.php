<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Support\Collection;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\DTO\DirectoryImportPage;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;
use Module\Proxy\DTO\ProxyPagination;
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

    /** @throws \Throwable */
    public function fetchPage(DirectoryImport $import, int $page): DirectoryImportPage
    {
        $config = $import->source_config_json;
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

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($items)->map(static fn(mixed $item): array => is_array($item) ? $item : []);

        $hasMore = false;
        $paginateRaw = $response->body['paginate'] ?? null;

        if (is_array($paginateRaw)) {
            $pagination = ProxyPagination::fromArray($paginateRaw);
            $hasMore = $pagination->currentPage < $pagination->lastPage;
        }

        $requestIdRaw = $response->headers['X-Request-Id'] ?? null;

        return new DirectoryImportPage(
            rows: $rows,
            hasMore: $hasMore,
            endpointName: $endpoint->name,
            requestId: is_string($requestIdRaw) ? $requestIdRaw : null,
            receivedCount: $rows->count(),
        );
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
