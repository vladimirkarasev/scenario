<?php

declare(strict_types=1);

namespace Module\Proxy\Listeners;

use Module\Proxy\Events\ProxyGatewayRequestFailed;
use Module\Proxy\Events\ProxyGatewayRequestSucceeded;
use Psr\Log\LoggerInterface;

final readonly class LogProxyGatewayRequest
{
    public function __construct(private LoggerInterface $logger) {}

    public function handleSucceeded(ProxyGatewayRequestSucceeded $event): void
    {
        $this->logger->info('Proxy gateway request succeeded', [
            'gateway' => $event->gateway,
            'method_key' => $event->methodKey,
            'http_method' => $event->httpMethod,
            'uri' => $event->uri,
            'mock' => $event->mock,
            'request_headers' => $event->requestHeaders,
            'request_query' => $event->requestQuery,
            'request_body' => $event->requestBody,
            'status_code' => $event->statusCode,
            'response_headers' => $event->responseHeaders,
            'response_body' => $event->responseBody,
            'duration_ms' => $event->durationMs,
        ]);
    }

    public function handleFailed(ProxyGatewayRequestFailed $event): void
    {
        $this->logger->error('Proxy gateway request failed', [
            'gateway' => $event->gateway,
            'method_key' => $event->methodKey,
            'http_method' => $event->httpMethod,
            'uri' => $event->uri,
            'mock' => $event->mock,
            'request_headers' => $event->requestHeaders,
            'request_query' => $event->requestQuery,
            'request_body' => $event->requestBody,
            'error' => $event->error,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
