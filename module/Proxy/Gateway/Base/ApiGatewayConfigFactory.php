<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\Base;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\CredentialCatalog;

final readonly class ApiGatewayConfigFactory
{
    public function __construct(private CredentialCatalog $credentials) {}

    public function forEndpoint(ProxyEndpoint $endpoint): ApiGatewayConfig
    {
        $connection = $endpoint->connection;

        return $connection === null
            ? ApiGatewayConfig::fromEndpoint($endpoint)
            : $this->forConnection($connection, $endpoint->code, $endpoint->is_mocked);
    }

    public function forConnection(
        ProxyConnection $connection,
        string $name,
        bool $mock = false,
    ): ApiGatewayConfig {
        return $this->credentials
            ->get($connection->credential_type)
            ->gatewayConfig($name, $connection->values(), $mock);
    }
}
