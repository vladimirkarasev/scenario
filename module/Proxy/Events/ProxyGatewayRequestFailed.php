<?php

declare(strict_types=1);

namespace Module\Proxy\Events;

final readonly class ProxyGatewayRequestFailed
{
    /**
     * @param  array<string, mixed>  $requestHeaders
     * @param  array<string, mixed>  $requestQuery
     */
    public function __construct(
        public string $gateway,
        public string $methodKey,
        public string $httpMethod,
        public string $uri,
        public bool $mock,
        public array $requestHeaders,
        public array $requestQuery,
        public mixed $requestBody,
        public string $error,
        public int $durationMs,
    ) {}
}
