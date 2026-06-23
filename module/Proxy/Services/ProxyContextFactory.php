<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;

final class ProxyContextFactory
{
    /**
     * Build a context for programmatic data queries (no real HTTP request).
     * The handler receives query params via $context->query() and is expected
     * to fetch data from a remote source and return it in ProxyResponse::ok($body).
     *
     * @param  array<string, mixed>  $query  Proxy field names: filter, sort, direction, page, per_page
     */
    public function forQuery(ProxyEndpoint $endpoint, array $query = []): ProxyContext
    {
        $requestId = (string)Str::uuid();
        $proxyRequest = new ProxyRequest;
        $proxyRequest->request_id = $requestId;
        $proxyRequest->proxy_endpoint_id = (string)$endpoint->id;

        return new ProxyContext(
            requestId: $requestId,
            endpoint: $endpoint,
            proxyRequest: $proxyRequest,
            rawRequest: [],
            payload: [],
            query: $query,
            headers: [],
            system: [],
            data: [],
            config: $endpoint->config ?? [],
            meta: [],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $normalizedData
     * @param  array<string, mixed>  $system
     * @param  array<string, mixed>  $meta
     */
    public function fromHttp(
        Request $request,
        ProxyEndpoint $endpoint,
        ProxyRequest $proxyRequest,
        array $payload,
        array $query,
        array $headers,
        array $normalizedData,
        array $system,
        array $meta = [],
    ): ProxyContext {
        return new ProxyContext(
            requestId: $proxyRequest->request_id,
            endpoint: $endpoint,
            proxyRequest: $proxyRequest,
            rawRequest: [
                'method' => $request->method(),
                'path' => $request->path(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ],
            payload: $payload,
            query: $query,
            headers: $headers,
            system: $system,
            data: $normalizedData,
            config: $endpoint->config ?? [],
            meta: $meta,
            files: $request->allFiles(),
        );
    }
}
