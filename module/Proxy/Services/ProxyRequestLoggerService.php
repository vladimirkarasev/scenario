<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Illuminate\Http\Request;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Enums\ProxyRequestStatus;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Models\ProxyRequest;

final class ProxyRequestLoggerService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $normalizedData
     */
    public function createFromHttp(
        Request $request,
        ProxyEndpoint $endpoint,
        string $requestId,
        array $payload,
        array $query,
        array $headers,
        array $normalizedData = [],
    ): ProxyRequest {
        return ProxyRequest::query()->create([
            'proxy_endpoint_id' => $endpoint->id,
            'request_id' => $requestId,
            'status' => ProxyRequestStatus::Received,
            'request' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'headers' => $this->maskHeaders($headers),
                'query' => $query,
                'payload' => $payload,
            ],
            'normalized_data' => $normalizedData,
            'received_at' => now(),
        ]);
    }

    public function markAccepted(ProxyRequest $request): void
    {
        $request->forceFill([
            'status' => ProxyRequestStatus::Accepted,
        ])->save();
    }

    public function markProcessed(ProxyRequest $request, ProxyResponse $response, bool $isMocked = false): void
    {
        $request->forceFill([
            'status' => ProxyRequestStatus::Processed,
            'is_mocked' => $isMocked,
            'response' => [
                'status_code' => $response->statusCode,
                'headers' => $response->headers,
                'body' => $response->body,
            ],
            'processed_at' => now(),
        ])->save();
    }

    public function markRejected(ProxyRequest $request, \Throwable|string $error): void
    {
        $request->forceFill([
            'status' => ProxyRequestStatus::Rejected,
            'response' => ['error' => is_string($error) ? $error : $error->getMessage()],
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(ProxyRequest $request, \Throwable $error): void
    {
        $request->forceFill([
            'status' => ProxyRequestStatus::Failed,
            'response' => ['error' => $error->getMessage()],
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function maskHeaders(array $headers): array
    {
        $masked = [];
        $sensitive = config('proxy.masked_headers', []);
        $sensitive = is_array($sensitive) ? array_map(static fn (mixed $v): string => strtolower(is_scalar($v) ? (string) $v : ''), $sensitive) : [];

        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $masked[$normalizedKey] = in_array($normalizedKey, $sensitive, true) ? '[masked]' : $value;
        }

        return $masked;
    }
}
