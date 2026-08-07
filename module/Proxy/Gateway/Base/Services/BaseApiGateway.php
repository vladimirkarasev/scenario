<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base\Services;

use Illuminate\Support\Facades\Event;
use Module\Proxy\Events\ProxyGatewayRequestFailed;
use Module\Proxy\Events\ProxyGatewayRequestSucceeded;
use Module\Proxy\Gateway\Base\Contracts\ApiGateway;
use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiTransport;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Throwable;

class BaseApiGateway implements ApiGateway
{
    private const SENSITIVE_KEYS = [
        'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
        'x-api-key', 'api-key', 'api_key', 'apikey', 'token', 'secret', 'password',
        'access-token', 'access_token', 'refresh-token', 'refresh_token',
    ];

    public function __construct(
        protected ApiGatewayConfig $config,
        protected ApiTransport $transport,
        protected MockApiTransport $mockTransport,
    ) {
    }

    public function send(ApiMethod $method): ApiGatewayResponse
    {
        $transport = $this->config->mock ? $this->mockTransport : $this->transport;
        $startedAt = microtime(true);

        try {
            $response = $transport->send($this->config, $method);
        } catch (Throwable $exception) {
            Event::dispatch(new ProxyGatewayRequestFailed(
                gateway: $this->config->name,
                methodKey: $method->key(),
                httpMethod: $method->method(),
                uri: $method->uri(),
                mock: $this->config->mock,
                requestHeaders: $this->maskSensitive($method->headers()),
                requestQuery: $this->maskSensitive($method->query()),
                requestBody: $method->body(),
                error: $exception->getMessage(),
                durationMs: $this->elapsedMs($startedAt),
            ));

            throw $exception;
        }

        Event::dispatch(new ProxyGatewayRequestSucceeded(
            gateway: $this->config->name,
            methodKey: $method->key(),
            httpMethod: $method->method(),
            uri: $method->uri(),
            mock: $this->config->mock,
            requestHeaders: $this->maskSensitive($method->headers()),
            requestQuery: $this->maskSensitive($method->query()),
            requestBody: $method->body(),
            statusCode: $response->statusCode,
            responseHeaders: $response->headers,
            responseBody: $response->body,
            durationMs: $this->elapsedMs($startedAt),
        ));

        return $response;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * @param  array<mixed, mixed>  $items
     * @return array<string, mixed>
     */
    private function maskSensitive(array $items): array
    {
        $result = [];

        foreach ($items as $key => $value) {
            $result[(string) $key] = in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)
                ? '••••••'
                : $value;
        }

        return $result;
    }
}
