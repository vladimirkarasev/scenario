<?php

declare(strict_types=1);

namespace Module\Directories\Services\ImportSources;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Module\Directories\DTO\DirectoryImportData;
use Module\Directories\Enums\DirectoryImportSourceType;
use Module\Directories\Exceptions\DirectoryImportException;
use Module\Directories\Models\DirectoryImport;

final class RemoteDirectoryImportSource implements DirectoryImportSource
{
    public function type(): DirectoryImportSourceType
    {
        return DirectoryImportSourceType::Remote;
    }

    public function runsInline(): bool
    {
        return true;
    }

    public function buildPayload(DirectoryImportData $data): array
    {
        $config = $this->normalizeRemoteConfig($data->remote, $data->chunkSize);

        return [
            'file_disk' => 'remote',
            'file_path' => $config['url'] ?: 'remote',
            'remote_config_json' => $config,
        ];
    }

    public function start(DirectoryImport $import, callable $importChunk): void
    {
        $config = $import->remote_config_json;
        $startPageRaw = $config['start_page'] ?? null;
        $page = max(1, is_int($startPageRaw) ? $startPageRaw : (is_numeric($startPageRaw) ? (int) $startPageRaw : 1));
        $perPageRaw = $config['per_page'] ?? null;
        $perPage = max(
            1,
            is_int($perPageRaw) ? $perPageRaw : (is_numeric($perPageRaw) ? (int) $perPageRaw : $import->chunk_size),
        );
        $baseRowNumber = 2;

        while (true) {
            $headers = is_array($config['headers'] ?? null) ? $config['headers'] : [];
            $client = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout(30);

            $authType = is_string($config['auth_type'] ?? null) ? $config['auth_type'] : 'none';
            $client = match ($authType) {
                'bearer' => $client->withToken(is_scalar($t = data_get($config, 'auth.token')) ? (string) $t : ''),
                'basic' => $client->withBasicAuth(
                    is_scalar($u = data_get($config, 'auth.username')) ? (string) $u : '',
                    is_scalar($p = data_get($config, 'auth.password')) ? (string) $p : '',
                ),
                default => $client,
            };

            $configQuery = is_array($config['query'] ?? null) ? $config['query'] : [];
            $pageParam = is_string($config['page_param'] ?? null) ? $config['page_param'] : 'page';
            $perPageParam = is_string($config['per_page_param'] ?? null) ? $config['per_page_param'] : 'per_page';
            /** @var array<string, mixed> $query */
            $query = array_filter([
                ...$configQuery,
                $pageParam => $page,
                $perPageParam => $perPage,
            ], static fn (mixed $value): bool => $value !== null);

            if ($authType === 'api_key' && filled(data_get($config, 'auth.key'))) {
                $authKey = data_get($config, 'auth.key');
                $authValue = data_get($config, 'auth.value');
                $query[is_scalar($authKey) ? (string) $authKey : ''] = is_scalar($authValue) ? (string) $authValue : '';
            }

            $method = strtoupper(is_string($config['method'] ?? null) ? $config['method'] : 'GET');
            $url = is_string($config['url'] ?? null) ? $config['url'] : '';
            $body = is_array($config['body'] ?? null) ? $config['body'] : [];
            $response = in_array($method, ['POST', 'PUT', 'PATCH'], true)
                ? $client->send($method, $url, ['query' => $query, 'json' => $body])->throw()
                : $client->get($url, $query)->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new DirectoryImportException('Remote API must return a JSON object or array.');
            }

            $itemsPath = is_string($config['items_path'] ?? null) ? $config['items_path'] : 'data';
            $perPagePath = is_string($config['per_page_path'] ?? null) ? $config['per_page_path'] : '';
            $items = $this->extractRemoteItems($payload, $itemsPath);
            $resolvedPerPage = $this->resolveResponseValue($response, $payload, $perPagePath);
            $effectivePerPage = is_numeric($resolvedPerPage) ? max(1, (int) $resolvedPerPage) : $perPage;

            if ($items->isEmpty()) {
                break;
            }

            $importChunk($import->id, $items, $baseRowNumber);

            if (! $this->hasRemoteNextPage($payload, $items->count(), $page, $effectivePerPage)) {
                break;
            }

            $baseRowNumber += $items->count();
            $page++;
        }
    }

    /**
     * @param  array<string, mixed> $remote
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function normalizeRemoteConfig(array $remote, int $chunkSize): array
    {
        /** @var array<string, mixed> $headers */
        $headers = collect(is_array($remote['headers'] ?? null) ? $remote['headers'] : [])
            ->mapWithKeys(
                static fn (mixed $value, mixed $key): array => [(string) $key => is_scalar($value) ? (string) $value : ''],
            )
            ->all();
        /** @var array<string, mixed> $query */
        $query = collect(is_array($remote['query'] ?? null) ? $remote['query'] : [])
            ->mapWithKeys(
                static fn (mixed $value, mixed $key): array => [
                    (string) $key => is_scalar(
                        $value,
                    ) ? (string) $value : $value,
                ],
            )
            ->all();

        return [
            'url' => trim(is_string($remote['url'] ?? null) ? $remote['url'] : ''),
            'method' => strtoupper(trim(is_string($remote['method'] ?? null) ? $remote['method'] : 'GET')),
            'items_path' => trim(is_string($remote['items_path'] ?? null) ? $remote['items_path'] : 'data'),
            'page_param' => trim(is_string($remote['page_param'] ?? null) ? $remote['page_param'] : 'page'),
            'per_page_param' => trim(
                is_string($remote['per_page_param'] ?? null) ? $remote['per_page_param'] : 'per_page',
            ),
            'per_page' => max(1, is_int($remote['per_page'] ?? null) ? $remote['per_page'] : $chunkSize),
            'per_page_path' => trim(is_string($remote['per_page_path'] ?? null) ? $remote['per_page_path'] : ''),
            'start_page' => max(1, is_int($remote['start_page'] ?? null) ? $remote['start_page'] : 1),
            'headers' => $headers,
            'query' => $query,
            'body' => is_array($remote['body'] ?? null) ? $remote['body'] : [],
            'auth_type' => is_string($remote['auth_type'] ?? null) ? $remote['auth_type'] : 'none',
            'auth' => is_array($remote['auth'] ?? null) ? $remote['auth'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>|array<int, mixed> $payload
     * @return Collection<int, array<string, mixed>>
     */
    private function extractRemoteItems(array $payload, string $itemsPath): Collection
    {
        $items = $itemsPath !== ''
            ? data_get($payload, $itemsPath, $payload)
            : $payload;

        if (! is_array($items)) {
            throw new DirectoryImportException('Remote items path must point to an array.');
        }

        $filtered = collect($items)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->values();

        /** @var Collection<int, array<string, mixed>> $result */
        $result = $filtered->map(static fn (mixed $row): array => (array) $row);

        return $result;
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    private function hasRemoteNextPage(array $payload, int $itemsCount, int $page, int $perPage): bool
    {
        $lastPage = data_get($payload, 'last_page') ?? data_get($payload, 'meta.last_page');
        $currentPage = data_get($payload, 'current_page') ?? data_get($payload, 'meta.current_page') ?? $page;

        if (is_numeric($lastPage) && is_numeric($currentPage)) {
            return (int) $currentPage < (int) $lastPage;
        }

        $nextLink = data_get($payload, 'next_page_url') ?? data_get($payload, 'links.next');

        if (filled($nextLink)) {
            return true;
        }

        return $itemsCount >= $perPage;
    }

    /**
     * Supported paths:
     * - headers.x-per-page
     * - data.per_page
     * - meta.per_page
     *
     * @param array<string, mixed>|array<int, mixed> $payload
     */
    private function resolveResponseValue(Response $response, array $payload, string $path): mixed
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            return null;
        }

        if (str_starts_with($normalizedPath, 'headers.')) {
            return $response->header(substr($normalizedPath, 8));
        }

        if (str_starts_with($normalizedPath, 'data.')) {
            return data_get($payload, substr($normalizedPath, 5));
        }

        return data_get($payload, $normalizedPath);
    }
}
