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

final class ProxyDirectoryImportSource implements DirectoryImportSource
{
    public function __construct(
        private readonly ProxyExecutor $proxyExecutor,
        private readonly ProxyContextFactory $contextFactory,
    ) {}

    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::Proxy;
    }

    public function runsInline(): bool
    {
        return true;
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

    public function start(DirectoryImport $import, callable $importChunk): void
    {
        $config = $import->remote_config_json;
        $proxyEndpointId = $config['proxy_endpoint_id'] ?? null;

        if (! is_int($proxyEndpointId)) {
            throw new DirectoryImportException('Proxy endpoint ID is missing from import config.');
        }

        $endpoint = ProxyEndpoint::query()->find($proxyEndpointId);

        if ($endpoint === null) {
            throw new DirectoryImportException("Proxy endpoint [{$proxyEndpointId}] not found.");
        }

        $perPage = max(1, $import->chunk_size ?? 500);
        $page = 1;
        $baseRowNumber = 2;

        while (true) {
            $query = ['page' => $page, 'per_page' => $perPage];
            $response = $this->proxyExecutor->executeLogged(
                $endpoint,
                $this->contextFactory->forQuery($endpoint, $query),
                $query,
                ['caller' => 'directory-import', 'directory_import_id' => $import->id],
            );

            $items = $this->extractItems($response->body);

            if ($items === []) {
                break;
            }

            /** @var Collection<int, array<string, mixed>> $rows */
            $rows = collect($items)->map(static fn (mixed $item): array => is_array($item) ? $item : []);

            $importChunk($import->id, $rows, $baseRowNumber);
            $baseRowNumber += $rows->count();

            // Останавливаемся если страница не полная — handler больше ничего не отдаст.
            if ($rows->count() < $perPage) {
                break;
            }

            $page++;
        }
    }

    /**
     * Извлекает массив элементов из тела ответа. Поддерживает наиболее
     * распространённые варианты: `items` и `data`.
     *
     * @param  array<string, mixed> $body
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
