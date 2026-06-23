<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;

final readonly class ProxyContext
{
    /**
     * @param  array<string, mixed>  $rawRequest
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $system
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $meta
     * @param  array<string, UploadedFile|array<UploadedFile>>  $files
     */
    public function __construct(
        private string $requestId,
        public ProxyEndpoint $endpoint,
        public ProxyRequest $proxyRequest,
        public array $rawRequest,
        private array $payload,
        private array $query,
        private array $headers,
        private array $system,
        private array $data,
        private array $config,
        private array $meta,
        public ?ProxyResponse $response = null,
        private array $files = [],
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function data(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->data, $key, $default);
    }

    public function payload(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->payload, $key, $default);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->query, $key, $default);
    }

    public function header(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->headers, $key === null ? null : strtolower($key), $default);
    }

    public function system(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->system, $key, $default);
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        return $this->read($this->config, $key, $default);
    }

    public function meta(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 2) {
            return new self(
                $this->requestId,
                $this->endpoint,
                $this->proxyRequest,
                $this->rawRequest,
                $this->payload,
                $this->query,
                $this->headers,
                $this->system,
                $this->data,
                $this->config,
                [...$this->meta, $key => $value],
                $this->response,
                $this->files,
            );
        }

        return Arr::get($this->meta, $key);
    }

    public function file(string $key): ?UploadedFile
    {
        $entry = $this->files[$key] ?? null;

        return $entry instanceof UploadedFile ? $entry : null;
    }

    /** @return array<int, UploadedFile> */
    public function fileList(string $key): array
    {
        $entry = $this->files[$key] ?? null;

        if ($entry === null) {
            return [];
        }

        if ($entry instanceof UploadedFile) {
            return [$entry];
        }

        return array_values($entry);
    }

    public function withResponse(ProxyResponse $response): self
    {
        return new self(
            $this->requestId,
            $this->endpoint,
            $this->proxyRequest,
            $this->rawRequest,
            $this->payload,
            $this->query,
            $this->headers,
            $this->system,
            $this->data,
            $this->config,
            $this->meta,
            $response,
            $this->files,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'endpoint' => [
                'id' => $this->endpoint->id,
                'uuid' => $this->endpoint->uuid,
                'code' => $this->endpoint->code,
                'name' => $this->endpoint->name,
                'handler_class' => $this->endpoint->handler_class,
            ],
            'proxy_request' => [
                'id' => $this->proxyRequest->id,
            ],
            'raw_request' => $this->rawRequest,
            'payload' => $this->payload,
            'query' => $this->query,
            'headers' => $this->headers,
            'system' => $this->system,
            'data' => $this->data,
            'config' => $this->config,
            'meta' => $this->meta,
            'response' => $this->response?->body,
        ];
    }

    /** @param  array<string, mixed>  $source */
    private function read(array $source, ?string $key, mixed $default): mixed
    {
        if ($key === null) {
            return $source;
        }

        return Arr::get($source, $key, $default);
    }
}
